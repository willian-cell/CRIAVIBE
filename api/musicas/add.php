<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../lib/Storage.php';
$u = require_fotografo();

$galeria_id = (int)($_POST['galeria_id'] ?? 0);
if (!$galeria_id) json_out(['status'=>'erro','mensagem'=>'galeria_id obrigatorio.'], 400);

function upload_error_message(int $error): string {
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Arquivo de audio muito grande para o servidor.',
        UPLOAD_ERR_PARTIAL => 'Upload incompleto. Tente enviar novamente.',
        UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado.',
        default => 'Falha ao receber o arquivo de audio.',
    };
}

// Verificar dono
$chk = db()->prepare("SELECT id FROM galerias WHERE id=? AND usuario_email=? LIMIT 1");
$chk->execute([$galeria_id, $u['email']]);
if (!$chk->fetch()) json_out(['status'=>'erro','mensagem'=>'Galeria nao encontrada.'], 404);

// Opcao 1: upload de arquivo de audio
$file = $_FILES['musica'] ?? null;
if ($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_out(['status'=>'erro','mensagem'=>upload_error_message((int)$file['error'])], 400);
    }

    $allowed = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/ogg' => 'ogg',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
    ];

    $type = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $type = $finfo->file($file['tmp_name']) ?: '';
    }
    if (!$type) $type = $file['type'] ?? '';

    if (!isset($allowed[$type])) {
        json_out(['status'=>'erro','mensagem'=>'Formato nao suportado. Use MP3, OGG, WAV ou M4A.'], 400);
    }

    if (($file['size'] ?? 0) > 50 * 1024 * 1024) {
        json_out(['status'=>'erro','mensagem'=>'Audio muito grande. Envie um arquivo de ate 50 MB.'], 400);
    }

    $ext = $allowed[$type];
    $filename = 'mus_'.$galeria_id.'_'.bin2hex(random_bytes(8)).'.'.$ext;
    $caminho = storage_put_upload($file['tmp_name'], 'musicas/'.$galeria_id.'/'.$filename, $type);

    $ord = db()->prepare("SELECT COALESCE(MAX(ordem),0)+1 FROM musicas WHERE galeria_id=?");
    $ord->execute([$galeria_id]);
    $ordem = (int)$ord->fetchColumn();

    $nome_exibicao = pathinfo($file['name'], PATHINFO_FILENAME);
    $stmt = db()->prepare("INSERT INTO musicas (galeria_id,nome_arquivo,nome_exibicao,caminho_arquivo,ordem) VALUES (?,?,?,?,?)");
    $stmt->execute([$galeria_id, $file['name'], $nome_exibicao, $caminho, $ordem]);
    json_out(['status'=>'ok','mensagem'=>'Musica adicionada.']);
}

// Opcao 2: URL YouTube
$yt_url = trim($_POST['yt_url'] ?? '');
$yt_nome = trim($_POST['yt_nome'] ?? 'YouTube');
if ($yt_url) {
    if (!preg_match('/youtube\.com|youtu\.be/', $yt_url)) {
        json_out(['status'=>'erro','mensagem'=>'URL invalida. Use YouTube.'], 400);
    }

    $ord = db()->prepare("SELECT COALESCE(MAX(ordem),0)+1 FROM musicas WHERE galeria_id=?");
    $ord->execute([$galeria_id]);
    $ordem = (int)$ord->fetchColumn();

    $stmt = db()->prepare("INSERT INTO musicas (galeria_id,nome_arquivo,nome_exibicao,caminho_arquivo,ordem) VALUES (?,?,?,?,?)");
    $stmt->execute([$galeria_id, 'youtube', $yt_nome, $yt_url, $ordem]);
    json_out(['status'=>'ok','mensagem'=>'YouTube adicionado.']);
}

json_out(['status'=>'erro','mensagem'=>'Nenhum arquivo ou URL fornecido.'], 400);
