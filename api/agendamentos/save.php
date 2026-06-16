<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

$body = body();
$studentData = agendamento_validate_student($body['aluno'] ?? []);
$lessons = agendamento_validate_lessons($body['aulas'] ?? []);
$token = trim($body['token_publico'] ?? $_SESSION['agendamento_aluno_token'] ?? '');
$isAdmin = agendamento_is_admin();
$planData = $body['plano'] ?? [];

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

if ($token !== '' && !$student && !$isAdmin) {
    json_out(['status' => 'erro', 'mensagem' => 'Pre-agendamento nao encontrado para este navegador.'], 404);
}

if (!$isAdmin) {
    $horasPorDia = [];
    foreach ($lessons as $lesson) {
        $data = $lesson['data_aula'];
        $horas = (int)$lesson['quantidade_horas'];
        if ($horas > 3) {
            json_out(['status' => 'erro', 'mensagem' => 'O limite máximo é de 3 horas de aula por dia para alunos.'], 400);
        }
        if (!isset($horasPorDia[$data])) {
            $horasPorDia[$data] = 0;
        }
        $horasPorDia[$data] += $horas;
        if ($horasPorDia[$data] > 3) {
            json_out(['status' => 'erro', 'mensagem' => 'Você não pode agendar mais de 3 horas de aula no mesmo dia (data: ' . date('d/m/Y', strtotime($data)) . ').'], 400);
        }
    }
}

$db->beginTransaction();

try {
    if (!$student) {
        $token = agendamento_public_token();
        $stmt = $db->prepare("
            INSERT INTO agendamento_alunos (nome, email, telefone, token_publico, codigo_acesso)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$studentData['nome'], $studentData['email'], $studentData['telefone'], $token, agendamento_codigo_acesso()]);
        $studentId = (int)$db->lastInsertId();
        agendamento_log($db, null, $studentId, 'aluno_criado', ['nome' => $studentData['nome']], 'aluno', $studentData['email']);
    } else {
        $studentId = (int)$student['id'];
        $stmt = $db->prepare("
            UPDATE agendamento_alunos
            SET nome = ?, email = ?, telefone = ?
            WHERE id = ?
        ");
        $stmt->execute([$studentData['nome'], $studentData['email'], $studentData['telefone'], $studentId]);
        $db->prepare("DELETE FROM agendamento_aulas WHERE aluno_id = ?")->execute([$studentId]);
        agendamento_log($db, null, $studentId, 'aluno_atualizado', ['nome' => $studentData['nome']], $isAdmin ? 'fotografo' : 'aluno', $isAdmin ? ($_SESSION['agendamento_admin_email'] ?? null) : $studentData['email']);
    }

    $planName = trim($planData['nome'] ?? 'Curso Fotografia Prática');
    $totalAulas = max(0, (int)($planData['total_aulas'] ?? count($lessons)));
    $planStatus = trim($planData['status'] ?? 'ativo');
    if (!in_array($planStatus, ['ativo', 'pausado', 'concluido', 'cancelado'], true)) {
        $planStatus = 'ativo';
    }

    $formaPagamento = trim($planData['forma_pagamento'] ?? '');

    $planStmt = $db->prepare("SELECT id FROM agendamento_planos WHERE aluno_id = ? ORDER BY id ASC LIMIT 1");
    $planStmt->execute([$studentId]);
    $planId = (int)($planStmt->fetchColumn() ?: 0);
    if ($planId > 0) {
        $updPlan = $db->prepare("
            UPDATE agendamento_planos
            SET nome = ?, total_aulas = ?, aulas_usadas = ?, status = ?, forma_pagamento = ?
            WHERE id = ?
        ");
        $updPlan->execute([$planName, $totalAulas, count($lessons), $planStatus, $formaPagamento ?: null, $planId]);
    } else {
        $insPlan = $db->prepare("
            INSERT INTO agendamento_planos (aluno_id, nome, total_aulas, aulas_usadas, status, forma_pagamento)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insPlan->execute([$studentId, $planName, $totalAulas, count($lessons), $planStatus, $formaPagamento ?: null]);
        $planId = (int)$db->lastInsertId();
    }

    agendamento_assert_no_schedule_overlap($db, $lessons, $studentId);

    $insert = $db->prepare("
        INSERT INTO agendamento_aulas (
            aluno_id,
            plano_id,
            modulo_id,
            assunto_id,
            dia_semana,
            data_aula,
            horario,
            quantidade_horas,
            cidade,
            valor_hora_centavos,
            valor_centavos,
            status,
            observacoes,
            endereco,
            latitude,
            longitude
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($lessons as $lesson) {
        $insert->execute([
            $studentId,
            $planId,
            $lesson['modulo_id'],
            $lesson['assunto_id'],
            $lesson['dia_semana'],
            $lesson['data_aula'],
            $lesson['horario'],
            $lesson['quantidade_horas'],
            $lesson['cidade'],
            $lesson['valor_hora_centavos'],
            $lesson['valor_centavos'],
            $lesson['status'],
            $lesson['observacoes'],
            $lesson['endereco'] ?? null,
            $lesson['latitude'] ?? null,
            $lesson['longitude'] ?? null,
        ]);
        agendamento_log($db, (int)$db->lastInsertId(), $studentId, 'aula_salva', $lesson, $isAdmin ? 'fotografo' : 'aluno', $isAdmin ? ($_SESSION['agendamento_admin_email'] ?? null) : $studentData['email']);
    }

    $db->commit();

    json_out([
        'status' => 'ok',
        'mensagem' => 'Pre-agendamento salvo com sucesso.',
        'token_publico' => $token,
    ]);
} catch (PDOException $e) {
    $db->rollBack();
    if ($e->getCode() === '23000') {
        json_out(['status' => 'erro', 'mensagem' => 'Esse horario ja foi preenchido para a data escolhida.'], 409);
    }
    error_log('Erro ao salvar pre-agendamento: ' . $e->getMessage());
    json_out([
        'status' => 'erro',
        'codigo' => 'save_database',
        'mensagem' => 'Nao foi possivel salvar o pre-agendamento. Codigo SQL: ' . $e->getCode(),
    ], 500);
} catch (Throwable $e) {
    $db->rollBack();
    error_log('Erro ao salvar pre-agendamento: ' . $e->getMessage());
    json_out([
        'status' => 'erro',
        'codigo' => 'save_unexpected',
        'mensagem' => 'Nao foi possivel salvar o pre-agendamento. Erro inesperado no servidor.',
    ], 500);
}
