<?php
// diagnostic_facial.php
require_once 'includes/config.php';

echo "<h1>Diagnóstico do Cadastro Facial</h1>";

// Verificar colunas
$stmt = $pdo->query("SHOW COLUMNS FROM funcionarios");
$colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "<h3>Colunas existentes:</h3>";
print_r($colunas);

// Verificar se as colunas necessárias existem
$necessarias = ['descritor_facial', 'imagem_facial', 'facial_cadastrado'];
foreach ($necessarias as $col) {
    if (in_array($col, $colunas)) {
        echo "✅ $col - OK<br>";
    } else {
        echo "❌ $col - FALTANDO! Execute o SQL de correção.<br>";
    }
}

// Verificar pasta
$pasta = __DIR__ . '/uploads/facial/';
echo "<h3>Pasta de uploads:</h3>";
if (is_dir($pasta)) {
    echo "✅ Pasta existe: $pasta<br>";
    echo "Permissão: " . substr(sprintf('%o', fileperms($pasta)), -4) . "<br>";
} else {
    echo "❌ Pasta não existe! Criando...<br>";
    mkdir($pasta, 0755, true);
    echo "✅ Pasta criada!<br>";
}
?>