<?php
// login_simples.php - VERSÃO APENAS PARA TESTE
session_start();

$error = '';

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    
    echo "<pre>";
    echo "Email: " . $email . "\n";
    echo "Senha: " . $senha . "\n";
    
    // Verificar na tabela usuarios_sistema
    $query = "SELECT * FROM usuarios_sistema WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch();
    
    if ($usuario) {
        echo "Usuário encontrado na tabela usuarios_sistema!\n";
        echo "Hash da senha: " . $usuario['senha'] . "\n";
        $verifica = password_verify($senha, $usuario['senha']);
        echo "Senha válida: " . ($verifica ? 'SIM' : 'NÃO') . "\n";
        
        if ($verifica) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];
            
            echo "Login realizado com sucesso! Redirecionando...\n";
            header('Location: modules/admin/dashboard.php');
            exit;
        } else {
            $error = 'Senha inválida';
        }
    } else {
        echo "Usuário NÃO encontrado na tabela usuarios_sistema!\n";
        
        // Tentar na tabela funcionarios
        $query = "SELECT * FROM funcionarios WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->execute([':email' => $email]);
        $func = $stmt->fetch();
        
        if ($func) {
            echo "Usuário encontrado na tabela funcionarios!\n";
            $verifica = password_verify($senha, $func['senha']);
            echo "Senha válida: " . ($verifica ? 'SIM' : 'NÃO') . "\n";
            
            if ($verifica) {
                $_SESSION['usuario_id'] = $func['id'];
                $_SESSION['usuario_nome'] = $func['nome'];
                $_SESSION['usuario_email'] = $func['email'];
                $_SESSION['usuario_tipo'] = $func['tipo_usuario'];
                
                header('Location: index.php');
                exit;
            }
        }
        
        $error = 'Usuário não encontrado';
    }
    
    echo "</pre>";
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Simplificado - Teste</title>
    <style>
        body { font-family: Arial; padding: 20px; text-align: center; }
        .container { max-width: 400px; margin: 0 auto; background: #f5f5f5; padding: 20px; border-radius: 10px; }
        input { display: block; width: 100%; padding: 10px; margin: 10px 0; }
        button { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Login Simplificado (Teste)</h1>
        
        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        
        <form method="POST">
            <input type="email" name="email" placeholder="E-mail" required autofocus>
            <input type="password" name="senha" placeholder="Senha" required>
            <button type="submit">Entrar</button>
        </form>
        
        <hr>
        <p><strong>Teste com:</strong></p>
        <p>👑 superadmin@pontofacil.com / admin123 (Super Admin)</p>
        <p>👤 admin@pontofacil.com / 123456 (Administrador)</p>
        <p>👤 joao@pontofacil.com / 123456 (Funcionário)</p>
    </div>
</body>
</html>