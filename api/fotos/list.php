<?php
require_once __DIR__.'/../config.php';
// Fotos são acessíveis publicamente se você tem o galeria_id (via token da galeria)
// Sem exigir login para permitir que clientes vejam as fotos

$galeria_id = (int)($_GET['galeria_id'] ?? 0);
if (!$galeria_id) json_out(['status'=>'erro','mensagem'=>'galeria_id obrigatório.'], 400);

// As migrações devem ser rodadas via db_migrations.php

try { db()->exec("ALTER TABLE imagens ADD COLUMN largura INT DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE imagens ADD COLUMN altura INT DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE imagens ADD COLUMN orientacao VARCHAR(20) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE imagens ADD COLUMN caminho_thumb_small VARCHAR(1024) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE imagens ADD COLUMN caminho_thumb_medium VARCHAR(1024) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE imagens ADD COLUMN caminho_thumb_large VARCHAR(1024) DEFAULT NULL"); } catch (Exception $e) {}

$ordem = $_GET['ordem'] ?? 'ordem';
$col   = $ordem === 'data' ? 'id' : 'ordem'; // id é sequencial como data

$stmt = db()->prepare("
    SELECT
        id,
        galeria_id,
        nome_arquivo,
        caminho_arquivo,
        caminho_thumb_small,
        caminho_thumb_medium,
        caminho_thumb_large,
        tamanho_bytes,
        largura,
        altura,
        orientacao,
        ordem,
        selecionada,
        eh_publica,
        is_capa,
        downloads,
        criado_em
    FROM imagens
    WHERE galeria_id=?
    ORDER BY $col ASC
");
$stmt->execute([$galeria_id]);
json_out(['status'=>'ok','fotos'=>$stmt->fetchAll()]);
