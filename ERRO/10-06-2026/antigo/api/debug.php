<?php
$host = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($host, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Acesso restrito');
}

// api/debug.php - Diagnóstico da API
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnóstico da API</h1>";

// Verificar extensões
echo "<h2>Extensões PHP:</h2>";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? '✅' : '❌') . "<br>";
echo "JSON: " . (extension_loaded('json') ? '✅' : '❌') . "<br>";
echo "OpenSSL: " . (extension_loaded('openssl') ? '✅' : '❌') . "<br>";

// Verificar conexão com banco
echo "<h2>Conexão com Banco:</h2>";
try {
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "✅ Conexão OK<br>";
    
    // Verificar tabela tokens
    $tables = $db->query("SHOW TABLES LIKE 'tokens'");
    if ($tables->rowCount() > 0) {
        echo "✅ Tabela 'tokens' existe<br>";
    } else {
        echo "❌ Tabela 'tokens' não existe - execute o SQL para criá-la<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}

// Testar auth.php
echo "<h2>Teste de auth.php:</h2>";
echo '<form method="POST" action="auth.php">';
echo '<input type="email" name="email" value="joao@pontofacil.com" placeholder="Email">';
echo '<input type="password" name="senha" value="123456" placeholder="Senha">';
echo '<button type="submit">Testar</button>';
echo '</form>';
?>
