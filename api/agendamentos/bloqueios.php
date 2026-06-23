<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

agendamento_require_admin();
$body = body();
$action = trim($body['acao'] ?? '');
$date = trim($body['data_aula'] ?? '');
$dateObject = DateTime::createFromFormat('Y-m-d', $date);

if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
    json_out(['status' => 'erro', 'mensagem' => 'Informe uma data valida.'], 400);
}

try {
    $db = db();
    agendamento_ensure_schema($db);

    if ($action === 'bloquear') {
        $check = $db->prepare('SELECT COUNT(*) FROM agendamento_aulas WHERE data_aula = ?');
        $check->execute([$date]);
        if ((int)$check->fetchColumn() > 0) {
            json_out(['status' => 'erro', 'mensagem' => 'Nao e possivel bloquear um dia que ja possui aulas.'], 409);
        }
        $stmt = $db->prepare('INSERT INTO agendamento_bloqueios (data_aula, motivo) VALUES (?, ?)');
        $stmt->execute([$date, trim($body['motivo'] ?? '') ?: null]);
        json_out(['status' => 'ok', 'mensagem' => 'Dia bloqueado para novos agendamentos.']);
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
