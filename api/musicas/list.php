<?php
require_once __DIR__.'/../config.php';
// Músicas são carregadas pelo cliente — verifica acesso via sessão ou galeria pública

$galeria_id = (int)($_GET['galeria_id'] ?? 0);
$token = $_GET['token'] ?? '';
if (!$galeria_id && !$token) json_out(['status'=>'erro','mensagem'=>'galeria_id ou token obrigatório.'], 400);

// Permite acesso se: fotógrafo logado, OU token válido, OU sessão de cliente válida, OU galeria pública
$acesso = false;
$u = me();
if ($u) {
    $acesso = true; // fotógrafo logado
} elseif ($token) {
    $chk = db()->prepare("SELECT id FROM galerias WHERE link_token=? LIMIT 1");
    $chk->execute([$token]);
    $g = $chk->fetch();
    if ($g) {
        $galeria_id = (int)$g['id'];
        $acesso = true;
    }
} elseif (!empty($_SESSION['galeria_access'][$galeria_id])) {
    $acesso = true; // cliente autenticado
} else {
    // Verifica se galeria é pública
    $chk = db()->prepare("SELECT id FROM galerias WHERE id=? AND privacidade='publica' LIMIT 1");
    $chk->execute([$galeria_id]);
    if ($chk->fetch()) $acesso = true;
}

if (!$acesso) json_out(['status'=>'erro','mensagem'=>'Sem acesso.'], 403);

$stmt = db()->prepare("SELECT * FROM musicas WHERE galeria_id=? ORDER BY ordem ASC");
$stmt->execute([$galeria_id]);
json_out(['status'=>'ok','musicas'=>$stmt->fetchAll()]);
