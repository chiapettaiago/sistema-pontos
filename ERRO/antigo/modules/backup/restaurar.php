<?php
// modules/backup/restaurar.php - Restaurar Backup
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

require_once '../../config/database.php';
require_once '../../includes/csrf.php';

$database = new Database();
$db = $database->getConnection();

function validarNomeBackup($filename) {
    return preg_match('/^backup_[A-Za-z0-9_-]+\.sql\.gz$/', $filename);
}

$filename = basename((string) ($_GET['file'] ?? ''));
$error = '';
$success = '';

$backupDir = __DIR__ . '/backups/';
$filepath = $backupDir . $filename;

if ($filename && !validarNomeBackup($filename)) {
    $error = 'Arquivo de backup invalido';
    $filename = '';
    $filepath = '';
}

// Processar restauração
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    verifyCSRFToken();

    $filename = basename((string) ($_POST['filename'] ?? ''));
    $filepath = $backupDir . $filename;
    
    if (!validarNomeBackup($filename)) {
        $error = 'Arquivo de backup invalido';
    } elseif (!file_exists($filepath)) {
        $error = 'Arquivo de backup não encontrado';
    } else {
        // Descomprimir arquivo
        $content = '';
        $gz = gzopen($filepath, 'rb');
        while (!gzeof($gz)) {
            $content .= gzread($gz, 8192);
        }
        gzclose($gz);
        
        // Executar SQL
        try {
            // Desabilitar verificações de chave estrangeira
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Dividir em comandos individuais
            $queries = explode(";\n", $content);
            
            $total = 0;
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query) && strpos($query, '--') !== 0) {
                    try {
                        $db->exec($query);
                        $total++;
                    } catch (PDOException $e) {
                        // Ignorar erros de tabelas que não existem
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            error_log("Erro na query: " . $e->getMessage());
                        }
                    }
                }
            }
            
            // Reabilitar verificações
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            // Registrar restauração
            $stmt = $db->prepare("INSERT INTO backups_registros (nome_arquivo, tamanho, tipo, status, created_by, created_at) 
                                  VALUES (:nome, :tamanho, 'restauracao', 'sucesso', :usuario, NOW())");
            $stmt->execute([
                ':nome' => 'restauracao_' . date('Y-m-d_H-i-s') . '.log',
                ':tamanho' => '0 B',
                ':usuario' => $_SESSION['usuario_id']
            ]);
            
            $success = "Backup restaurado com sucesso! $total comandos executados.";
            
        } catch (Exception $e) {
            $error = 'Erro ao restaurar backup: ' . $e->getMessage();
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        }
    }
}

function formatarTamanho($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

// Informações do arquivo
$file_info = null;
if ($filename && file_exists($filepath)) {
    $stat = stat($filepath);
    $file_info = [
        'nome' => $filename,
        'tamanho' => formatarTamanho(filesize($filepath)),
        'data' => date('d/m/Y H:i:s', $stat['mtime'])
    ];
}
?>

<style>
.backup-container {
    max-width: 600px;
    margin: 0 auto;
}

.form-card {
    background: var(--bg-primary);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.form-header {
    padding: 20px 24px;
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
}

.form-header h3 {
    margin: 0;
    font-size: 20px;
}

.form-header p {
    margin: 8px 0 0;
    opacity: 0.9;
    font-size: 14px;
}

.form-body {
    padding: 24px;
}

.warning-box {
    background: #fee2e2;
    border-left: 4px solid #dc2626;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.warning-box p {
    margin: 0;
    font-size: 13px;
    color: #991b1b;
}

.warning-box i {
    margin-right: 8px;
}

.info-box {
    background: #f3f4f6;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e5e7eb;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: var(--text-secondary);
}

.info-value {
    color: var(--text-primary);
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.3s;
    width: 100%;
    justify-content: center;
}

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-danger:hover {
    background: #b91c1c;
    transform: translateY(-2px);
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    margin-top: 12px;
}

.alert {
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
</style>

<div class="backup-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Restaurar Backup</h3>
            <p>ATENÇÃO: Esta ação substituirá todos os dados atuais!</p>
        </div>
        
        <div class="form-body">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
                <a href="index.php" class="btn btn-secondary">Voltar</a>
            <?php elseif ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <a href="index.php" class="btn btn-secondary">Voltar</a>
            <?php elseif ($file_info): ?>
                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Esta ação não pode ser desfeita!</strong><br>
                    Restaurar este backup irá substituir TODOS os dados atuais do sistema.
                </div>
                
                <div class="info-box">
                    <div class="info-item">
                        <span class="info-label">Arquivo:</span>
                        <span class="info-value"><?php echo htmlspecialchars($file_info['nome']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tamanho:</span>
                        <span class="info-value"><?php echo $file_info['tamanho']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Data do backup:</span>
                        <span class="info-value"><?php echo $file_info['data']; ?></span>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="filename" value="<?php echo htmlspecialchars($filename); ?>">
                    <input type="hidden" name="confirmar" value="1">
                    
                    <button type="submit" class="btn btn-danger" onclick="return confirm('ATENÇÃO! Esta ação irá SUBSTITUIR todos os dados atuais. Tem certeza absoluta?')">
                        <i class="fas fa-undo-alt"></i> Confirmar Restauração
                    </button>
                </form>
                
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
                
            <?php else: ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> Nenhum arquivo de backup selecionado.
                </div>
                <a href="index.php" class="btn btn-secondary">Voltar</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
