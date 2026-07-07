<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_helpers.php';

// Verify admin permissions
agendamento_require_admin();

$body = body();

// Validate fields
$valorSantoAntonio = (int)($body['valor_santo_antonio_centavos'] ?? 10000);
$valorOutraCidade = (int)($body['valor_outra_cidade_centavos'] ?? 15000);
$popupMensagem = trim($body['popup_mensagem'] ?? '');
$desconto2Aulas = (int)($body['desconto_2_aulas'] ?? 10);
$desconto3Aulas = (int)($body['desconto_3_aulas'] ?? 20);

if ($valorSantoAntonio < 0 || $valorOutraCidade < 0) {
    json_out(['status' => 'erro', 'mensagem' => 'Os valores base das aulas devem ser maiores ou iguais a zero.'], 400);
}

if ($desconto2Aulas < 0 || $desconto2Aulas > 100 || $desconto3Aulas < 0 || $desconto3Aulas > 100) {
    json_out(['status' => 'erro', 'mensagem' => 'Os descontos devem estar entre 0% e 100%.'], 400);
}

if ($popupMensagem === '') {
    json_out(['status' => 'erro', 'mensagem' => 'A mensagem do popup de aviso não pode estar vazia.'], 400);
}

try {
    $db = db();
    agendamento_ensure_schema($db);
    
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $stmt = $db->prepare("INSERT OR REPLACE INTO agendamento_config (chave, valor) VALUES (?, ?)");
    } else {
        $stmt = $db->prepare("INSERT INTO agendamento_config (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
    }
    
    $configs = [
        'valor_santo_antonio_centavos' => (string)$valorSantoAntonio,
        'valor_outra_cidade_centavos' => (string)$valorOutraCidade,
        'popup_mensagem' => $popupMensagem,
        'desconto_2_aulas' => (string)$desconto2Aulas,
        'desconto_3_aulas' => (string)$desconto3Aulas,
    ];
    
    $db->beginTransaction();
    
    foreach ($configs as $chave => $valor) {
        if ($driver === 'sqlite') {
            $stmt->execute([$chave, $valor]);
        } else {
            $stmt->execute([$chave, $valor, $valor]);
        }
    }
    
    $db->commit();
    
    json_out(['status' => 'ok', 'mensagem' => 'Configurações salvas com sucesso.']);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Erro ao salvar configuracoes de agendamento: ' . $e->getMessage());
    json_out(['status' => 'erro', 'mensagem' => 'Erro ao salvar configurações no banco de dados.'], 500);
}
