<?php

const AGENDAMENTO_ADMIN_EMAILS = [
    'willianb.o.1993@gmail.com',
    'dododouglas04@outlook.com',
    'dougdouglas04@outlook.com',
];

const AGENDAMENTO_DIAS = ['SEGUNDA', 'TERÇA', 'QUARTA', 'QUINTA', 'SEXTA'];
const AGENDAMENTO_HORARIOS = ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];
const AGENDAMENTO_VALOR_SANTO_ANTONIO_CENTAVOS = 10000;
const AGENDAMENTO_VALOR_OUTRA_CIDADE_CENTAVOS = 15000;
const AGENDAMENTO_HORAS_OPCOES = [1, 2, 3, 4, 5, 6, 7, 8];
const AGENDAMENTO_STATUS = ['pre_agendado', 'confirmado', 'concluido', 'cancelado', 'remarcado'];

function agendamento_ensure_schema(PDO $db): void {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $db->exec("
            CREATE TABLE IF NOT EXISTS agendamento_alunos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome VARCHAR(160) NOT NULL,
                email VARCHAR(190) NOT NULL,
                telefone VARCHAR(40) NOT NULL,
                senha_hash VARCHAR(255) DEFAULT NULL,
                token_publico VARCHAR(96) NOT NULL UNIQUE,
                codigo_acesso VARCHAR(12) DEFAULT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_agendamento_alunos_email ON agendamento_alunos (email)");

        $db->exec("
            CREATE TABLE IF NOT EXISTS agendamento_modulos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome VARCHAR(160) NOT NULL,
                descricao TEXT NULL,
                ordem INT NOT NULL DEFAULT 0,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS agendamento_assuntos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                modulo_id INT NOT NULL,
                titulo VARCHAR(180) NOT NULL,
                descricao TEXT NULL,
                ordem INT NOT NULL DEFAULT 0,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_assuntos_modulo ON agendamento_assuntos (modulo_id)");

        $db->exec("
            CREATE TABLE IF NOT EXISTS agendamento_planos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                aluno_id INT NOT NULL,
                nome VARCHAR(180) NOT NULL,
                total_aulas INT NOT NULL DEFAULT 0,
                aulas_usadas INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'ativo',
                forma_pagamento VARCHAR(50) DEFAULT NULL,
                cidade VARCHAR(160) NOT NULL DEFAULT 'Santo Antônio do Descoberto',
                valor_hora_centavos INT NOT NULL DEFAULT 10000,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_planos_aluno ON agendamento_planos (aluno_id)");

        $db->exec("
            CREATE TABLE IF NOT EXISTS agendamento_aulas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                aluno_id INT NOT NULL,
                plano_id INT NULL,
                modulo_id INT NULL,
                assunto_id INT NULL,
                dia_semana VARCHAR(20) NOT NULL,
                data_aula DATE NOT NULL,
                horario VARCHAR(5) NOT NULL,
                quantidade_horas INT NOT NULL DEFAULT 1,
                cidade VARCHAR(160) NOT NULL DEFAULT 'Santo Antônio do Descoberto',
                valor_hora_centavos INT NOT NULL DEFAULT 10000,
                valor_centavos INT NOT NULL DEFAULT 10000,
                status VARCHAR(30) NOT NULL DEFAULT 'pre_agendado',
                observacoes TEXT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (data_aula, horario)
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_aulas_aluno ON agendamento_aulas (aluno_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_aulas_plano ON agendamento_aulas (plano_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_aulas_data ON agendamento_aulas (data_aula)");

        $db->exec("
            CREATE TABLE IF NOT EXISTS agendamento_historico (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                aula_id INT NULL,
                aluno_id INT NULL,
                acao VARCHAR(80) NOT NULL,
                detalhes TEXT NULL,
                autor_tipo VARCHAR(30) NOT NULL DEFAULT 'sistema',
                autor_email VARCHAR(190) NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_historico_aula ON agendamento_historico (aula_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_historico_aluno ON agendamento_historico (aluno_id)");

        try {
            agendamento_seed_course_defaults($db);
        } catch (Throwable $e) {}

        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS agendamento_alunos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(160) NOT NULL,
            email VARCHAR(190) NOT NULL,
            telefone VARCHAR(40) NOT NULL,
            senha_hash VARCHAR(255) DEFAULT NULL,
            token_publico VARCHAR(96) NOT NULL UNIQUE,
            codigo_acesso VARCHAR(12) DEFAULT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_agendamento_alunos_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS agendamento_modulos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(160) NOT NULL,
            descricao TEXT NULL,
            ordem INT NOT NULL DEFAULT 0,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS agendamento_assuntos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            modulo_id INT NOT NULL,
            titulo VARCHAR(180) NOT NULL,
            descricao TEXT NULL,
            ordem INT NOT NULL DEFAULT 0,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_assuntos_modulo (modulo_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS agendamento_planos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            aluno_id INT NOT NULL,
            nome VARCHAR(180) NOT NULL,
            total_aulas INT NOT NULL DEFAULT 0,
            aulas_usadas INT NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'ativo',
            forma_pagamento VARCHAR(50) DEFAULT NULL,
            cidade VARCHAR(160) NOT NULL DEFAULT 'Santo Antônio do Descoberto',
            valor_hora_centavos INT NOT NULL DEFAULT 10000,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_planos_aluno (aluno_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS agendamento_aulas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            aluno_id INT NOT NULL,
            plano_id INT NULL,
            modulo_id INT NULL,
            assunto_id INT NULL,
            dia_semana VARCHAR(20) NOT NULL,
            data_aula DATE NOT NULL,
            horario VARCHAR(5) NOT NULL,
            quantidade_horas INT NOT NULL DEFAULT 1,
            cidade VARCHAR(160) NOT NULL DEFAULT 'Santo Antônio do Descoberto',
            valor_hora_centavos INT NOT NULL DEFAULT 10000,
            valor_centavos INT NOT NULL DEFAULT 10000,
            status VARCHAR(30) NOT NULL DEFAULT 'pre_agendado',
            observacoes TEXT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_agendamento_slot (data_aula, horario),
            INDEX idx_aulas_aluno (aluno_id),
            INDEX idx_aulas_plano (plano_id),
            INDEX idx_aulas_data (data_aula)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS agendamento_historico (
            id INT AUTO_INCREMENT PRIMARY KEY,
            aula_id INT NULL,
            aluno_id INT NULL,
            acao VARCHAR(80) NOT NULL,
            detalhes TEXT NULL,
            autor_tipo VARCHAR(30) NOT NULL DEFAULT 'sistema',
            autor_email VARCHAR(190) NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_historico_aula (aula_id),
            INDEX idx_historico_aluno (aluno_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    agendamento_ensure_columns($db, 'agendamento_alunos', [
        'senha_hash' => 'VARCHAR(255) DEFAULT NULL',
        'codigo_acesso' => 'VARCHAR(12) DEFAULT NULL',
        'criado_em' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'atualizado_em' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ]);

    agendamento_ensure_columns($db, 'agendamento_planos', [
        'total_aulas' => 'INT NOT NULL DEFAULT 0',
        'aulas_usadas' => 'INT NOT NULL DEFAULT 0',
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'ativo'",
        'forma_pagamento' => 'VARCHAR(50) DEFAULT NULL',
        'cidade' => "VARCHAR(160) NOT NULL DEFAULT 'Santo Antônio do Descoberto'",
        'valor_hora_centavos' => 'INT NOT NULL DEFAULT 10000',
        'criado_em' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'atualizado_em' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ]);

    foreach ([
        'plano_id' => 'INT NULL',
        'modulo_id' => 'INT NULL',
        'assunto_id' => 'INT NULL',
        'quantidade_horas' => 'INT NOT NULL DEFAULT 1',
        'cidade' => "VARCHAR(160) NOT NULL DEFAULT 'Santo Antônio do Descoberto'",
        'valor_hora_centavos' => 'INT NOT NULL DEFAULT 10000',
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'pre_agendado'",
        'observacoes' => 'TEXT NULL',
    ] as $column => $definition) {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'agendamento_aulas' AND column_name = ?
        ");
        $stmt->execute([$column]);
        if ((int)$stmt->fetchColumn() === 0) {
            try {
                $db->exec("ALTER TABLE agendamento_aulas ADD COLUMN `$column` $definition");
            } catch (Throwable $e) {
                error_log("Nao foi possivel adicionar coluna {$column} em agendamento_aulas: " . $e->getMessage());
            }
        }
    }

    try {
        agendamento_drop_unique_email_if_needed($db);
    } catch (Throwable $e) {
        error_log('Nao foi possivel ajustar indice de email em agendamento_alunos: ' . $e->getMessage());
    }

    try {
        agendamento_seed_course_defaults($db);
    } catch (Throwable $e) {
        error_log('Nao foi possivel semear modulos padrao de agendamento: ' . $e->getMessage());
    }

    try {
        agendamento_migrate_pre_agendamento($db);
    } catch (Throwable $e) {
        error_log('Nao foi possivel migrar pre_agendamento legado: ' . $e->getMessage());
    }
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

function agendamento_codigo_acesso(): string {
    return (string)random_int(100000, 999999);
}

function agendamento_column_exists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function agendamento_ensure_columns(PDO $db, string $table, array $columns): void {
    foreach ($columns as $column => $definition) {
        if (agendamento_column_exists($db, $table, $column)) continue;

        try {
            $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        } catch (Throwable $e) {
            error_log("Nao foi possivel adicionar coluna {$column} em {$table}: " . $e->getMessage());
        }
    }
}

function agendamento_index_exists(PDO $db, string $table, string $index): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
    ");
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function agendamento_drop_unique_email_if_needed(PDO $db): void {
    if (agendamento_index_exists($db, 'agendamento_alunos', 'email')) {
        try {
            $db->exec("ALTER TABLE agendamento_alunos DROP INDEX email");
        } catch (Throwable $e) {
            error_log('Nao foi possivel remover indice unico email de agendamento_alunos: ' . $e->getMessage());
        }
    }

    if (!agendamento_index_exists($db, 'agendamento_alunos', 'idx_agendamento_alunos_email')) {
        try {
            $db->exec("ALTER TABLE agendamento_alunos ADD INDEX idx_agendamento_alunos_email (email)");
        } catch (Throwable $e) {
            error_log('Nao foi possivel criar indice de email em agendamento_alunos: ' . $e->getMessage());
        }
    }
}

function agendamento_seed_course_defaults(PDO $db): void {
    $count = (int)$db->query("SELECT COUNT(*) FROM agendamento_modulos")->fetchColumn();
    if ($count > 0) return;

    $modules = [
        ['Módulo 1: Câmera', 'Fundamentos de câmera, exposição e operação prática.', ['ISO, abertura e velocidade', 'Foco e lentes', 'Configuração da câmera']],
        ['Módulo 2: Luz', 'Leitura de luz natural e direção básica.', ['Luz natural', 'Sombra e contraste', 'Direção de retrato']],
        ['Módulo 3: Ensaio', 'Planejamento e condução de ensaios.', ['Briefing do cliente', 'Poses e direção', 'Fluxo do ensaio']],
        ['Módulo 4: Pós-produção', 'Seleção, tratamento e entrega.', ['Curadoria', 'Edição básica', 'Entrega profissional']],
    ];

    $insertModule = $db->prepare("INSERT INTO agendamento_modulos (nome, descricao, ordem) VALUES (?, ?, ?)");
    $insertSubject = $db->prepare("INSERT INTO agendamento_assuntos (modulo_id, titulo, ordem) VALUES (?, ?, ?)");
    foreach ($modules as $index => $module) {
        $insertModule->execute([$module[0], $module[1], $index + 1]);
        $moduleId = (int)$db->lastInsertId();
        foreach ($module[2] as $subjectIndex => $subject) {
            $insertSubject->execute([$moduleId, $subject, $subjectIndex + 1]);
        }
    }
}

function agendamento_migrate_pre_agendamento(PDO $db): void {
    $exists = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name IN ('pre_agendamento_alunos', 'pre_agendamento_aulas')
    ");
    $exists->execute();
    if ((int)$exists->fetchColumn() < 2) return;

    $hasRows = (int)$db->query("SELECT COUNT(*) FROM agendamento_alunos")->fetchColumn() > 0;
    if ($hasRows) return;

    $quantidadeExpr = agendamento_column_exists($db, 'pre_agendamento_aulas', 'quantidade_horas')
        ? 'COALESCE(a.quantidade_horas, 1)'
        : '1';
    $cidadeExpr = agendamento_column_exists($db, 'pre_agendamento_aulas', 'cidade')
        ? "COALESCE(a.cidade, 'Santo Antônio do Descoberto')"
        : "'Santo Antônio do Descoberto'";
    $valorHoraExpr = agendamento_column_exists($db, 'pre_agendamento_aulas', 'valor_hora_centavos')
        ? 'COALESCE(a.valor_hora_centavos, 10000)'
        : '10000';

    $rows = $db->query("
        SELECT
            al.token_publico,
            al.nome,
            al.email,
            al.telefone,
            a.dia_semana,
            a.data_aula,
            a.horario,
            {$quantidadeExpr} AS quantidade_horas,
            {$cidadeExpr} AS cidade,
            {$valorHoraExpr} AS valor_hora_centavos,
            a.valor_centavos
        FROM pre_agendamento_aulas a
        JOIN pre_agendamento_alunos al ON al.id = a.aluno_id
        ORDER BY al.id, a.data_aula, a.horario
    ")->fetchAll();

    if (!$rows) return;

    $students = [];
    foreach ($rows as $row) {
        if (!isset($students[$row['token_publico']])) {
            $stmt = $db->prepare("
                INSERT INTO agendamento_alunos (nome, email, telefone, token_publico, codigo_acesso)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$row['nome'], $row['email'], $row['telefone'], $row['token_publico'], agendamento_codigo_acesso()]);
            $studentId = (int)$db->lastInsertId();

            $plan = $db->prepare("
                INSERT INTO agendamento_planos (aluno_id, nome, total_aulas, aulas_usadas, status)
                VALUES (?, 'Curso Fotografia Prática', 0, 0, 'ativo')
            ");
            $plan->execute([$studentId]);
            $students[$row['token_publico']] = ['aluno_id' => $studentId, 'plano_id' => (int)$db->lastInsertId()];
        }

        $ids = $students[$row['token_publico']];
        $insert = $db->prepare("
            INSERT IGNORE INTO agendamento_aulas (
                aluno_id, plano_id, dia_semana, data_aula, horario,
                quantidade_horas, cidade, valor_hora_centavos, valor_centavos, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pre_agendado')
        ");
        $insert->execute([
            $ids['aluno_id'], $ids['plano_id'], $row['dia_semana'], $row['data_aula'], $row['horario'],
            (int)$row['quantidade_horas'], $row['cidade'], (int)$row['valor_hora_centavos'], (int)$row['valor_centavos'],
        ]);
    }
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
        $quantidadeHoras = (int)($lesson['quantidade_horas'] ?? 1);
        $cidade = trim($lesson['cidade'] ?? 'Santo Antônio do Descoberto');
        $moduloId = (int)($lesson['modulo_id'] ?? 0);
        $assuntoId = (int)($lesson['assunto_id'] ?? 0);
        $status = trim($lesson['status'] ?? 'pre_agendado');
        $observacoes = trim($lesson['observacoes'] ?? '');

        if (!in_array($dia, AGENDAMENTO_DIAS, true)) {
            json_out(['status' => 'erro', 'mensagem' => 'Dia da semana invalido.'], 400);
        }

        $date = DateTime::createFromFormat('Y-m-d', $data);
        if (!$date || $date->format('Y-m-d') !== $data) {
            json_out(['status' => 'erro', 'mensagem' => 'Data da aula invalida.'], 400);
        }

        if (agendamento_day_from_date($data) !== $dia) {
            json_out(['status' => 'erro', 'mensagem' => 'A data escolhida nao corresponde ao dia da semana selecionado.'], 400);
        }

        if (!in_array($horario, AGENDAMENTO_HORARIOS, true)) {
            json_out(['status' => 'erro', 'mensagem' => 'Horario invalido.'], 400);
        }

        if (!in_array($quantidadeHoras, AGENDAMENTO_HORAS_OPCOES, true)) {
            json_out(['status' => 'erro', 'mensagem' => 'Quantidade de horas invalida.'], 400);
        }

        if ($cidade === '') {
            json_out(['status' => 'erro', 'mensagem' => 'Informe a cidade da aula.'], 400);
        }

        if ($moduloId < 0 || $assuntoId < 0) {
            json_out(['status' => 'erro', 'mensagem' => 'Modulo ou assunto invalido.'], 400);
        }

        if (!in_array($status, AGENDAMENTO_STATUS, true)) {
            json_out(['status' => 'erro', 'mensagem' => 'Status de aula invalido.'], 400);
        }

        $valorHora = agendamento_valor_hora_centavos($cidade);
        $key = $dia . '|' . $data . '|' . $horario;
        $valid[$key] = [
            'dia_semana' => $dia,
            'data_aula' => $data,
            'horario' => $horario,
            'quantidade_horas' => $quantidadeHoras,
            'cidade' => $cidade,
            'modulo_id' => $moduloId ?: null,
            'assunto_id' => $assuntoId ?: null,
            'status' => $status,
            'observacoes' => $observacoes ?: null,
            'valor_hora_centavos' => $valorHora,
            'valor_centavos' => $valorHora * $quantidadeHoras,
        ];
    }

    return array_values($valid);
}

function agendamento_fetch_board(PDO $db): array {
    $stmt = $db->query("
        SELECT
            a.id AS aula_id,
            a.plano_id,
            a.modulo_id,
            a.assunto_id,
            a.dia_semana,
            a.data_aula,
            a.horario,
            a.quantidade_horas,
            a.cidade,
            a.valor_hora_centavos,
            a.valor_centavos,
            a.status,
            a.observacoes,
            al.id AS aluno_id,
            al.token_publico,
            al.nome,
            al.email,
            al.telefone,
            p.nome AS plano_nome,
            p.total_aulas,
            p.aulas_usadas,
            p.status AS plano_status,
            p.forma_pagamento AS plano_forma_pagamento,
            m.nome AS modulo_nome,
            s.titulo AS assunto_titulo,
            al.criado_em,
            al.atualizado_em
        FROM agendamento_aulas a
        JOIN agendamento_alunos al ON al.id = a.aluno_id
        LEFT JOIN agendamento_planos p ON p.id = a.plano_id
        LEFT JOIN agendamento_modulos m ON m.id = a.modulo_id
        LEFT JOIN agendamento_assuntos s ON s.id = a.assunto_id
        ORDER BY CASE a.dia_semana WHEN 'SEGUNDA' THEN 1 WHEN 'TERÇA' THEN 2 WHEN 'QUARTA' THEN 3 WHEN 'QUINTA' THEN 4 WHEN 'SEXTA' THEN 5 ELSE 6 END, a.data_aula, a.horario
    ");
    return $stmt->fetchAll();
}

function agendamento_format_board(array $rows, ?string $currentToken, bool $isAdmin): array {
    $items = [];
    foreach ($rows as $row) {
        $isOwner = $currentToken && hash_equals($row['token_publico'], $currentToken);
        $item = [
            'aula_id' => (int)$row['aula_id'],
            'plano_id' => isset($row['plano_id']) ? (int)$row['plano_id'] : null,
            'modulo_id' => isset($row['modulo_id']) ? (int)$row['modulo_id'] : null,
            'assunto_id' => isset($row['assunto_id']) ? (int)$row['assunto_id'] : null,
            'dia_semana' => $row['dia_semana'],
            'data_aula' => $row['data_aula'],
            'horario' => $row['horario'],
            'quantidade_horas' => (int)($row['quantidade_horas'] ?? 1),
            'cidade' => $row['cidade'] ?? 'Santo Antônio do Descoberto',
            'valor_hora_centavos' => (int)($row['valor_hora_centavos'] ?? AGENDAMENTO_VALOR_SANTO_ANTONIO_CENTAVOS),
            'valor_centavos' => (int)$row['valor_centavos'],
            'status' => $row['status'] ?? 'pre_agendado',
            'observacoes' => $row['observacoes'] ?? null,
            'plano_nome' => $row['plano_nome'] ?? null,
            'total_aulas' => isset($row['total_aulas']) ? (int)$row['total_aulas'] : 0,
            'aulas_usadas' => isset($row['aulas_usadas']) ? (int)$row['aulas_usadas'] : 0,
            'plano_status' => $row['plano_status'] ?? null,
            'modulo_nome' => $row['modulo_nome'] ?? null,
            'assunto_titulo' => $row['assunto_titulo'] ?? null,
            'aluno' => $row['nome'],
            'is_owner' => $isOwner,
        ];

        if ($isOwner || $isAdmin) {
            $item['aluno_id'] = (int)$row['aluno_id'];
            $item['token_publico'] = $row['token_publico'];
            $item['email'] = $row['email'];
            $item['telefone'] = $row['telefone'];
            $item['forma_pagamento'] = $row['plano_forma_pagamento'] ?? null;
        } else {
            $item['plano_id'] = null;
            $item['modulo_id'] = null;
            $item['assunto_id'] = null;
            $item['cidade'] = null;
            $item['valor_hora_centavos'] = null;
            $item['valor_centavos'] = null;
            $item['status'] = null;
            $item['observacoes'] = null;
            $item['plano_nome'] = null;
            $item['total_aulas'] = null;
            $item['aulas_usadas'] = null;
            $item['plano_status'] = null;
            $item['modulo_nome'] = null;
            $item['assunto_titulo'] = null;
            $item['aluno'] = null;
        }

        $items[] = $item;
    }

    return $items;
}

function agendamento_fetch_student_by_token(PDO $db, string $token): ?array {
    if ($token === '') return null;
    $stmt = $db->prepare("SELECT * FROM agendamento_alunos WHERE token_publico = ? LIMIT 1");
    $stmt->execute([$token]);
    $student = $stmt->fetch();
    return $student ?: null;
}

function agendamento_fetch_course(PDO $db): array {
    $modules = $db->query("
        SELECT id, nome, descricao, ordem
        FROM agendamento_modulos
        WHERE ativo = 1
        ORDER BY ordem ASC, nome ASC
    ")->fetchAll();

    $subjects = $db->query("
        SELECT id, modulo_id, titulo, descricao, ordem
        FROM agendamento_assuntos
        WHERE ativo = 1
        ORDER BY modulo_id ASC, ordem ASC, titulo ASC
    ")->fetchAll();

    return ['modulos' => $modules, 'assuntos' => $subjects];
}

function agendamento_log(PDO $db, ?int $aulaId, ?int $alunoId, string $acao, array $details = [], string $autorTipo = 'sistema', ?string $autorEmail = null): void {
    $stmt = $db->prepare("
        INSERT INTO agendamento_historico (aula_id, aluno_id, acao, detalhes, autor_tipo, autor_email)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $aulaId,
        $alunoId,
        $acao,
        $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        $autorTipo,
        $autorEmail,
    ]);
}

function agendamento_time_to_minutes(string $time): ?int {
    if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $matches)) {
        return null;
    }
    return ((int)$matches[1] * 60) + (int)$matches[2];
}

function agendamento_ranges_overlap(string $startA, int $durationA, string $startB, int $durationB): bool {
    $aStart = agendamento_time_to_minutes($startA);
    $bStart = agendamento_time_to_minutes($startB);
    if ($aStart === null || $bStart === null) return false;

    $aEnd = $aStart + max(1, $durationA) * 60;
    $bEnd = $bStart + max(1, $durationB) * 60;

    return $aStart < $bEnd && $bStart < $aEnd;
}

function agendamento_assert_no_schedule_overlap(PDO $db, array $lessons, ?int $studentId = null): void {
    $dates = [];
    foreach ($lessons as $lesson) {
        $dates[] = $lesson['data_aula'];
    }
    $dates = array_values(array_unique($dates));
    if (!$dates) return;

    $placeholders = implode(',', array_fill(0, count($dates), '?'));
    $params = $dates;
    $sql = "
        SELECT aluno_id, data_aula, horario, quantidade_horas
        FROM agendamento_aulas
        WHERE data_aula IN ($placeholders)
    ";
    if ($studentId) {
        $sql .= " AND aluno_id <> ?";
        $params[] = $studentId;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $saved = $stmt->fetchAll();

    foreach ($lessons as $lesson) {
        foreach ($saved as $item) {
            if ($item['data_aula'] !== $lesson['data_aula']) continue;
            if (agendamento_ranges_overlap(
                $lesson['horario'],
                (int)$lesson['quantidade_horas'],
                $item['horario'],
                (int)($item['quantidade_horas'] ?? 1)
            )) {
                json_out(['status' => 'erro', 'mensagem' => 'Esse intervalo de horario ja foi preenchido para a data escolhida.'], 409);
            }
        }
    }
}

function agendamento_valor_hora_centavos(string $cidade): int {
    $normalized = strtolower(trim($cidade));
    $normalized = str_replace(['â', 'ã', 'á', 'à', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ç'], ['a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'c'], $normalized);
    return strpos($normalized, 'santo antonio do descoberto') !== false
        ? AGENDAMENTO_VALOR_SANTO_ANTONIO_CENTAVOS
        : AGENDAMENTO_VALOR_OUTRA_CIDADE_CENTAVOS;
}

function agendamento_day_from_date(string $date): string {
    $weekday = (int)(new DateTime($date))->format('N');
    return [
        1 => 'SEGUNDA',
        2 => 'TERÇA',
        3 => 'QUARTA',
        4 => 'QUINTA',
        5 => 'SEXTA',
        6 => 'SÁBADO',
        7 => 'DOMINGO',
    ][$weekday] ?? '';
}
