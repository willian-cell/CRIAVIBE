<?php
require_once __DIR__.'/../config.php';

$u = require_fotografo();

try { db()->exec("ALTER TABLE clientes ADD COLUMN foto_cliente VARCHAR(512) DEFAULT NULL"); } catch (Exception $e) {}

$cliente_id = (int)($_POST['cliente_id'] ?? 0);
if (!$cliente_id) json_out(['status'=>'erro','mensagem'=>'cliente_id obrigatorio.'], 400);

$chk = db()->prepare("SELECT id FROM clientes WHERE id=? AND fotografo_email=? LIMIT 1");
$chk->execute([$cliente_id, $u['email']]);
if (!$chk->fetch()) json_out(['status'=>'erro','mensagem'=>'Cliente nao encontrado ou acesso restrito.'], 404);

$file = $_FILES['foto_cliente'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    json_out(['status'=>'erro','mensagem'=>'Nenhuma imagem enviada.'], 400);
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

$type = '';
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $type = $finfo->file($file['tmp_name']) ?: '';
}
if (!$type) {
    $type = $file['type'] ?? '';
}
if (!isset($allowed[$type])) {
    json_out(['status'=>'erro','mensagem'=>'Tipo de imagem nao permitido. Use JPG, PNG, WEBP ou GIF.'], 400);
}

if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
    json_out(['status'=>'erro','mensagem'=>'Imagem muito grande. Envie um arquivo de ate 5 MB.'], 400);
}

$filename = 'cliente_'.$cliente_id.'_'.bin2hex(random_bytes(6)).'.'.$allowed[$type];
$caminho = '';

if (R2_ACCESS_KEY && R2_SECRET_KEY && R2_BUCKET && R2_ENDPOINT && R2_PUBLIC_URL) {
    require_once __DIR__.'/../lib/R2Storage.php';
    $r2Path = 'clientes/'.$cliente_id.'/'.$filename;
    $r2 = new R2Storage(R2_ACCESS_KEY, R2_SECRET_KEY, R2_BUCKET, R2_ENDPOINT);
    if (!$r2->upload($file['tmp_name'], $r2Path, $type)) {
        json_out(['status'=>'erro','mensagem'=>'Falha ao salvar a imagem no armazenamento.'], 500);
    }
    $caminho = rtrim(R2_PUBLIC_URL, '/').'/'.$r2Path;
} else {
    $uploadDir = __DIR__.'/../../uploads/clientes/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

    $dest = $uploadDir.$filename;
    $caminho = 'uploads/clientes/'.$filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        json_out(['status'=>'erro','mensagem'=>'Falha ao salvar a imagem no servidor.'], 500);
    }
}

$stmt = db()->prepare("UPDATE clientes SET foto_cliente = ? WHERE id = ? AND fotografo_email = ?");
$stmt->execute([$caminho, $cliente_id, $u['email']]);

json_out([
    'status'=>'ok',
    'mensagem'=>'Foto do cliente atualizada.',
    'foto_cliente'=>$caminho
]);
