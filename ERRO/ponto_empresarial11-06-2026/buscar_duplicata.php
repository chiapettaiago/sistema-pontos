<?php
// buscar_duplicata.php - Encontrar onde a função está duplicada
$directory = __DIR__;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

echo "<h1>Buscando 'function canEditFuncionario'</h1>";
echo "<ul>";

foreach ($files as $file) {
    if ($file->getExtension() === 'php') {
        $content = file_get_contents($file->getRealPath());
        if (preg_match('/function\s+canEditFuncionario\s*\(/', $content)) {
            $relativePath = str_replace($directory . '\\', '', $file->getRealPath());
            echo "<li><strong>$relativePath</strong> - Linha: " . encontrarLinha($content) . "</li>";
        }
    }
}

echo "</ul>";

function encontrarLinha($content) {
    $lines = explode("\n", $content);
    foreach ($lines as $i => $line) {
        if (preg_match('/function\s+canEditFuncionario\s*\(/', $line)) {
            return $i + 1;
        }
    }
    return 'não identificado';
}

echo "<hr>";
echo "<p>Se aparecer mais de um arquivo, remova a função do arquivo que não é o <strong>includes/auth.php</strong></p>";