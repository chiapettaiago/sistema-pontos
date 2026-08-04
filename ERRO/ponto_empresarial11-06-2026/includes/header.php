<?php
// includes/header.php - layout autenticado padrao do sistema

if (!isset($skipAuth) || !$skipAuth) {
    require_once __DIR__ . '/auth.php';
    redirectIfNotLoggedIn();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#667eea">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Ponto Facil Empresarial'); ?></title>

    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
    <link rel="shortcut icon" href="/assets/favicon.svg">
    <link rel="apple-touch-icon" href="/assets/favicon.svg">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/dark-theme.css">

    <style>
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-primary);
            margin-right: 16px;
            width: 44px;
            height: 44px;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }

        .menu-toggle:hover {
            background: var(--bg-secondary);
        }

        .theme-toggle-btn {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 40px;
            padding: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .theme-option {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .theme-option.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.48);
            z-index: 90;
        }

        .sidebar-backdrop.open {
            display: block;
        }

        @media (max-width: 1024px) {
            .menu-toggle {
                display: inline-flex;
            }

            .top-bar {
                min-height: 64px;
            }

            .datetime {
                display: none;
            }

            .top-bar {
                padding: 12px 16px;
                gap: 10px;
            }

            .top-bar-actions {
                gap: 10px;
            }
        }

        @media (max-width: 768px) {
            .page-title h2 {
                font-size: 20px;
            }

            .theme-toggle-btn {
                padding: 4px;
            }

            .theme-option {
                width: 32px;
                height: 32px;
            }

            .sidebar-backdrop.open {
                backdrop-filter: blur(2px);
            }
        }
    </style>
</head>
<body class="light-theme">
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="app-container">
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <button class="menu-toggle" id="menuToggle" type="button" aria-label="Abrir menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></h2>
                </div>
                <div class="top-bar-actions">
                    <div class="datetime">
                        <span id="currentDate"></span>
                        <span id="currentTime"></span>
                    </div>
                    <div class="theme-toggle-btn" id="themeToggleBtn" title="Alternar tema">
                        <div class="theme-option light-option" data-theme="light">
                            <i class="fas fa-sun"></i>
                        </div>
                        <div class="theme-option dark-option" data-theme="dark">
                            <i class="fas fa-moon"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-wrapper">
                <script>
                    function atualizarRelogio() {
                        const agora = new Date();
                        const dateElement = document.getElementById('currentDate');
                        const timeElement = document.getElementById('currentTime');

                        if (dateElement) {
                            dateElement.textContent = agora.toLocaleDateString('pt-BR', {
                                weekday: 'long',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            });
                        }

                        if (timeElement) {
                            timeElement.textContent = agora.toLocaleTimeString('pt-BR');
                        }
                    }

                    setInterval(atualizarRelogio, 1000);
                    atualizarRelogio();

                    const menuToggle = document.getElementById('menuToggle');
                    const sidebar = document.querySelector('.sidebar');
                    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

                    function closeSidebar() {
                        if (sidebar) sidebar.classList.remove('open');
                        if (sidebarBackdrop) sidebarBackdrop.classList.remove('open');
                    }

                    if (menuToggle && sidebar) {
                        menuToggle.addEventListener('click', function(event) {
                            event.stopPropagation();
                            sidebar.classList.toggle('open');
                            if (sidebarBackdrop) sidebarBackdrop.classList.toggle('open');
                        });
                    }

                    if (sidebarBackdrop) {
                        sidebarBackdrop.addEventListener('click', closeSidebar);
                    }

                    document.addEventListener('keydown', function(event) {
                        if (event.key === 'Escape') closeSidebar();
                    });

                    const lightOption = document.querySelector('.light-option');
                    const darkOption = document.querySelector('.dark-option');

                    function setTheme(theme) {
                        document.body.classList.remove('light-theme', 'dark-theme');
                        document.body.classList.add(theme + '-theme');
                        localStorage.setItem('theme', theme);

                        if (lightOption && darkOption) {
                            lightOption.classList.toggle('active', theme === 'light');
                            darkOption.classList.toggle('active', theme === 'dark');
                        }
                    }

                    setTheme(localStorage.getItem('theme') || 'light');

                    if (lightOption) lightOption.addEventListener('click', () => setTheme('light'));
                    if (darkOption) darkOption.addEventListener('click', () => setTheme('dark'));
                </script>
