<?php
// modules/backup/criar.php - Criar Backup (CORRIGIDO)
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

$empresa_id = $_SESSION['empresa_id'] ?? 1;

$success = '';
$error = '';
$backup_file = '';

// Pasta de backups
$backupDir = __DIR__ . '/backups/';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0777, true);
}

// Função para criar backup
function criarBackup($db, $backupDir, $empresa_id, $usuario_id, $tipo = 'manual') {
    global $error;
    
    // Nome do arquivo de backup
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . $filename;
    
    // Obter todas as tabelas do banco
    $tables = [];
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    // Criar conteúdo do backup
    $sql = "-- Backup do Banco de Dados\n";
    $sql .= "-- Gerado em: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Empresa ID: " . $empresa_id . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    // Para cada tabela
    foreach ($tables as $table) {
        // Verificar se a tabela pertence à empresa (se tiver coluna empresa_id)
        try {
            $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE 'empresa_id'");
            $stmt->execute();
            $hasEmpresaId = $stmt->rowCount() > 0;
        } catch (Exception $e) {
            $hasEmpresaId = false;
        }
        
        // Estrutura da tabela - CORRIGIDO: usar SHOW CREATE TABLE
        try {
            $stmt = $db->prepare("SHOW CREATE TABLE `$table`");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // A chave pode ser 'Create Table' ou 'Create Table' dependendo do driver
            $createTable = $row['Create Table'] ?? $row[1] ?? null;
            
            if ($createTable) {
                $sql .= "\n-- Estrutura da tabela `$table`\n";
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $createTable . ";\n\n";
            } else {
                // Fallback: tentar obter de outra forma
                $sql .= "\n-- Estrutura da tabela `$table` (não disponível)\n";
            }
        } catch (Exception $e) {
            error_log("Erro ao obter estrutura da tabela $table: " . $e->getMessage());
            $sql .= "\n-- Erro ao obter estrutura da tabela `$table`\n\n";
        }
        
        // Dados da tabela
        $sql .= "-- Dados da tabela `$table`\n";
        
        try {
            if ($hasEmpresaId) {
                $stmt = $db->prepare("SELECT * FROM `$table` WHERE empresa_id = :empresa_id OR empresa_id IS NULL");
                $stmt->execute([':empresa_id' => $empresa_id]);
            } else {
                $stmt = $db->prepare("SELECT * FROM `$table`");
                $stmt->execute();
            }
            
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $insert = "INSERT INTO `$table` (`" . implode("`, `", $columns) . "`) VALUES ";
                $values = [];
                
                foreach ($rows as $row) {
                    $rowValues = [];
                    foreach ($columns as $col) {
                        $value = $row[$col];
                        if ($value === null) {
                            $rowValues[] = "NULL";
                        } else {
                            $rowValues[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $values[] = "(" . implode(", ", $rowValues) . ")";
                }
                $sql .= $insert . implode(",\n", $values) . ";\n\n";
            }
        } catch (Exception $e) {
            error_log("Erro ao obter dados da tabela $table: " . $e->getMessage());
            $sql .= "-- Erro ao obter dados da tabela `$table`\n\n";
        }
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    // Salvar arquivo
    if (file_put_contents($filepath, $sql)) {
        // Comprimir arquivo
        $gzpath = $filepath . '.gz';
        $gz = gzopen($gzpath, 'wb9');
        gzwrite($gz, $sql);
        gzclose($gz);
        
        // Remover arquivo não comprimido
        unlink($filepath);
        
        // Registrar no banco
        try {
            $stmt = $db->prepare("INSERT INTO backups_registros (nome_arquivo, tamanho, tipo, status, created_by, created_at) 
                                  VALUES (:nome, :tamanho, :tipo, 'sucesso', :usuario, NOW())");
            $stmt->execute([
                ':nome' => basename($gzpath),
                ':tamanho' => formatarTamanho(filesize($gzpath)),
                ':tipo' => $tipo,
                ':usuario' => $usuario_id
            ]);
        } catch (Exception $e) {
            error_log("Erro ao registrar backup: " . $e->getMessage());
        }
        
        return basename($gzpath);
    } else {
        $error = 'Erro ao salvar arquivo de backup';
        return false;
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

// Processar criação de backup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken();

    $tipo = $_POST['tipo'] ?? 'manual';
    $backup_file = criarBackup($db, $backupDir, $empresa_id, $_SESSION['usuario_id'], $tipo);
    
    if ($backup_file) {
        $success = 'Backup criado com sucesso!';
    } else {
        $error = 'Falha ao criar backup';
    }
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
    background: linear-gradient(135deg, #667eea, #764ba2);
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

.info-box {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.info-box p {
    margin: 0;
    font-size: 13px;
    color: #92400e;
}

.info-box i {
    margin-right: 8px;
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
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    width: 100%;
    justify-content: center;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    margin-top: 12px;
    width: 100%;
    justify-content: center;
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

.loading {
    display: none;
    text-align: center;
    padding: 20px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<div class="backup-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-database"></i> Criar Novo Backup</h3>
            <p>Faça uma cópia de segurança do banco de dados</p>
        </div>
        
        <div class="form-body">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="index.php" class="btn btn-secondary">Voltar</a>
                    <a href="download.php?file=<?php echo urlencode($backup_file); ?>" class="btn btn-primary" style="margin-top: 12px; background: #10b981;">
                        <i class="fas fa-download"></i> Baixar Backup
                    </a>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <a href="index.php" class="btn btn-secondary">Voltar</a>
            <?php else: ?>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>O que será incluído no backup?</strong><br>
                    • Todas as tabelas do banco de dados da sua empresa<br>
                    • Registros específicos da sua empresa (quando aplicável)<br>
                    • Estrutura e dados completos
                </div>
                
                <form method="POST" action="" id="backupForm">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="tipo" value="manual">
                    
                    <div class="info-box" style="background: #d1fae5; border-left-color: #10b981;">
                        <i class="fas fa-clock"></i>
                        O backup pode levar alguns segundos dependendo do tamanho do banco.<br>
                        Não feche esta página durante o processo.
                    </div>
                    
                    <div id="loading" class="loading">
                        <div class="spinner"></div>
                        <p>Criando backup... Aguarde</p>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="btnBackup">
                        <i class="fas fa-database"></i> Criar Backup Agora
                    </button>
                </form>
                
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('backupForm')?.addEventListener('submit', function() {
    document.getElementById('btnBackup').style.display = 'none';
    document.getElementById('loading').style.display = 'block';
});
</script>

<?php require_once '../../includes/footer.php'; ?>
