<?php
$host = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($host, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Acesso restrito');
}

// debug_funcao.php - Encontrar onde a função está duplicada
echo "<h1>Procurando função canEditFuncionario()</h1>";

// Listar todos os arquivos PHP recursivamente
$directory = 'C:/xampp/htdocs/ponto_empresarial/';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

$encontrados = [];

foreach ($files as $file) {
    if ($file->getExtension() === 'php') {
        $content = file_get_contents($file->getRealPath());
        if (strpos($content, 'function canEditFuncionario') !== false) {
            $encontrados[] = $file->getRealPath();
        }
    }
}

if (empty($encontrados)) {
    echo "<p style='color:green'>Nenhuma declaração da função encontrada!</p>";
} else {
    echo "<p style='color:red'>Função encontrada nos seguintes arquivos:</p>";
    echo "<ul>";
    foreach ($encontrados as $arquivo) {
        echo "<li>$arquivo</li>";
    }
    echo "</ul>";
}

echo "<hr>";
echo "<h3>Conteúdo do auth.php (linhas 140-160):</h3>";
$authFile = $directory . 'includes/auth.php';
if (file_exists($authFile)) {
    $lines = file($authFile);
    for ($i = 139; $i < 160 && $i < count($lines); $i++) {
        echo "Linha " . ($i + 1) . ": " . htmlspecialchars($lines[$i]) . "<br>";
    }
}
?>
