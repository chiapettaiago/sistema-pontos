<?php
/**
 * Configuracao central do sistema.
 * Ajuste apenas as credenciais do banco abaixo quando publicar no servidor.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'appcas29_pontofacil');
define('DB_USER', 'appcas29_ponto_empresarial');
define('DB_PASS', 'Cl4r1c#2018@#'); // preencha com a senha do banco no servidor
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'Sistema de Ponto Eletronico');
define('SITE_URL', 'https://divulgpontofacil.com.br');
define('TIMEZONE', 'America/Sao_Paulo');

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
    die('Erro ao conectar ao banco de dados. Contate o administrador.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
