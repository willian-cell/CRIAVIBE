<?php
require_once __DIR__.'/config.php';

function table_exists(PDO $db, string $table): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function column_exists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function index_exists(PDO $db, string $table, string $index): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?"
    );
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function add_index_if_missing(PDO $db, string $table, string $indexName, string $definition): void {
    if (!index_exists($db, $table, $indexName)) {
        $db->exec("ALTER TABLE `$table` ADD INDEX `$indexName` ($definition)");
    }
}

function add_column_if_missing(PDO $db, string $table, string $column, string $definition): void {
    if (!column_exists($db, $table, $column)) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

try {
    $db = db();

    $usuariosExiste = table_exists($db, 'usuarios');
    $temUsuarios = false;
    if ($usuariosExiste) {
        $temUsuarios = (int)$db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn() > 0;
    }

    if ($temUsuarios) {
        $u = me();
        if (!$u || !in_array($u['tipo'], ['admin', 'fotografo'])) {
            json_out(['status' => 'erro', 'mensagem' => 'Acesso negado para migracoes.'], 403);
        }
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(160) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            senha VARCHAR(255) NOT NULL,
            tipo VARCHAR(30) NOT NULL DEFAULT 'fotografo',
            foto_perfil VARCHAR(512) DEFAULT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS clientes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fotografo_email VARCHAR(190) NOT NULL,
            nome VARCHAR(160) NOT NULL,
            email VARCHAR(190) DEFAULT NULL,
            telefone VARCHAR(40) DEFAULT NULL,
            foto_cliente VARCHAR(512) DEFAULT NULL,
            senha_acesso VARCHAR(40) NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_clientes_fotografo (fotografo_email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS galerias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_email VARCHAR(190) NOT NULL,
            cliente_id INT DEFAULT NULL,
            nome VARCHAR(180) NOT NULL,
            descricao TEXT DEFAULT NULL,
            privacidade VARCHAR(20) NOT NULL DEFAULT 'privada',
            senha VARCHAR(255) DEFAULT NULL,
            link_token VARCHAR(128) NOT NULL UNIQUE,
            entrega_em_alta TINYINT(1) NOT NULL DEFAULT 1,
            selecao_ativa TINYINT(1) NOT NULL DEFAULT 1,
            musicas_ativas TINYINT(1) NOT NULL DEFAULT 0,
            max_downloads INT NOT NULL DEFAULT 0,
            max_selecao INT NOT NULL DEFAULT 0,
            dl_count INT NOT NULL DEFAULT 0,
            capa_apresentacao VARCHAR(512) DEFAULT NULL,
            capa_crop_horizontal TEXT NULL,
            capa_crop_vertical TEXT NULL,
            tema VARCHAR(10) NOT NULL DEFAULT 'escuro',
            nome_fonte VARCHAR(80) DEFAULT NULL,
            nome_formato VARCHAR(40) DEFAULT NULL,
            nome_tamanho INT DEFAULT NULL,
            nome_negrito TINYINT(1) DEFAULT NULL,
            descricao_fonte VARCHAR(80) DEFAULT NULL,
            descricao_formato VARCHAR(40) DEFAULT NULL,
            descricao_tamanho INT DEFAULT NULL,
            descricao_negrito TINYINT(1) DEFAULT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_galerias_usuario (usuario_email),
            INDEX idx_galerias_cliente (cliente_id),
            INDEX idx_galerias_token (link_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS imagens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            galeria_id INT NOT NULL,
            nome_arquivo VARCHAR(255) NOT NULL,
            caminho_arquivo VARCHAR(1024) NOT NULL,
            tamanho_bytes BIGINT DEFAULT 0,
            largura INT DEFAULT NULL,
            altura INT DEFAULT NULL,
            orientacao VARCHAR(20) DEFAULT NULL,
            ordem INT NOT NULL DEFAULT 0,
            selecionada TINYINT(1) NOT NULL DEFAULT 0,
            eh_publica TINYINT(1) NOT NULL DEFAULT 1,
            is_capa TINYINT(1) NOT NULL DEFAULT 0,
            downloads INT NOT NULL DEFAULT 0,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_imagens_galeria (galeria_id),
            INDEX idx_imagens_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS musicas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            galeria_id INT NOT NULL,
            nome_arquivo VARCHAR(255) NOT NULL,
            nome_exibicao VARCHAR(255) NOT NULL,
            caminho_arquivo VARCHAR(1024) NOT NULL,
            ordem INT NOT NULL DEFAULT 0,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_musicas_galeria (galeria_id),
            INDEX idx_musicas_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

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
            quantidade_horas INT NOT NULL DEFAULT 1,
            cidade VARCHAR(160) NOT NULL DEFAULT 'Santo Antônio do Descoberto',
            valor_hora_centavos INT NOT NULL DEFAULT 10000,
            valor_centavos INT NOT NULL DEFAULT 10000,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_pre_agendamento_slot (data_aula, horario),
            INDEX idx_pre_agendamento_aluno (aluno_id),
            INDEX idx_pre_agendamento_data (data_aula)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

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

    $db->exec("
        CREATE TABLE IF NOT EXISTS agendamento_alunos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(160) NOT NULL,
            email VARCHAR(190) NOT NULL,
            telefone VARCHAR(40) NOT NULL,
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

    add_column_if_missing($db, 'usuarios', 'criado_em', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    add_column_if_missing($db, 'usuarios', 'foto_perfil', 'VARCHAR(512) DEFAULT NULL');
    add_column_if_missing($db, 'clientes', 'criado_em', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    add_column_if_missing($db, 'clientes', 'foto_cliente', 'VARCHAR(512) DEFAULT NULL');
    add_column_if_missing($db, 'galerias', 'cliente_id', 'INT DEFAULT NULL');
    add_column_if_missing($db, 'galerias', 'entrega_em_alta', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($db, 'galerias', 'selecao_ativa', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($db, 'galerias', 'musicas_ativas', 'TINYINT(1) NOT NULL DEFAULT 0');
    add_column_if_missing($db, 'galerias', 'max_downloads', 'INT NOT NULL DEFAULT 0');
    add_column_if_missing($db, 'galerias', 'max_selecao', 'INT NOT NULL DEFAULT 0');
    add_column_if_missing($db, 'galerias', 'dl_count', 'INT NOT NULL DEFAULT 0');
    add_column_if_missing($db, 'galerias', 'capa_apresentacao', 'VARCHAR(512) DEFAULT NULL');
    add_column_if_missing($db, 'galerias', 'capa_crop_horizontal', 'TEXT NULL');
    add_column_if_missing($db, 'galerias', 'capa_crop_vertical', 'TEXT NULL');
    add_column_if_missing($db, 'galerias', 'tema', "VARCHAR(10) NOT NULL DEFAULT 'escuro'");
    add_column_if_missing($db, 'galerias', 'nome_fonte', 'VARCHAR(80) DEFAULT NULL');
    add_column_if_missing($db, 'galerias', 'nome_formato', 'VARCHAR(40) DEFAULT NULL');
    add_column_if_missing($db, 'galerias', 'nome_tamanho', 'INT DEFAULT NULL');
    add_column_if_missing($db, 'galerias', 'nome_negrito', 'TINYINT(1) DEFAULT NULL');
    add_column_if_missing($db, 'galerias', 'descricao_fonte', 'VARCHAR(80) DEFAULT NULL');
    add_column_if_missing($db, 'galerias', 'descricao_formato', 'VARCHAR(40) DEFAULT NULL');
    add_column_if_missing($db, 'galerias', 'descricao_tamanho', 'INT DEFAULT NULL');
    add_column_if_missing($db, 'galerias', 'descricao_negrito', 'TINYINT(1) DEFAULT NULL');
    add_column_if_missing($db, 'imagens', 'selecionada', 'TINYINT(1) NOT NULL DEFAULT 0');
    add_column_if_missing($db, 'imagens', 'eh_publica', 'TINYINT(1) NOT NULL DEFAULT 1');
    add_column_if_missing($db, 'imagens', 'is_capa', 'TINYINT(1) NOT NULL DEFAULT 0');
    add_column_if_missing($db, 'imagens', 'downloads', 'INT NOT NULL DEFAULT 0');
    add_column_if_missing($db, 'imagens', 'criado_em', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    add_column_if_missing($db, 'imagens', 'largura', 'INT DEFAULT NULL');
    add_column_if_missing($db, 'imagens', 'altura', 'INT DEFAULT NULL');
    add_column_if_missing($db, 'imagens', 'orientacao', 'VARCHAR(20) DEFAULT NULL');
    add_column_if_missing($db, 'pre_agendamento_alunos', 'criado_em', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    add_column_if_missing($db, 'pre_agendamento_alunos', 'atualizado_em', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    add_column_if_missing($db, 'pre_agendamento_aulas', 'criado_em', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    add_column_if_missing($db, 'pre_agendamento_aulas', 'atualizado_em', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    add_column_if_missing($db, 'pre_agendamento_aulas', 'quantidade_horas', 'INT NOT NULL DEFAULT 1');
    add_column_if_missing($db, 'pre_agendamento_aulas', 'cidade', "VARCHAR(160) NOT NULL DEFAULT 'Santo Antônio do Descoberto'");
    add_column_if_missing($db, 'pre_agendamento_aulas', 'valor_hora_centavos', 'INT NOT NULL DEFAULT 10000');
    add_column_if_missing($db, 'agendamento_alunos', 'codigo_acesso', 'VARCHAR(12) DEFAULT NULL');
    add_column_if_missing($db, 'agendamento_alunos', 'foto_url', 'VARCHAR(512) DEFAULT NULL');
    add_column_if_missing($db, 'agendamento_planos', 'total_aulas', 'INT NOT NULL DEFAULT 0');
    add_column_if_missing($db, 'agendamento_planos', 'aulas_usadas', 'INT NOT NULL DEFAULT 0');
    add_column_if_missing($db, 'agendamento_planos', 'status', "VARCHAR(30) NOT NULL DEFAULT 'ativo'");
    add_column_if_missing($db, 'agendamento_planos', 'criado_em', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    add_column_if_missing($db, 'agendamento_planos', 'atualizado_em', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    add_column_if_missing($db, 'agendamento_aulas', 'plano_id', 'INT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'modulo_id', 'INT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'assunto_id', 'INT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'quantidade_horas', 'INT NOT NULL DEFAULT 1');
    add_column_if_missing($db, 'agendamento_aulas', 'cidade', "VARCHAR(160) NOT NULL DEFAULT 'Santo Antônio do Descoberto'");
    add_column_if_missing($db, 'agendamento_aulas', 'valor_hora_centavos', 'INT NOT NULL DEFAULT 10000');
    add_column_if_missing($db, 'agendamento_aulas', 'status', "VARCHAR(30) NOT NULL DEFAULT 'pre_agendado'");
    add_column_if_missing($db, 'agendamento_aulas', 'observacoes', 'TEXT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'endereco', 'VARCHAR(512) DEFAULT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'latitude', 'DECIMAL(10, 8) DEFAULT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'longitude', 'DECIMAL(11, 8) DEFAULT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'cep', 'VARCHAR(9) DEFAULT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'endereco_numero', 'VARCHAR(40) DEFAULT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'endereco_quadra', 'VARCHAR(80) DEFAULT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'endereco_lote', 'VARCHAR(80) DEFAULT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'endereco_predio', 'VARCHAR(120) DEFAULT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'endereco_apartamento', 'VARCHAR(80) DEFAULT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'endereco_complemento', 'VARCHAR(180) DEFAULT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'localizacao_origem', 'VARCHAR(20) DEFAULT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'localizacao_precisao', 'VARCHAR(20) DEFAULT NULL');
    add_column_if_missing($db, 'agendamento_aulas', 'localizacao_precisao_metros', 'INT DEFAULT NULL');

    try {
        if (index_exists($db, 'agendamento_alunos', 'email')) {
            $db->exec("ALTER TABLE agendamento_alunos DROP INDEX email");
        }
        add_index_if_missing($db, 'agendamento_alunos', 'idx_agendamento_alunos_email', 'email');
    } catch (Throwable $e) {
        error_log('Nao foi possivel ajustar indice de email em agendamento_alunos: ' . $e->getMessage());
    }

    try {
        if (index_exists($db, 'pre_agendamento_aulas', 'uniq_pre_agendamento_dia')) {
            $db->exec("ALTER TABLE pre_agendamento_aulas DROP INDEX uniq_pre_agendamento_dia");
        }
    } catch (Throwable $e) {
        error_log('Não foi possível remover UNIQUE INDEX uniq_pre_agendamento_dia: ' . $e->getMessage());
    }

    // Adicionar colunas para caminhos de thumbnails
    add_column_if_missing($db, 'imagens', 'caminho_thumb_small', 'VARCHAR(1024) DEFAULT NULL');
    add_column_if_missing($db, 'imagens', 'caminho_thumb_medium', 'VARCHAR(1024) DEFAULT NULL');
    add_column_if_missing($db, 'imagens', 'caminho_thumb_large', 'VARCHAR(1024) DEFAULT NULL');

    // Índice único para evitar duplicatas em caminho_arquivo (apenas se possível)
    try {
        // Tentar criar índice único para idempotência. Se houver duplicatas, a operação falhará e será logada.
        $db->exec("ALTER TABLE imagens ADD UNIQUE INDEX uniq_caminho_arquivo (caminho_arquivo(255))");
    } catch (Throwable $e) {
        error_log('Não foi possível adicionar UNIQUE INDEX uniq_caminho_arquivo: ' . $e->getMessage());
    }

    // Índice para tamanho_bytes para acelerar buscas por tamanho e ordenações
    try {
        add_index_if_missing($db, 'imagens', 'idx_imagens_tamanho', 'tamanho_bytes');
    } catch (Throwable $e) {
        error_log('Não foi possível adicionar índice idx_imagens_tamanho: ' . $e->getMessage());
    }

    try {
        $db->exec("UPDATE agendamento_aulas SET valor_hora_centavos = 10000, valor_centavos = 10000 * quantidade_horas WHERE valor_hora_centavos = 7500");
        $db->exec("UPDATE agendamento_aulas SET valor_hora_centavos = 15000, valor_centavos = 15000 * quantidade_horas WHERE valor_hora_centavos = 12000");
        $db->exec("UPDATE agendamento_planos SET valor_hora_centavos = 10000 WHERE valor_hora_centavos = 7500");
        $db->exec("UPDATE agendamento_planos SET valor_hora_centavos = 15000 WHERE valor_hora_centavos = 12000");
    } catch (Throwable $e) {
        error_log('Erro ao atualizar valores antigos de agendamento nas tabelas: ' . $e->getMessage());
    }

    try {
        $emailAdmin = 'dougdouglas04@outlook.com';
        $senhaAdmin = 'd19581958';
        $nomeAdmin = 'Douglas Admin';
        $hashAdmin = password_hash($senhaAdmin, PASSWORD_DEFAULT);

        $chkUser = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $chkUser->execute([$emailAdmin]);
        $user = $chkUser->fetch();
        if ($user) {
            $updUser = $db->prepare("UPDATE usuarios SET senha = ?, tipo = 'admin' WHERE id = ?");
            $updUser->execute([$hashAdmin, $user['id']]);
        } else {
            $insUser = $db->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'admin')");
            $insUser->execute([$nomeAdmin, $emailAdmin, $hashAdmin]);
        }
    } catch (Throwable $e) {
        error_log('Erro ao assegurar administrador dougdouglas04 no banco: ' . $e->getMessage());
    }

    json_out(['status' => 'ok', 'mensagem' => 'Banco verificado e schema preparado com sucesso.']);
} catch (Throwable $e) {
    error_log('Erro na migracao: ' . $e->getMessage());
    json_out(['status' => 'erro', 'mensagem' => 'Erro na migracao: ' . $e->getMessage()], 500);
}
