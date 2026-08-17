<?php
// login.php - Login unificado do sistema web
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['usuario_tipo'] === 'super_admin') {
        header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
    } elseif ($_SESSION['usuario_tipo'] === 'admin_empresa' || $_SESSION['usuario_tipo'] === 'gestor') {
        header('Location: ' . BASE_URL . '/modules/dashboard_empresa/index.php');
    } else {
        header('Location: ' . BASE_URL . '/modules/ponto/ponto.php');
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

function senhaConfere(string $senhaDigitada, string $senhaSalva): bool {
    if (password_verify($senhaDigitada, $senhaSalva)) {
        return true;
    }

    $senhaSalvaLimpa = trim($senhaSalva);

    if (strlen($senhaSalvaLimpa) === 32 && ctype_xdigit($senhaSalvaLimpa)) {
        return hash_equals(strtolower($senhaSalvaLimpa), md5($senhaDigitada));
    }

    return hash_equals($senhaSalvaLimpa, $senhaDigitada);
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

            if ($usuario && senhaConfere($senha, $usuario['senha'])) {
                finalizarLoginSistema($usuario);

                $update = $db->prepare("UPDATE usuarios_sistema SET ultimo_acesso = NOW() WHERE id = :id");
                $update->execute([':id' => $usuario['id']]);

                logAcao($db, 'LOGIN', 'usuarios_sistema', $usuario['id'], "Login realizado: {$usuario['email']}");

                if ($usuario['tipo'] === 'super_admin') {
                    header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
                } elseif ($usuario['tipo'] === 'admin_empresa' || $usuario['tipo'] === 'gestor') {
                    header('Location: ' . BASE_URL . '/modules/dashboard_empresa/index.php');
                } else {
                    header('Location: ' . BASE_URL . '/modules/ponto/ponto.php');
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

            if ($funcionario && senhaConfere($senha, $funcionario['senha'])) {
                finalizarLoginFuncionario($funcionario);
                logAcao($db, 'LOGIN', 'funcionarios', $funcionario['id'], "Login funcionario: {$funcionario['email']}");

                header('Location: ' . BASE_URL . '/modules/ponto/ponto.php');
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Ponto Facil Empresarial</title>

    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">

    <!-- Aplica tema antes de renderizar (evita flash) -->
    <script>
        (function(){
            var t = localStorage.getItem('pf_theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>

    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            font-family: var(--bs-font-sans-serif);
            transition: background .3s ease, color .3s ease;
            position: relative;
        }
        [data-bs-theme="dark"] body {
            background: var(--pf-gradient-dark);
        }
        .pf-login-theme-pos {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 100;
        }
        .pf-login-card {
            background: var(--bg-primary);
            border-radius: 1.75rem;
            padding: 2.25rem;
            box-shadow: 0 24px 70px rgba(15,23,42,.15);
            border: 1px solid var(--border-color);
            width: 100%;
            max-width: 460px;
            color: var(--text-primary);
        }
        [data-bs-theme="dark"] .pf-login-card {
            box-shadow: 0 24px 70px rgba(0,0,0,.5);
        }
        .pf-login-logo-mark {
            width: 70px; height: 70px;
            margin: 0 auto 1rem;
            border-radius: 1.5rem;
            display: grid;
            place-items: center;
            font-size: 2rem;
            background: var(--pf-gradient);
            color: #fff;
            box-shadow: 0 8px 20px rgba(102,126,234,.35);
        }
        .pf-login-card .form-control {
            padding-left: 2.75rem;
        }
        .pf-login-card .input-icon {
            position: absolute;
            left: .875rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }
        .pf-login-card .input-group { position: relative; }
        .btn-login {
            width: 100%;
            padding: .875rem;
            border-radius: 1rem;
            font-size: 1rem;
            font-weight: 700;
            border: none;
            background: var(--pf-gradient);
            color: #fff;
            transition: transform .2s, box-shadow .2s;
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(102,126,234,.40);
            color: #fff;
        }
        @media (max-width: 480px) {
            body { align-items: stretch; padding: 1rem; }
            .pf-login-card { border-radius: 1.25rem; padding: 1.5rem; }
        }
    </style>
</head>
<body>

<!-- Theme toggle flutuante no login -->
<div class="pf-login-theme-pos">
    <div class="pf-theme-toggle" id="pfThemeToggle" title="Alternar tema">
        <div class="pf-theme-opt" data-theme="light" title="Modo claro">
            <i class="fas fa-sun"></i>
        </div>
        <div class="pf-theme-opt" data-theme="dark" title="Modo escuro">
            <i class="fas fa-moon"></i>
        </div>
    </div>
</div>

<div class="pf-login-card mx-auto">

    <!-- Logo -->
    <div class="text-center mb-4">
        <div class="pf-login-logo-mark">
            <i class="fas fa-clock"></i>
        </div>
        <h1 class="h3 fw-bold mb-1">Ponto Fácil</h1>
        <p class="text-muted small">Sistema empresarial de ponto</p>
    </div>

    <!-- Erro -->
    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
    </div>
    <?php endif; ?>

    <!-- Info -->
    <div class="alert alert-info d-flex align-items-start gap-2 py-2 small mb-3" role="alert">
        <i class="fas fa-info-circle mt-1 flex-shrink-0"></i>
        <span><strong>Entrada unificada:</strong> use e-mail e senha ou reconhecimento facial. No celular, prefira HTTPS para liberar a câmera.</span>
    </div>

    <!-- Formulário -->
    <form method="POST" action="" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <div class="input-group">
                <i class="fas fa-envelope input-icon"></i>
                <input type="email" id="email" name="email" class="form-control"
                       placeholder="seu@email.com" required autocomplete="email" autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label for="senha" class="form-label">Senha</label>
            <div class="input-group">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" id="senha" name="senha" class="form-control"
                       placeholder="Digite sua senha" required autocomplete="current-password">
            </div>
        </div>

        <button type="submit" class="btn-login mb-3">
            <i class="fas fa-sign-in-alt me-2"></i>Entrar
        </button>
    </form>

    <!-- Login facial -->
    <div class="text-center mb-3">
        <a href="modules/funcionarios/login_facial.php" class="text-primary fw-semibold text-decoration-none small">
            <i class="fas fa-camera me-1"></i>Entrar com reconhecimento facial
        </a>
    </div>

    <p class="text-center text-muted small mb-0">&copy; <?php echo date('Y'); ?> Ponto Fácil</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/theme.js"></script>
</body>
</html>

