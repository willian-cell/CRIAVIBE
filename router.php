<?php
/**
 * Roteador do servidor embutido do PHP (`php -S ... router.php`).
 *
 * O comportamento padrao do servidor embutido e entregar qualquer arquivo que
 * exista no diretorio, e a imagem do container carrega o repositorio inteiro:
 * documentacao, Dockerfile, Procfile e scripts ficavam acessiveis publicamente
 * so por existirem. Aqui a entrega e restrita ao que o site realmente serve.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/**
 * Caminhos que nunca devem ser entregues, mesmo existindo no disco.
 */
function caminho_bloqueado(string $path): bool {
    $segmentos = array_values(array_filter(explode('/', $path), 'strlen'));
    if (!$segmentos) return false;

    // Travessia de diretorio e arquivos ocultos (.git, .env, .gitignore...).
    foreach ($segmentos as $segmento) {
        if ($segmento === '..' || $segmento[0] === '.') return true;
    }

    // Diretorios que existem no repositorio mas nao fazem parte do site.
    $pastasInternas = ['documentacao', 'DOCUMENTATION', 'scripts', 'agente-willianbo'];
    if (in_array($segmentos[0], $pastasInternas, true)) return true;

    $arquivo = end($segmentos);

    // Arquivos de configuracao e operacao, identificados pelo nome exato.
    $nomesInternos = [
        'Dockerfile', 'Procfile', 'docker-compose.yml', 'router.php',
        'env_example.txt', 'CREDENCIAIS.md',
    ];
    if (in_array($arquivo, $nomesInternos, true)) return true;

    // Extensoes que so aparecem em documentacao, configuracao ou scripts.
    $extensoesInternas = [
        'md', 'yml', 'yaml', 'sql', 'sh', 'ps1', 'log', 'lock',
        'ini', 'conf', 'service', 'bak', 'sample', 'dist',
    ];
    $ext = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));

    return $ext !== '' && in_array($ext, $extensoesInternas, true);
}

if ($path === '/') {
    require __DIR__ . '/index.html';
    return true;
}

if (caminho_bloqueado($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo '404 - Arquivo nao encontrado';
    return true;
}

if (is_file(__DIR__ . $path)) {
    return false;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo '404 - Arquivo nao encontrado';
