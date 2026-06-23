<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

agendamento_require_admin();
$body = body();
$action = trim($body['acao'] ?? '');
$mode = trim($body['modo'] ?? 'somente_novos');
$date = trim($body['data_aula'] ?? '');
$dateObject = DateTime::createFromFormat('Y-m-d', $date);

if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
    json_out(['status' => 'erro', 'mensagem' => 'Informe uma data valida.'], 400);
}

try {
    $db = db();
    agendamento_ensure_schema($db);

    if ($action === 'bloquear') {
        $db->beginTransaction();
        try {
        $check = $db->prepare("SELECT COUNT(*) FROM agendamento_aulas WHERE data_aula = ? AND status NOT IN ('cancelado', 'remarcado')");
        $check->execute([$date]);
        $activeLessons = (int)$check->fetchColumn();
        if ($activeLessons > 0 && $mode === 'cancelar_aulas') {
            $reason = trim($body['motivo'] ?? '') ?: 'Dia bloqueado pelo professor.';
            $cancel = $db->prepare("UPDATE agendamento_aulas SET status = 'cancelado', observacoes = TRIM(CONCAT_WS('\n', observacoes, ?)) WHERE data_aula = ? AND status NOT IN ('cancelado', 'remarcado')");
            $cancel->execute([$reason, $date]);
        }
        $stmt = $db->prepare('INSERT INTO agendamento_bloqueios (data_aula, motivo) VALUES (?, ?)');
        $stmt->execute([$date, trim($body['motivo'] ?? '') ?: null]);
        $db->commit();
        json_out(['status' => 'ok', 'mensagem' => 'Dia bloqueado para novos agendamentos.']);
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    if ($action === 'desbloquear') {
        $stmt = $db->prepare('DELETE FROM agendamento_bloqueios WHERE data_aula = ?');
        $stmt->execute([$date]);
        json_out(['status' => 'ok', 'mensagem' => 'Dia liberado para agendamentos.']);
    }

    json_out(['status' => 'erro', 'mensagem' => 'Acao invalida.'], 400);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        json_out(['status' => 'erro', 'mensagem' => 'Este dia ja esta bloqueado.'], 409);
    }
    throw $e;
}
