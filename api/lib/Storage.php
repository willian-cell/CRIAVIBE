<?php
/**
 * Storage - CriaVibe
 * Camada unica de armazenamento. Todo arquivo enviado pelo sistema vai para o
 * Cloudflare R2. Nao existe fallback para disco local: o filesystem do container
 * e efemero e qualquer arquivo gravado nele some no proximo restart/deploy.
 */
require_once __DIR__ . '/R2Storage.php';

/**
 * Lista de constantes R2 ausentes. Vazio significa configuracao completa.
 */
function storage_missing_config(): array {
    $missing = [];
    if (!R2_ACCESS_KEY) $missing[] = 'R2_ACCESS_KEY_ID';
    if (!R2_SECRET_KEY) $missing[] = 'R2_SECRET_KEY';
    if (!R2_BUCKET)     $missing[] = 'R2_BUCKET_NAME';
    if (!R2_ENDPOINT)   $missing[] = 'R2_ACCOUNT_ID';
    if (!R2_PUBLIC_URL) $missing[] = 'R2_PUBLIC_URL';
    return $missing;
}

/**
 * Interrompe a requisicao quando o R2 nao esta configurado.
 * Falhar aqui e proposital: melhor recusar o upload do que grava-lo em disco
 * efemero e perder o arquivo silenciosamente no proximo deploy.
 */
function storage_require_config(): void {
    $missing = storage_missing_config();
    if (!$missing) return;

    error_log('Upload recusado: configuracao R2 incompleta (' . implode(', ', $missing) . ')');
    json_out([
        'status' => 'erro',
        'mensagem' => 'Armazenamento nao configurado: ' . implode(', ', $missing) . '. Verifique as variaveis no Railway.'
    ], 500);
}

/**
 * Cliente R2 ja configurado. Assume que storage_require_config() passou.
 */
function storage_client(): R2Storage {
    return new R2Storage(R2_ACCESS_KEY, R2_SECRET_KEY, R2_BUCKET, R2_ENDPOINT);
}

/**
 * URL publica de um caminho dentro do bucket.
 *
 * O caminho e percent-encoded preservando as barras: uma chave com espaco ou
 * acento gravada crua no banco vira uma URL invalida no <img> do navegador.
 * Para as chaves geradas por uniqid() a codificacao nao muda nada, entao os
 * registros antigos continuam validos.
 */
function storage_public_url(string $r2Path): string {
    $partes = explode('/', ltrim($r2Path, '/'));
    return rtrim(R2_PUBLIC_URL, '/') . '/' . implode('/', array_map('rawurlencode', $partes));
}

/**
 * Envia um arquivo recebido via upload HTTP para o R2 e devolve a URL publica.
 * Encerra a requisicao com erro caso a configuracao falte ou o envio falhe.
 *
 * A mensagem devolvida ao navegador carrega o motivo real da recusa do R2. Antes
 * ela era sempre "verifique as credenciais e o bucket", o que apontava para o
 * lugar errado em tres das quatro causas possiveis.
 */
function storage_put_upload(string $tmpPath, string $r2Path, string $mimeType): string {
    storage_require_config();

    if (!is_uploaded_file($tmpPath)) {
        json_out(['status' => 'erro', 'mensagem' => 'Arquivo temporario invalido.'], 400);
    }

    $r2 = storage_client();
    if (!$r2->upload($tmpPath, $r2Path, $mimeType)) {
        $motivo = $r2->lastError() ?: 'motivo nao identificado.';
        error_log("Falha ao enviar $r2Path para o R2: $motivo");
        json_out([
            'status' => 'erro',
            'mensagem' => 'Falha ao salvar o arquivo no Cloudflare R2. ' . $motivo,
            'r2_status' => $r2->lastStatus(),
        ], 502);
    }

    return storage_public_url($r2Path);
}

