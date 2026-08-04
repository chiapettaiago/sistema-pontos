<?php
// config/config.php - Configuração do Sistema
// NÃO PODE HAVER NADA ANTES DESTA LINHA

// ============================================
// DETECTAR AMBIENTE (LOCAL x PRODUÇÃO)
// ============================================

// Função para detectar se está em desenvolvimento local
function isLocalhost() {
    $localhosts = ['localhost', '127.0.0.1', '::1'];
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return in_array($host, $localhosts) || strpos($host, '.test') !== false || strpos($host, '.local') !== false;
}

// Detectar protocolo (HTTP ou HTTPS)
function getProtocol() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return 'https://';
    }
    return 'http://';
}

// Obter URL base do sistema
function getBaseUrl() {
    $protocol = getProtocol();
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    return $protocol . $host . $uri;
}

// ============================================
// CONFIGURAÇÕES DO SISTEMA
// ============================================

define('BASE_URL', getBaseUrl());
define('IS_LOCAL', isLocalhost());
define('APP_NAME', 'Ponto Fácil');
define('APP_VERSION', '2.0.0');

// Configurações da API de QR Code
if (IS_LOCAL) {
    // Para desenvolvimento local, usar API gratuita (pode ter limitações)
    define('QR_CODE_API', 'https://quickchart.io/qr');
} else {
    // Para produção, usar API mais confiável
    define('QR_CODE_API', 'https://quickchart.io/qr');
}

// Configurações do Face Recognition
if (IS_LOCAL) {
    // Local: usar modelos locais
    define('FACE_API_MODELS_PATH', BASE_URL . '/assets/models/');
    define('USE_CDN_MODELS', false);
} else {
    // Produção: usar CDN para evitar problemas com caminhos
    define('FACE_API_MODELS_PATH', 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/weights/');
    define('USE_CDN_MODELS', true);
}

// Configurações de Upload
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');
?>