<?php
/**
 * CONFIGURAÇÃO DO BANCO DE DADOS
 * Ajuste conforme sua instalação
 */

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'ponto_empresarial');  // Nome do seu banco
define('DB_USER', 'root');               // Usuário do MySQL
define('DB_PASS', '');                   // Senha (XAMPP geralmente vazio)
define('DB_CHARSET', 'utf8mb4');

// Configurações do Sistema
define('SITE_NAME', 'Sistema de Ponto Eletrônico');
define('SITE_URL', 'http://localhost/ponto_empresarial');
define('TIMEZONE', 'America/Sao_Paulo');

// Configurações de Segurança
define('SESSION_TIMEOUT', 7200); // 2 horas
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos

// Configurações de Upload
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// Configurações de Backup
define('BACKUP_DIR', 'C:/backups_ponto/'); // Fora do htdocs

// Configuração de Timezone
date_default_timezone_set(TIMEZONE);

// Configuração de Error Reporting (desligar em produção)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Conexão PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // Log do erro (sem exibir detalhes em produção)
    error_log("Erro de conexão: " . $e->getMessage());
    die("Erro ao conectar ao banco de dados. Contate o administrador.");
}

// Iniciar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>