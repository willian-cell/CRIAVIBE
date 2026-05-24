<?php
require_once __DIR__.'/../config.php';

$id    = (int)($_GET['id'] ?? 0);
$token = $_GET['token'] ?? '';

// Tenta adicionar a coluna is_capa, max_downloads, max_selecao e dl_count caso ainda não existam (Lazy migration)
try { db()->exec("ALTER TABLE galerias ADD COLUMN max_selecao INT DEFAULT 0"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN dl_count INT DEFAULT 0"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN capa_apresentacao VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN nome_fonte VARCHAR(80) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN nome_tamanho INT DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN nome_negrito TINYINT(1) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN descricao_fonte VARCHAR(80) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN descricao_tamanho INT DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN descricao_negrito TINYINT(1) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE imagens ADD COLUMN caminho_thumb_small VARCHAR(1024) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE imagens ADD COLUMN caminho_thumb_medium VARCHAR(1024) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE imagens ADD COLUMN caminho_thumb_large VARCHAR(1024) DEFAULT NULL"); } catch (Exception $e) {}

if ($id) {
    $stmt = db()->prepare("SELECT * FROM galerias WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
} elseif ($token) {
    $stmt = db()->prepare("SELECT * FROM galerias WHERE link_token = ? LIMIT 1");
    $stmt->execute([$token]);
} else {
    json_out(['status'=>'erro','mensagem'=>'Parâmetro id ou token obrigatório.'], 400);
}

$g = $stmt->fetch();
if (!$g) json_out(['status'=>'erro','mensagem'=>'Galeria não encontrada.'], 404);

// Acesso por token: o token em si já é a autorização (link direto)
// Acesso por id: só o dono pode ver galeria privada
if (!$token && $id) {
    if ($g['privacidade'] === 'privada') {
        $u = me();
        if (!$u || $u['email'] !== $g['usuario_email'])
            json_out(['status'=>'erro','mensagem'=>'Acesso negado.'], 403);
    }
}

// Retorna galeria sem expor a senha hash
unset($g['senha']);

try { db()->exec("ALTER TABLE usuarios ADD COLUMN foto_perfil VARCHAR(512) DEFAULT NULL"); } catch (Exception $e) {}
$stmtLogo = db()->prepare("SELECT foto_perfil FROM usuarios WHERE email = ? LIMIT 1");
$stmtLogo->execute([$g['usuario_email']]);
$dono = $stmtLogo->fetch();
$g['foto_perfil'] = $dono['foto_perfil'] ?? null;

$g['capa_preview'] = $g['capa_apresentacao'] ?? null;

if (!empty($g['capa_apresentacao'])) {
    $stmtCapa = db()->prepare("
        SELECT COALESCE(caminho_thumb_large, caminho_thumb_medium, caminho_thumb_small, caminho_arquivo) AS capa_preview
        FROM imagens
        WHERE galeria_id = ? AND caminho_arquivo = ?
        LIMIT 1
    ");
    $stmtCapa->execute([$g['id'], $g['capa_apresentacao']]);
    $capa = $stmtCapa->fetch();
    if (!empty($capa['capa_preview'])) {
        $g['capa_preview'] = $capa['capa_preview'];
    }
} else {
    $stmtCapa = db()->prepare("
        SELECT COALESCE(caminho_thumb_large, caminho_thumb_medium, caminho_thumb_small, caminho_arquivo) AS capa_preview
        FROM imagens
        WHERE galeria_id = ?
        ORDER BY is_capa DESC, ordem ASC
        LIMIT 1
    ");
    $stmtCapa->execute([$g['id']]);
    $capa = $stmtCapa->fetch();
    if (!empty($capa['capa_preview'])) {
        $g['capa_preview'] = $capa['capa_preview'];
    }
}

json_out(['status'=>'ok','galeria'=>$g]);
