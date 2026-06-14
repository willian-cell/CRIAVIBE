<?php
require_once __DIR__ . '/../config.php';

unset($_SESSION['agendamento_admin_email']);
unset($_SESSION['agendamento_aluno_id']);
unset($_SESSION['agendamento_aluno_token']);

json_out([
    'status' => 'ok',
    'mensagem' => 'Deslogado com sucesso.'
]);
