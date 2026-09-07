<?php
/**
 * Autenticacao centralizada para telas administrativas legadas.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/router.php';

function authCheckUrl($path = '') {
    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    return $base . $path;
}

function forceAuthentication() {
    if (isset($_SESSION['funcionario_id'])) {
        return true;
    }

    if (isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_nome'])) {
        return true;
    }

    if (isset($_SESSION['user_id']) && isset($_SESSION['user_nome'])) {
        return true;
    }

    appRememberIntendedRoute();
    header('Location: ' . authCheckUrl('/login.php'));
    exit;
}

function requireAdmin() {
    forceAuthentication();

    $tipo = $_SESSION['usuario_tipo'] ?? ($_SESSION['user_tipo'] ?? '');
    $isAdmin = in_array($tipo, ['super_admin', 'admin_empresa', 'admin'], true);

    if (isset($_SESSION['funcionario_is_admin']) && $_SESSION['funcionario_is_admin'] == 1) {
        $isAdmin = true;
    }

    if (!$isAdmin) {
        header('HTTP/1.0 403 Forbidden');
        die('<h1>Acesso Negado</h1><p>Acesso restrito a administradores.</p><a href="' . authCheckUrl('/index.php') . '">Voltar ao Dashboard</a>');
    }

    return true;
}

// Nome próprio para este adaptador legado. A autorização principal usa
// checkModuleAccess() em includes/auth.php; manter o mesmo nome aqui causava
// erro fatal quando uma página também carregava o header compartilhado.
function authCheckModuleAccess($modulo, $acao = 'visualizar') {
    forceAuthentication();

    $tipo = $_SESSION['usuario_tipo'] ?? ($_SESSION['user_tipo'] ?? '');
    if (in_array($tipo, ['super_admin', 'admin_empresa', 'admin'], true)) {
        return true;
    }

    if ($acao !== 'visualizar') {
        requireAdmin();
    }

    return true;
}

function logAccess($acao, $detalhes = null) {
    global $pdo;

    if (!$pdo instanceof PDO) {
        return;
    }

    $usuario_id = $_SESSION['usuario_id'] ?? ($_SESSION['user_id'] ?? ($_SESSION['funcionario_id'] ?? null));
    $usuario_nome = $_SESSION['usuario_nome'] ?? ($_SESSION['user_nome'] ?? ($_SESSION['funcionario_nome'] ?? 'Desconhecido'));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs_acesso (usuario_id, usuario_nome, acao, detalhes, ip, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$usuario_id, $usuario_nome, $acao, $detalhes, $ip, $user_agent]);
    } catch (Exception $e) {
        error_log('Erro ao logar acesso: ' . $e->getMessage());
    }
}
?>
