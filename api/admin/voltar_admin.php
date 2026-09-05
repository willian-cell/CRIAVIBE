<?php
/**
 * Encerra a personificacao iniciada em impersonar.php e devolve a sessao
 * administrativa original.
 *
 * Nao usa require_super_admin(): durante a personificacao a sessao ativa e a
 * do fotografo. A autorizacao aqui vem de $_SESSION['admin_origem'], que so o
 * proprio impersonar.php grava.
 */
require_once __DIR__ . '/../config.php';

$origem = $_SESSION['admin_origem'] ?? null;
if (!$origem || !is_super_admin($origem)) {
    json_out(['status' => 'erro', 'mensagem' => 'Nenhuma sessao administrativa para restaurar.'], 400);
}

$_SESSION['usuario'] = $origem;
unset($_SESSION['admin_origem']);

json_out([
    'status' => 'ok',
    'mensagem' => 'Voce voltou para a conta administradora.',
    'usuario' => $_SESSION['usuario'],
]);
