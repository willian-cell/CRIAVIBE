<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

$token = trim($_POST['token_publico'] ?? $_SESSION['agendamento_aluno_token'] ?? '');
$isAdmin = agendamento_is_admin();

try {
    $db = db();
    agendamento_ensure_schema($db);
} catch (Throwable $e) {
    json_out(['status' => 'erro', 'mensagem' => 'Erro ao conectar ao banco de dados.'], 500);
}

$student = $token ? agendamento_fetch_student_by_token($db, $token) : null;

// Se não for admin e não encontrar o aluno, barra
if (!$student && !$isAdmin) {
    json_out(['status' => 'erro', 'mensagem' => 'Sessão inválida ou não autorizado.'], 403);
}

// Se for admin, o ID do aluno deve ser passado no POST
$studentId = $student ? (int)$student['id'] : (int)($_POST['aluno_id'] ?? 0);
if (!$studentId) {
    json_out(['status' => 'erro', 'mensagem' => 'ID do aluno é obrigatório.'], 400);
}

// Se for admin, verifica se o aluno existe
if ($isAdmin && !$student) {
    $chk = $db->prepare("SELECT id FROM agendamento_alunos WHERE id = ? LIMIT 1");
    $chk->execute([$studentId]);
    if (!$chk->fetch()) {
        json_out(['status' => 'erro', 'mensagem' => 'Aluno não encontrado.'], 404);
    }
}

$file = $_FILES['foto_aluno'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    json_out(['status' => 'erro', 'mensagem' => 'Nenhuma imagem enviada ou erro no envio.'], 400);
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

$type = '';
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $type = $finfo->file($file['tmp_name']) ?: '';
}
if (!$type) {
    $type = $file['type'] ?? '';
}
if (!isset($allowed[$type])) {
    json_out(['status' => 'erro', 'mensagem' => 'Tipo de imagem não permitido. Use JPG, PNG, WEBP ou GIF.'], 400);
}

if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
    json_out(['status' => 'erro', 'mensagem' => 'Imagem muito grande. Envie um arquivo de até 5 MB.'], 400);
}

$filename = 'aluno_' . $studentId . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$type];
$caminho = '';

if (R2_ACCESS_KEY && R2_SECRET_KEY && R2_BUCKET && R2_ENDPOINT && R2_PUBLIC_URL) {
    require_once __DIR__ . '/../lib/R2Storage.php';
    $r2Path = 'alunos/' . $studentId . '/' . $filename;
    $r2 = new R2Storage(R2_ACCESS_KEY, R2_SECRET_KEY, R2_BUCKET, R2_ENDPOINT);
    if (!$r2->upload($file['tmp_name'], $r2Path, $type)) {
        json_out(['status' => 'erro', 'mensagem' => 'Falha ao salvar a imagem no Cloudflare R2.'], 500);
    }
    $caminho = rtrim(R2_PUBLIC_URL, '/') . '/' . $r2Path;
} else {
    $uploadDir = __DIR__ . '/../../uploads/alunos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $dest = $uploadDir . $filename;
    $caminho = 'uploads/alunos/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        json_out(['status' => 'erro', 'mensagem' => 'Falha ao salvar a imagem no servidor.'], 500);
    }
}

// Salva o caminho no cadastro do aluno
$upd = $db->prepare("UPDATE agendamento_alunos SET foto_url = ? WHERE id = ?");
$upd->execute([$caminho, $studentId]);

agendamento_log($db, null, $studentId, 'foto_perfil_atualizada', ['caminho' => $caminho], $isAdmin ? 'fotografo' : 'aluno', $isAdmin ? ($_SESSION['agendamento_admin_email'] ?? null) : ($student['email'] ?? null));

json_out([
    'status' => 'ok',
    'mensagem' => 'Foto de perfil atualizada com sucesso.',
    'foto_url' => $caminho,
]);
