<?php
// modules/backup/download.php - Download do Backup
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa'])) {
    header('Location: ../../index.php');
    exit;
}

$filename = basename((string) ($_GET['file'] ?? ''));

if (empty($filename)) {
    header('Location: index.php');
    exit;
}

// Garantir segurança - apenas arquivos .sql.gz
if (!preg_match('/^backup_[A-Za-z0-9_-]+\.sql\.gz$/', $filename)) {
    die('Arquivo inválido');
}

$filepath = __DIR__ . '/backups/' . $filename;

if (!file_exists($filepath)) {
    die('Arquivo não encontrado');
}

// Configurar cabeçalhos para download
header('Content-Type: application/gzip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Enviar arquivo
readfile($filepath);
exit;
?>
