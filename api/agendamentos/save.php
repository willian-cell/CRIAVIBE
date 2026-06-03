<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

$body = body();
$studentData = agendamento_validate_student($body['aluno'] ?? []);
$lessons = agendamento_validate_lessons($body['aulas'] ?? []);
$token = trim($body['token_publico'] ?? '');
$isAdmin = agendamento_is_admin();

$db = db();
agendamento_ensure_schema($db);
$student = $token ? agendamento_fetch_student_by_token($db, $token) : null;

if ($token !== '' && !$student && !$isAdmin) {
    json_out(['status' => 'erro', 'mensagem' => 'Pre-agendamento nao encontrado para este navegador.'], 404);
}

$db->beginTransaction();

try {
    if (!$student) {
        $token = agendamento_public_token();
        $stmt = $db->prepare("
            INSERT INTO pre_agendamento_alunos (token_publico, nome, email, telefone)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$token, $studentData['nome'], $studentData['email'], $studentData['telefone']]);
        $studentId = (int)$db->lastInsertId();
    } else {
        $studentId = (int)$student['id'];
        $stmt = $db->prepare("
            UPDATE pre_agendamento_alunos
            SET nome = ?, email = ?, telefone = ?
            WHERE id = ?
        ");
        $stmt->execute([$studentData['nome'], $studentData['email'], $studentData['telefone'], $studentId]);
        $db->prepare("DELETE FROM pre_agendamento_aulas WHERE aluno_id = ?")->execute([$studentId]);
    }

    $insert = $db->prepare("
        INSERT INTO pre_agendamento_aulas (aluno_id, dia_semana, data_aula, horario, valor_centavos)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($lessons as $lesson) {
        $insert->execute([
            $studentId,
            $lesson['dia_semana'],
            $lesson['data_aula'],
            $lesson['horario'],
            $lesson['valor_centavos'],
        ]);
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
        json_out(['status' => 'erro', 'mensagem' => 'Esse dia da semana ja foi preenchido por outro aluno.'], 409);
    }
    error_log('Erro ao salvar pre-agendamento: ' . $e->getMessage());
    json_out(['status' => 'erro', 'mensagem' => 'Nao foi possivel salvar o pre-agendamento.'], 500);
} catch (Throwable $e) {
    $db->rollBack();
    error_log('Erro ao salvar pre-agendamento: ' . $e->getMessage());
    json_out(['status' => 'erro', 'mensagem' => 'Nao foi possivel salvar o pre-agendamento.'], 500);
}
