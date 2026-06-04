<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

agendamento_require_admin();

$body = body();
$tipo = trim($body['tipo'] ?? '');
$db = db();
agendamento_ensure_schema($db);

if ($tipo === 'aula') {
    $aulaId = (int)($body['aula_id'] ?? 0);
    if ($aulaId <= 0) json_out(['status' => 'erro', 'mensagem' => 'Aula invalida.'], 400);

    $sel = $db->prepare("SELECT aluno_id FROM agendamento_aulas WHERE id = ? LIMIT 1");
    $sel->execute([$aulaId]);
    $alunoId = (int)($sel->fetchColumn() ?: 0);

    $stmt = $db->prepare("DELETE FROM agendamento_aulas WHERE id = ?");
    $stmt->execute([$aulaId]);
    agendamento_log($db, $aulaId, $alunoId ?: null, 'aula_excluida', [], 'fotografo', $_SESSION['agendamento_admin_email'] ?? null);
    json_out(['status' => 'ok', 'mensagem' => 'Aula removida.']);
}

if ($tipo === 'aluno') {
    $token = trim($body['token_publico'] ?? '');
    if ($token === '') json_out(['status' => 'erro', 'mensagem' => 'Aluno invalido.'], 400);

    $student = agendamento_fetch_student_by_token($db, $token);
    if (!$student) json_out(['status' => 'erro', 'mensagem' => 'Aluno nao encontrado.'], 404);

    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM agendamento_aulas WHERE aluno_id = ?")->execute([(int)$student['id']]);
        $db->prepare("DELETE FROM agendamento_planos WHERE aluno_id = ?")->execute([(int)$student['id']]);
        agendamento_log($db, null, (int)$student['id'], 'aluno_excluido', ['nome' => $student['nome']], 'fotografo', $_SESSION['agendamento_admin_email'] ?? null);
        $db->prepare("DELETE FROM agendamento_alunos WHERE id = ?")->execute([(int)$student['id']]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('Erro ao excluir pre-agendamento: ' . $e->getMessage());
        json_out(['status' => 'erro', 'mensagem' => 'Nao foi possivel excluir o pre-agendamento.'], 500);
    }

    json_out(['status' => 'ok', 'mensagem' => 'Pre-agendamento removido.']);
}

json_out(['status' => 'erro', 'mensagem' => 'Tipo de exclusao invalido.'], 400);
