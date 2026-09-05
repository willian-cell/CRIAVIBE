<?php
/**
 * Exclusao de qualquer galeria do sistema pelo painel administrativo,
 * incluindo a remocao dos arquivos no R2.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Galeria.php';
require_once __DIR__ . '/_guard.php';

require_super_admin();

$body = body();
$id = (int)($body['id'] ?? 0);
if (!$id) json_out(['status' => 'erro', 'mensagem' => 'ID da galeria obrigatorio.'], 400);

$db = db();
$stmt = $db->prepare("SELECT id, nome, usuario_email FROM galerias WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$galeria = $stmt->fetch();
if (!$galeria) json_out(['status' => 'erro', 'mensagem' => 'Galeria nao encontrada.'], 404);

$r = galeria_excluir($db, $id);

error_log("Admin removeu a galeria #{$id} ({$galeria['nome']}) de {$galeria['usuario_email']}: {$r['fotos_removidas']} fotos.");

json_out([
    'status' => 'ok',
    'mensagem' => 'Galeria excluida.',
    'removidos' => $r,
]);
