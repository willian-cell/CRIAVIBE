<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../lib/Storage.php';

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
$caminho = storage_put_upload($file['tmp_name'], 'clientes/'.$cliente_id.'/'.$filename, $type);

$stmt = db()->prepare("UPDATE clientes SET foto_cliente = ? WHERE id = ? AND fotografo_email = ?");
$stmt->execute([$caminho, $cliente_id, $u['email']]);

json_out([
    'status'=>'ok',
    'mensagem'=>'Foto do cliente atualizada.',
    'foto_cliente'=>$caminho
]);
