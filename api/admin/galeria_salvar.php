<?php
/**
 * Edicao de qualquer galeria do sistema pelo painel administrativo.
 * Diferente de api/galerias/update.php, aqui nao ha filtro por dono.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

require_super_admin();

$body = body();
$id = (int)($body['id'] ?? 0);
if (!$id) json_out(['status' => 'erro', 'mensagem' => 'ID da galeria obrigatorio.'], 400);

$db = db();
$stmt = $db->prepare("SELECT id, nome, usuario_email, privacidade, selecao_ativa, max_downloads FROM galerias WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$galeria = $stmt->fetch();
if (!$galeria) json_out(['status' => 'erro', 'mensagem' => 'Galeria nao encontrada.'], 404);

$campos = [];
$params = [];

if (array_key_exists('nome', $body)) {
    $nome = trim((string)$body['nome']);
    if ($nome === '' || mb_strlen($nome) > 180) {
        json_out(['status' => 'erro', 'mensagem' => 'Nome invalido.'], 400);
    }
    $campos[] = 'nome = ?';
    $params[] = $nome;
}

if (array_key_exists('privacidade', $body)) {
    $priv = strtolower(trim((string)$body['privacidade']));
    if (!in_array($priv, ['publica', 'privada'], true)) {
        json_out(['status' => 'erro', 'mensagem' => 'Privacidade deve ser publica ou privada.'], 400);
    }
    $campos[] = 'privacidade = ?';
    $params[] = $priv;
}

if (array_key_exists('selecao_ativa', $body)) {
    $campos[] = 'selecao_ativa = ?';
    $params[] = ((int)$body['selecao_ativa'] === 1) ? 1 : 0;
}

if (array_key_exists('max_downloads', $body)) {
    $campos[] = 'max_downloads = ?';
    $params[] = max(0, (int)$body['max_downloads']);
}

// Transferir a galeria para outro fotografo existente.
if (array_key_exists('usuario_email', $body)) {
    $novoDono = strtolower(trim((string)$body['usuario_email']));
    $chk = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $chk->execute([$novoDono]);
    if (!$chk->fetch()) {
        json_out(['status' => 'erro', 'mensagem' => 'Nao existe fotografo com esse e-mail.'], 400);
    }
    $campos[] = 'usuario_email = ?';
    $params[] = $novoDono;
}

if (!$campos) json_out(['status' => 'erro', 'mensagem' => 'Nada para alterar.'], 400);

$params[] = $id;
$db->prepare("UPDATE galerias SET " . implode(', ', $campos) . " WHERE id = ?")->execute($params);

$stmt->execute([$id]);
json_out([
    'status' => 'ok',
    'mensagem' => 'Galeria atualizada.',
    'galeria' => $stmt->fetch(),
]);
