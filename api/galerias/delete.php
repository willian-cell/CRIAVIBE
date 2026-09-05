<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../lib/Storage.php';
$u = require_fotografo();
$body = body();
$id = (int)($body['id'] ?? 0);
if (!$id) json_out(['status'=>'erro','mensagem'=>'ID inválido.'], 400);

$chk = db()->prepare("SELECT id FROM galerias WHERE id=? AND usuario_email=? LIMIT 1");
$chk->execute([$id, $u['email']]);
if (!$chk->fetch()) json_out(['status'=>'erro','mensagem'=>'Galeria não encontrada.'], 404);

// Remover do R2 os arquivos referenciados por esta galeria.
// A varredura e feita pelos registros do banco, nunca por prefixo, para que um
// id inesperado jamais possa apagar objetos de outra galeria.
$imgs = db()->prepare("SELECT caminho_arquivo, caminho_thumb_small, caminho_thumb_medium, caminho_thumb_large FROM imagens WHERE galeria_id=?");
$imgs->execute([$id]);
foreach ($imgs->fetchAll() as $img) {
    storage_delete_imagem($img);
}

$mus = db()->prepare("SELECT caminho_arquivo FROM musicas WHERE galeria_id=?");
$mus->execute([$id]);
foreach ($mus->fetchAll() as $m) {
    storage_delete_url($m['caminho_arquivo']);
}

// A capa de apresentacao pode ser um upload proprio, fora da tabela de imagens.
$capa = db()->prepare("SELECT capa_apresentacao FROM galerias WHERE id=?");
$capa->execute([$id]);
$capaUrl = $capa->fetchColumn();
if ($capaUrl && strpos((string)$capaUrl, '/capas/') !== false) {
    storage_delete_url($capaUrl);
}

db()->prepare("DELETE FROM imagens WHERE galeria_id=?")->execute([$id]);
db()->prepare("DELETE FROM musicas WHERE galeria_id=?")->execute([$id]);
db()->prepare("DELETE FROM galerias WHERE id=?")->execute([$id]);

json_out(['status'=>'ok','mensagem'=>'Galeria excluída.']);
