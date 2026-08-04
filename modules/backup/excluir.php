<?php
// modules/backup/excluir.php - Excluir Backup com POST + CSRF
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa'], true)) {
    header('Location: ../../index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensagem'] = 'Acao invalida. Exclusao de backup exige confirmacao segura.';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: index.php');
    exit;
}

verifyCSRFToken();

$database = new Database();
$db = $database->getConnection();

$filename = basename((string) ($_POST['file'] ?? ''));
$excluir_todos = isset($_POST['todos']) && $_POST['todos'] === '1';
$backupDir = __DIR__ . '/backups/';

if ($excluir_todos) {
    $files = glob($backupDir . 'backup_*.sql.gz') ?: [];
    $total = 0;

    foreach ($files as $file) {
        $backupName = basename($file);
        if (!preg_match('/^backup_[A-Za-z0-9_-]+\.sql\.gz$/', $backupName)) {
            continue;
        }

        if (is_file($file) && unlink($file)) {
            $total++;
            $stmt = $db->prepare("DELETE FROM backups_registros WHERE nome_arquivo = :nome");
            $stmt->execute([':nome' => $backupName]);
        }
    }

    $_SESSION['mensagem'] = $total . ' backup(s) excluido(s) com sucesso!';
    $_SESSION['tipo_mensagem'] = 'success';
    header('Location: index.php');
    exit;
}

if ($filename === '') {
    $_SESSION['mensagem'] = 'Nenhum backup selecionado para exclusao';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: index.php');
    exit;
}

if (!preg_match('/^backup_[A-Za-z0-9_-]+\.sql\.gz$/', $filename)) {
    $_SESSION['mensagem'] = 'Arquivo invalido';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: index.php');
    exit;
}

$filepath = $backupDir . $filename;
if (!is_file($filepath)) {
    $_SESSION['mensagem'] = 'Arquivo nao encontrado';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: index.php');
    exit;
}

if (unlink($filepath)) {
    $stmt = $db->prepare("DELETE FROM backups_registros WHERE nome_arquivo = :nome");
    $stmt->execute([':nome' => $filename]);

    $_SESSION['mensagem'] = 'Backup excluido com sucesso!';
    $_SESSION['tipo_mensagem'] = 'success';
} else {
    $_SESSION['mensagem'] = 'Erro ao excluir backup';
    $_SESSION['tipo_mensagem'] = 'error';
}

header('Location: index.php');
exit;
?>
