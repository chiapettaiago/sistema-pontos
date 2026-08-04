<?php
$host = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($host, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Acesso restrito');
}

// teste_superadmin.php - Diagnóstico do Super Admin
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<h1>Diagnóstico do Super Admin</h1>";

$database = new Database();
$db = $database->getConnection();

// 1. Verificar se a tabela usuarios_sistema existe
echo "<h2>1. Verificando tabela usuarios_sistema:</h2>";
$tables = $db->query("SHOW TABLES LIKE 'usuarios_sistema'");
if ($tables->rowCount() > 0) {
    echo "✅ Tabela 'usuarios_sistema' existe<br>";
} else {
    echo "❌ Tabela 'usuarios_sistema' NÃO existe!<br>";
    echo "<a href='criar_tabelas.php'>Clique aqui para criar as tabelas</a><br>";
}

// 2. Listar usuários da tabela usuarios_sistema
echo "<h2>2. Usuários na tabela usuarios_sistema:</h2>";
$query = "SELECT id, nome, email, senha, tipo FROM usuarios_sistema";
$stmt = $db->query($query);
$usuarios = $stmt->fetchAll();

if (empty($usuarios)) {
    echo "❌ Nenhum usuário encontrado na tabela usuarios_sistema!<br>";
} else {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Tipo</th><th>Hash da Senha</th><th>Teste 'admin123'</th></tr>";
    foreach ($usuarios as $u) {
        $teste = password_verify('admin123', $u['senha']);
        $cor = $teste ? '#90EE90' : '#FFCCCC';
        echo "<tr style='background: $cor'>";
        echo "<td>{$u['id']}</td>";
        echo "<td>{$u['nome']}</td>";
        echo "<td>{$u['email']}</td>";
        echo "<td>{$u['tipo']}</td>";
        echo "<td style='font-size:11px'>{$u['senha']}</td>";
        echo "<td>" . ($teste ? "✅ OK" : "❌ Inválida") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Verificar funcionários
echo "<h2>3. Funcionários na tabela funcionarios:</h2>";
$query = "SELECT id, nome, email, senha, tipo_usuario FROM funcionarios LIMIT 5";
$stmt = $db->query($query);
$funcionarios = $stmt->fetchAll();

if (empty($funcionarios)) {
    echo "❌ Nenhum funcionário encontrado!<br>";
} else {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Tipo</th><th>Teste '123456'</th></tr>";
    foreach ($funcionarios as $f) {
        $teste = password_verify('123456', $f['senha']);
        $cor = $teste ? '#90EE90' : '#FFCCCC';
        echo "<tr style='background: $cor'>";
        echo "<td>{$f['id']}</td>";
        echo "<td>{$f['nome']}</td>";
        echo "<td>{$f['email']}</td>";
        echo "<td>{$f['tipo_usuario']}</td>";
        echo "<td>" . ($teste ? "✅ OK" : "❌ Inválida") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 4. Botão para corrigir
echo "<h2>4. Ações:</h2>";
echo "<form method='post'>";
echo "<button type='submit' name='fix' value='superadmin'>Criar/Corrigir Super Admin</button>";
echo "</form>";

if (isset($_POST['fix']) && $_POST['fix'] == 'superadmin') {
    // Remover usuário existente
    $db->exec("DELETE FROM usuarios_sistema WHERE email = 'superadmin@pontofacil.com'");
    
    // Criar novo Super Admin
    $senha_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $query = "INSERT INTO usuarios_sistema (nome, email, senha, tipo, status) 
              VALUES ('Super Administrador', 'superadmin@pontofacil.com', :senha, 'super_admin', 'ativo')";
    $stmt = $db->prepare($query);
    $stmt->execute([':senha' => $senha_hash]);
    
    echo "<p style='color: green'>✅ Super Admin recriado com sucesso!</p>";
    echo "<meta http-equiv='refresh' content='2'>";
}

// 5. Link para login
echo "<h2>5. Acessar:</h2>";
echo "<a href='login.php'>Ir para o Login</a>";
?>
