<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

agendamento_require_admin();
$body = body();
$alunoId = (int)($body['aluno_id'] ?? 0);
$lessons = agendamento_validate_lessons([$body['aula'] ?? []]);

try {
    $db = db();
    agendamento_ensure_schema($db);
    agendamento_assert_dates_not_blocked($db, $lessons);
    $db->beginTransaction();

    $student = null;
    if ($alunoId > 0) {
        $stmt = $db->prepare('SELECT * FROM agendamento_alunos WHERE id = ? LIMIT 1');
        $stmt->execute([$alunoId]);
        $student = $stmt->fetch();
        if (!$student) json_out(['status' => 'erro', 'mensagem' => 'Aluno selecionado nao foi encontrado.'], 404);
    } else {
        $studentData = agendamento_validate_student($body['aluno'] ?? []);
        $stmt = $db->prepare('SELECT * FROM agendamento_alunos WHERE email = ? ORDER BY id ASC LIMIT 1');
        $stmt->execute([$studentData['email']]);
        $student = $stmt->fetch();
        if (!$student) {
            $insertStudent = $db->prepare('INSERT INTO agendamento_alunos (nome, email, telefone, token_publico, codigo_acesso) VALUES (?, ?, ?, ?, ?)');
            $insertStudent->execute([$studentData['nome'], $studentData['email'], $studentData['telefone'], agendamento_public_token(), agendamento_codigo_acesso()]);
            $student = ['id' => (int)$db->lastInsertId(), 'nome' => $studentData['nome'], 'email' => $studentData['email'], 'telefone' => $studentData['telefone']];
        }
    }

    $studentId = (int)$student['id'];
    agendamento_assert_no_schedule_overlap($db, $lessons, null);

    $planStmt = $db->prepare('SELECT id FROM agendamento_planos WHERE aluno_id = ? ORDER BY id ASC LIMIT 1');
    $planStmt->execute([$studentId]);
    $planId = (int)($planStmt->fetchColumn() ?: 0);
    if (!$planId) {
        $newPlan = $db->prepare("INSERT INTO agendamento_planos (aluno_id, nome, total_aulas, aulas_usadas, status) VALUES (?, 'Curso Fotografia Pratica', 1, 0, 'ativo')");
        $newPlan->execute([$studentId]);
        $planId = (int)$db->lastInsertId();
    }

    $lesson = $lessons[0];
    $insert = $db->prepare('INSERT INTO agendamento_aulas (aluno_id, plano_id, modulo_id, assunto_id, dia_semana, data_aula, horario, quantidade_horas, cidade, valor_hora_centavos, valor_centavos, status, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$studentId, $planId, $lesson['modulo_id'], $lesson['assunto_id'], $lesson['dia_semana'], $lesson['data_aula'], $lesson['horario'], $lesson['quantidade_horas'], $lesson['cidade'], $lesson['valor_hora_centavos'], $lesson['valor_centavos'], $lesson['status'], $lesson['observacoes']]);
    $aulaId = (int)$db->lastInsertId();
    $db->prepare('UPDATE agendamento_planos SET aulas_usadas = aulas_usadas + 1 WHERE id = ?')->execute([$planId]);
    agendamento_log($db, $aulaId, $studentId, 'aula_agendada_professor', $lesson, 'fotografo', $_SESSION['agendamento_admin_email'] ?? null);
    $db->commit();
    json_out(['status' => 'ok', 'aula_id' => $aulaId, 'aluno_id' => $studentId]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    throw $e;
}
