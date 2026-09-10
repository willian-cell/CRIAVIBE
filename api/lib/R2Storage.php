<?php
/**
 * R2Storage - CriaVibe
 * Implementacao leve do protocolo S3 (Signature V4) para Cloudflare R2.
 *
 * Todo metodo publico guarda o motivo da ultima falha em $lastError. Sem isso
 * um `false` de retorno se confunde com dez causas diferentes (rede, credencial
 * invalida, token sem permissao, bucket inexistente) e o diagnostico vira
 * tentativa e erro.
 *
 * Autor: Willian Batista Oliveira
 */
class R2Storage {
    private $accessKey;
    private $secretKey;
    private $bucket;
    private $endpoint;
    private $region = 'auto';

    /** Motivo legivel da ultima falha. Vazio quando a ultima operacao deu certo. */
    private string $lastError = '';
    /** Codigo HTTP da ultima resposta do R2. 0 quando a requisicao nem completou. */
    private int $lastStatus = 0;

    public function __construct($accessKey, $secretKey, $bucket, $endpoint) {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->bucket = $bucket;
        $this->endpoint = rtrim($endpoint, '/');
    }

    public function lastError(): string { return $this->lastError; }
    public function lastStatus(): int { return $this->lastStatus; }

    /**
     * Opcoes de curl comuns a toda chamada ao R2.
     *
     * As tres travas de transporte existem por causa de um `alert handshake
     * failure` visto no container do Railway enquanto a mesma requisicao, com o
     * mesmo codigo e as mesmas credenciais, funcionava de fora. Elas eliminam as
     * variaveis que o ambiente do container pode negociar errado:
     *
     * - TLS 1.2 como minimo, em vez de deixar a stack escolher;
     * - HTTP/1.1, evitando a negociacao de HTTP/2 via ALPN;
     * - IPv4, porque um IPv6 de saida sem rota valida derruba a conexao antes
     *   de qualquer resposta.
     */
    private static function opcoesTransporte(): array {
        return [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION     => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];
    }

    /**
     * Upload de arquivo para o R2.
     *
     * O corpo vai em streaming a partir do descritor do arquivo: carregar a foto
     * inteira em memoria com file_get_contents estourava o memory_limit do PHP em
     * arquivos grandes, e o erro resultante nao tinha nenhuma relacao aparente
     * com o R2.
     */
    public function upload($filePath, $r2Path, $mimeType = 'application/octet-stream') {
        $this->lastError = '';
        $this->lastStatus = 0;

        if (!is_file($filePath) || !is_readable($filePath)) {
            return $this->falharLocal("Arquivo temporario nao existe ou o PHP nao consegue le-lo ($filePath).");
        }

        $size = filesize($filePath);
        if ($size === false || $size <= 0) {
            return $this->falharLocal('Arquivo temporario vazio (0 bytes).');
        }

        $payloadHash = hash_file('sha256', $filePath);
        if ($payloadHash === false) {
            return $this->falharLocal('Nao foi possivel calcular o hash do arquivo temporario.');
        }

        $fh = @fopen($filePath, 'rb');
        if (!$fh) {
            return $this->falharLocal("Nao foi possivel abrir o arquivo temporario ($filePath).");
        }

        // Falha de transporte (TLS, DNS, conexao recusada) costuma atingir um no
        // de borda especifico, entao uma segunda tentativa tem chance real de
        // pegar outro. Erro de HTTP nao e repetido: 401 e 403 nao melhoram com
        // insistencia.
        $tentativas = 2;
        $response = false;
        $curlError = '';

        for ($i = 1; $i <= $tentativas; $i++) {
            rewind($fh);

            $headers = $this->signedHeaders('PUT', $r2Path, $payloadHash);
            $headers[] = 'Content-Type: ' . $mimeType;
            // Sem isso o curl anuncia Expect: 100-continue e espera a resposta
            // intermediaria antes de mandar o corpo, somando latencia a cada arquivo.
            $headers[] = 'Expect:';

            $ch = curl_init($this->objectUrl($r2Path));
            curl_setopt_array($ch, self::opcoesTransporte() + [
                CURLOPT_UPLOAD         => true,
                CURLOPT_INFILE         => $fh,
                CURLOPT_INFILESIZE     => $size,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => 180,
            ]);

            $response = curl_exec($ch);
            $this->lastStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            if ($response !== false && $this->lastStatus !== 0) break;

            if ($i < $tentativas) {
                error_log("R2Storage: tentativa $i de enviar $r2Path falhou no transporte ($curlError). Repetindo.");
                usleep(500000);
            }
        }

        fclose($fh);

        return $this->avaliarResposta('enviar', $r2Path, $response, $curlError);
    }

