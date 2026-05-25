<?php
require_once __DIR__.'/../config.php';

$body  = body();
$token = $body['token'] ?? '';
$senha = $body['senha'] ?? '';

if (!$token || !$senha)
    json_out(['status'=>'erro','mensagem'=>'Token e senha obrigatorios.'], 400);

try { db()->exec("ALTER TABLE galerias ADD COLUMN nome_fonte VARCHAR(80) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN nome_formato VARCHAR(40) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN nome_tamanho INT DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN nome_negrito TINYINT(1) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN descricao_fonte VARCHAR(80) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN descricao_formato VARCHAR(40) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN descricao_tamanho INT DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN descricao_negrito TINYINT(1) DEFAULT NULL"); } catch (Exception $e) {}

$stmt = db()->prepare("SELECT * FROM galerias WHERE link_token = ? LIMIT 1");
$stmt->execute([$token]);
$g = $stmt->fetch();
if (!$g) json_out(['status'=>'erro','mensagem'=>'Galeria nao encontrada.'], 404);

if ($g['cliente_id']) {
    $cli = db()->prepare("SELECT * FROM clientes WHERE id = ? LIMIT 1");
    $cli->execute([$g['cliente_id']]);
    $cliente = $cli->fetch();
    if (!$cliente || strtoupper($cliente['senha_acesso']) !== strtoupper(trim($senha)))
        json_out(['status'=>'erro','mensagem'=>'Senha incorreta.'], 401);
} elseif ($g['senha']) {
    if (!password_verify($senha, $g['senha']))
        json_out(['status'=>'erro','mensagem'=>'Senha incorreta.'], 401);
}

$_SESSION['galeria_access'][$g['id']] = true;

unset($g['senha']);
json_out([
    'status'    => 'ok',
    'galeria'   => $g,
    'dl_count'  => (int)($g['dl_count'] ?? 0),
    'dl_max'    => (int)($g['max_downloads'] ?? 0),
]);
