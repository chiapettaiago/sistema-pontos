<?php
// login.php - Sistema de Login Unificado (CORRIGIDO)
session_start();

// Se já estiver logado, redireciona
if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['usuario_tipo'] === 'super_admin') {
        header('Location: modules/admin/dashboard.php');
    } elseif ($_SESSION['usuario_tipo'] === 'funcionario') {
        header('Location: modules/ponto/ponto.php');  // <-- REDIRECIONA PARA O PONTO
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';

require_once 'config/database.php';
require_once 'includes/auth.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    
    if (empty($email) || empty($senha)) {
        $error = 'Preencha todos os campos';
    } else {
        try {
            // ============================================
            // PRIMEIRO: Tentar como USUÁRIO DO SISTEMA
            // ============================================
            $query = "SELECT u.*, e.nome as empresa_nome 
                      FROM usuarios_sistema u
                      LEFT JOIN empresas e ON u.empresa_id = e.id
                      WHERE u.email = :email AND u.status = 'ativo'";
            $stmt = $db->prepare($query);
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch();
            
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // Login como usuário do sistema
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['usuario_tipo'] = $usuario['tipo'];
                $_SESSION['empresa_id'] = $usuario['empresa_id'];
                $_SESSION['empresa_nome'] = $usuario['empresa_nome'];
                $_SESSION['funcionario_id'] = $usuario['funcionario_id'];
                $_SESSION['tipo_login'] = 'sistema';
                
                // Registrar último acesso
                $update = $db->prepare("UPDATE usuarios_sistema SET ultimo_acesso = NOW() WHERE id = :id");
                $update->execute([':id' => $usuario['id']]);
                
                // Log de login
                logAcao($db, 'LOGIN', 'usuarios_sistema', $usuario['id'], "Login realizado: {$usuario['email']}");
                
                // Redirecionar baseado no tipo
                if ($usuario['tipo'] === 'super_admin') {
                    header('Location: modules/admin/dashboard.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            }
            
            // ============================================
            // SEGUNDO: Tentar como FUNCIONÁRIO
            // ============================================
            $query2 = "SELECT f.*, 
                       e.nome_empresa as empresa_nome,
                       fi.nome_fantasia as filial_nome
                       FROM funcionarios f
                       LEFT JOIN empresa e ON f.empresa_id = e.id
                       LEFT JOIN filiais fi ON f.filial_id = fi.id
                       WHERE f.email = :email AND f.status = 'ativo'";
            $stmt2 = $db->prepare($query2);
            $stmt2->execute([':email' => $email]);
            $funcionario = $stmt2->fetch();
            
            if ($funcionario && password_verify($senha, $funcionario['senha'])) {
                // Login como funcionário
                $_SESSION['usuario_id'] = $funcionario['id'];
                $_SESSION['usuario_nome'] = $funcionario['nome'];
                $_SESSION['usuario_email'] = $funcionario['email'];
                $_SESSION['usuario_tipo'] = 'funcionario';
                $_SESSION['empresa_id'] = $funcionario['empresa_id'];
                $_SESSION['empresa_nome'] = $funcionario['empresa_nome'];
                $_SESSION['filial_id'] = $funcionario['filial_id'];
                $_SESSION['funcionario_id'] = $funcionario['id'];
                $_SESSION['tipo_login'] = 'funcionario';
                
                // Log de login
                logAcao($db, 'LOGIN', 'funcionarios', $funcionario['id'], "Login funcionário: {$funcionario['email']}");
                
                // REDIRECIONA PARA A TELA DE PONTO
                header('Location: modules/ponto/ponto.php');
                exit;
            }
            
            // Se chegou aqui, não encontrou em nenhuma tabela
            $error = 'Email ou senha inválidos';
            
        } catch (Exception $e) {
            $error = 'Erro ao fazer login: ' . $e->getMessage();
            error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Ponto Fácil Empresarial</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
        }
        
        .login-card {
            background: white;
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .logo-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        
        .logo h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 8px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .input-group input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e5e5e5;
            border-radius: 16px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        
        .error-message i {
            margin-right: 8px;
        }
        
        .test-users {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e5e5;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        
        .test-users p {
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .test-users small {
            display: block;
            padding: 4px 0;
        }
        
        .footer {
            text-align: center;
            margin-top: 24px;
            font-size: 11px;
            color: #999;
        }
        
        @media (max-width: 480px) {
            .login-card {
                padding: 24px;
            }
            
            .logo-icon {
                font-size: 48px;
            }
            
            .logo h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <div class="logo-icon">⏰</div>
                <h1>Ponto Fácil</h1>
                <p>Sistema Empresarial de Ponto</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>E-mail</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" required placeholder="seu@email.com" autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Senha</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="senha" required placeholder="••••••">
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </button>
            </form>
            
            <div class="test-users">
                <p><i class="fas fa-info-circle"></i> Credenciais de acesso:</p>
                <small><i class="fas fa-user-shield"></i> <strong>Super Admin:</strong> superadmin@pontofacil.com / admin123</small>
                <small><i class="fas fa-building"></i> <strong>Admin Empresa:</strong> admin@pontofacil.com / 123456</small>
                <small><i class="fas fa-chart-line"></i> <strong>Gestor:</strong> carla@pontofacil.com / 123456</small>
                <small><i class="fas fa-users"></i> <strong>Supervisor:</strong> joao@pontofacil.com / 123456</small>
                <small><i class="fas fa-user-check"></i> <strong>Funcionário:</strong> email cadastrado / senha definida</small>
            </div>
            
            <div class="footer">
                <p>&copy; <?php echo date('Y'); ?> Ponto Fácil - Todos os direitos reservados</p>
                <p>Versão 2.0.0</p>
            </div>
        </div>
    </div>
</body>
</html>