/**
 * Converte uma URL publica armazenada no banco de volta para a chave do bucket.
 * Devolve null para valores que nao pertencem ao R2 (links do YouTube, caminhos
 * legados em uploads/, campos vazios).
 */
function storage_key_from_url(?string $url): ?string {
    $url = trim((string)$url);
    if ($url === '' || !R2_PUBLIC_URL) return null;

    $base = rtrim(R2_PUBLIC_URL, '/') . '/';
    if (strpos($url, $base) !== 0) return null;

    $key = substr($url, strlen($base));
    return $key !== '' ? rawurldecode($key) : null;
}

/**
 * Remove do R2 o objeto referenciado por uma URL publica.
 * Silencioso para URLs que nao sao do bucket - a exclusao no banco deve
 * prosseguir mesmo quando o arquivo ja nao existe no storage.
 */
function storage_delete_url(?string $url): bool {
    $key = storage_key_from_url($url);
    if ($key === null || storage_missing_config()) return false;

    return storage_client()->delete($key);
}

/**
 * Remove o objeto original e os derivados (thumbnails) gerados a partir dele.
 */
function storage_delete_imagem(array $img): void {
    storage_delete_url($img['caminho_arquivo'] ?? null);
    foreach (['caminho_thumb_small', 'caminho_thumb_medium', 'caminho_thumb_large'] as $campo) {
        if (!empty($img[$campo])) storage_delete_url($img[$campo]);
    }
}

/**
 * Round-trip completo contra o bucket: grava um objeto minusculo, confirma que
 * ele existe pela API S3 e pela URL publica, e apaga em seguida.
 *
 * Existe para responder de uma vez, com evidencia, a pergunta que sempre volta
 * quando um upload falha: o problema esta na credencial, na permissao do token,
 * no nome do bucket, no acesso publico ou na rede do container?
 */
