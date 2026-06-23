<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/R2Storage.php';

// Eleva limites para o processamento de imagens
@ini_set('memory_limit', '512M');
@set_time_limit(180);

$galeria_id = (int)($_GET['galeria_id'] ?? 0);
if (!$galeria_id) {
    json_out(['status' => 'erro', 'mensagem' => 'galeria_id obrigatório.'], 400);
}

try {
    $db = db();
    
    // Busca até 3 fotos da galeria que ainda estejam com a miniatura medium nula ou vazia
    $stmt = $db->prepare("
        SELECT id, galeria_id, nome_arquivo, caminho_arquivo 
        FROM imagens 
        WHERE galeria_id = ? AND (caminho_thumb_small IS NULL OR caminho_thumb_small = '')
        LIMIT 3
    ");
    $stmt->execute([$galeria_id]);
    $fotos = $stmt->fetchAll();
    
    if (empty($fotos)) {
        json_out(['status' => 'ok', 'processadas' => 0, 'fotos_atualizadas' => [], 'restantes' => 0]);
    }
    
    $r2 = new R2Storage(R2_ACCESS_KEY, R2_SECRET_KEY, R2_BUCKET, R2_ENDPOINT);
    $arrContextOptions = [
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
        ],
    ];
    
    $sizes = ['small' => 360];
    $qualities = ['small' => 68];
    
    $atualizadas = [];
    
    foreach ($fotos as $foto) {
        $public_url = $foto['caminho_arquivo'];
        $r2_path = $public_url;
        
        if (R2_PUBLIC_URL && strpos($public_url, rtrim(R2_PUBLIC_URL, '/') . '/') === 0) {
            $r2_path = substr($public_url, strlen(rtrim(R2_PUBLIC_URL, '/')) + 1);
        }
        
        // Baixa o arquivo original
        $tmp = tempnam(sys_get_temp_dir(), 'cv_process_');
        $content = @file_get_contents($public_url, false, stream_context_create($arrContextOptions));
        
        if ($content === false) {
            @unlink($tmp);
            continue; // Falha no download, pula para a próxima
        }
        
        file_put_contents($tmp, $content);
        
        $urls_thumbs = [];
        $sucesso = true;
        
        // Gera cada derivado
        foreach ($sizes as $label => $w) {
            $base = pathinfo($r2_path, PATHINFO_FILENAME);
            $dir = pathinfo($r2_path, PATHINFO_DIRNAME);
            $derPath = $dir . '/derivados/' . $label . '_' . $base . '.webp';
            
            $outTmp = tempnam(sys_get_temp_dir(), 'cv_der_');
            
            // Redimensiona usando Imagick ou GD
            if (class_exists('Imagick')) {
                try {
                    $img = new Imagick($tmp);
                    $img->setImageColorspace(Imagick::COLORSPACE_RGB);
                    $img->thumbnailImage($w, 0);
                    $img->setImageFormat('webp');
                    $img->setImageCompressionQuality((int)($qualities[$label] ?? 72));
                    $img->stripImage();
                    $img->writeImage($outTmp);
                    $img->clear();
                    $img->destroy();
                } catch (Throwable $e) {
                    $sucesso = false;
                }
            } else {
                // GD Fallback
                $src = @imagecreatefromstring($content);
                if ($src !== false) {
                    $sw = imagesx($src);
                    $sh = imagesy($src);
                    $nw = $w;
                    $nh = intval($sh * ($nw / $sw));
                    $dst = imagecreatetruecolor($nw, $nh);
                    imagecopyresampled($dst, $src, 0,0,0,0,$nw,$nh,$sw,$sh);
                    if (!function_exists('imagewebp')) { $sucesso = false; }
                    else imagewebp($dst, $outTmp, (int)($qualities[$label] ?? 68));
                    imagedestroy($dst);
                    imagedestroy($src);
                } else {
                    $sucesso = false;
                }
            }
            
            if ($sucesso) {
                // Upload para R2
                $mtype = 'image/webp';
                $ok = $r2->upload($outTmp, $derPath, $mtype);
                if ($ok) {
                    $urls_thumbs[$label] = rtrim(R2_PUBLIC_URL, '/') . '/' . ltrim($derPath, '/');
                } else {
                    $sucesso = false;
                }
            }
            
            @unlink($outTmp);
        }
        
        @unlink($tmp);
        
        if ($sucesso && !empty($urls_thumbs)) {
            // Atualiza o banco de dados
            $upd = $db->prepare("
                UPDATE imagens 
                SET caminho_thumb_small = ?
                WHERE id = ?
            ");
            $upd->execute([
                $urls_thumbs['small'] ?? null,
                $foto['id']
            ]);
            
            $atualizadas[] = [
                'id' => $foto['id'],
                'caminho_thumb_small' => $urls_thumbs['small'] ?? null,
                'caminho_thumb_medium' => $urls_thumbs['medium'] ?? null,
                'caminho_thumb_large' => $urls_thumbs['large'] ?? null
            ];
        }
    }
    
    // Conta quantas fotos ainda restam sem miniaturas nesta galeria
    $restantes_stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM imagens 
        WHERE galeria_id = ? AND (caminho_thumb_small IS NULL OR caminho_thumb_small = '')
    ");
    $restantes_stmt->execute([$galeria_id]);
    $restantes = (int)$restantes_stmt->fetchColumn();
    
    json_out([
        'status' => 'ok',
        'processadas' => count($atualizadas),
        'fotos_atualizadas' => $atualizadas,
        'restantes' => $restantes
    ]);
    
} catch (Throwable $e) {
    error_log('Erro ao processar miniaturas sob demanda: ' . $e->getMessage());
    json_out(['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
}
