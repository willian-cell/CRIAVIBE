<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

json_out([
    'status' => 'ok',
    'admin' => agendamento_is_admin()
        ? ['email' => $_SESSION['agendamento_admin_email']]
        : null,
]);
