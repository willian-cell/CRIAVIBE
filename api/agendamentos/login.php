<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

$body = body();
$email = strtolower(trim($body['email'] ?? ''));
$senha = $body['senha'] ?? '';

if (!$email || !$senha) {
    json_out(['status' => 'erro', 'mensagem' => 'E-mail e senha obrigatórios.'], 400);
}

try {
    $db = db();
    agendamento_ensure_schema($db);

    // 1. Verifica se é Professor Fotógrafo (Admin)
    if (in_array($email, AGENDAMENTO_ADMIN_EMAILS, true)) {
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if ($u && password_verify($senha, $u['senha'])) {
            $_SESSION['agendamento_admin_email'] = $email;
            // Desloga sessão de aluno para evitar conflitos
            unset($_SESSION['agendamento_aluno_id']);
            unset($_SESSION['agendamento_aluno_token']);

            json_out([
                'status' => 'ok',
                'tipo' => 'fotografo',
                'admin' => ['email' => $email, 'nome' => $u['nome']]
            ]);
        }
    }

    // 2. Se não for admin ou se a senha do admin falhou, tenta logar como Aluno
    $stmt = $db->prepare("SELECT * FROM agendamento_alunos WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $aluno = $stmt->fetch();

    if ($aluno && $aluno['senha_hash'] && password_verify($senha, $aluno['senha_hash'])) {
        $_SESSION['agendamento_aluno_id'] = $aluno['id'];
        $_SESSION['agendamento_aluno_token'] = $aluno['token_publico'];
        // Desloga admin
        unset($_SESSION['agendamento_admin_email']);

        json_out([
            'status' => 'ok',
            'tipo' => 'aluno',
            'token' => $aluno['token_publico'],
            'aluno' => [
                'id' => $aluno['id'],
                'nome' => $aluno['nome'],
                'email' => $aluno['email'],
                'telefone' => $aluno['telefone']
            ]
        ]);
    }

    // Se falhou em ambos
    json_out(['status' => 'erro', 'mensagem' => 'E-mail ou senha incorretos.'], 401);

} catch (Throwable $e) {
    error_log('Erro ao realizar login no agendamento: ' . $e->getMessage());
    json_out(['status' => 'erro', 'mensagem' => 'Erro interno no servidor ao processar o login.'], 500);
}
