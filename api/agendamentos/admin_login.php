<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

$body = body();
$email = strtolower(trim($body['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['status' => 'erro', 'mensagem' => 'Informe um email valido.'], 400);
}

if (!in_array($email, AGENDAMENTO_ADMIN_EMAILS, true)) {
    json_out(['status' => 'erro', 'mensagem' => 'Email nao autorizado para edicao do pre-agendamento.'], 403);
}

$_SESSION['agendamento_admin_email'] = $email;

json_out([
    'status' => 'ok',
    'admin' => ['email' => $email],
]);
