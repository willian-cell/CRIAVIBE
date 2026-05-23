<?php
require_once __DIR__.'/../config.php';

$u = require_auth();
$body = body();

$nome = trim($body['nome'] ?? '');
$email = strtolower(trim($body['email'] ?? ''));
$emailAtual = strtolower(trim($u['email'] ?? ''));

if (!$nome) {
    json_out(['status' => 'erro', 'mensagem' => 'Nome obrigatorio.'], 400);
}

if (!$email) {
    json_out(['status' => 'erro', 'mensagem' => 'E-mail obrigatorio.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['status' => 'erro', 'mensagem' => 'E-mail invalido.'], 400);
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    if ($email !== $emailAtual) {
        $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $pdo->rollBack();
            json_out(['status' => 'erro', 'mensagem' => 'E-mail ja cadastrado.'], 409);
        }

        $updGalerias = $pdo->prepare("UPDATE galerias SET usuario_email = ? WHERE usuario_email = ?");
        $updGalerias->execute([$email, $emailAtual]);

        $updClientes = $pdo->prepare("UPDATE clientes SET fotografo_email = ? WHERE fotografo_email = ?");
        $updClientes->execute([$email, $emailAtual]);
    }

    $updUsuario = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ? WHERE id = ?");
    $updUsuario->execute([$nome, $email, $u['id']]);

    $pdo->commit();

    $_SESSION['usuario']['nome'] = $nome;
    $_SESSION['usuario']['email'] = $email;

    json_out([
        'status' => 'ok',
        'mensagem' => 'Dados atualizados com sucesso.',
        'usuario' => $_SESSION['usuario']
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro ao atualizar perfil: '.$e->getMessage());
    json_out(['status' => 'erro', 'mensagem' => 'Nao foi possivel atualizar os dados.'], 500);
}
