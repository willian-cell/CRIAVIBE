<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

agendamento_require_admin();

try {
    $db = db();
    agendamento_ensure_schema($db);
    $stmt = $db->query("SELECT a.id, a.nome, a.email, a.telefone, p.nome AS plano_nome FROM agendamento_alunos a LEFT JOIN agendamento_planos p ON p.aluno_id = a.id ORDER BY a.nome ASC");
    json_out(['status' => 'ok', 'alunos' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    json_out(['status' => 'erro', 'mensagem' => 'Nao foi possivel carregar os alunos.'], 500);
}
