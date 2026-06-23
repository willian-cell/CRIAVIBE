<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

$body = body();
$email = strtolower(trim($body['email'] ?? ''));
$senha = $body['senha'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['status' => 'erro', 'mensagem' => 'Informe um email valido.'], 400);
}

try {
    $stmt = db()->prepare("SELECT tipo, senha FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !in_array($user['tipo'] ?? '', ['fotografo', 'admin'], true) || !password_verify($senha, $user['senha'] ?? '')) {
        json_out(['status' => 'erro', 'mensagem' => 'Email nao autorizado para edicao do pre-agendamento.'], 403);
    }
} catch (Throwable $e) {
    json_out(['status' => 'erro', 'mensagem' => 'Nao foi possivel validar a conta do fotografo.'], 500);
}

$_SESSION['agendamento_admin_email'] = $email;

json_out([
    'status' => 'ok',
    'admin' => ['email' => $email],
]);
