<?php
/**
 * R2Storage - CriaVibe
 * Implementação leve do protocolo S3 para Cloudflare R2.
 * Autor: Willian Batista Oliveira
 */
class R2Storage {
    private $accessKey;
    private $secretKey;
    private $bucket;
    private $endpoint;
    private $region = 'auto';

    public function __construct($accessKey, $secretKey, $bucket, $endpoint) {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->bucket = $bucket;
        $this->endpoint = rtrim($endpoint, '/');
    }

    /**
     * Upload de arquivo para o R2
     */
    public function upload($filePath, $r2Path, $mimeType = 'application/octet-stream') {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            error_log("R2Storage Error: Não foi possível ler o arquivo $filePath");
            return false;
        }

        $host = parse_url($this->endpoint, PHP_URL_HOST);
        $url = $this->endpoint . '/' . ltrim($r2Path, '/');
        
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        
        $payloadHash = hash('sha256', $content);
        
        // 1. Canonical Request
        // Para endpoints no formato account-id.r2.cloudflarestorage.com/bucket,
        // o Canonical URI deve incluir o /bucket/
        $canonicalUri = '/' . $this->bucket . '/' . ltrim($r2Path, '/');
        $canonicalQuery = '';
        $canonicalHeaders = "host:$host\nx-amz-content-sha256:$payloadHash\nx-amz-date:$timestamp\n";
        $signedHeaders = "host;x-amz-content-sha256;x-amz-date";
        
        $canonicalRequest = "PUT\n$canonicalUri\n$canonicalQuery\n$canonicalHeaders\n$signedHeaders\n$payloadHash";
        
        // 2. String to Sign
        $algorithm = "AWS4-HMAC-SHA256";
        $credentialScope = "$date/{$this->region}/s3/aws4_request";
        $stringToSign = "$algorithm\n$timestamp\n$credentialScope\n" . hash('sha256', $canonicalRequest);
        
        // 3. Signature
        $kDate = hash_hmac('sha256', $date, "AWS4" . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', "s3", $kRegion, true);
        $kSigning = hash_hmac('sha256', "aws4_request", $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        
        $authorization = "$algorithm Credential={$this->accessKey}/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

        $headers = [
            "Host: $host",
            "x-amz-date: $timestamp",
            "x-amz-content-sha256: $payloadHash",
            "Authorization: $authorization",
            "Content-Type: $mimeType",
            "Content-Length: " . strlen($content)
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($response === false) {
            $err = curl_error($ch);
            error_log("R2Storage CURL Error: $err");
        }
        
        if ($httpCode >= 400) {
            error_log("R2Storage HTTP Error $httpCode: $response");
        }
        
        return ($httpCode === 200 || $httpCode === 204);
    }

    /**
     * Remove um objeto do R2. Retorna true também quando o objeto já não existe,
     * pois o efeito desejado (ausência do arquivo) foi atingido.
     */
    public function delete($r2Path) {
        $host = parse_url($this->endpoint, PHP_URL_HOST);
        $url = $this->endpoint . '/' . ltrim($r2Path, '/');

        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $payloadHash = hash('sha256', '');

        $canonicalUri = '/' . $this->bucket . '/' . ltrim($r2Path, '/');
        $canonicalQuery = '';
        $canonicalHeaders = "host:$host\nx-amz-content-sha256:$payloadHash\nx-amz-date:$timestamp\n";
        $signedHeaders = "host;x-amz-content-sha256;x-amz-date";

        $canonicalRequest = "DELETE\n$canonicalUri\n$canonicalQuery\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

        $algorithm = "AWS4-HMAC-SHA256";
        $credentialScope = "$date/{$this->region}/s3/aws4_request";
        $stringToSign = "$algorithm\n$timestamp\n$credentialScope\n" . hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $date, "AWS4" . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', "s3", $kRegion, true);
        $kSigning = hash_hmac('sha256', "aws4_request", $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "$algorithm Credential={$this->accessKey}/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Host: $host",
            "x-amz-date: $timestamp",
            "x-amz-content-sha256: $payloadHash",
            "Authorization: $authorization"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            error_log("R2Storage CURL Error (delete): " . curl_error($ch));
            return false;
        }

        // 404 significa que o objeto já não está lá — resultado equivalente ao sucesso.
        if ($httpCode >= 400 && $httpCode !== 404) {
            error_log("R2Storage HTTP Error $httpCode ao remover $r2Path: $response");
            return false;
        }

        return true;
    }
}
