<?php

const AGENDAMENTO_ADMIN_EMAILS = [
    'willianb.o.1993@gmail.com',
    'dododouglas04@outlook.com',
];

const AGENDAMENTO_DIAS = ['SEGUNDA', 'TERÇA', 'QUARTA', 'QUINTA', 'SEXTA'];
const AGENDAMENTO_HORARIOS = ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];
const AGENDAMENTO_VALOR_CENTAVOS = 12000;

function agendamento_ensure_schema(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS pre_agendamento_alunos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token_publico VARCHAR(96) NOT NULL UNIQUE,
            nome VARCHAR(160) NOT NULL,
            email VARCHAR(190) NOT NULL,
            telefone VARCHAR(40) NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pre_agendamento_alunos_nome (nome)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS pre_agendamento_aulas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            aluno_id INT NOT NULL,
            dia_semana VARCHAR(20) NOT NULL,
            data_aula DATE NOT NULL,
            horario VARCHAR(5) NOT NULL,
            valor_centavos INT NOT NULL DEFAULT 12000,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_pre_agendamento_dia (dia_semana),
            UNIQUE KEY uniq_pre_agendamento_slot (dia_semana, data_aula, horario),
            INDEX idx_pre_agendamento_aluno (aluno_id),
            INDEX idx_pre_agendamento_data (data_aula)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function agendamento_is_admin(): bool {
    $email = strtolower(trim($_SESSION['agendamento_admin_email'] ?? ''));
    return $email !== '' && in_array($email, AGENDAMENTO_ADMIN_EMAILS, true);
}

function agendamento_require_admin(): string {
    if (!agendamento_is_admin()) {
        json_out(['status' => 'erro', 'mensagem' => 'Acesso restrito ao fotografo.'], 403);
    }
    return strtolower(trim($_SESSION['agendamento_admin_email']));
}

function agendamento_public_token(): string {
    return bin2hex(random_bytes(32));
}

function agendamento_clean_phone(string $phone): string {
    return trim(preg_replace('/\s+/', ' ', $phone));
}

function agendamento_normalize_day(string $day): string {
    $day = trim($day);
    $day = str_replace(['terça', 'Terça'], 'TERÇA', $day);
    $day = strtoupper($day);
    $map = [
        'TERCA' => 'TERÇA',
        'TERÇA' => 'TERÇA',
        'QUARTA-FEIRA' => 'QUARTA',
        'QUINTA-FEIRA' => 'QUINTA',
        'SEXTA-FEIRA' => 'SEXTA',
    ];
    return $map[$day] ?? $day;
}

function agendamento_validate_student(array $student): array {
    $nome = trim($student['nome'] ?? '');
    $email = strtolower(trim($student['email'] ?? ''));
    $telefone = agendamento_clean_phone($student['telefone'] ?? '');

    if ($nome === '' || $email === '' || $telefone === '') {
        json_out(['status' => 'erro', 'mensagem' => 'Preencha aluno, email e telefone para liberar o pre-agendamento.'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(['status' => 'erro', 'mensagem' => 'Informe um email valido.'], 400);
    }

    if (strlen($nome) < 3) {
        json_out(['status' => 'erro', 'mensagem' => 'Informe o nome completo do aluno.'], 400);
    }

    return ['nome' => $nome, 'email' => $email, 'telefone' => $telefone];
}

function agendamento_validate_lessons(array $lessons): array {
    if (!$lessons) {
        json_out(['status' => 'erro', 'mensagem' => 'Selecione pelo menos uma aula na tabela.'], 400);
    }

    $valid = [];
    foreach ($lessons as $lesson) {
        $dia = agendamento_normalize_day($lesson['dia_semana'] ?? '');
        $data = trim($lesson['data_aula'] ?? '');
        $horario = trim($lesson['horario'] ?? '');

        if (!in_array($dia, AGENDAMENTO_DIAS, true)) {
            json_out(['status' => 'erro', 'mensagem' => 'Dia da semana invalido.'], 400);
        }

        $date = DateTime::createFromFormat('Y-m-d', $data);
        if (!$date || $date->format('Y-m-d') !== $data) {
            json_out(['status' => 'erro', 'mensagem' => 'Data da aula invalida.'], 400);
        }

        if (!in_array($horario, AGENDAMENTO_HORARIOS, true)) {
            json_out(['status' => 'erro', 'mensagem' => 'Horario invalido.'], 400);
        }

        $key = $dia . '|' . $data . '|' . $horario;
        $valid[$key] = [
            'dia_semana' => $dia,
            'data_aula' => $data,
            'horario' => $horario,
            'valor_centavos' => AGENDAMENTO_VALOR_CENTAVOS,
        ];
    }

    return array_values($valid);
}

function agendamento_fetch_board(PDO $db): array {
    $stmt = $db->query("
        SELECT
            a.id AS aula_id,
            a.dia_semana,
            a.data_aula,
            a.horario,
            a.valor_centavos,
            al.id AS aluno_id,
            al.token_publico,
            al.nome,
            al.email,
            al.telefone,
            al.criado_em,
            al.atualizado_em
        FROM pre_agendamento_aulas a
        JOIN pre_agendamento_alunos al ON al.id = a.aluno_id
        ORDER BY FIELD(a.dia_semana, 'SEGUNDA', 'TERÇA', 'QUARTA', 'QUINTA', 'SEXTA'), a.data_aula, a.horario
    ");
    return $stmt->fetchAll();
}

function agendamento_format_board(array $rows, ?string $currentToken, bool $isAdmin): array {
    $items = [];
    foreach ($rows as $row) {
        $isOwner = $currentToken && hash_equals($row['token_publico'], $currentToken);
        $item = [
            'aula_id' => (int)$row['aula_id'],
            'dia_semana' => $row['dia_semana'],
            'data_aula' => $row['data_aula'],
            'horario' => $row['horario'],
            'valor_centavos' => (int)$row['valor_centavos'],
            'aluno' => $row['nome'],
            'is_owner' => $isOwner,
        ];

        if ($isOwner || $isAdmin) {
            $item['aluno_id'] = (int)$row['aluno_id'];
            $item['token_publico'] = $row['token_publico'];
            $item['email'] = $row['email'];
            $item['telefone'] = $row['telefone'];
        }

        $items[] = $item;
    }

    return $items;
}

function agendamento_fetch_student_by_token(PDO $db, string $token): ?array {
    if ($token === '') return null;
    $stmt = $db->prepare("SELECT * FROM pre_agendamento_alunos WHERE token_publico = ? LIMIT 1");
    $stmt->execute([$token]);
    $student = $stmt->fetch();
    return $student ?: null;
}
