<?php
/**
 * SISTEMA DE BACKUP SEGURO
 * Armazena backups FORA do diretório público
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/csrf.php';

forceAuthentication();
requireAdmin();

// Define diretório de backup FORA do webroot
define('BACKUP_DIR', '/var/backups/sistema_ponto/');

// Cria diretório se não existir
if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0750, true);
}

$action = $_GET['action'] ?? 'listar';

// CSRF para ações que modificam
if (in_array($action, ['criar', 'restaurar', 'excluir']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken();
}

switch ($action) {
    case 'criar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            die('Método não permitido');
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupPath = BACKUP_DIR . $filename;
        
        // Comando mysqldump seguro
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_NAME),
            escapeshellarg($backupPath)
        );
        
        system($command, $returnCode);
        
        if ($returnCode === 0 && file_exists($backupPath)) {
            // Registra no banco
            $stmt = $pdo->prepare("
                INSERT INTO backups (filename, size, created_by, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$filename, filesize($backupPath), $_SESSION['user_id'] ?? $_SESSION['funcionario_id']]);
            
            $_SESSION['success'] = "Backup criado: $filename";
        } else {
            $_SESSION['error'] = 'Erro ao criar backup';
        }
        
        header('Location: ' . BASE_URL . '/modules/backup/seguro.php');
        break;
        
    case 'restaurar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            die('Método não permitido');
        }
        
        $filename = basename($_POST['filename'] ?? '');
        $backupPath = BACKUP_DIR . $filename;
        
        if (!file_exists($backupPath)) {
            $_SESSION['error'] = 'Arquivo de backup não encontrado';
            header('Location: ' . BASE_URL . '/modules/backup/seguro.php');
            exit;
        }
        
        // Restauração segura
        $command = sprintf(
            'mysql --host=%s --user=%s --password=%s %s < %s',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_NAME),
            escapeshellarg($backupPath)
        );
        
        system($command, $returnCode);
        
        if ($returnCode === 0) {
            logAccess('restaurar_backup', "Restaurou backup: $filename");
            $_SESSION['success'] = "Backup restaurado: $filename";
        } else {
            $_SESSION['error'] = 'Erro ao restaurar backup';
        }
        
        header('Location: ' . BASE_URL . '/modules/backup/seguro.php');
        break;
        
    case 'excluir':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            die('Método não permitido');
        }
        
        $filename = basename($_POST['filename'] ?? '');
        $backupPath = BACKUP_DIR . $filename;
        
        if (file_exists($backupPath) && unlink($backupPath)) {
            $stmt = $pdo->prepare("DELETE FROM backups WHERE filename = ?");
            $stmt->execute([$filename]);
            
            logAccess('excluir_backup', "Excluiu backup: $filename");
            $_SESSION['success'] = "Backup excluído: $filename";
        } else {
            $_SESSION['error'] = 'Erro ao excluir backup';
        }
        
        header('Location: ' . BASE_URL . '/modules/backup/seguro.php');
        break;
        
    case 'download':
        $filename = basename($_GET['filename'] ?? '');
        $backupPath = BACKUP_DIR . $filename;
        
        if (!file_exists($backupPath)) {
            die('Arquivo não encontrado');
        }
        
        // Log de download
        logAccess('download_backup', "Baixou backup: $filename");
        
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($backupPath));
        readfile($backupPath);
        exit;
        
    case 'listar':
    default:
        // Lista backups
        $backups = glob(BACKUP_DIR . '*.sql');
        rsort($backups);
        ?>
        <!DOCTYPE html>
        <html lang="pt-br">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Backup Seguro</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </head>
        <body>
            <div class="container mt-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-database"></i> Sistema de Backup Seguro</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>
                        
                        <!-- Formulário com CSRF -->
                        <form method="POST" action="?action=criar" class="mb-4">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <button type="submit" class="btn btn-success" onclick="return confirm('Criar novo backup?')">
                                <i class="fas fa-plus"></i> Criar Novo Backup
                            </button>
                        </form>
                        
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Arquivo</th>
                                    <th>Tamanho</th>
                                    <th>Data</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $backup): 
                                    $filename = basename($backup);
                                    $size = round(filesize($backup) / 1024 / 1024, 2);
                                    $date = date('d/m/Y H:i:s', filemtime($backup));
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($filename) ?></td>
                                        <td><?= $size ?> MB</td>
                                        <td><?= $date ?></td>
                                        <td>
                                            <a href="?action=download&filename=<?= urlencode($filename) ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <form method="POST" action="?action=restaurar" style="display:inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="filename" value="<?= htmlspecialchars($filename) ?>">
                                                <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Restaurar este backup? Isso sobrescreverá o banco atual!')">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="?action=excluir" style="display:inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="filename" value="<?= htmlspecialchars($filename) ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Excluir este backup?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        break;
}
?>
