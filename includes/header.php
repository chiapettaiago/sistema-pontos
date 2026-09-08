<?php
// includes/header.php — layout autenticado Bootstrap 5

if (!isset($skipAuth) || !$skipAuth) {
    require_once __DIR__ . '/auth.php';
    redirectIfNotLoggedIn();
}

$_base_dir = str_replace('\\', '/', dirname(__DIR__));
$_doc_root  = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/\\'));
$baseUrl    = rtrim(str_replace($_doc_root, '', $_base_dir), '/');
$topUserName = $_SESSION['usuario_nome'] ?? 'Usuário';
$topUserType = $_SESSION['usuario_tipo'] ?? 'funcionario';
$topRoles = [
    'super_admin' => 'Super Admin',
    'admin_empresa' => 'Administrador',
    'gestor' => 'Gestor',
    'supervisor' => 'Supervisor',
    'funcionario' => 'Funcionário',
];
$topUserRole = $topRoles[$topUserType] ?? ucfirst($topUserType);
$topFuncionarioId = (int) ($_SESSION['funcionario_id'] ?? 0);
$topUserPhotoUrl = null;

if ($topFuncionarioId > 0 && isset($db) && $db instanceof PDO) {
    try {
        $photoStmt = $db->prepare('SELECT foto FROM funcionarios WHERE id = :id LIMIT 1');
        $photoStmt->execute([':id' => $topFuncionarioId]);
        $photoPath = ltrim((string) $photoStmt->fetchColumn(), '/');
        $photoRoot = realpath(__DIR__ . '/../uploads/funcionarios');
        $photoFile = $photoPath !== '' ? realpath(__DIR__ . '/../' . $photoPath) : false;

        if ($photoRoot && $photoFile && str_starts_with($photoFile, $photoRoot . DIRECTORY_SEPARATOR)) {
            $encodedPhotoPath = implode('/', array_map('rawurlencode', explode('/', $photoPath)));
            $topUserPhotoUrl = $baseUrl . '/' . $encodedPhotoPath;
        }
    } catch (Throwable $e) {
        error_log('Não foi possível carregar a foto da navbar: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Ponto Facil Empresarial'); ?></title>

    <link rel="icon" type="image/svg+xml" href="<?php echo $baseUrl; ?>/assets/favicon.svg">
    <link rel="shortcut icon" href="<?php echo $baseUrl; ?>/assets/favicon.svg">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Custom CSS (paleta + sobrescritas) -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/responsive.css">

    <!-- Aplica tema antes de renderizar (evita flash) -->
    <script>
        (function(){
            var t = localStorage.getItem('pf_theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
</head>
<body>

    <!-- Backdrop mobile sidebar -->
    <div class="pf-sidebar-backdrop" id="pfSidebarBackdrop"></div>

    <div class="pf-app-wrapper">
        <!-- SIDEBAR -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <!-- CONTEUDO PRINCIPAL -->
        <div class="pf-main-content">

            <!-- TOP BAR -->
            <header class="pf-topbar">
                <button class="pf-menu-toggle" id="pfMenuToggle" aria-label="Abrir menu">
                    <i class="fas fa-bars"></i>
                </button>

                <span class="pf-topbar-title"><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></span>

                <!-- Data/Hora -->
                <div class="pf-datetime d-none d-md-flex">
                    <i class="far fa-calendar-alt pf-datetime-icon" aria-hidden="true"></i>
                    <span id="pfDate" class="pf-datetime-date"></span>
                    <span id="pfTime" class="pf-datetime-time" aria-label="Horário atual"></span>
                </div>

                <!-- Theme toggle -->
                <div class="pf-theme-toggle" id="pfThemeToggle" title="Alternar tema">
                    <div class="pf-theme-opt" data-theme="light" title="Modo claro">
                        <i class="fas fa-sun"></i>
                    </div>
                    <div class="pf-theme-opt" data-theme="dark" title="Modo escuro">
                        <i class="fas fa-moon"></i>
                    </div>
                </div>

                <div class="dropdown pf-topbar-user">
                    <button class="pf-topbar-user-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Abrir menu do usuário">
                        <span class="pf-topbar-avatar">
                            <?php if ($topUserPhotoUrl): ?>
                            <img src="<?php echo htmlspecialchars($topUserPhotoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto de <?php echo htmlspecialchars($topUserName, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else: ?>
                            <i class="fas fa-user" aria-hidden="true"></i>
                            <?php endif; ?>
                        </span>
                        <span class="pf-topbar-user-copy d-none d-lg-flex">
                            <strong><?php echo htmlspecialchars($topUserName); ?></strong>
                            <small><?php echo htmlspecialchars($topUserRole); ?></small>
                        </span>
                        <i class="fas fa-chevron-down pf-topbar-chevron d-none d-lg-inline"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end pf-user-dropdown">
                        <div class="pf-user-dropdown-head">
                            <strong><?php echo htmlspecialchars($topUserName); ?></strong>
                            <span><?php echo htmlspecialchars($topUserRole); ?></span>
                        </div>
                        <?php if ($topFuncionarioId > 0): ?>
                        <a class="dropdown-item" href="<?php echo $baseUrl; ?>/modules/funcionarios/visualizar?id=<?php echo $topFuncionarioId; ?>">
                            <i class="fas fa-id-card"></i> Meu cadastro
                        </a>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="<?php echo $baseUrl; ?>/logout.php">
                            <i class="fas fa-right-from-bracket"></i> Sair
                        </a>
                    </div>
                </div>
            </header>

            <!-- AREA DE CONTEUDO -->
            <div class="pf-content">
