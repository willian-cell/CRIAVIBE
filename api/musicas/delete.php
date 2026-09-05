<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../lib/Storage.php';
$u = require_fotografo();
$body = body();
$id = (int)($body['id'] ?? 0);
if (!$id) json_out(['status'=>'erro','mensagem'=>'ID inválido.'], 400);

$chk = db()->prepare("SELECT m.id, m.caminho_arquivo, g.usuario_email FROM musicas m JOIN galerias g ON g.id=m.galeria_id WHERE m.id=? LIMIT 1");
$chk->execute([$id]);
$m = $chk->fetch();
if (!$m || $m['usuario_email'] !== $u['email'])
    json_out(['status'=>'erro','mensagem'=>'Música não encontrada.'], 404);

// Links do YouTube nao tem objeto no bucket - storage_delete_url ignora esses casos.
storage_delete_url($m['caminho_arquivo']);
db()->prepare("DELETE FROM musicas WHERE id=?")->execute([$id]);
json_out(['status'=>'ok']);
