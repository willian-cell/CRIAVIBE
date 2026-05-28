<?php
require_once __DIR__.'/../config.php';
$u = require_fotografo();
$body = body();

$id = (int)($body['id'] ?? 0);
if (!$id) json_out(['status'=>'erro','mensagem'=>'ID inválido.'], 400);

try { db()->exec("ALTER TABLE galerias ADD COLUMN capa_crop_horizontal TEXT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN capa_crop_vertical TEXT NULL"); } catch (Exception $e) {}

$chk = db()->prepare("SELECT id FROM galerias WHERE id=? AND usuario_email=? LIMIT 1");
$chk->execute([$id, $u['email']]);
if (!$chk->fetch()) json_out(['status'=>'erro','mensagem'=>'Galeria não encontrada.'], 404);

function normalizar_crop($crop): array {
    if (!is_array($crop)) return ['x'=>50, 'y'=>50, 'zoom'=>1];
    $x = max(0, min(100, (float)($crop['x'] ?? 50)));
    $y = max(0, min(100, (float)($crop['y'] ?? 50)));
    $zoom = max(1, min(3, (float)($crop['zoom'] ?? 1)));
    return [
        'x' => round($x, 2),
        'y' => round($y, 2),
        'zoom' => round($zoom, 3),
    ];
}

$horizontal = normalizar_crop($body['horizontal'] ?? null);
$vertical = normalizar_crop($body['vertical'] ?? null);

$stmt = db()->prepare("UPDATE galerias SET capa_crop_horizontal=?, capa_crop_vertical=? WHERE id=?");
$stmt->execute([
    json_encode($horizontal, JSON_UNESCAPED_UNICODE),
    json_encode($vertical, JSON_UNESCAPED_UNICODE),
    $id
]);

json_out([
    'status'=>'ok',
    'mensagem'=>'Cortes da capa salvos.',
    'horizontal'=>$horizontal,
    'vertical'=>$vertical
]);
?>
