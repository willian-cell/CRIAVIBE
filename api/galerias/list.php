<?php
require_once __DIR__.'/../config.php';
$u = require_fotografo();

try { db()->exec("ALTER TABLE imagens ADD COLUMN caminho_thumb_small VARCHAR(1024) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE imagens ADD COLUMN caminho_thumb_medium VARCHAR(1024) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE imagens ADD COLUMN caminho_thumb_large VARCHAR(1024) DEFAULT NULL"); } catch (Exception $e) {}

// As migrações devem ser rodadas via db_migrations.php

$sql = "
    SELECT g.*,
           COUNT(i.id) as total_fotos,
           SUM(CASE WHEN i.selecionada = 1 THEN 1 ELSE 0 END) as total_selecionadas,
           COALESCE(
            NULLIF(g.capa_apresentacao, ''),
            (SELECT COALESCE(i2.caminho_thumb_medium, i2.caminho_thumb_large, i2.caminho_thumb_small, i2.caminho_arquivo) FROM imagens i2
            WHERE i2.galeria_id = g.id 
            ORDER BY i2.is_capa DESC, i2.ordem ASC LIMIT 1)
           ) as thumb,
           (SELECT COUNT(*) FROM musicas m WHERE m.galeria_id = g.id) as total_musicas,
           (SELECT GROUP_CONCAT(m2.nome_exibicao SEPARATOR '||')
            FROM musicas m2
            WHERE m2.galeria_id = g.id
            ORDER BY m2.id LIMIT 2) as playlist_nomes
    FROM galerias g
    LEFT JOIN imagens i ON i.galeria_id = g.id
    WHERE g.usuario_email = ?
    GROUP BY g.id
    ORDER BY g.criado_em DESC
";
$stmt = db()->prepare($sql);
$stmt->execute([$u['email']]);
json_out(['status'=>'ok','galerias'=>$stmt->fetchAll()]);