    /**
     * Remove um objeto do R2. Retorna true tambem quando o objeto ja nao existe,
     * pois o efeito desejado (ausencia do arquivo) foi atingido.
     */
    public function delete($r2Path) {
        $this->lastError = '';
        $this->lastStatus = 0;

        $payloadHash = hash('sha256', '');

        $ch = curl_init($this->objectUrl($r2Path));
        curl_setopt_array($ch, self::opcoesTransporte() + [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->signedHeaders('DELETE', $r2Path, $payloadHash),
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($ch);
        $this->lastStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($this->lastStatus === 404) return true;

        return $this->avaliarResposta('remover', $r2Path, $response, $curlError);
    }

    /**
     * HEAD em um objeto: confirma que o arquivo realmente chegou ao bucket.
     */
    public function exists($r2Path): bool {
        $this->lastError = '';
        $this->lastStatus = 0;

        $payloadHash = hash('sha256', '');

        $ch = curl_init($this->objectUrl($r2Path));
        curl_setopt_array($ch, self::opcoesTransporte() + [
            CURLOPT_NOBODY         => true,
            CURLOPT_CUSTOMREQUEST  => 'HEAD',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->signedHeaders('HEAD', $r2Path, $payloadHash),
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $this->lastStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        return $this->avaliarResposta('consultar', $r2Path, $response, $curlError);
    }

    /**
     * URL do objeto dentro do endpoint S3 da conta.
     * O endpoint ja inclui o nome do bucket (ver R2_ENDPOINT em api/config.php).
     */
    private function objectUrl(string $r2Path): string {
        return $this->endpoint . '/' . $this->encodeKey($r2Path);
    }

    /**
     * Cabecalhos assinados no padrao AWS Signature V4.
     * Upload, delete e exists compartilham exatamente o mesmo calculo; duplica-lo
     * era a origem de divergencias sutis entre eles.
     */
    private function signedHeaders(string $method, string $r2Path, string $payloadHash): array {
        $host = parse_url($this->endpoint, PHP_URL_HOST);
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        // O Canonical URI precisa incluir /bucket/ porque o endpoint do R2 usa o
        // formato account-id.r2.cloudflarestorage.com/bucket.
        $canonicalUri = '/' . $this->bucket . '/' . $this->encodeKey($r2Path);
        $canonicalHeaders = "host:$host\nx-amz-content-sha256:$payloadHash\nx-amz-date:$timestamp\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';

        $canonicalRequest = "$method\n$canonicalUri\n\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "$date/{$this->region}/s3/aws4_request";
        $stringToSign = "$algorithm\n$timestamp\n$credentialScope\n" . hash('sha256', $canonicalRequest);

        $kDate    = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        return [
            "Host: $host",
            "x-amz-date: $timestamp",
            "x-amz-content-sha256: $payloadHash",
            "Authorization: $algorithm Credential={$this->accessKey}/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature",
        ];
    }

    /**
     * Percent-encoding do caminho preservando as barras, como exige o Canonical
     * URI do SigV4. Sem isso qualquer chave com espaco ou acento gera
     * SignatureDoesNotMatch.
     */
    private function encodeKey(string $r2Path): string {
        $partes = explode('/', ltrim($r2Path, '/'));
        return implode('/', array_map('rawurlencode', $partes));
    }

    /**
     * Falha detectada antes de qualquer chamada de rede.
     */
    private function falharLocal(string $motivo): bool {
        $this->lastError = $motivo;
        error_log('R2Storage: ' . $motivo);
        return false;
    }

    /**
     * Traduz a resposta do R2 em true/false, guardando o motivo real da falha.
     */
    private function avaliarResposta(string $acao, string $r2Path, $response, string $curlError): bool {
        if ($response === false || $this->lastStatus === 0) {
            $this->lastError = 'Nao foi possivel falar com o Cloudflare R2'
                . ($curlError !== '' ? ": $curlError" : '.');
            error_log("R2Storage: falha de rede ao $acao $r2Path - $curlError");
            return false;
        }

        if ($this->lastStatus >= 200 && $this->lastStatus < 300) return true;

        $this->lastError = $this->descreverErro((string)$response);
        error_log("R2Storage: HTTP {$this->lastStatus} ao $acao $r2Path - " . substr((string)$response, 0, 500));
        return false;
    }

    /**
     * Converte o par (codigo HTTP, codigo de erro do R2) na causa concreta.
     * A tabela vem do diagnostico registrado em
     * documentacao/trabalho/trabalho_05_09_2026.md.
     */
    private function descreverErro(string $xml): string {
        $codigo = $this->tagXml($xml, 'Code');
        $detalhe = $this->tagXml($xml, 'Message');

        $causas = [
            'NotEntitled'           => 'o R2 nao esta habilitado nesta conta Cloudflare (ative em R2 no painel).',
            'InvalidAccessKeyId'    => 'R2_ACCESS_KEY_ID nao existe nesta conta. Gere um token novo em R2 > Manage API Tokens.',
            'SignatureDoesNotMatch' => 'R2_SECRET_KEY nao corresponde a R2_ACCESS_KEY_ID. Confira o par de credenciais.',
            'AccessDenied'          => 'a credencial e valida mas nao tem permissao para esta operacao. O token precisa ser Object Read & Write neste bucket.',
            'NoSuchBucket'          => "o bucket '{$this->bucket}' nao existe nesta conta. Confira R2_BUCKET_NAME.",
            'EntityTooLarge'        => 'o arquivo excede o limite aceito pelo R2 em um unico PUT.',
        ];

        if ($codigo !== '' && isset($causas[$codigo])) {
            return "R2 respondeu {$this->lastStatus} {$codigo}: " . $causas[$codigo];
        }

        if ($this->lastStatus === 401) {
            return 'R2 respondeu 401 Unauthorized: credencial inexistente ou secret incorreto. Gere um token novo em R2 > Manage API Tokens e atualize R2_ACCESS_KEY_ID e R2_SECRET_KEY.';
        }

        $sufixo = $codigo !== '' ? " ($codigo)" : '';
        if ($detalhe !== '') $sufixo .= ": $detalhe";

        return "R2 respondeu HTTP {$this->lastStatus}{$sufixo}";
    }

    private function tagXml(string $xml, string $tag): string {
        if (preg_match("#<{$tag}>(.*?)</{$tag}>#s", $xml, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
        }
        return '';
    }
}
