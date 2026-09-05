<?php
/**
 * Edita e bloqueia contas de fotografos a partir do painel administrativo.
 * Bloquear nao apaga nada: apenas impede o login, preservando galerias,
 * clientes e arquivos.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

require_super_admin();
admin_ensure_schema();

$body = body();
$id = (int)($body['id'] ?? 0);
if (!$id) json_out(['status' => 'erro', 'mensagem' => 'ID do fotografo obrigatorio.'], 400);

$db = db();
$stmt = $db->prepare("SELECT id, nome, email, tipo, bloqueado, telefone, cidade FROM usuarios WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$alvo = $stmt->fetch();
if (!$alvo) json_out(['status' => 'erro', 'mensagem' => 'Fotografo nao encontrado.'], 404);

$campos = [];
$params = [];

if (array_key_exists('nome', $body)) {
    $nome = trim((string)$body['nome']);
    if ($nome === '' || mb_strlen($nome) > 160) {
        json_out(['status' => 'erro', 'mensagem' => 'Nome invalido.'], 400);
    }
    $campos[] = 'nome = ?';
    $params[] = $nome;
}

if (array_key_exists('tipo', $body)) {
    $tipo = strtolower(trim((string)$body['tipo']));
    if (!in_array($tipo, ['fotografo', 'admin'], true)) {
        json_out(['status' => 'erro', 'mensagem' => 'Tipo deve ser fotografo ou admin.'], 400);
    }
    $campos[] = 'tipo = ?';
    $params[] = $tipo;
}

foreach (['telefone' => 40, 'cidade' => 120] as $campo => $limite) {
    if (!array_key_exists($campo, $body)) continue;
    $valor = trim((string)$body[$campo]);
    if (mb_strlen($valor) > $limite) {
        json_out(['status' => 'erro', 'mensagem' => ucfirst($campo) . ' excede o tamanho permitido.'], 400);
    }
    $campos[] = "$campo = ?";
    $params[] = $valor !== '' ? $valor : null;
}

if (array_key_exists('bloqueado', $body)) {
    // A conta administradora precisa continuar entrando para nao trancar o sistema.
    admin_bloquear_auto_alvo($alvo);
    $campos[] = 'bloqueado = ?';
    $params[] = ((int)$body['bloqueado'] === 1) ? 1 : 0;
}

if (!$campos) json_out(['status' => 'erro', 'mensagem' => 'Nada para alterar.'], 400);

$params[] = $id;
$upd = $db->prepare("UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = ?");
$upd->execute($params);

$stmt->execute([$id]);
json_out([
    'status' => 'ok',
    'mensagem' => 'Fotografo atualizado.',
    'fotografo' => $stmt->fetch(),
]);
