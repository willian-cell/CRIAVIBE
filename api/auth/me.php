<?php
require_once __DIR__.'/../config.php';

$u = me();
if (!$u) json_out(['status'=>'erro','mensagem'=>'Nao autenticado.'], 401);

try { db()->exec("ALTER TABLE usuarios ADD COLUMN foto_perfil VARCHAR(512) DEFAULT NULL"); } catch (Exception $e) {}

try { db()->exec("ALTER TABLE usuarios ADD COLUMN bloqueado TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $e) {}

if (!empty($u['id'])) {
    $stmt = db()->prepare("SELECT id, nome, email, tipo, foto_perfil, bloqueado FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$u['id']]);
    $fresh = $stmt->fetch();
    if ($fresh) {
        // Bloqueio aplicado durante a sessao encerra o acesso na proxima chamada.
        if (!empty($fresh['bloqueado'])) {
            session_destroy();
            json_out(['status'=>'erro','mensagem'=>'Esta conta está bloqueada. Fale com o administrador.'], 403);
        }
        $_SESSION['usuario'] = $fresh;
        $u = $fresh;
    }
}

$u['is_admin'] = is_super_admin($u);

// Sessao aberta pelo painel administrativo em nome de outro fotografo.
$origem = $_SESSION['admin_origem'] ?? null;
$u['impersonando'] = (bool)$origem;
$u['admin_origem_nome'] = $origem['nome'] ?? null;

json_out(['status'=>'ok','usuario'=>$u]);
