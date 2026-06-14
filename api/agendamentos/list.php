<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

$token = trim($_GET['token'] ?? $_SESSION['agendamento_aluno_token'] ?? '');
$isAdmin = agendamento_is_admin();

try {
    $db = db();
    agendamento_ensure_schema($db);
} catch (Throwable $e) {
    error_log('Erro ao preparar schema de agendamento: ' . $e->getMessage());
    json_out([
        'status' => 'erro',
        'codigo' => 'schema_prepare',
        'mensagem' => 'Nao foi possivel preparar o banco de agendamento. Verifique os logs do deploy.',
    ], 500);
}

$student = $token ? agendamento_fetch_student_by_token($db, $token) : null;
$rows = agendamento_fetch_board($db);
$items = agendamento_format_board($rows, $student['token_publico'] ?? null, $isAdmin);
$course = agendamento_fetch_course($db);
$plan = null;
if ($student) {
    $stmt = $db->prepare("
        SELECT id, nome, total_aulas, aulas_usadas, status, forma_pagamento, cidade, valor_hora_centavos
        FROM agendamento_planos
        WHERE aluno_id = ?
        ORDER BY id ASC
        LIMIT 1
    ");
    $stmt->execute([(int)$student['id']]);
    $plan = $stmt->fetch() ?: null;
}

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
    'horas_opcoes' => AGENDAMENTO_HORAS_OPCOES,
    'valor_santo_antonio_centavos' => AGENDAMENTO_VALOR_SANTO_ANTONIO_CENTAVOS,
    'valor_outra_cidade_centavos' => AGENDAMENTO_VALOR_OUTRA_CIDADE_CENTAVOS,
    'status_opcoes' => AGENDAMENTO_STATUS,
    'curso' => $course,
    'admin' => $isAdmin ? ['email' => $_SESSION['agendamento_admin_email']] : null,
    'aluno_atual' => $student ? [
        'token_publico' => $student['token_publico'],
        'nome' => $student['nome'],
        'email' => $student['email'],
        'telefone' => $student['telefone'],
        'total_centavos' => $total,
        'plano' => $plan,
    ] : null,
    'aulas' => $items,
];

json_out($payload);
