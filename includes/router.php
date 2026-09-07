<?php
/**
 * Rotas e destinos de navegacao do Ponto Facil.
 * Mantem a regra de acesso em um unico lugar e nunca redireciona para URLs externas.
 */

function appNormalizeUserType(string $tipo): string
{
    // O esquema legado de funcionarios usa "admin"; as regras atuais usam
    // "admin_empresa" para delimitar a administracao da propria empresa.
    return $tipo === 'admin' ? 'admin_empresa' : $tipo;
}

function appHomeRouteFor(string $tipo): string
{
    $tipo = appNormalizeUserType($tipo);
    $routes = [
        'super_admin' => '/modules/admin/dashboard.php',
        'admin_empresa' => '/modules/dashboard_empresa/index.php',
        'gestor' => '/modules/dashboard_empresa/index.php',
        'supervisor' => '/index.php',
        'funcionario' => '/modules/ponto/ponto.php',
    ];

    return $routes[$tipo] ?? '/modules/ponto/ponto.php';
}

function appSafeInternalRoute(?string $route): ?string
{
    if (!$route || strpos($route, '//') === 0 || preg_match('#^[a-z][a-z0-9+.-]*:#i', $route)) {
        return null;
    }

    $parts = parse_url($route);
    if ($parts === false || isset($parts['host']) || isset($parts['scheme'])) {
        return null;
    }

    $path = $parts['path'] ?? '';
    if ($path === '' || $path[0] !== '/' || strpos($path, "\0") !== false || strpos($path, '..') !== false) {
        return null;
    }

    $base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
    if ($base !== '' && strpos($path, $base . '/') !== 0 && $path !== $base) {
        return null;
    }

    return $route;
}

function appRememberIntendedRoute(): void
{
    $route = ($_SERVER['REQUEST_URI'] ?? '');
    if (($safeRoute = appSafeInternalRoute($route)) !== null) {
        $_SESSION['redirect_after_login'] = $safeRoute;
    }
}

function appDestinationAfterLogin(string $tipo): string
{
    $intended = appSafeInternalRoute($_SESSION['redirect_after_login'] ?? null);
    unset($_SESSION['redirect_after_login']);

    return $intended ?? appHomeRouteFor($tipo);
}

function appUrl(string $route): string
{
    $cleanRoute = preg_replace('#/index\.php$#', '/', $route);
    $cleanRoute = preg_replace('#\.php$#', '', $cleanRoute);

    return rtrim(defined('BASE_URL') ? BASE_URL : '', '/') . $cleanRoute;
}

function appRedirectAfterLogin(string $tipo): void
{
    header('Location: ' . appUrl(appDestinationAfterLogin($tipo)));
    exit;
}

function appRequestBasePath(): string
{
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $documentRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));

    return rtrim(str_replace($documentRoot, '', $projectRoot), '/');
}

function appCurrentRoute(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = appRequestBasePath();

    if ($base !== '' && ($path === $base || strpos($path, $base . '/') === 0)) {
        $path = substr($path, strlen($base));
    }

    return '/' . ltrim($path, '/');
}

function appRouteIsPublic(string $route): bool
{
    if (strpos($route, '/api/') === 0) {
        return true;
    }

    return in_array($route, [
        '/login',
        '/login.php',
        '/logout',
        '/logout.php',
        '/modules/funcionarios/login_facial',
        '/modules/funcionarios/login_facial.php',
    ], true);
}

function appRouteAllows(string $route, string $userType): bool
{
    if (in_array($route, [
        '/modules/funcionarios/cadastro_facial',
        '/modules/funcionarios/cadastro_facial.php',
    ], true)) {
        return true;
    }

    $policies = [
        '/modules/admin/' => ['super_admin'],
        '/modules/usuarios/' => ['super_admin', 'admin_empresa'],
        '/modules/filiais/' => ['super_admin', 'admin_empresa'],
        '/modules/backup/' => ['super_admin', 'admin_empresa'],
        '/modules/configuracoes/' => ['super_admin', 'admin_empresa'],
        '/modules/auditoria/' => ['super_admin', 'admin_empresa'],
        '/modules/notificacoes/' => ['super_admin', 'admin_empresa'],
        '/modules/relatorios/' => ['super_admin', 'admin_empresa', 'gestor', 'supervisor'],
        '/modules/funcionarios/' => ['super_admin', 'admin_empresa', 'gestor', 'supervisor'],
        '/modules/biometrico/' => ['super_admin', 'admin_empresa', 'gestor'],
        '/modules/escala/' => ['super_admin', 'admin_empresa', 'gestor'],
    ];

    foreach ($policies as $prefix => $allowedTypes) {
        if (strpos($route, $prefix) === 0) {
            return in_array($userType, $allowedTypes, true);
        }
    }

    return true;
}

function appProtectCurrentRoute(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $route = appCurrentRoute();
    if (appRouteIsPublic($route)) {
        return;
    }

    $isAuthenticated = isset($_SESSION['usuario_id'], $_SESSION['usuario_tipo'])
        || isset($_SESSION['funcionario_id'], $_SESSION['usuario_nome']);

    if (!$isAuthenticated) {
        appRememberIntendedRoute();
        header('Location: ' . appRequestBasePath() . '/login');
        exit;
    }

    $userType = appNormalizeUserType($_SESSION['usuario_tipo'] ?? 'funcionario');
    $_SESSION['usuario_tipo'] = $userType;
    if (!appRouteAllows($route, $userType)) {
        http_response_code(403);
        header('Location: ' . appUrl(appHomeRouteFor($userType)));
        exit;
    }
}