function storage_diagnostico(): array {
    $faltando = storage_missing_config();
    $resultado = [
        'configuracao' => [
            'bucket'           => R2_BUCKET ?: null,
            'endpoint'         => R2_ENDPOINT ?: null,
            'url_publica'      => R2_PUBLIC_URL ?: null,
            'access_key_id'    => R2_ACCESS_KEY ? substr(R2_ACCESS_KEY, 0, 6) . '...' : null,
            'secret_definido'  => R2_SECRET_KEY ? true : false,
            'variaveis_faltando' => $faltando,
        ],
        'etapas' => [],
        'ok' => false,
    ];

    if ($faltando) {
        $resultado['etapas'][] = [
            'etapa' => 'configuracao',
            'ok' => false,
            'detalhe' => 'Variaveis ausentes: ' . implode(', ', $faltando),
        ];
        return $resultado;
    }

    $resultado['ambiente'] = storage_ambiente();
    $resultado['transporte'] = storage_probes_tls(R2_ENDPOINT);

    $chave = 'diagnostico/checagem_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
    $tmp = tempnam(sys_get_temp_dir(), 'r2diag');
    // PNG 1x1 valido, ~70 bytes.
    file_put_contents($tmp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

    $r2 = storage_client();

    $enviou = $r2->upload($tmp, $chave, 'image/png');
    $resultado['etapas'][] = [
        'etapa' => 'gravar objeto (PUT assinado)',
        'ok' => $enviou,
        'http' => $r2->lastStatus(),
        'detalhe' => $enviou ? 'Objeto gravado em ' . $chave : $r2->lastError(),
    ];

    if ($enviou) {
        $existe = $r2->exists($chave);
        $resultado['etapas'][] = [
            'etapa' => 'confirmar objeto (HEAD assinado)',
            'ok' => $existe,
            'http' => $r2->lastStatus(),
            'detalhe' => $existe ? 'Objeto encontrado no bucket.' : $r2->lastError(),
        ];

        $publico = storage_http_status(storage_public_url($chave));
        $resultado['etapas'][] = [
            'etapa' => 'ler pela URL publica',
            'ok' => $publico === 200,
            'http' => $publico,
            'detalhe' => $publico === 200
                ? 'Acesso publico do bucket esta ligado.'
                : 'Esperado 200 e veio ' . $publico . '. Em 403 o acesso publico (r2.dev) esta desligado; sem ele o cliente nao ve as fotos e as miniaturas nao sao geradas.',
        ];

        $apagou = $r2->delete($chave);
        $resultado['etapas'][] = [
            'etapa' => 'apagar objeto de teste',
            'ok' => $apagou,
            'http' => $r2->lastStatus(),
            'detalhe' => $apagou ? 'Objeto de teste removido.' : $r2->lastError(),
        ];
    }

    @unlink($tmp);

    $resultado['ok'] = !in_array(false, array_column($resultado['etapas'], 'ok'), true);
    return $resultado;
}

/**
 * Versoes que decidem como o container negocia TLS.
 *
 * Um `alert handshake failure` contra a Cloudflare quase sempre se explica por
 * uma destas linhas, e nenhuma delas e visivel de fora do container.
 */
function storage_ambiente(): array {
    $curl = function_exists('curl_version') ? curl_version() : [];

    return [
        'php'          => PHP_VERSION,
        'curl'         => $curl['version'] ?? null,
        'ssl_do_curl'  => $curl['ssl_version'] ?? null,
        'openssl'      => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : null,
        'protocolos'   => isset($curl['protocols']) ? implode(',', array_slice($curl['protocols'], 0, 12)) : null,
    ];
}

/**
 * Sonda o endpoint S3 do R2 com quatro combinacoes de transporte.
 *
 * Nao autentica nada: interessa apenas saber se o TLS fecha. Um codigo HTTP
 * qualquer (o R2 responde 400 a um GET sem assinatura) significa handshake bem
 * sucedido; um erro de curl sem codigo significa que a conexao morreu antes.
 *
 * Quando a linha "padrao" falha e alguma das outras passa, a que passou aponta
 * exatamente qual trava resolve o problema no ambiente.
 */
function storage_probes_tls(string $endpoint): array {
    $base = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
    ];

    $variantes = [
        'padrao'            => [],
        'forcando IPv4'     => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
        'forcando TLS 1.2'  => [CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2],
        'IPv4 + TLS 1.2 + HTTP/1.1 (o que o app usa)' => [
            CURLOPT_IPRESOLVE    => CURL_IPRESOLVE_V4,
            CURLOPT_SSLVERSION   => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ],
        'sem verificar o certificado' => [CURLOPT_SSL_VERIFYPEER => false],
    ];

    $saida = [];
    foreach ($variantes as $nome => $extra) {
        $ch = curl_init($endpoint . '/');
        curl_setopt_array($ch, $extra + $base);
        curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch);

        $saida[] = [
            'variante'  => $nome,
            'tls_ok'    => $http > 0,
            'http'      => $http,
            'ip'        => curl_getinfo($ch, CURLINFO_PRIMARY_IP) ?: null,
            'detalhe'   => $http > 0 ? 'Handshake TLS concluido.' : ($erro !== '' ? $erro : 'Conexao falhou sem mensagem.'),
        ];
    }

    return $saida;
}

/**
 * Codigo HTTP de um GET simples. 0 quando a requisicao nem completa.
 */
function storage_http_status(string $url): int {
    $ch = curl_init($url);
    curl_setopt_array($ch, storage_opcoes_transporte() + [
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    curl_exec($ch);
    return (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
}

/**
 * Travas de transporte para qualquer chamada a um host da Cloudflare, incluindo
 * a URL publica do bucket. Espelha R2Storage::opcoesTransporte(): a URL publica
 * fica no mesmo edge e falha do mesmo jeito.
 */
function storage_opcoes_transporte(): array {
    return [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSLVERSION     => CURL_SSLVERSION_TLSv1_2,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => 10,
    ];
}
