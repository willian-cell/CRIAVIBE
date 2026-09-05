<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../lib/R2Presigner.php';

$u = require_fotografo();
$body = body();
$galeria_id = (int)($body['galeria_id'] ?? 0);
$files = $body['files'] ?? [];

if (!$galeria_id) json_out(['status'=>'erro','mensagem'=>'galeria_id obrigatorio.'], 400);
if (!is_array($files) || !$files) json_out(['status'=>'erro','mensagem'=>'Nenhum arquivo informado.'], 400);
if (count($files) > 250) json_out(['status'=>'erro','mensagem'=>'Envie no maximo 250 arquivos por preparacao.'], 400);

// Rate limiting: evitar abuse de preparacao (ex: 10 prepares por minuto)
try {
    require_once __DIR__ . '/../lib/RateLimiter.php';
    $rl = new RateLimiter();
    $key = 'prepare_'.$u['email'];
    if (!$rl->allow($key, 10, 60)) {
        json_out(['status'=>'erro','mensagem'=>'Limite de preparacao atingido. Tente novamente mais tarde.'], 429);
    }
} catch (Throwable $e) {
    // Se RateLimiter falhar, não bloqueia o usuário (falta de Redis, etc.)
}

$chk = db()->prepare("SELECT id FROM galerias WHERE id=? AND usuario_email=? LIMIT 1");
$chk->execute([$galeria_id, $u['email']]);
if (!$chk->fetch()) json_out(['status'=>'erro','mensagem'=>'Galeria nao encontrada.'], 404);

$missing = [];
if (!R2_ACCESS_KEY) $missing[] = 'R2_ACCESS_KEY_ID';
if (!R2_SECRET_KEY) $missing[] = 'R2_SECRET_KEY';
if (!R2_BUCKET) $missing[] = 'R2_BUCKET_NAME';
if (!R2_ENDPOINT) $missing[] = 'R2_ACCOUNT_ID';
if (!R2_PUBLIC_URL) $missing[] = 'R2_PUBLIC_URL';
if ($missing) {
    json_out([
        'status'=>'erro',
        'mensagem'=>'Configuracao R2 incompleta: '.implode(', ', $missing).'.'
    ], 500);
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'image/heic' => 'heic',
    'image/heif' => 'heif',
    'image/avif' => 'avif',
    'image/svg+xml' => 'svg',
    'image/tiff' => 'tiff',
    'image/x-tiff' => 'tiff',
    'image/bmp' => 'bmp',
    'image/x-icon' => 'ico',
    'application/octet-stream' => 'bin',
];
$extensionMap = [
    'heic' => 'image/heic',
    'heif' => 'image/heif',
    'avif' => 'image/avif',
    'svg' => 'image/svg+xml',
    'tiff' => 'image/tiff',
    'tif' => 'image/tiff',
    'bmp' => 'image/bmp',
    'ico' => 'image/x-icon',
    'psd' => 'application/octet-stream',
    'raw' => 'application/octet-stream',
    'cr2' => 'application/octet-stream',
    'nef' => 'application/octet-stream',
    'arw' => 'application/octet-stream',
    'dng' => 'application/octet-stream',
];

$presigner = new R2Presigner(R2_ACCESS_KEY, R2_SECRET_KEY, R2_BUCKET, R2_ENDPOINT);
$uploads = [];

// Arquivos recusados aqui voltam para o cliente em vez de sumirem calados:
// sem isso a barra de progresso trava abaixo de 100% e o usuario recebe
// mensagem de sucesso mesmo tendo perdido fotos.
$ignorados = [];

// Validade maxima aceita pelo R2. As URLs de um lote sao assinadas todas de
// uma vez, mas so algumas sobem em paralelo: com arquivos grandes e envio
// lento, as ultimas da fila expiravam antes da vez e devolviam HTTP 403.
$validadeSegundos = 3600;

foreach ($files as $idx => $file) {
    $name = trim((string)($file['name'] ?? ''));
    $type = strtolower(trim((string)($file['type'] ?? '')));
    $size = (int)($file['size'] ?? 0);
    $largura = max(0, (int)($file['largura'] ?? 0));
    $altura = max(0, (int)($file['altura'] ?? 0));
    $orientacao = $largura && $altura ? ($largura > $altura ? 'horizontal' : ($altura > $largura ? 'vertical' : 'quadrada')) : null;
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ((!$type || !isset($allowed[$type])) && $ext && isset($extensionMap[$ext])) {
        $type = $extensionMap[$ext];
    }

    if (!$name || $size <= 0 || !isset($allowed[$type])) {
        $ignorados[] = [
            'client_id' => (string)$idx,
            'nome' => $name !== '' ? $name : 'arquivo sem nome',
            'motivo' => !$name
                ? 'Arquivo sem nome.'
                : ($size <= 0
                    ? 'Arquivo vazio.'
                    : 'Formato nao suportado' . ($type !== '' ? " ({$type})" : '') . '.'),
        ];
        continue;
    }

    if (!$ext || strlen($ext) > 12 || !preg_match('/^[a-z0-9]+$/', $ext)) {
        $ext = $allowed[$type];
    }

    $filename = uniqid('foto_', true).'.'.$ext;
    $r2Path = "galerias/{$galeria_id}/{$filename}";

    $uploads[] = [
        'client_id' => (string)$idx,
        'original_name' => $name,
        'mime_type' => $type,
        'size' => $size,
        'largura' => $largura ?: null,
        'altura' => $altura ?: null,
        'orientacao' => $orientacao,
        'r2_path' => $r2Path,
        'public_url' => R2_PUBLIC_URL . '/' . $r2Path,
        'upload_url' => $presigner->signedPutUrl($r2Path, $validadeSegundos, $type),
        'expira_em' => time() + $validadeSegundos,
    ];
}

if (!$uploads) {
    $detalhe = $ignorados ? ' ' . $ignorados[0]['motivo'] : '';
    json_out([
        'status' => 'erro',
        'mensagem' => 'Nenhum arquivo valido para upload.' . $detalhe,
        'ignorados' => $ignorados,
    ], 400);
}

json_out(['status'=>'ok','uploads'=>$uploads,'ignorados'=>$ignorados]);
