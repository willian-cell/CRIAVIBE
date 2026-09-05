<?php
/**
 * Retrato completo do sistema para o painel administrativo:
 * fotografos cadastrados, galerias de cada um, clientes de cada um e os
 * registros orfaos - galerias e clientes cujo dono ja nao existe na tabela
 * de usuarios.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

require_super_admin();
admin_ensure_schema();

$db = db();

// ── Fotografos, com os numeros agregados de cada um ──────────────────────────
$usuarios = $db->query("
    SELECT
        u.id,
        u.nome,
        u.email,
        u.tipo,
        u.foto_perfil,
        u.bloqueado,
        u.telefone,
        u.cidade,
        u.ultimo_acesso,
        u.criado_em,
        (SELECT COUNT(*) FROM galerias g WHERE g.usuario_email = u.email) AS total_galerias,
        (SELECT COUNT(*) FROM clientes c WHERE c.fotografo_email = u.email) AS total_clientes,
        (SELECT COUNT(*) FROM imagens i
            JOIN galerias g2 ON g2.id = i.galeria_id
            WHERE g2.usuario_email = u.email) AS total_fotos,
        (SELECT COALESCE(SUM(i2.tamanho_bytes), 0) FROM imagens i2
            JOIN galerias g3 ON g3.id = i2.galeria_id
            WHERE g3.usuario_email = u.email) AS bytes
    FROM usuarios u
    ORDER BY u.criado_em DESC, u.id DESC
")->fetchAll();

// ── Galerias de todo o sistema ───────────────────────────────────────────────
$galerias = $db->query("
    SELECT
        g.id,
        g.nome,
        g.usuario_email,
        g.cliente_id,
        g.privacidade,
        g.link_token,
        g.criado_em,
        g.selecao_ativa,
        g.max_downloads,
        g.dl_count,
        c.nome AS cliente_nome,
        (SELECT COUNT(*) FROM imagens i WHERE i.galeria_id = g.id) AS total_fotos,
        (SELECT COALESCE(SUM(i2.tamanho_bytes), 0) FROM imagens i2 WHERE i2.galeria_id = g.id) AS bytes,
        (SELECT COUNT(*) FROM imagens i3 WHERE i3.galeria_id = g.id AND i3.selecionada = 1) AS total_selecionadas,
        (SELECT COUNT(*) FROM musicas m WHERE m.galeria_id = g.id) AS total_musicas,
        EXISTS(SELECT 1 FROM usuarios u2 WHERE u2.email = g.usuario_email) AS dono_existe
    FROM galerias g
    LEFT JOIN clientes c ON c.id = g.cliente_id
    ORDER BY g.criado_em DESC, g.id DESC
")->fetchAll();

// ── Clientes de todo o sistema ───────────────────────────────────────────────
$clientes = $db->query("
    SELECT
        c.id,
        c.nome,
        c.email,
        c.telefone,
        c.foto_cliente,
        c.fotografo_email,
        c.criado_em,
        (SELECT COUNT(*) FROM galerias g WHERE g.cliente_id = c.id) AS total_galerias,
        EXISTS(SELECT 1 FROM usuarios u3 WHERE u3.email = c.fotografo_email) AS dono_existe
    FROM clientes c
    ORDER BY c.criado_em DESC, c.id DESC
")->fetchAll();

// ── Totais gerais ────────────────────────────────────────────────────────────
$totFotos = $db->query("SELECT COUNT(*) FROM imagens")->fetchColumn();
$totBytes = $db->query("SELECT COALESCE(SUM(tamanho_bytes), 0) FROM imagens")->fetchColumn();

$galeriasOrfas = 0;
foreach ($galerias as $g) { if (!$g['dono_existe']) $galeriasOrfas++; }
$clientesOrfaos = 0;
foreach ($clientes as $c) { if (!$c['dono_existe']) $clientesOrfaos++; }

$bloqueados = 0;
foreach ($usuarios as $u) { if ((int)$u['bloqueado'] === 1) $bloqueados++; }

json_out([
    'status' => 'ok',
    'admin_email' => ADMIN_EMAIL,
    'resumo' => [
        'fotografos'      => count($usuarios),
        'bloqueados'      => $bloqueados,
        'galerias'        => count($galerias),
        'clientes'        => count($clientes),
        'fotos'           => (int)$totFotos,
        'bytes'           => (int)$totBytes,
        'galerias_orfas'  => $galeriasOrfas,
        'clientes_orfaos' => $clientesOrfaos,
    ],
    'fotografos' => $usuarios,
    'galerias'   => $galerias,
    'clientes'   => $clientes,
]);
