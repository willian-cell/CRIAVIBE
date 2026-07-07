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
                foto_url VARCHAR(512) DEFAULT NULL,
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
                endereco VARCHAR(512) DEFAULT NULL,
                latitude DECIMAL(10, 8) DEFAULT NULL,
                longitude DECIMAL(11, 8) DEFAULT NULL,
                cep VARCHAR(9) DEFAULT NULL,
                endereco_tipo_logradouro VARCHAR(40) DEFAULT NULL,
                endereco_logradouro VARCHAR(180) DEFAULT NULL,
                endereco_bairro VARCHAR(120) DEFAULT NULL,
                endereco_cidade VARCHAR(120) DEFAULT NULL,
                endereco_uf VARCHAR(2) DEFAULT NULL,
                tipo_imovel VARCHAR(40) DEFAULT NULL,
                endereco_condominio VARCHAR(160) DEFAULT NULL,
                endereco_numero VARCHAR(40) DEFAULT NULL,
                endereco_quadra VARCHAR(80) DEFAULT NULL,
                endereco_conjunto VARCHAR(80) DEFAULT NULL,
                endereco_lote VARCHAR(80) DEFAULT NULL,
                endereco_predio VARCHAR(120) DEFAULT NULL,
                endereco_bloco VARCHAR(80) DEFAULT NULL,
                endereco_torre VARCHAR(80) DEFAULT NULL,
                endereco_andar VARCHAR(40) DEFAULT NULL,
                endereco_apartamento VARCHAR(80) DEFAULT NULL,
                endereco_casa VARCHAR(80) DEFAULT NULL,
                endereco_sala VARCHAR(80) DEFAULT NULL,
                endereco_complemento VARCHAR(180) DEFAULT NULL,
                ponto_referencia VARCHAR(220) DEFAULT NULL,
                localizacao_origem VARCHAR(20) DEFAULT NULL,
                localizacao_precisao VARCHAR(20) DEFAULT NULL,
                localizacao_precisao_metros INT DEFAULT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (data_aula, horario)
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_aulas_aluno ON agendamento_aulas (aluno_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_aulas_plano ON agendamento_aulas (plano_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_aulas_data ON agendamento_aulas (data_aula)");

        $db->exec("
            CREATE TABLE IF NOT EXISTS agendamento_bloqueios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                data_aula DATE NOT NULL UNIQUE,
                motivo VARCHAR(255) DEFAULT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

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
            foto_url VARCHAR(512) DEFAULT NULL,
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
            endereco VARCHAR(512) DEFAULT NULL,
            latitude DECIMAL(10, 8) DEFAULT NULL,
            longitude DECIMAL(11, 8) DEFAULT NULL,
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

    $db->exec("
        CREATE TABLE IF NOT EXISTS agendamento_bloqueios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data_aula DATE NOT NULL UNIQUE,
            motivo VARCHAR(255) DEFAULT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bloqueios_data (data_aula)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    agendamento_ensure_columns($db, 'agendamento_alunos', [
        'senha_hash' => 'VARCHAR(255) DEFAULT NULL',
        'codigo_acesso' => 'VARCHAR(12) DEFAULT NULL',
        'foto_url' => 'VARCHAR(512) DEFAULT NULL',
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
        'endereco' => 'VARCHAR(512) DEFAULT NULL',
        'latitude' => 'DECIMAL(10, 8) DEFAULT NULL',
        'longitude' => 'DECIMAL(11, 8) DEFAULT NULL',
        'cep' => 'VARCHAR(9) DEFAULT NULL',
        'endereco_tipo_logradouro' => 'VARCHAR(40) DEFAULT NULL',
        'endereco_logradouro' => 'VARCHAR(180) DEFAULT NULL',
        'endereco_bairro' => 'VARCHAR(120) DEFAULT NULL',
        'endereco_cidade' => 'VARCHAR(120) DEFAULT NULL',
        'endereco_uf' => 'VARCHAR(2) DEFAULT NULL',
        'tipo_imovel' => 'VARCHAR(40) DEFAULT NULL',
        'endereco_condominio' => 'VARCHAR(160) DEFAULT NULL',
        'endereco_numero' => 'VARCHAR(40) DEFAULT NULL',
        'endereco_quadra' => 'VARCHAR(80) DEFAULT NULL',
        'endereco_conjunto' => 'VARCHAR(80) DEFAULT NULL',
        'endereco_lote' => 'VARCHAR(80) DEFAULT NULL',
        'endereco_predio' => 'VARCHAR(120) DEFAULT NULL',
        'endereco_bloco' => 'VARCHAR(80) DEFAULT NULL',
        'endereco_torre' => 'VARCHAR(80) DEFAULT NULL',
        'endereco_andar' => 'VARCHAR(40) DEFAULT NULL',
        'endereco_apartamento' => 'VARCHAR(80) DEFAULT NULL',
        'endereco_casa' => 'VARCHAR(80) DEFAULT NULL',
        'endereco_sala' => 'VARCHAR(80) DEFAULT NULL',
        'endereco_complemento' => 'VARCHAR(180) DEFAULT NULL',
        'ponto_referencia' => 'VARCHAR(220) DEFAULT NULL',
        'localizacao_origem' => 'VARCHAR(20) DEFAULT NULL',
        'localizacao_precisao' => 'VARCHAR(20) DEFAULT NULL',
        'localizacao_precisao_metros' => 'INT DEFAULT NULL',
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

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS agendamento_config (
                chave VARCHAR(100) PRIMARY KEY,
                valor TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $chkConfig = $db->query("SELECT COUNT(*) FROM agendamento_config")->fetchColumn();
        if ((int)$chkConfig === 0) {
            $db->exec("
                INSERT INTO agendamento_config (chave, valor) VALUES
                ('valor_santo_antonio_centavos', '10000'),
                ('valor_outra_cidade_centavos', '15000'),
                ('popup_mensagem', 'Você pode selecionar até três horários no mesmo dia e terá um super desconto se forem no mesmo dia!'),
                ('desconto_2_aulas', '10'),
                ('desconto_3_aulas', '20')
            ");
        }
    } catch (Throwable $e) {
        error_log('Nao foi possivel inicializar tabela de configuracoes de cobranca: ' . $e->getMessage());
    }
}

function agendamento_is_admin(): bool {
    $email = strtolower(trim($_SESSION['agendamento_admin_email'] ?? ''));
    return $email !== '';
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

    $lessonsByDateCount = [];
    foreach ($lessons as $lesson) {
        $d = trim($lesson['data_aula'] ?? '');
        if ($d) {
            $lessonsByDateCount[$d] = ($lessonsByDateCount[$d] ?? 0) + 1;
        }
    }

    try {
        $db = db();
        $configs = agendamento_get_configs($db);
        $desc2 = (int)($configs['desconto_2_aulas'] ?? 10);
        $desc3 = (int)($configs['desconto_3_aulas'] ?? 20);
    } catch (Throwable $e) {
        $desc2 = 10;
        $desc3 = 20;
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
        $latitude = isset($lesson['latitude']) && $lesson['latitude'] !== '' ? (float)$lesson['latitude'] : null;
        $longitude = isset($lesson['longitude']) && $lesson['longitude'] !== '' ? (float)$lesson['longitude'] : null;
        $cep = preg_replace('/\D/', '', (string)($lesson['cep'] ?? ''));
        $enderecoTipoLogradouro = trim((string)($lesson['endereco_tipo_logradouro'] ?? ''));
        $enderecoLogradouro = trim((string)($lesson['endereco_logradouro'] ?? ''));
        $enderecoBairro = trim((string)($lesson['endereco_bairro'] ?? ''));
        $enderecoCidade = trim((string)($lesson['endereco_cidade'] ?? ''));
        $enderecoUf = strtoupper(trim((string)($lesson['endereco_uf'] ?? '')));
        $tipoImovel = trim((string)($lesson['tipo_imovel'] ?? ''));
        $enderecoCondominio = trim((string)($lesson['endereco_condominio'] ?? ''));
        $enderecoNumero = trim((string)($lesson['endereco_numero'] ?? ''));
        $enderecoQuadra = trim((string)($lesson['endereco_quadra'] ?? ''));
        $enderecoConjunto = trim((string)($lesson['endereco_conjunto'] ?? ''));
        $enderecoLote = trim((string)($lesson['endereco_lote'] ?? ''));
        $enderecoPredio = trim((string)($lesson['endereco_predio'] ?? ''));
        $enderecoBloco = trim((string)($lesson['endereco_bloco'] ?? ''));
        $enderecoTorre = trim((string)($lesson['endereco_torre'] ?? ''));
        $enderecoAndar = trim((string)($lesson['endereco_andar'] ?? ''));
        $enderecoApartamento = trim((string)($lesson['endereco_apartamento'] ?? ''));
        $enderecoCasa = trim((string)($lesson['endereco_casa'] ?? ''));
        $enderecoSala = trim((string)($lesson['endereco_sala'] ?? ''));
        $enderecoComplemento = trim((string)($lesson['endereco_complemento'] ?? ''));
        $pontoReferencia = trim((string)($lesson['ponto_referencia'] ?? ''));
        $origem = trim((string)($lesson['localizacao_origem'] ?? ''));
        $precisao = trim((string)($lesson['localizacao_precisao'] ?? ''));
        $precisaoMetros = isset($lesson['localizacao_precisao_metros']) ? (int)$lesson['localizacao_precisao_metros'] : null;

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

        if (($latitude === null) !== ($longitude === null) || ($latitude !== null && ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180))) {
            json_out(['status' => 'erro', 'mensagem' => 'Coordenadas de localização inválidas.'], 400);
        }
        if ($cep !== '' && strlen($cep) !== 8) {
            json_out(['status' => 'erro', 'mensagem' => 'CEP de localização inválido.'], 400);
        }
        if ($enderecoUf !== '' && strlen($enderecoUf) !== 2) {
            json_out(['status' => 'erro', 'mensagem' => 'UF do endereco invalida.'], 400);
        }
        if ($tipoImovel !== '' && !in_array($tipoImovel, ['casa', 'apartamento', 'condominio_horizontal', 'sala_comercial', 'loja', 'galpao', 'chacara', 'sitio', 'fazenda', 'outro'], true)) {
            json_out(['status' => 'erro', 'mensagem' => 'Tipo do imovel invalido.'], 400);
        }
        if ($origem !== '' && !in_array($origem, ['gps', 'cep', 'autocomplete', 'mapa', 'manual', 'estudio'], true)) {
            json_out(['status' => 'erro', 'mensagem' => 'Origem da localização inválida.'], 400);
        }
        if ($precisao !== '' && !in_array($precisao, ['confirmada', 'aproximada', 'pendente'], true)) {
            json_out(['status' => 'erro', 'mensagem' => 'Precisão da localização inválida.'], 400);
        }

        $valorHora = agendamento_valor_hora_centavos($cidade);
        $countOnDay = $lessonsByDateCount[$data] ?? 1;
        if ($countOnDay == 2) {
            $valorHora = (int)round($valorHora * (1 - $desc2 / 100));
        } else if ($countOnDay >= 3) {
            $valorHora = (int)round($valorHora * (1 - $desc3 / 100));
        }
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
            'endereco' => isset($lesson['endereco']) ? trim($lesson['endereco']) : null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'cep' => $cep ?: null,
            'endereco_tipo_logradouro' => $enderecoTipoLogradouro ?: null,
            'endereco_logradouro' => $enderecoLogradouro ?: null,
            'endereco_bairro' => $enderecoBairro ?: null,
            'endereco_cidade' => $enderecoCidade ?: null,
            'endereco_uf' => $enderecoUf ?: null,
            'tipo_imovel' => $tipoImovel ?: null,
            'endereco_condominio' => $enderecoCondominio ?: null,
            'endereco_numero' => $enderecoNumero ?: null,
            'endereco_quadra' => $enderecoQuadra ?: null,
            'endereco_conjunto' => $enderecoConjunto ?: null,
            'endereco_lote' => $enderecoLote ?: null,
            'endereco_predio' => $enderecoPredio ?: null,
            'endereco_bloco' => $enderecoBloco ?: null,
            'endereco_torre' => $enderecoTorre ?: null,
            'endereco_andar' => $enderecoAndar ?: null,
            'endereco_apartamento' => $enderecoApartamento ?: null,
            'endereco_casa' => $enderecoCasa ?: null,
            'endereco_sala' => $enderecoSala ?: null,
            'endereco_complemento' => $enderecoComplemento ?: null,
            'ponto_referencia' => $pontoReferencia ?: null,
            'localizacao_origem' => $origem ?: null,
            'localizacao_precisao' => $precisao ?: null,
            'localizacao_precisao_metros' => $precisaoMetros && $precisaoMetros > 0 ? $precisaoMetros : null,
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
            a.endereco,
            a.latitude,
            a.longitude,
            a.cep,
            a.endereco_tipo_logradouro,
            a.endereco_logradouro,
            a.endereco_bairro,
            a.endereco_cidade,
            a.endereco_uf,
            a.tipo_imovel,
            a.endereco_condominio,
            a.endereco_numero,
            a.endereco_quadra,
            a.endereco_conjunto,
            a.endereco_lote,
            a.endereco_predio,
            a.endereco_bloco,
            a.endereco_torre,
            a.endereco_andar,
            a.endereco_apartamento,
            a.endereco_casa,
            a.endereco_sala,
            a.endereco_complemento,
            a.ponto_referencia,
            a.localizacao_origem,
            a.localizacao_precisao,
            a.localizacao_precisao_metros,
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

function agendamento_fetch_bloqueios(PDO $db): array {
    $stmt = $db->query("SELECT id, data_aula, motivo FROM agendamento_bloqueios ORDER BY data_aula");
    return $stmt->fetchAll();
}

function agendamento_assert_dates_not_blocked(PDO $db, array $lessons): void {
    $dates = array_values(array_unique(array_column($lessons, 'data_aula')));
    if (!$dates) return;
    $stmt = $db->prepare("SELECT data_aula FROM agendamento_bloqueios WHERE data_aula IN (" . implode(',', array_fill(0, count($dates), '?')) . ")");
    $stmt->execute($dates);
    if ($blocked = $stmt->fetchColumn()) {
        json_out(['status' => 'erro', 'mensagem' => 'Esta data foi bloqueada pelo professor e nao aceita agendamentos.'], 409);
    }
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
            $item['endereco'] = $row['endereco'] ?? null;
            $item['latitude'] = isset($row['latitude']) ? (float)$row['latitude'] : null;
            $item['longitude'] = isset($row['longitude']) ? (float)$row['longitude'] : null;
            $item['cep'] = $row['cep'] ?? null;
            $item['endereco_tipo_logradouro'] = $row['endereco_tipo_logradouro'] ?? null;
            $item['endereco_logradouro'] = $row['endereco_logradouro'] ?? null;
            $item['endereco_bairro'] = $row['endereco_bairro'] ?? null;
            $item['endereco_cidade'] = $row['endereco_cidade'] ?? null;
            $item['endereco_uf'] = $row['endereco_uf'] ?? null;
            $item['tipo_imovel'] = $row['tipo_imovel'] ?? null;
            $item['endereco_condominio'] = $row['endereco_condominio'] ?? null;
            $item['endereco_numero'] = $row['endereco_numero'] ?? null;
            $item['endereco_quadra'] = $row['endereco_quadra'] ?? null;
            $item['endereco_conjunto'] = $row['endereco_conjunto'] ?? null;
            $item['endereco_lote'] = $row['endereco_lote'] ?? null;
            $item['endereco_predio'] = $row['endereco_predio'] ?? null;
            $item['endereco_bloco'] = $row['endereco_bloco'] ?? null;
            $item['endereco_torre'] = $row['endereco_torre'] ?? null;
            $item['endereco_andar'] = $row['endereco_andar'] ?? null;
            $item['endereco_apartamento'] = $row['endereco_apartamento'] ?? null;
            $item['endereco_casa'] = $row['endereco_casa'] ?? null;
            $item['endereco_sala'] = $row['endereco_sala'] ?? null;
            $item['endereco_complemento'] = $row['endereco_complemento'] ?? null;
            $item['ponto_referencia'] = $row['ponto_referencia'] ?? null;
            $item['localizacao_origem'] = $row['localizacao_origem'] ?? null;
            $item['localizacao_precisao'] = $row['localizacao_precisao'] ?? null;
            $item['localizacao_precisao_metros'] = isset($row['localizacao_precisao_metros']) ? (int)$row['localizacao_precisao_metros'] : null;
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

function agendamento_period_for_time(string $time): string {
    $minutes = agendamento_time_to_minutes($time);
    return $minutes !== null && $minutes < (12 * 60) ? 'manha' : 'tarde';
}

/**
 * Cada aluno ocupa apenas um periodo (manha ou tarde) em uma data, por ate 3h.
 * Isso deixa o outro periodo disponivel para um segundo aluno no mesmo dia.
 */
function agendamento_assert_student_period_limits(PDO $db, array $lessons, ?int $studentId = null): void {
    $requested = [];
    foreach ($lessons as $lesson) {
        $date = $lesson['data_aula'];
        $period = agendamento_period_for_time($lesson['horario']);
        if (!isset($requested[$date])) {
            $requested[$date] = ['hours' => 0, 'periods' => []];
        }
        $requested[$date]['hours'] += max(1, (int)$lesson['quantidade_horas']);
        $requested[$date]['periods'][$period] = true;
    }

    foreach ($requested as $date => $selection) {
        if ($selection['hours'] > 3) {
            json_out(['status' => 'erro', 'mensagem' => 'Cada aluno pode selecionar no maximo 3 horarios no mesmo periodo.'], 400);
        }
        if (count($selection['periods']) > 1) {
            json_out(['status' => 'erro', 'mensagem' => 'Escolha horarios somente pela manha ou somente pela tarde para esta data.'], 400);
        }
    }

    if (!$requested) return;
    $dates = array_keys($requested);
    $stmt = $db->prepare('SELECT aluno_id, data_aula, horario FROM agendamento_aulas WHERE data_aula IN (' . implode(',', array_fill(0, count($dates), '?')) . ')');
    $stmt->execute($dates);
    $saved = $stmt->fetchAll();
    $reservedPeriods = [];
    $studentsByDate = [];

    foreach ($saved as $item) {
        if ($studentId !== null && (int)$item['aluno_id'] === $studentId) continue;
        $date = $item['data_aula'];
        $reservedPeriods[$date][agendamento_period_for_time($item['horario'])] = true;
        $studentsByDate[$date][(int)$item['aluno_id']] = true;
    }

    foreach ($requested as $date => $selection) {
        if (count($studentsByDate[$date] ?? []) >= 2) {
            json_out(['status' => 'erro', 'mensagem' => 'Esta data ja atingiu o limite de 2 alunos agendados.'], 409);
        }
        foreach (array_keys($selection['periods']) as $period) {
            if (!empty($reservedPeriods[$date][$period])) {
                $label = $period === 'manha' ? 'manha' : 'tarde';
                json_out(['status' => 'erro', 'mensagem' => 'O periodo da ' . $label . ' ja esta reservado para outro aluno nesta data.'], 409);
            }
        }
    }
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
    try {
        $db = db();
        $configs = agendamento_get_configs($db);
        $valLocal = (int)($configs['valor_santo_antonio_centavos'] ?? 10000);
        $valOutra = (int)($configs['valor_outra_cidade_centavos'] ?? 15000);
    } catch (Throwable $e) {
        $valLocal = AGENDAMENTO_VALOR_SANTO_ANTONIO_CENTAVOS;
        $valOutra = AGENDAMENTO_VALOR_OUTRA_CIDADE_CENTAVOS;
    }

    $normalized = strtolower(trim($cidade));
    $normalized = str_replace(['â', 'ã', 'á', 'à', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ç'], ['a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'c'], $normalized);
    return strpos($normalized, 'santo antonio do descoberto') !== false
        ? $valLocal
        : $valOutra;
}

function agendamento_get_configs(PDO $db): array {
    $defaults = [
        'valor_santo_antonio_centavos' => 10000,
        'valor_outra_cidade_centavos' => 15000,
        'popup_mensagem' => 'Você pode selecionar até três horários no mesmo dia e terá um super desconto se forem no mesmo dia!',
        'desconto_2_aulas' => 10,
        'desconto_3_aulas' => 20
    ];
    try {
        $stmt = $db->query("SELECT chave, valor FROM agendamento_config");
        if ($stmt) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $key = $row['chave'];
                $val = $row['valor'];
                if (isset($defaults[$key])) {
                    if (strpos($key, 'valor') !== false || strpos($key, 'desconto') !== false) {
                        $defaults[$key] = (int)$val;
                    } else {
                        $defaults[$key] = $val;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // Table might not exist yet
    }
    return $defaults;
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
