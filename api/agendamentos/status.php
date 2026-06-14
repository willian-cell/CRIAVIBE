<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

try {
    $db = db();
    agendamento_ensure_schema($db);

    // 1. Verifica se está logado como Fotógrafo (Admin)
    if (agendamento_is_admin()) {
        $email = $_SESSION['agendamento_admin_email'];
        // Tenta pegar o nome na tabela usuarios
        $stmt = $db->prepare("SELECT nome FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        json_out([
            'status' => 'ok',
            'logado' => true,
            'tipo' => 'fotografo',
            'admin' => [
                'email' => $email,
                'nome' => $u['nome'] ?? 'Professor'
            ]
        ]);
    }

    // 2. Verifica se está logado como Aluno
    $alunoId = $_SESSION['agendamento_aluno_id'] ?? 0;
    $token = $_SESSION['agendamento_aluno_token'] ?? '';

    $student = null;
    if ($alunoId) {
        $stmt = $db->prepare("SELECT * FROM agendamento_alunos WHERE id = ? LIMIT 1");
        $stmt->execute([$alunoId]);
        $student = $stmt->fetch();
    } elseif ($token) {
        $student = agendamento_fetch_student_by_token($db, $token);
    }

    if ($student) {
        // Renova a sessão se ela expirou mas o token estava guardado
        $_SESSION['agendamento_aluno_id'] = $student['id'];
        $_SESSION['agendamento_aluno_token'] = $student['token_publico'];

        // Pega plano do aluno
        $planStmt = $db->prepare("SELECT * FROM agendamento_planos WHERE aluno_id = ? ORDER BY id ASC LIMIT 1");
        $planStmt->execute([$student['id']]);
        $plan = $planStmt->fetch() ?: null;

        json_out([
            'status' => 'ok',
            'logado' => true,
            'tipo' => 'aluno',
            'token' => $student['token_publico'],
            'aluno' => [
                'id' => $student['id'],
                'nome' => $student['nome'],
                'email' => $student['email'],
                'telefone' => $student['telefone'],
                'plano' => $plan
            ]
        ]);
    }

    // Não logado
    json_out([
        'status' => 'ok',
        'logado' => false
    ]);

} catch (Throwable $e) {
    error_log('Erro ao checar status de autenticacao de agendamento: ' . $e->getMessage());
    json_out(['status' => 'erro', 'mensagem' => 'Erro interno ao recuperar status.'], 500);
}
