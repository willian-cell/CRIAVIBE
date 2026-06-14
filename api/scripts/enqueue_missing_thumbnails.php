<?php
require_once __DIR__ . '/../config.php';

$isCli = (PHP_SAPI === 'cli');
$user = null;

if (!$isCli) {
    $user = require_fotografo();
}

$galeriaId = 0;
$limit = 500;

if ($isCli) {
    foreach (array_slice($argv ?? [], 1) as $arg) {
        if (strpos($arg, '--galeria=') === 0) $galeriaId = (int)substr($arg, 10);
        if (strpos($arg, '--limit=') === 0) $limit = (int)substr($arg, 8);
    }
} else {
    $galeriaId = (int)($_GET['galeria_id'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 500);
}

$limit = max(1, min($limit, 2000));

try {
    require_once __DIR__ . '/../lib/Queue.php';
    $q = new Queue();

    $where = [
        "(i.caminho_thumb_medium IS NULL OR i.caminho_thumb_medium = '')",
        "i.caminho_arquivo IS NOT NULL",
        "i.caminho_arquivo <> ''"
    ];
    $params = [];

    if ($galeriaId > 0) {
        $where[] = 'i.galeria_id = ?';
        $params[] = $galeriaId;
    }

    if (!$isCli && $user) {
        $where[] = 'g.usuario_email = ?';
        $params[] = $user['email'];
    }

    $sql = "
        SELECT i.galeria_id, i.nome_arquivo, i.caminho_arquivo
        FROM imagens i
        JOIN galerias g ON g.id = i.galeria_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY i.id ASC
        LIMIT {$limit}
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $enfileiradas = 0;
    foreach ($rows as $row) {
        $public = $row['caminho_arquivo'];
        $r2Path = $public;
        if (R2_PUBLIC_URL && strpos($public, rtrim(R2_PUBLIC_URL, '/') . '/') === 0) {
            $r2Path = substr($public, strlen(rtrim(R2_PUBLIC_URL, '/')) + 1);
        }

        $q->push(WORKER_QUEUE_NAME, [
            'type' => 'generate_derivatives',
            'galeria_id' => (int)$row['galeria_id'],
            'r2_path' => $r2Path,
            'public_url' => $public,
            'original_name' => $row['nome_arquivo'] ?? '',
            'sizes' => ['small' => 360, 'medium' => 700, 'large' => 1080],
            'qualities' => ['small' => 68, 'medium' => 72, 'large' => 76],
        ]);
        $enfileiradas++;
    }

    json_out([
        'status' => 'ok',
        'enfileiradas' => $enfileiradas,
        'limit' => $limit,
        'galeria_id' => $galeriaId ?: null,
    ]);
} catch (Throwable $e) {
    error_log('Erro ao enfileirar thumbnails ausentes: ' . $e->getMessage());
    json_out(['status' => 'erro', 'mensagem' => 'Erro ao enfileirar thumbnails: ' . $e->getMessage()], 500);
}
