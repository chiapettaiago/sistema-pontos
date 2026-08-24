<?php
/**
 * Configuracao central do sistema.
 * Ajuste apenas as credenciais do banco abaixo quando publicar no servidor.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'appcas29_pontofacil');
define('DB_USER', 'root');
define('DB_PASS', ''); // ambiente local WAMP
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'Sistema de Ponto Eletronico');
define('SITE_URL', 'https://divulgpontofacil.com.br');
define('TIMEZONE', 'America/Sao_Paulo');

// BASE_URL: caminho da aplicacao a partir da raiz do servidor web.
// Calculado automaticamente — funciona em localhost/subpasta e em producao.
if (!defined('BASE_URL')) {
    $_cfg_dir = str_replace('\\', '/', dirname(__DIR__));
    $_cfg_root = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
    define('BASE_URL', rtrim(str_replace($_cfg_root, '', $_cfg_dir), '/'));
}

define('SESSION_TIMEOUT', 7200);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900);

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);
define('BACKUP_DIR', 'C:/backups_ponto/');

date_default_timezone_set(TIMEZONE);

error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Erro de conexao: ' . $e->getMessage());
    die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
