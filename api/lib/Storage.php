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
 * URL publica de um caminho dentro do bucket.
 */
function storage_public_url(string $r2Path): string {
    return rtrim(R2_PUBLIC_URL, '/') . '/' . ltrim($r2Path, '/');
}

/**
 * Envia um arquivo recebido via upload HTTP para o R2 e devolve a URL publica.
 * Encerra a requisicao com erro caso a configuracao falte ou o envio falhe.
 */
function storage_put_upload(string $tmpPath, string $r2Path, string $mimeType): string {
    storage_require_config();

    if (!is_uploaded_file($tmpPath)) {
        json_out(['status' => 'erro', 'mensagem' => 'Arquivo temporario invalido.'], 400);
    }

    $r2 = new R2Storage(R2_ACCESS_KEY, R2_SECRET_KEY, R2_BUCKET, R2_ENDPOINT);
    if (!$r2->upload($tmpPath, $r2Path, $mimeType)) {
        error_log("Falha ao enviar $r2Path para o R2.");
        json_out([
            'status' => 'erro',
            'mensagem' => 'Falha ao salvar o arquivo no Cloudflare R2. Verifique as credenciais e o bucket.'
        ], 500);
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
    return $key !== '' ? $key : null;
}

/**
 * Remove do R2 o objeto referenciado por uma URL publica.
 * Silencioso para URLs que nao sao do bucket - a exclusao no banco deve
 * prosseguir mesmo quando o arquivo ja nao existe no storage.
 */
function storage_delete_url(?string $url): bool {
    $key = storage_key_from_url($url);
    if ($key === null || storage_missing_config()) return false;

    $r2 = new R2Storage(R2_ACCESS_KEY, R2_SECRET_KEY, R2_BUCKET, R2_ENDPOINT);
    return $r2->delete($key);
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
