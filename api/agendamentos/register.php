<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

$body = body();
$dados = $body['dados'] ?? [];
$planoInput = $body['plano'] ?? [];
$pagamentoInput = $body['pagamento'] ?? [];
$aulasInput = $body['aulas'] ?? [];

$nome = trim($dados['nome'] ?? '');
$email = strtolower(trim($dados['email'] ?? ''));
$telefone = agendamento_clean_phone($dados['telefone'] ?? '');
$senha = $dados['senha'] ?? '';

if (!$nome || !$email || !$telefone || !$senha) {
    json_out(['status' => 'erro', 'mensagem' => 'Todos os campos do perfil são obrigatórios (nome, email, telefone, senha).'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['status' => 'erro', 'mensagem' => 'Informe um e-mail válido.'], 400);
}

if (strlen($nome) < 3) {
    json_out(['status' => 'erro', 'mensagem' => 'Informe o nome completo.'], 400);
}

if (strlen($senha) < 6) {
    json_out(['status' => 'erro', 'mensagem' => 'A senha deve conter pelo menos 6 caracteres.'], 400);
}

try {
    $db = db();
    agendamento_ensure_schema($db);

    // Verifica se e-mail de aluno já existe
    $chk = $db->prepare("SELECT id FROM agendamento_alunos WHERE email = ? LIMIT 1");
    $chk->execute([$email]);
    if ($chk->fetch()) {
        json_out(['status' => 'erro', 'mensagem' => 'Este e-mail já está cadastrado.'], 400);
    }

    // Valida as aulas se houver
    $aulasValidadas = [];
    if (!empty($aulasInput)) {
        $aulasValidadas = agendamento_validate_lessons($aulasInput);
        
        $horasPorDia = [];
        foreach ($aulasValidadas as $aula) {
            $data = $aula['data_aula'];
            $horas = (int)$aula['quantidade_horas'];
            if ($horas > 3) {
                json_out(['status' => 'erro', 'mensagem' => 'O limite máximo é de 3 horas de aula por dia para alunos.'], 400);
            }
            if (!isset($horasPorDia[$data])) {
                $horasPorDia[$data] = 0;
            }
            $horasPorDia[$data] += $horas;
            if ($horasPorDia[$data] > 3) {
                json_out(['status' => 'erro', 'mensagem' => 'Você não pode agendar mais de 3 horas de aula no mesmo dia (data: ' . date('d/m/Y', strtotime($data)) . ').'], 400);
            }
        }
        agendamento_assert_dates_not_blocked($db, $aulasValidadas);
        agendamento_assert_student_period_limits($db, $aulasValidadas);
    }

    $db->beginTransaction();

    // 1. Cadastra o Aluno
    $token = agendamento_public_token();
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    $codigoAcesso = agendamento_codigo_acesso();

    $stmt = $db->prepare("
        INSERT INTO agendamento_alunos (nome, email, telefone, senha_hash, token_publico, codigo_acesso)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$nome, $email, $telefone, $senhaHash, $token, $codigoAcesso]);
    $alunoId = (int)$db->lastInsertId();

    agendamento_log($db, null, $alunoId, 'aluno_registrado_sistema', ['nome' => $nome], 'aluno', $email);

    // 2. Cadastra o Plano
    $planoNome = trim($planoInput['nome'] ?? 'Curso Fotografia Prática');
    $totalAulas = max(0, (int)($planoInput['total_aulas'] ?? 0));
    $formaPagamento = trim($pagamentoInput['forma_pagamento'] ?? '');
    $cidade = trim($planoInput['cidade'] ?? 'Santo Antônio do Descoberto');
    $valorHora = agendamento_valor_hora_centavos($cidade);

    $insPlan = $db->prepare("
        INSERT INTO agendamento_planos (aluno_id, nome, total_aulas, aulas_usadas, status, forma_pagamento, cidade, valor_hora_centavos)
        VALUES (?, ?, ?, ?, 'ativo', ?, ?, ?)
    ");
    $insPlan->execute([$alunoId, $planoNome, $totalAulas, count($aulasValidadas), $formaPagamento, $cidade, $valorHora]);
    $planoId = (int)$db->lastInsertId();

    // 3. Cadastra as aulas
    if (!empty($aulasValidadas)) {
        agendamento_assert_no_schedule_overlap($db, $aulasValidadas, $alunoId);

        $insertAula = $db->prepare("
            INSERT INTO agendamento_aulas (
                aluno_id, plano_id, dia_semana, data_aula, horario,
                quantidade_horas, cidade, valor_hora_centavos, valor_centavos, status,
                endereco, latitude, longitude, cep, localizacao_origem, localizacao_precisao, localizacao_precisao_metros
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pre_agendado', ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($aulasValidadas as $aula) {
            $insertAula->execute([
                $alunoId,
                $planoId,
                $aula['dia_semana'],
                $aula['data_aula'],
                $aula['horario'],
                $aula['quantidade_horas'],
                $aula['cidade'],
                $aula['valor_hora_centavos'],
                $aula['valor_centavos'],
                $aula['endereco'] ?? null,
                $aula['latitude'] ?? null,
                $aula['longitude'] ?? null,
                $aula['cep'] ?? null,
                $aula['localizacao_origem'] ?? null,
                $aula['localizacao_precisao'] ?? null,
                $aula['localizacao_precisao_metros'] ?? null
            ]);
            agendamento_log($db, (int)$db->lastInsertId(), $alunoId, 'aula_inicial_agendada', $aula, 'aluno', $email);
        }
    }

    $db->commit();

    // Inicia sessão automaticamente
    $_SESSION['agendamento_aluno_id'] = $alunoId;
    $_SESSION['agendamento_aluno_token'] = $token;
    unset($_SESSION['agendamento_admin_email']); // desloga admin se houver

    json_out([
        'status' => 'ok',
        'mensagem' => 'Cadastro realizado com sucesso.',
        'token' => $token,
        'aluno' => [
            'id' => $alunoId,
            'nome' => $nome,
            'email' => $email,
            'telefone' => $telefone
        ]
    ]);

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    if ($e->getCode() === '23000') {
        json_out(['status' => 'erro', 'mensagem' => 'Um ou mais horários selecionados já estão ocupados.'], 409);
    }
    error_log('Erro ao cadastrar aluno: ' . $e->getMessage());
    json_out(['status' => 'erro', 'mensagem' => 'Erro de banco de dados ao salvar o cadastro.'], 500);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Erro ao cadastrar aluno: ' . $e->getMessage());
    json_out(['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
}
