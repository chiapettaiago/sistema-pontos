<?php
// login.php - Login unificado do sistema web
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['usuario_tipo'] === 'super_admin') {
        header('Location: modules/admin/dashboard.php');
    } elseif ($_SESSION['usuario_tipo'] === 'admin_empresa' || $_SESSION['usuario_tipo'] === 'gestor') {
        header('Location: modules/dashboard_empresa/index.php');
    } else {
        header('Location: modules/ponto/ponto.php');
    }
    exit;
}

$error = '';

$database = new Database();
$db = $database->getConnection();

function finalizarLoginSistema(array $usuario): void {
    session_regenerate_id(true);

    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['usuario_tipo'] = $usuario['tipo'];
    $_SESSION['empresa_id'] = $usuario['empresa_id'];
    $_SESSION['empresa_nome'] = $usuario['empresa_nome'] ?? null;
    $_SESSION['funcionario_id'] = $usuario['funcionario_id'] ?? null;
    $_SESSION['tipo_login'] = 'sistema';

    // Compatibilidade com telas novas que usam outro padrao de sessao.
    $_SESSION['user_id'] = $usuario['id'];
    $_SESSION['user_nome'] = $usuario['nome'];
    $_SESSION['user_email'] = $usuario['email'];
    $_SESSION['user_tipo'] = $usuario['tipo'];
}

function finalizarLoginFuncionario(array $funcionario): void {
    session_regenerate_id(true);

    $_SESSION['usuario_id'] = $funcionario['id'];
    $_SESSION['usuario_nome'] = $funcionario['nome'];
    $_SESSION['usuario_email'] = $funcionario['email'];
    $_SESSION['usuario_tipo'] = 'funcionario';
    $_SESSION['empresa_id'] = $funcionario['empresa_id'];
    $_SESSION['empresa_nome'] = $funcionario['empresa_nome'] ?? null;
    $_SESSION['filial_id'] = $funcionario['filial_id'] ?? null;
    $_SESSION['usuario_filial_id'] = $funcionario['filial_id'] ?? null;
    $_SESSION['funcionario_id'] = $funcionario['id'];
    $_SESSION['tipo_login'] = 'funcionario';

    // Compatibilidade com telas novas que usam outro padrao de sessao.
    $_SESSION['funcionario_nome'] = $funcionario['nome'];
    $_SESSION['funcionario_email'] = $funcionario['email'];
    $_SESSION['funcionario_empresa_id'] = $funcionario['empresa_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken();

    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $error = 'Preencha todos os campos';
    } else {
        try {
            $query = "SELECT u.*, e.nome as empresa_nome
                      FROM usuarios_sistema u
                      LEFT JOIN empresas e ON u.empresa_id = e.id
                      WHERE u.email = :email AND u.status = 'ativo'";
            $stmt = $db->prepare($query);
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                finalizarLoginSistema($usuario);

                $update = $db->prepare("UPDATE usuarios_sistema SET ultimo_acesso = NOW() WHERE id = :id");
                $update->execute([':id' => $usuario['id']]);

                logAcao($db, 'LOGIN', 'usuarios_sistema', $usuario['id'], "Login realizado: {$usuario['email']}");

                if ($usuario['tipo'] === 'super_admin') {
                    header('Location: modules/admin/dashboard.php');
                } elseif ($usuario['tipo'] === 'admin_empresa' || $usuario['tipo'] === 'gestor') {
                    header('Location: modules/dashboard_empresa/index.php');
                } else {
                    header('Location: modules/ponto/ponto.php');
                }
                exit;
            }

            $query = "SELECT f.*,
                             e.nome_empresa as empresa_nome,
                             fi.nome_fantasia as filial_nome
                      FROM funcionarios f
                      LEFT JOIN empresa e ON f.empresa_id = e.id
                      LEFT JOIN filiais fi ON f.filial_id = fi.id
                      WHERE f.email = :email AND f.status = 'ativo'";
            $stmt = $db->prepare($query);
            $stmt->execute([':email' => $email]);
            $funcionario = $stmt->fetch();

            if ($funcionario && password_verify($senha, $funcionario['senha'])) {
                finalizarLoginFuncionario($funcionario);
                logAcao($db, 'LOGIN', 'funcionarios', $funcionario['id'], "Login funcionario: {$funcionario['email']}");

                header('Location: modules/ponto/ponto.php');
                exit;
            }

            $error = 'Email ou senha invalidos';
        } catch (Exception $e) {
            error_log($e->getMessage());
            $error = 'Erro ao fazer login. Tente novamente.';
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#667eea">
    <title>Login - Ponto Facil Empresarial</title>
    <link rel="icon" type="image/svg+xml" href="/ponto_empresarial/assets/favicon.svg">
    <link rel="shortcut icon" href="/ponto_empresarial/assets/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
        }

        .login-card {
            background: #fff;
            border-radius: 24px;
            padding: 34px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.28);
        }

        .logo {
            text-align: center;
            margin-bottom: 28px;
        }

        .logo-mark {
            width: 70px;
            height: 70px;
            margin: 0 auto 14px;
            border-radius: 20px;
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 32px;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .logo h1 {
            font-size: 28px;
            color: #1f2937;
        }

        .logo p {
            color: #6b7280;
            margin-top: 6px;
            font-size: 14px;
        }

        .error-message {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 600;
            font-size: 14px;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        input {
            width: 100%;
            min-height: 48px;
            padding: 13px 14px 13px 44px;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            font-size: 16px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.16);
        }

        .btn-login {
            width: 100%;
            min-height: 50px;
            border: 0;
            border-radius: 14px;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            background: linear-gradient(135deg, #667eea, #764ba2);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(102, 126, 234, 0.35);
        }

        .footer {
            text-align: center;
            color: #6b7280;
            font-size: 12px;
            margin-top: 22px;
        }

        @media (max-width: 480px) {
            body { padding: 14px; align-items: stretch; }
            .login-container { display: flex; align-items: center; }
            .login-card { padding: 26px 20px; border-radius: 20px; }
            .logo h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <div class="logo-mark"><i class="fas fa-clock"></i></div>
                <h1>Ponto Facil</h1>
                <p>Sistema empresarial de ponto</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label for="email">E-mail</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" required placeholder="seu@email.com" autocomplete="email" autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="senha">Senha</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="senha" name="senha" required placeholder="Digite sua senha" autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </button>
            </form>

            <div class="footer">
                &copy; <?php echo date('Y'); ?> Ponto Facil
            </div>
        </div>
    </div>
</body>
</html>
