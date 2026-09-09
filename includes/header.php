<?php
// includes/header.php — layout autenticado Bootstrap 5

if (!isset($skipAuth) || !$skipAuth) {
    require_once __DIR__ . '/auth.php';
    redirectIfNotLoggedIn();
}

$baseUrl = rtrim(defined('BASE_URL') ? BASE_URL : appBasePath(), '/');
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
$topPontosHoje = [];
$topTiposPonto = [
    'entrada' => ['label' => 'Entrada', 'icon' => 'fa-sign-in-alt'],
    'saida_almoco' => ['label' => 'Saída almoço', 'icon' => 'fa-utensils'],
    'volta_almoco' => ['label' => 'Volta almoço', 'icon' => 'fa-rotate-left'],
    'saida' => ['label' => 'Saída', 'icon' => 'fa-sign-out-alt'],
];

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

if ($topFuncionarioId > 0 && isset($db) && $db instanceof PDO) {
    try {
        $pontosStmt = $db->prepare(
            "SELECT tipo, DATE_FORMAT(data_hora, '%H:%i') AS hora
             FROM pontos
             WHERE funcionario_id = :funcionario_id AND DATE(data_hora) = CURDATE()
             ORDER BY data_hora ASC"
        );
        $pontosStmt->execute([':funcionario_id' => $topFuncionarioId]);
        $topPontosHoje = $pontosStmt->fetchAll();
    } catch (Throwable $e) {
        error_log('Não foi possível carregar os pontos de hoje: ' . $e->getMessage());
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

    <style>
        .pf-today-points { margin-left: auto; }
        .pf-today-points > .dropdown-toggle { border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); border-radius: 10px; padding: 8px 12px; font-size: 13px; }
        .pf-today-points > .dropdown-toggle:hover { border-color: var(--pf-primary); color: var(--pf-primary); }
        .pf-today-points .dropdown-menu { min-width: 250px; padding: 10px; border: 1px solid var(--border-color); background: var(--bg-primary); box-shadow: var(--shadow-lg, 0 12px 30px rgba(15,23,42,.15)); }
        .pf-point-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 9px 10px; border-radius: 8px; color: var(--text-primary); font-size: 13px; }
        .pf-point-row + .pf-point-row { margin-top: 2px; }
        .pf-point-row i { width: 18px; color: var(--pf-primary); }
        .pf-point-row time { font-weight: 800; color: var(--text-primary); }
        .pf-point-empty { padding: 12px 10px; color: var(--text-muted); font-size: 13px; }
        @media (max-width: 575.98px) { .pf-today-points { margin-left: auto; margin-right: 6px; } .pf-today-points .today-label { display: none; } .pf-today-points > .dropdown-toggle { padding: 8px 10px; } }
    </style>

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

                <div class="dropdown pf-today-points">
                    <button class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ver batidas de ponto de hoje">
                        <i class="fas fa-clock me-1"></i><span class="today-label">Pontos de hoje</span>
                        <?php if ($topPontosHoje): ?><span class="badge rounded-pill text-bg-primary ms-1"><?php echo count($topPontosHoje); ?></span><?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <div class="px-2 pb-2 border-bottom"><strong>Batidas de hoje</strong><small class="d-block text-body-secondary"><?php echo date('d/m/Y'); ?></small></div>
                        <?php if ($topFuncionarioId <= 0): ?>
                            <div class="pf-point-empty">Usuário sem vínculo de funcionário.</div>
                        <?php elseif (!$topPontosHoje): ?>
                            <div class="pf-point-empty"><i class="fas fa-info-circle me-1"></i>Nenhuma batida registrada hoje.</div>
                        <?php else: foreach ($topPontosHoje as $ponto): $pontoTipo = $topTiposPonto[$ponto['tipo']] ?? ['label' => ucfirst(str_replace('_', ' ', $ponto['tipo'])), 'icon' => 'fa-clock']; ?>
                            <div class="pf-point-row"><span><i class="fas <?php echo htmlspecialchars($pontoTipo['icon']); ?>"></i><?php echo htmlspecialchars($pontoTipo['label']); ?></span><time><?php echo htmlspecialchars($ponto['hora']); ?></time></div>
                        <?php endforeach; endif; ?>
                        <?php if ($topFuncionarioId > 0): ?><a class="dropdown-item text-center border-top mt-2 pt-2" href="<?php echo $baseUrl; ?>/modules/ponto/ponto.php">Abrir registro de ponto</a><?php endif; ?>
                    </div>
                </div>

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
                        <?php if (in_array($topUserType, ['super_admin', 'admin_empresa'], true)): ?>
                        <a class="dropdown-item" href="<?php echo $baseUrl; ?>/modules/funcionarios/cadastro_facial.php?id=<?php echo $topFuncionarioId; ?>">
                            <i class="fas fa-face-smile"></i> Minha biometria facial
                        </a>
                        <?php endif; ?>
                        <?php endif; ?>
                        <a class="dropdown-item" href="<?php echo $baseUrl; ?>/modules/perfil/alterar_senha.php">
                            <i class="fas fa-key"></i> Alterar minha senha
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="<?php echo $baseUrl; ?>/logout.php">
                            <i class="fas fa-right-from-bracket"></i> Sair
                        </a>
                    </div>
                </div>
            </header>

            <!-- AREA DE CONTEUDO -->
            <div class="pf-content">
