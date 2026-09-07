<?php
/**
 * Inicia MariaDB local via Podman usando as credenciais existentes no .env.
 * Requer Podman rootless; não exibe a senha no terminal.
 */

$root = dirname(__DIR__);
$envFile = $root . '/.env';
$backup = $root . '/modules/backup/backups/backup_2026-06-12_11-29-43.sql.gz';
$config = is_readable($envFile) ? parse_ini_file($envFile, false, INI_SCANNER_RAW) : false;

foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
    if (!is_array($config) || !array_key_exists($key, $config) || $config[$key] === '') {
        fwrite(STDERR, "Defina {$key} no arquivo .env antes de iniciar o banco local.\n");
        exit(1);
    }
}

$bindHost = $config['DB_HOST'];
if (!filter_var($bindHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    fwrite(STDERR, "DB_HOST deve ser um endereço IPv4 local para iniciar o banco via Podman.\n");
    exit(1);
}

if (!is_file($backup)) {
    fwrite(STDERR, "Backup inicial não encontrado.\n");
    exit(1);
}

$command = [
    'podman', 'run', '-d',
    '--name', 'ponto-mysql',
    '-p', $bindHost . ':' . (int) $config['DB_PORT'] . ':3306',
    '-v', 'ponto_mysql_data:/var/lib/mysql',
    '-v', $backup . ':/docker-entrypoint-initdb.d/01-schema.sql.gz:ro',
    '-e', 'MARIADB_DATABASE=' . $config['DB_NAME'],
    '-e', 'MARIADB_USER=' . $config['DB_USER'],
    '-e', 'MARIADB_PASSWORD=' . $config['DB_PASS'],
    '-e', 'MARIADB_ROOT_PASSWORD=' . $config['DB_PASS'],
    '--health-cmd', 'healthcheck.sh --connect --innodb_initialized',
    '--health-interval', '5s',
    '--health-retries', '20',
    'docker.io/library/mariadb:11.4',
    '--sql-mode=NO_ENGINE_SUBSTITUTION',
];

$process = proc_open($command, [1 => STDOUT, 2 => STDERR], $pipes, $root);
if (!is_resource($process) || proc_close($process) !== 0) {
    fwrite(STDERR, "Não foi possível iniciar o container ponto-mysql.\n");
    exit(1);
}

echo "Container ponto-mysql iniciado. Aguarde o healthcheck antes de abrir a aplicação.\n";
