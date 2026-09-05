<?php
/**
 * Cadastro de um novo fotografo direto pelo painel administrativo.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

require_super_admin();
admin_ensure_schema();

$body = body();
$nome = trim((string)($body['nome'] ?? ''));
$email = strtolower(trim((string)($body['email'] ?? '')));
$senha = (string)($body['senha'] ?? '');
$tipo = strtolower(trim((string)($body['tipo'] ?? 'fotografo')));
$telefone = trim((string)($body['telefone'] ?? ''));
$cidade = trim((string)($body['cidade'] ?? ''));

if ($nome === '' || mb_strlen($nome) > 160) {
    json_out(['status' => 'erro', 'mensagem' => 'Informe um nome valido.'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    json_out(['status' => 'erro', 'mensagem' => 'Informe um e-mail valido.'], 400);
}
if (strlen($senha) < 6) {
    json_out(['status' => 'erro', 'mensagem' => 'A senha deve ter ao menos 6 caracteres.'], 400);
}
if (!in_array($tipo, ['fotografo', 'admin'], true)) {
    json_out(['status' => 'erro', 'mensagem' => 'Tipo deve ser fotografo ou admin.'], 400);
}

$db = db();
$chk = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
$chk->execute([$email]);
if ($chk->fetch()) {
    json_out(['status' => 'erro', 'mensagem' => 'Ja existe uma conta com esse e-mail.'], 409);
}

$ins = $db->prepare("
    INSERT INTO usuarios (nome, email, senha, tipo, telefone, cidade, bloqueado)
    VALUES (?, ?, ?, ?, ?, ?, 0)
");
$ins->execute([
    $nome,
    $email,
    password_hash($senha, PASSWORD_DEFAULT),
    $tipo,
    $telefone !== '' ? $telefone : null,
    $cidade !== '' ? $cidade : null,
]);

json_out([
    'status' => 'ok',
    'mensagem' => 'Fotografo cadastrado.',
    'id' => (int)$db->lastInsertId(),
]);
