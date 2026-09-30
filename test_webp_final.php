<?php
// Testa as 3 extensões de WebP
header('Content-Type: text/plain; charset=utf-8');

echo "=== TESTE DE EXTENSÕES WEBP ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n";
echo "PHP: " . phpversion() . "\n";
echo "=".str_repeat("=", 48)."\n\n";

// Teste 1
echo "1️⃣  php -r \"var_dump(function_exists('imagewebp'));\"\n";
$test1 = function_exists('imagewebp');
var_dump($test1);
echo "\n";

// Teste 2
echo "2️⃣  php -r \"var_dump(extension_loaded('imagick'));\"\n";
$test2 = extension_loaded('imagick');
var_dump($test2);
echo "\n";

// Teste 3
echo "3️⃣  php -r '\$i = new Imagick(); var_dump(in_array(\"WEBP\", \$i->queryFormats()));'\n";
$test3 = false;
if ($test2) {
    try {
        $i = new Imagick();
        $test3 = in_array("WEBP", $i->queryFormats());
        var_dump($test3);
    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage() . "\n";
        var_dump(false);
    }
} else {
    echo "Imagick não está carregado\n";
    var_dump(false);
}
echo "\n";

// Resultado
echo "=".str_repeat("=", 48)."\n";
echo "RESULTADO ESPERADO: bool(true) bool(true) bool(true)\n";
echo "=".str_repeat("=", 48)."\n\n";

if ($test1 && $test2 && $test3) {
    echo "✅ ✅ ✅ SUCESSO!\n";
    echo "\nTodos os 3 testes retornaram TRUE:\n";
    echo "  ✅ GD com imagewebp()\n";
    echo "  ✅ Imagick carregado\n";
    echo "  ✅ Imagick com suporte a WebP\n";
    echo "\n🚀 Sistema está pronto para gerar miniaturas WebP com SUPER VELOCIDADE!\n";
    echo "\nFluxo:\n";
    echo "  Original no R2\n";
    echo "    → worker processa\n";
    echo "    → thumbnail WebP 360px / qualidade 68\n";
    echo "    → telas usam somente a thumbnail\n";
    echo "    → download usa o original\n";
} else {
    echo "❌ FALTAM DEPENDÊNCIAS:\n\n";
    echo "  " . ($test1 ? "✅" : "❌") . " GD com imagewebp()\n";
    echo "  " . ($test2 ? "✅" : "❌") . " Imagick carregado\n";
    echo "  " . ($test3 ? "✅" : "❌") . " Imagick com suporte a WebP\n";
    echo "\n⚠️  Precisa fazer deploy com o Dockerfile atualizado.\n";
}
?>

