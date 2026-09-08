<?php
/**
 * Configuracao central do sistema.
 * Ajuste apenas as credenciais do banco abaixo quando publicar no servidor.
 */

require_once __DIR__ . '/router.php';
appProtectCurrentRoute();

function loadDatabaseEnvironment(string $path): array
{
    if (!is_readable($path)) {
        throw new RuntimeException('Arquivo .env não encontrado.');
    }

    $values = parse_ini_file($path, false, INI_SCANNER_RAW);
    if (!is_array($values)) {
        throw new RuntimeException('Arquivo .env inválido.');
    }

    foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET', 'DB_CONNECT_TIMEOUT'] as $key) {
        if (!array_key_exists($key, $values) || trim((string) $values[$key]) === '') {
            throw new RuntimeException("Configuração {$key} ausente no arquivo .env.");
        }
    }

    return $values;
}

$databaseEnvironment = loadDatabaseEnvironment(dirname(__DIR__) . '/.env');

define('DB_HOST', $databaseEnvironment['DB_HOST']);
define('DB_PORT', (int) $databaseEnvironment['DB_PORT']);
define('DB_NAME', $databaseEnvironment['DB_NAME']);
define('DB_USER', $databaseEnvironment['DB_USER']);
define('DB_PASS', $databaseEnvironment['DB_PASS']);
define('DB_CHARSET', $databaseEnvironment['DB_CHARSET']);
define('DB_CONNECT_TIMEOUT', (int) $databaseEnvironment['DB_CONNECT_TIMEOUT']);

define('SITE_NAME', 'Sistema de Ponto Eletronico');
define('SITE_URL', 'https://divulgpontofacil.com.br');
define('TIMEZONE', 'America/Sao_Paulo');

// BASE_URL: caminho da aplicacao a partir da raiz do servidor web.
// Calculado automaticamente — funciona em localhost/subpasta e em producao.
if (!defined('BASE_URL')) {
    define('BASE_URL', appBasePath());
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

function createDatabaseConnection(): PDO
{
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => DB_CONNECT_TIMEOUT,
        ]
    );
}

try {
    $pdo = createDatabaseConnection();
    $pdo->exec("SET time_zone = '-03:00'");
} catch (PDOException $e) {
    error_log('Falha na conexao MySQL: ' . $e->getMessage());
    http_response_code(503);
    exit('Serviço de banco de dados temporariamente indisponível.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
