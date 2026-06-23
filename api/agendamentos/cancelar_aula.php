<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

$body = body();
$aulaId = (int)($body['aula_id'] ?? 0);
$token = trim($body['token_publico'] ?? $_SESSION['agendamento_aluno_token'] ?? '');

if ($aulaId <= 0 || $token === '') {
    json_out(['status' => 'erro', 'mensagem' => 'Aula ou sessão inválida.'], 400);
}

try {
    $db = db();
    agendamento_ensure_schema($db);
    $student = agendamento_fetch_student_by_token($db, $token);
    if (!$student) {
        json_out(['status' => 'erro', 'mensagem' => 'Sessão do aluno não encontrada.'], 403);
    }

    $db->beginTransaction();
    $select = $db->prepare('SELECT id, plano_id, status FROM agendamento_aulas WHERE id = ? AND aluno_id = ? LIMIT 1');
    $select->execute([$aulaId, (int)$student['id']]);
    $lesson = $select->fetch();
    if (!$lesson) {
        $db->rollBack();
        json_out(['status' => 'erro', 'mensagem' => 'Aula não encontrada ou sem permissão para removê-la.'], 404);
    }

    $db->prepare('DELETE FROM agendamento_aulas WHERE id = ? AND aluno_id = ?')->execute([$aulaId, (int)$student['id']]);
    if (!empty($lesson['plano_id'])) {
        $db->prepare('UPDATE agendamento_planos SET aulas_usadas = CASE WHEN aulas_usadas > 0 THEN aulas_usadas - 1 ELSE 0 END WHERE id = ?')
            ->execute([(int)$lesson['plano_id']]);
    }
    agendamento_log($db, $aulaId, (int)$student['id'], 'aula_cancelada_aluno', ['status_anterior' => $lesson['status']], 'aluno', $student['email']);
    $db->commit();
    json_out(['status' => 'ok', 'mensagem' => 'Agendamento removido com sucesso.']);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Erro ao cancelar aula pelo aluno: ' . $e->getMessage());
    json_out(['status' => 'erro', 'mensagem' => 'Não foi possível remover o agendamento.'], 500);
}
