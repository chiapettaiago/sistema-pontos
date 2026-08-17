<?php
// includes/header.php — layout autenticado Bootstrap 5

if (!isset($skipAuth) || !$skipAuth) {
    require_once __DIR__ . '/auth.php';
    redirectIfNotLoggedIn();
}

$_base_dir = str_replace('\\', '/', dirname(__DIR__));
$_doc_root  = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/\\'));
$baseUrl    = rtrim(str_replace($_doc_root, '', $_base_dir), '/');
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Custom CSS (paleta + sobrescritas) -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/style.css">

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
                    <span id="pfDate"></span>
                    <span id="pfTime" class="fw-semibold"></span>
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
            </header>

            <!-- AREA DE CONTEUDO -->
            <div class="pf-content">
