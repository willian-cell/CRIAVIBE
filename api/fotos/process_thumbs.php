<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/R2Storage.php';

// Eleva limites para o processamento de imagens
@ini_set('memory_limit', '512M');
@set_time_limit(180);

/**
 * Marca mais uma tentativa frustrada de gerar a miniatura desta foto, para que
 * a proxima chamada priorize outras e a galeria nao fique presa na mesma foto.
 */
function registrar_falha_thumb(PDO $db, int $imagemId, string $motivo): void {
    try {
        $db->prepare("UPDATE imagens SET thumb_tentativas = thumb_tentativas + 1 WHERE id = ?")
           ->execute([$imagemId]);
    } catch (Exception $e) {}
    error_log("Miniatura da imagem #{$imagemId} falhou ({$motivo}).");
}

$galeria_id = (int)($_GET['galeria_id'] ?? 0);
if (!$galeria_id) {
    json_out(['status' => 'erro', 'mensagem' => 'galeria_id obrigatório.'], 400);
}

try {
    $db = db();
    
    // Contador de tentativas por foto. Sem ele, a consulta abaixo devolvia
    // sempre as mesmas 3 primeiras fotos sem miniatura: bastava uma falhar de
    // forma permanente (arquivo corrompido, formato que o servidor nao
    // decodifica) para travar a geracao de miniaturas da galeria inteira.
    try { $db->exec("ALTER TABLE imagens ADD COLUMN thumb_tentativas INT NOT NULL DEFAULT 0"); } catch (Exception $e) {}

    // Fotos sem miniatura, priorizando as que ainda nao falharam. Depois de
    // 3 tentativas a foto sai da fila e a galeria segue adiante.
    $stmt = $db->prepare("
        SELECT id, galeria_id, nome_arquivo, caminho_arquivo
        FROM imagens
        WHERE galeria_id = ?
          AND (caminho_thumb_small IS NULL OR caminho_thumb_small = '')
          AND thumb_tentativas < 3
        ORDER BY thumb_tentativas ASC, id ASC
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
            registrar_falha_thumb($db, (int)$foto['id'], 'download do original falhou');
            continue;
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
        
        if (!$sucesso || empty($urls_thumbs)) {
            registrar_falha_thumb($db, (int)$foto['id'], 'geracao ou envio do derivado falhou');
        }

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
    
    // Restantes que ainda vale a pena tentar. Fotos que estouraram o limite de
    // tentativas ficam de fora para o cliente parar de repetir chamadas.
    $restantes_stmt = $db->prepare("
        SELECT COUNT(*)
        FROM imagens
        WHERE galeria_id = ?
          AND (caminho_thumb_small IS NULL OR caminho_thumb_small = '')
          AND thumb_tentativas < 3
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
