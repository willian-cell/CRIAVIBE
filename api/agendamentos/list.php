<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

$token = trim($_GET['token'] ?? '');
$isAdmin = agendamento_is_admin();
$db = db();

$student = $token ? agendamento_fetch_student_by_token($db, $token) : null;
$rows = agendamento_fetch_board($db);
$items = agendamento_format_board($rows, $student['token_publico'] ?? null, $isAdmin);

$total = 0;
foreach ($items as $item) {
    if (!empty($item['is_owner'])) {
        $total += (int)$item['valor_centavos'];
    }
}

$payload = [
    'status' => 'ok',
    'dias' => AGENDAMENTO_DIAS,
    'horarios' => AGENDAMENTO_HORARIOS,
    'valor_centavos' => AGENDAMENTO_VALOR_CENTAVOS,
    'admin' => $isAdmin ? ['email' => $_SESSION['agendamento_admin_email']] : null,
    'aluno_atual' => $student ? [
        'token_publico' => $student['token_publico'],
        'nome' => $student['nome'],
        'email' => $student['email'],
        'telefone' => $student['telefone'],
        'total_centavos' => $total,
    ] : null,
    'aulas' => $items,
];

json_out($payload);
