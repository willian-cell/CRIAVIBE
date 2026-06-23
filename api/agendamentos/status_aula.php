<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

agendamento_require_admin();
$body = body();
$aulaId = (int)($body['aula_id'] ?? 0);
$status = trim($body['status'] ?? '');
$motivo = trim($body['motivo'] ?? '');

if ($aulaId < 1 || !in_array($status, AGENDAMENTO_STATUS, true)) {
    json_out(['status' => 'erro', 'mensagem' => 'Aula ou status invalido.'], 400);
}

try {
    $db = db();
    agendamento_ensure_schema($db);
    $find = $db->prepare('SELECT aluno_id, observacoes FROM agendamento_aulas WHERE id = ? LIMIT 1');
    $find->execute([$aulaId]);
    $aula = $find->fetch();
    if (!$aula) json_out(['status' => 'erro', 'mensagem' => 'Aula nao encontrada.'], 404);

    $notes = $aula['observacoes'] ?? '';
    if ($motivo !== '') $notes = trim($notes . "\n" . $motivo);
    $upd = $db->prepare('UPDATE agendamento_aulas SET status = ?, observacoes = ? WHERE id = ?');
    $upd->execute([$status, $notes ?: null, $aulaId]);
    agendamento_log($db, $aulaId, (int)$aula['aluno_id'], 'status_atualizado', ['status' => $status, 'motivo' => $motivo ?: null], 'fotografo', $_SESSION['agendamento_admin_email'] ?? null);
    json_out(['status' => 'ok']);
} catch (Throwable $e) {
    json_out(['status' => 'erro', 'mensagem' => 'Nao foi possivel atualizar o status da aula.'], 500);
}
