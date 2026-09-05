<?php
/**
 * Exclusao de qualquer cliente do sistema pelo painel administrativo.
 *
 * As galerias do cliente nao sao apagadas junto: elas apenas deixam de ter
 * cliente vinculado. Apagar fotos como efeito colateral de remover um contato
 * seria destrutivo demais para uma acao de cadastro.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Storage.php';
require_once __DIR__ . '/_guard.php';

require_super_admin();
admin_ensure_schema();

$body = body();
$id = (int)($body['id'] ?? 0);
if (!$id) json_out(['status' => 'erro', 'mensagem' => 'ID do cliente obrigatorio.'], 400);

$db = db();
$stmt = $db->prepare("SELECT id, nome, fotografo_email, foto_cliente FROM clientes WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$cliente = $stmt->fetch();
if (!$cliente) json_out(['status' => 'erro', 'mensagem' => 'Cliente nao encontrado.'], 404);

$vinculadas = $db->prepare("SELECT COUNT(*) FROM galerias WHERE cliente_id = ?");
$vinculadas->execute([$id]);
$total = (int)$vinculadas->fetchColumn();

$db->prepare("UPDATE galerias SET cliente_id = NULL WHERE cliente_id = ?")->execute([$id]);
storage_delete_url($cliente['foto_cliente'] ?? null);
$db->prepare("DELETE FROM clientes WHERE id = ?")->execute([$id]);

json_out([
    'status' => 'ok',
    'mensagem' => $total
        ? "Cliente removido. {$total} galeria(s) ficaram sem cliente vinculado."
        : 'Cliente removido.',
    'galerias_desvinculadas' => $total,
]);
