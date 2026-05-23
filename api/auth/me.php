<?php
require_once __DIR__.'/../config.php';

$u = me();
if (!$u) json_out(['status'=>'erro','mensagem'=>'Nao autenticado.'], 401);

try { db()->exec("ALTER TABLE usuarios ADD COLUMN foto_perfil VARCHAR(512) DEFAULT NULL"); } catch (Exception $e) {}

if (!empty($u['id'])) {
    $stmt = db()->prepare("SELECT id, nome, email, tipo, foto_perfil FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$u['id']]);
    $fresh = $stmt->fetch();
    if ($fresh) {
        $_SESSION['usuario'] = $fresh;
        $u = $fresh;
    }
}

json_out(['status'=>'ok','usuario'=>$u]);
