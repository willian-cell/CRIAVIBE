<?php
require_once __DIR__.'/../config.php';

$body = body();
$email = strtolower(trim($body['email'] ?? ''));
$senha = $body['senha'] ?? '';

if (!$email || !$senha) json_out(['status'=>'erro','mensagem'=>'E-mail e senha obrigatórios.'], 400);

$stmt = db()->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$u = $stmt->fetch();

if (!$u || !password_verify($senha, $u['senha']))
    json_out(['status'=>'erro','mensagem'=>'E-mail ou senha incorretos.'], 401);

// Conta bloqueada pelo administrador: dados preservados, acesso negado.
if (!empty($u['bloqueado']))
    json_out(['status'=>'erro','mensagem'=>'Esta conta está bloqueada. Fale com o administrador.'], 403);

$_SESSION['usuario'] = [
    'id'    => $u['id'],
    'nome'  => $u['nome'],
    'email' => $u['email'],
    'tipo'  => $u['tipo'],
    'foto_perfil' => $u['foto_perfil'] ?? null,
];
$_SESSION['usuario']['is_admin'] = is_super_admin($_SESSION['usuario']);

// Alimenta a coluna "Ultimo acesso" do painel administrativo.
try {
    db()->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?")->execute([$u['id']]);
} catch (Exception $e) {}

json_out(['status'=>'ok','usuario'=>$_SESSION['usuario']]);
