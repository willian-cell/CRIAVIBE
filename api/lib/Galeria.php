<?php
/**
 * Galeria - CriaVibe
 * Operacoes de dominio compartilhadas entre a exclusao feita pelo proprio
 * fotografo e a exclusao feita pelo painel administrativo, para que as duas
 * limpem exatamente os mesmos arquivos no R2.
 */
require_once __DIR__ . '/Storage.php';

/**
 * Remove do R2 todos os arquivos referenciados por uma galeria e apaga os
 * registros correspondentes.
 *
 * A varredura e sempre feita pelos registros do banco, nunca por prefixo no
 * bucket: assim um id inesperado jamais consegue apagar objetos de outra
 * galeria.
 *
 * @return array contadores do que foi removido
 */
function galeria_excluir(PDO $db, int $galeriaId): array {
    $imgs = $db->prepare("SELECT caminho_arquivo, caminho_thumb_small, caminho_thumb_medium, caminho_thumb_large FROM imagens WHERE galeria_id=?");
    $imgs->execute([$galeriaId]);
    $fotos = $imgs->fetchAll();
    foreach ($fotos as $img) {
        storage_delete_imagem($img);
    }

    $mus = $db->prepare("SELECT caminho_arquivo FROM musicas WHERE galeria_id=?");
    $mus->execute([$galeriaId]);
    $musicas = $mus->fetchAll();
    foreach ($musicas as $m) {
        storage_delete_url($m['caminho_arquivo']);
    }

    // A capa de apresentacao pode ser um upload proprio, fora da tabela de imagens.
    $capa = $db->prepare("SELECT capa_apresentacao FROM galerias WHERE id=?");
    $capa->execute([$galeriaId]);
    $capaUrl = $capa->fetchColumn();
    if ($capaUrl && strpos((string)$capaUrl, '/capas/') !== false) {
        storage_delete_url($capaUrl);
    }

    $db->prepare("DELETE FROM imagens WHERE galeria_id=?")->execute([$galeriaId]);
    $db->prepare("DELETE FROM musicas WHERE galeria_id=?")->execute([$galeriaId]);
    $db->prepare("DELETE FROM galerias WHERE id=?")->execute([$galeriaId]);

    return [
        'fotos_removidas'   => count($fotos),
        'musicas_removidas' => count($musicas),
    ];
}
