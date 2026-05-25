<?php
require_once __DIR__.'/../config.php';
$u = require_fotografo();
$body = body();

try { db()->exec("ALTER TABLE galerias ADD COLUMN nome_fonte VARCHAR(80) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN nome_formato VARCHAR(40) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN nome_tamanho INT DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN nome_negrito TINYINT(1) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN descricao_fonte VARCHAR(80) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN descricao_formato VARCHAR(40) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN descricao_tamanho INT DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN descricao_negrito TINYINT(1) DEFAULT NULL"); } catch (Exception $e) {}

$id = (int)($body['id'] ?? 0);
if (!$id) json_out(['status'=>'erro','mensagem'=>'ID invalido.'], 400);

$chk = db()->prepare("SELECT * FROM galerias WHERE id=? AND usuario_email=? LIMIT 1");
$chk->execute([$id, $u['email']]);
$galeria = $chk->fetch();
if (!$galeria) json_out(['status'=>'erro','mensagem'=>'Galeria nao encontrada.'], 404);

function clean_gallery_font($value) {
    $allowed = [
        'Inter', 'Arial', 'Arial Narrow', 'Georgia', 'Times New Roman', 'Verdana', 'Tahoma',
        'Alex Brush', 'Allura', 'Arizonia', 'Balqis', 'Black Jack', 'Blenda', 'Bolina', 'Sophia',
        'Bukhari Script', 'CAC Champagne', 'Champignon', 'Cookie', 'Cursif', 'Dancing Script',
        'Deftone Stylus', 'Dr Sugiyama', 'Freebooter Script', 'Germanica', 'Good Vibes', 'Great Vibes'
    ];
    $value = trim((string)$value);
    return in_array($value, $allowed, true) ? $value : null;
}

function clean_gallery_format($value) {
    $allowed = [
        'normal', 'fraktur', 'fraktur_bold', 'monospace', 'double_struck', 'script', 'script_bold',
        'roman', 'canadian', 'tai_le', 'small_caps', 'superscript', 'inverted', 'serif_bold',
        'serif_bold_italic', 'sans', 'sans_bold', 'sans_italic', 'sans_bold_italic', 'full_width'
    ];
    $value = trim((string)$value);
    return in_array($value, $allowed, true) ? $value : 'normal';
}

function clean_gallery_size($value, $min, $max) {
    if ($value === null || $value === '') return null;
    $size = (int)$value;
    if ($size < $min) return $min;
    if ($size > $max) return $max;
    return $size;
}

function clean_gallery_bool($value) {
    return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
}

$nome        = trim($body['nome'] ?? '');
$descricao   = trim($body['descricao'] ?? '');
$privacidade = in_array($body['privacidade']??'', ['publica','privada']) ? $body['privacidade'] : 'privada';
$senha_raw   = $body['senha'] ?? null;
$max_downloads = array_key_exists('max_downloads', $body) ? max(0, (int)$body['max_downloads']) : (int)($galeria['max_downloads'] ?? 0);
$max_selecao  = array_key_exists('max_selecao', $body) ? max(0, (int)$body['max_selecao']) : (int)($galeria['max_selecao'] ?? 0);

$nome_fonte = array_key_exists('nome_fonte', $body) ? clean_gallery_font($body['nome_fonte']) : ($galeria['nome_fonte'] ?? null);
$nome_tamanho = array_key_exists('nome_tamanho', $body) ? clean_gallery_size($body['nome_tamanho'], 28, 96) : ($galeria['nome_tamanho'] ?? null);
$nome_negrito = array_key_exists('nome_negrito', $body) ? clean_gallery_bool($body['nome_negrito']) : ($galeria['nome_negrito'] ?? null);
$descricao_fonte = array_key_exists('descricao_fonte', $body) ? clean_gallery_font($body['descricao_fonte']) : ($galeria['descricao_fonte'] ?? null);
$descricao_tamanho = array_key_exists('descricao_tamanho', $body) ? clean_gallery_size($body['descricao_tamanho'], 12, 42) : ($galeria['descricao_tamanho'] ?? null);
$descricao_negrito = array_key_exists('descricao_negrito', $body) ? clean_gallery_bool($body['descricao_negrito']) : ($galeria['descricao_negrito'] ?? null);
$nome_formato = array_key_exists('nome_formato', $body) ? clean_gallery_format($body['nome_formato']) : ($galeria['nome_formato'] ?? null);
$descricao_formato = array_key_exists('descricao_formato', $body) ? clean_gallery_format($body['descricao_formato']) : ($galeria['descricao_formato'] ?? null);

if (!$nome) json_out(['status'=>'erro','mensagem'=>'Nome obrigatorio.'], 400);

if ($senha_raw) {
    $stmt = db()->prepare("UPDATE galerias SET nome=?,descricao=?,privacidade=?,senha=?,max_downloads=?,max_selecao=?,nome_fonte=?,nome_formato=?,nome_tamanho=?,nome_negrito=?,descricao_fonte=?,descricao_formato=?,descricao_tamanho=?,descricao_negrito=? WHERE id=?");
    $stmt->execute([$nome, $descricao, $privacidade, password_hash($senha_raw, PASSWORD_DEFAULT), $max_downloads, $max_selecao, $nome_fonte, $nome_formato, $nome_tamanho, $nome_negrito, $descricao_fonte, $descricao_formato, $descricao_tamanho, $descricao_negrito, $id]);
} else {
    $stmt = db()->prepare("UPDATE galerias SET nome=?,descricao=?,privacidade=?,max_downloads=?,max_selecao=?,nome_fonte=?,nome_formato=?,nome_tamanho=?,nome_negrito=?,descricao_fonte=?,descricao_formato=?,descricao_tamanho=?,descricao_negrito=? WHERE id=?");
    $stmt->execute([$nome, $descricao, $privacidade, $max_downloads, $max_selecao, $nome_fonte, $nome_formato, $nome_tamanho, $nome_negrito, $descricao_fonte, $descricao_formato, $descricao_tamanho, $descricao_negrito, $id]);
}

json_out(['status'=>'ok','mensagem'=>'Galeria atualizada.']);
