<?php
/**
 * Exclusao definitiva de um fotografo e de tudo que pertence a ele:
 * galerias, fotos, musicas, clientes e os arquivos correspondentes no R2.
 *
 * Operacao irreversivel. Por isso exige que o chamador repita o e-mail exato
 * da conta em `confirmacao` - um id trocado por engano nao passa por essa
 * checagem.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Galeria.php';
require_once __DIR__ . '/_guard.php';

require_super_admin();
admin_ensure_schema();

$body = body();
$id = (int)($body['id'] ?? 0);
$confirmacao = strtolower(trim((string)($body['confirmacao'] ?? '')));

if (!$id) json_out(['status' => 'erro', 'mensagem' => 'ID do fotografo obrigatorio.'], 400);

$db = db();
$stmt = $db->prepare("SELECT id, nome, email, foto_perfil FROM usuarios WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$alvo = $stmt->fetch();
if (!$alvo) json_out(['status' => 'erro', 'mensagem' => 'Fotografo nao encontrado.'], 404);

admin_bloquear_auto_alvo($alvo);

if ($confirmacao !== strtolower(trim($alvo['email']))) {
    json_out([
        'status' => 'erro',
        'mensagem' => 'Confirmacao invalida: repita o e-mail exato da conta que sera excluida.'
    ], 400);
}

$email = $alvo['email'];

// Galerias primeiro, uma a uma, para que cada arquivo saia do R2.
$gals = $db->prepare("SELECT id FROM galerias WHERE usuario_email = ?");
$gals->execute([$email]);
$ids = $gals->fetchAll(PDO::FETCH_COLUMN);

$fotos = 0;
$musicas = 0;
foreach ($ids as $gid) {
    $r = galeria_excluir($db, (int)$gid);
    $fotos += $r['fotos_removidas'];
    $musicas += $r['musicas_removidas'];
}

// Fotos de perfil dos clientes tambem vivem no R2.
$cli = $db->prepare("SELECT id, foto_cliente FROM clientes WHERE fotografo_email = ?");
$cli->execute([$email]);
$clientes = $cli->fetchAll();
foreach ($clientes as $c) {
    storage_delete_url($c['foto_cliente'] ?? null);
}
$db->prepare("DELETE FROM clientes WHERE fotografo_email = ?")->execute([$email]);

storage_delete_url($alvo['foto_perfil'] ?? null);
$db->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);

error_log("Admin removeu o fotografo {$email}: " . count($ids) . " galerias, {$fotos} fotos, " . count($clientes) . " clientes.");

json_out([
    'status' => 'ok',
    'mensagem' => 'Fotografo e todo o conteudo dele foram removidos.',
    'removidos' => [
        'galerias' => count($ids),
        'fotos'    => $fotos,
        'musicas'  => $musicas,
        'clientes' => count($clientes),
    ],
]);
