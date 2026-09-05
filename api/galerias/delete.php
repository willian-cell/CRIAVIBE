<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../lib/Galeria.php';
$u = require_fotografo();
$body = body();
$id = (int)($body['id'] ?? 0);
if (!$id) json_out(['status'=>'erro','mensagem'=>'ID inválido.'], 400);

$chk = db()->prepare("SELECT id FROM galerias WHERE id=? AND usuario_email=? LIMIT 1");
$chk->execute([$id, $u['email']]);
if (!$chk->fetch()) json_out(['status'=>'erro','mensagem'=>'Galeria não encontrada.'], 404);

// Remove os arquivos do R2 e os registros associados.
galeria_excluir(db(), $id);

json_out(['status'=>'ok','mensagem'=>'Galeria excluída.']);
