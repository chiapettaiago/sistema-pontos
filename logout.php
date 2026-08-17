<?php
session_start();

if (file_exists(__DIR__ . '/includes/auth.php') && file_exists(__DIR__ . '/config/database.php')) {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/includes/auth.php';

    try {
        if (function_exists('logAcao') && isset($_SESSION['usuario_id'])) {
            $database = new Database();
            $db = $database->getConnection();
            logAcao($db, 'LOGOUT', 'funcionarios', $_SESSION['usuario_id'], 'Usuario fez logout');
        }
    } catch (Exception $e) {
        // Ignorar erro ao registrar logout
    }
}

$_SESSION = [];

if (isset($_COOKIE[session_name()])) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 3600,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        $params['secure'] ?? false,
        $params['httponly'] ?? true
    );
}

session_destroy();

header('Location: ' . BASE_URL . '/login.php');
exit;

