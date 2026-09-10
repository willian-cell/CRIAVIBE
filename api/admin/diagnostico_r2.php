<?php
/**
 * Diagnostico do armazenamento no Cloudflare R2.
 *
 * Executa o mesmo caminho de um upload real - PUT assinado, HEAD assinado,
 * leitura pela URL publica e DELETE - e devolve o resultado de cada etapa com o
 * codigo HTTP e a causa concreta da falha.
 *
 * Serve para separar em um unico acesso as quatro camadas que ja derrubaram o
 * upload (R2 desabilitado na conta, credencial invalida, token sem permissao de
 * escrita e acesso publico desligado), sem depender de ler o log do Railway.
 *
 * Uso: abrir /api/admin/diagnostico_r2.php no navegador logado como
 * administrador do sistema. Nao aceita parametros e nao altera nada alem de
 * gravar e apagar um PNG de 70 bytes em diagnostico/.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../lib/Storage.php';

require_super_admin();

$diag = storage_diagnostico();

$resumo = $diag['ok']
    ? 'Armazenamento R2 operacional: gravar, confirmar, ler em publico e apagar funcionaram.'
    : 'Armazenamento R2 com problema. Veja a primeira etapa marcada como ok=false.';

json_out([
    'status'  => 'ok',
    'r2_ok'   => $diag['ok'],
    'resumo'  => $resumo,
    'configuracao' => $diag['configuracao'],
    'etapas'  => $diag['etapas'],
]);
