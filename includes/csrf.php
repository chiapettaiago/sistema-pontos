<?php
/**
 * Proteção CSRF (Cross-Site Request Forgery)
 * Para TODOS os formulários que modificam dados (POST, DELETE, etc)
 */

// Garante sessão iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Gera um token CSRF único para o formulário
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            // Fallback se random_bytes falhar
            $_SESSION['csrf_token'] = md5(uniqid(mt_rand(), true));
        }
    }
    return $_SESSION['csrf_token'];
}

/**
 * Retorna o HTML do campo hidden para formulários
 */
function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Verifica se o token CSRF é válido
 */
function verifyCSRFToken($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        error_log("CSRF inválido - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        // Se for requisição AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('HTTP/1.0 403 Forbidden');
            die(json_encode(['error' => 'Token de segurança inválido. Recarregue a página e tente novamente.']));
        }
        
        die('<h1>Erro de Segurança</h1><p>Token de segurança inválido. <a href="javascript:history.back()">Voltar e tentar novamente</a></p>');
    }
    
    // Remove token após uso (one-time use)
    // unset($_SESSION['csrf_token']);
    
    return true;
}

/**
 * Verifica automaticamente se é POST e valida CSRF
 * Use em arquivos que processam formulários
 */
function autoVerifyCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCSRFToken();
    }
}
?>