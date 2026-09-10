<?php
/**
 * Define a capa de apresentacao de uma galeria.
 *
 * Dois caminhos possiveis:
 *  - foto_id: promove a capa uma foto que ja esta na galeria;
 *  - arquivo `capa`: envia uma imagem avulsa, que nao entra na grade de fotos.
 */
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../lib/Storage.php';
$u = require_fotografo();

$galeria_id = (int)($_POST['galeria_id'] ?? 0);
if (!$galeria_id) json_out(['status'=>'erro','mensagem'=>'galeria_id obrigatório.'], 400);

try { db()->exec("ALTER TABLE imagens ADD COLUMN caminho_thumb_small VARCHAR(1024) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE imagens ADD COLUMN caminho_thumb_medium VARCHAR(1024) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE imagens ADD COLUMN caminho_thumb_large VARCHAR(1024) DEFAULT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN capa_crop_horizontal TEXT NULL"); } catch (Exception $e) {}
try { db()->exec("ALTER TABLE galerias ADD COLUMN capa_crop_vertical TEXT NULL"); } catch (Exception $e) {}

// Verificar dono
$chk = db()->prepare("SELECT id, capa_apresentacao FROM galerias WHERE id=? AND usuario_email=? LIMIT 1");
$chk->execute([$galeria_id, $u['email']]);
$galeriaAtual = $chk->fetch();
if (!$galeriaAtual) json_out(['status'=>'erro','mensagem'=>'Galeria não encontrada.'], 404);

$capaAnterior = $galeriaAtual['capa_apresentacao'] ?? null;

/**
 * Motivo concreto de uma falha no upload HTTP.
 *
 * A mensagem generica anterior ("tente uma imagem menor") era um chute: em
 * quatro dos seis codigos possiveis o tamanho do arquivo nao tem nada a ver com
 * a recusa, e o usuario ficava tentando reduzir uma foto que o servidor nunca
 * chegou a receber.
 */
function motivo_upload(?array $file): string {
    $codigo = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    switch ($codigo) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $limite = ini_get('upload_max_filesize');
            return "Imagem maior que o limite do servidor ({$limite}). Envie uma versao menor.";
        case UPLOAD_ERR_PARTIAL:
            return 'O envio foi interrompido antes do fim. Tente novamente.';
        case UPLOAD_ERR_NO_FILE:
            return 'Nenhum arquivo foi enviado.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'O servidor esta sem diretorio temporario para receber uploads.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'O servidor nao conseguiu gravar o arquivo temporario.';
        case UPLOAD_ERR_EXTENSION:
            return 'Uma extensao do PHP interrompeu o upload.';
        default:
            return 'Falha desconhecida no envio do arquivo.';
    }
}

// Suporte a definir capa puxando foto já existente da galeria ou por novo upload
$caminho = null;
$caminhoPreview = null;
$fid = null;

// foto_id vazio (o formulario chega a mandar o campo em branco em alguns fluxos)
// nao pode ser confundido com "promover a foto de id 0".
$fotoIdBruto = trim((string)($_POST['foto_id'] ?? ''));

if ($fotoIdBruto !== '') {
    $fid = (int)$fotoIdBruto;
    if ($fid <= 0) json_out(['status'=>'erro','mensagem'=>'foto_id inválido.'], 400);

    $stmtF = db()->prepare("SELECT caminho_arquivo, caminho_thumb_small, caminho_thumb_medium, caminho_thumb_large FROM imagens WHERE id=? AND galeria_id=?");
    $stmtF->execute([$fid, $galeria_id]);
    $foto = $stmtF->fetch();

    if (!$foto) {
        json_out(['status'=>'erro','mensagem'=>'Foto não encontrada ou não pertence a esta galeria.'], 404);
    }

    $caminho = $foto['caminho_arquivo'];
    $caminhoPreview = $foto['caminho_thumb_small'] ?: $foto['caminho_arquivo'];
    $stmt = db()->prepare("UPDATE galerias SET capa_apresentacao = ?, capa_crop_horizontal = NULL, capa_crop_vertical = NULL WHERE id = ?");
    $stmt->execute([$caminho, $galeria_id]);
} else {
    $file = $_FILES['capa'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_out(['status'=>'erro','mensagem'=>motivo_upload($file)], 400);
    }

    if ((int)($file['size'] ?? 0) <= 0) {
        json_out(['status'=>'erro','mensagem'=>'A imagem enviada esta vazia (0 bytes).'], 400);
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    $type = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $type = $finfo->file($file['tmp_name']) ?: '';
    }
    if (!$type) $type = $file['type'] ?? '';

    if (!isset($allowed[$type])) {
        $detectado = $type !== '' ? " (detectado: {$type})" : '';
        json_out([
            'status'=>'erro',
            'mensagem'=>"Tipo de arquivo não permitido{$detectado}. A capa aceita JPEG, PNG, WEBP ou GIF."
        ], 400);
    }

    $filename = uniqid('capa_', true).'.'.$allowed[$type];
    $caminho  = storage_put_upload($file['tmp_name'], "galerias/{$galeria_id}/capas/{$filename}", $type);
    $caminhoPreview = $caminho;

    // Atualizar o banco de dados (Capa da Galeria)
    $stmt = db()->prepare("UPDATE galerias SET capa_apresentacao = ?, capa_crop_horizontal = NULL, capa_crop_vertical = NULL WHERE id = ?");
    $stmt->execute([$caminho, $galeria_id]);
}

// Sincronizar o selo de "Coroa" (is_capa) na tabela de imagens de forma ATÔMICA
$db = db();
try {
    $db->beginTransaction();

    // 1. Remove de todas as fotos da galeria (Garante exclusividade)
    $stmt1 = $db->prepare("UPDATE imagens SET is_capa = 0 WHERE galeria_id = ?");
    $stmt1->execute([$galeria_id]);

    // 2. Marca a nova capa. So existe selo quando a capa e uma foto da grade:
    //    a capa avulsa nao tem linha em `imagens`, e a busca por caminho_arquivo
    //    que havia aqui nunca casava com nada.
    if ($fid !== null) {
        $stmt2 = $db->prepare("UPDATE imagens SET is_capa = 1 WHERE id = ? AND galeria_id = ?");
        $stmt2->execute([$fid, $galeria_id]);
    }

    $db->commit();
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    json_out(['status'=>'erro','mensagem'=>'Erro ao sincronizar selo de capa: ' . $e->getMessage()], 500);
}

// A capa avulsa anterior vira lixo no bucket assim que deixa de ser referenciada.
// So pode ser apagada quando nao for tambem uma foto da galeria - nesse caso o
// arquivo continua em uso pela grade.
if ($capaAnterior && $capaAnterior !== $caminho) {
    $emUso = db()->prepare("SELECT 1 FROM imagens WHERE caminho_arquivo = ? LIMIT 1");
    $emUso->execute([$capaAnterior]);
    if (!$emUso->fetch()) {
        storage_delete_url($capaAnterior);
    }
}

json_out([
    'status'=>'ok',
    'caminho' => $caminho,
    'caminho_preview' => $caminhoPreview ?: $caminho,
    'mensagem'=>'Capa definida e sincronizada com sucesso!'
]);
