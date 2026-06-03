<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

unset($_SESSION['agendamento_admin_email']);

json_out(['status' => 'ok']);
