<?php
/**
 * Entra no sistema como um fotografo, sem precisar da senha dele.
 *
 * A sessao original do administrador fica guardada em $_SESSION['admin_origem'],
 * de onde volta_admin.php a restaura. Enquanto a personificacao esta ativa,
 * me.php sinaliza `impersonando`, para que a interface deixe isso visivel -
 * agir como outra pessoa sem aviso na tela e receita de erro operacional.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

$admin = require_super_admin();
admin_ensure_schema();

$body = body();
$id = (int)($body['id'] ?? 0);
if (!$id) json_out(['status' => 'erro', 'mensagem' => 'ID do fotografo obrigatorio.'], 400);

$stmt = db()->prepare("SELECT id, nome, email, tipo, foto_perfil, bloqueado FROM usuarios WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$alvo = $stmt->fetch();
if (!$alvo) json_out(['status' => 'erro', 'mensagem' => 'Fotografo nao encontrado.'], 404);

if (strtolower(trim($alvo['email'])) === strtolower(trim(ADMIN_EMAIL))) {
    json_out(['status' => 'erro', 'mensagem' => 'Voce ja esta nesta conta.'], 400);
}

error_log("Admin {$admin['email']} entrou como {$alvo['email']}.");

$_SESSION['admin_origem'] = $admin;
$_SESSION['usuario'] = [
    'id'    => $alvo['id'],
    'nome'  => $alvo['nome'],
    'email' => $alvo['email'],
    'tipo'  => $alvo['tipo'],
    'foto_perfil' => $alvo['foto_perfil'] ?? null,
];

json_out([
    'status' => 'ok',
    'mensagem' => 'Sessao aberta como ' . $alvo['nome'] . '.',
    'usuario' => $_SESSION['usuario'],
]);
