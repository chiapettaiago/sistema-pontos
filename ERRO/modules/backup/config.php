<?php
// modules/backup/config.php - Configurações de Backup
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

$pageTitle = 'Configurações de Backup';
$activePage = 'backup';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../includes/csrf.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$success = '';
$error = '';

// Buscar configurações atuais
$stmt = $db->prepare("SELECT * FROM backups_config WHERE empresa_id = :empresa_id");
$stmt->execute([':empresa_id' => $empresa_id]);
$config = $stmt->fetch();

if (!$config) {
    $stmt = $db->prepare("INSERT INTO backups_config (empresa_id) VALUES (:empresa_id)");
    $stmt->execute([':empresa_id' => $empresa_id]);
    
    $stmt = $db->prepare("SELECT * FROM backups_config WHERE empresa_id = :empresa_id");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $config = $stmt->fetch();
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken();

    $backup_automatico = isset($_POST['backup_automatico']) ? 1 : 0;
    $frequencia = $_POST['frequencia'] ?? 'diario';
    $hora_backup = $_POST['hora_backup'] ?? '02:00:00';
    $dias_retencao = (int)($_POST['dias_retencao'] ?? 30);
    $incluir_uploads = isset($_POST['incluir_uploads']) ? 1 : 0;
    $notificar_email = isset($_POST['notificar_email']) ? 1 : 0;
    $email_notificacao = trim($_POST['email_notificacao'] ?? '');
    
    if ($dias_retencao < 1) $dias_retencao = 1;
    if ($dias_retencao > 365) $dias_retencao = 365;
    
    try {
        $stmt = $db->prepare("UPDATE backups_config SET 
            backup_automatico = :backup_automatico,
            frequencia = :frequencia,
            hora_backup = :hora_backup,
            dias_retencao = :dias_retencao,
            incluir_uploads = :incluir_uploads,
            notificar_email = :notificar_email,
            email_notificacao = :email_notificacao
            WHERE empresa_id = :empresa_id");
        
        $stmt->execute([
            ':backup_automatico' => $backup_automatico,
            ':frequencia' => $frequencia,
            ':hora_backup' => $hora_backup,
            ':dias_retencao' => $dias_retencao,
            ':incluir_uploads' => $incluir_uploads,
            ':notificar_email' => $notificar_email,
            ':email_notificacao' => $email_notificacao,
            ':empresa_id' => $empresa_id
        ]);
        
        $success = 'Configurações salvas com sucesso!';
        
        // Recarregar configurações
        $stmt = $db->prepare("SELECT * FROM backups_config WHERE empresa_id = :empresa_id");
        $stmt->execute([':empresa_id' => $empresa_id]);
        $config = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = 'Erro ao salvar: ' . $e->getMessage();
    }
}

// Gerar token para CRON
$cron_token = md5($empresa_id . 'backup_secret_' . date('Y'));
?>

<style>
.config-container {
    max-width: 800px;
    margin: 0 auto;
}

.form-card {
    background: var(--bg-primary);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    margin-bottom: 24px;
}

.form-header {
    padding: 16px 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.form-header h3 {
    margin: 0;
    font-size: 18px;
}

.form-header i {
    margin-right: 8px;
}

.form-body {
    padding: 24px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    font-size: 14px;
}

.form-group label i {
    margin-right: 6px;
    color: #667eea;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-group small {
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 4px;
    display: block;
}

.switch-group {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
}

.switch-group:last-child {
    border-bottom: none;
}

.switch-label {
    font-weight: 500;
}

.switch-label small {
    font-size: 11px;
    color: var(--text-secondary);
    display: block;
    font-weight: normal;
    margin-top: 4px;
}

.switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #667eea;
}

input:checked + .slider:before {
    transform: translateX(24px);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.btn {
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    justify-content: center;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
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

.alert-info {
    background: #bfdbfe;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}

.cron-box {
    background: #1e293b;
    color: #e2e8f0;
    padding: 16px;
    border-radius: 12px;
    font-family: monospace;
    font-size: 12px;
    margin-top: 16px;
    overflow-x: auto;
}

.cron-box code {
    display: block;
    white-space: pre-wrap;
    word-break: break-all;
}
</style>

<div class="config-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-cog"></i> Configurações de Backup</h3>
        </div>
        
        <div class="form-body">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <!-- Backup Automático -->
                <div class="switch-group">
                    <div class="switch-label">
                        Backup Automático
                        <small>Ativar backups automáticos agendados</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="backup_automatico" value="1" <?php echo $config['backup_automatico'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <!-- Frequência -->
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Frequência</label>
                    <select name="frequencia">
                        <option value="diario" <?php echo $config['frequencia'] == 'diario' ? 'selected' : ''; ?>>Diário</option>
                        <option value="semanal" <?php echo $config['frequencia'] == 'semanal' ? 'selected' : ''; ?>>Semanal</option>
                        <option value="mensal" <?php echo $config['frequencia'] == 'mensal' ? 'selected' : ''; ?>>Mensal</option>
                    </select>
                    <small>Frequência de execução dos backups automáticos</small>
                </div>
                
                <!-- Horário -->
                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Horário do Backup</label>
                    <input type="time" name="hora_backup" value="<?php echo substr($config['hora_backup'], 0, 5); ?>">
                    <small>Horário recomendado: madrugada (menor uso do sistema)</small>
                </div>
                
                <!-- Retenção -->
                <div class="form-group">
                    <label><i class="fas fa-database"></i> Dias de Retenção</label>
                    <input type="number" name="dias_retencao" value="<?php echo $config['dias_retencao']; ?>" min="1" max="365">
                    <small>Backups mais antigos que este período serão removidos automaticamente</small>
                </div>
                
                <!-- Incluir Uploads -->
                <div class="switch-group">
                    <div class="switch-label">
                        Incluir Arquivos de Upload
                        <small>Incluir fotos e documentos nos backups</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="incluir_uploads" value="1" <?php echo $config['incluir_uploads'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <!-- Notificação por E-mail -->
                <div class="switch-group">
                    <div class="switch-label">
                        Notificar por E-mail
                        <small>Receber notificações sobre status dos backups</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="notificar_email" value="1" <?php echo $config['notificar_email'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> E-mail para Notificação</label>
                    <input type="email" name="email_notificacao" value="<?php echo htmlspecialchars($config['email_notificacao'] ?? ''); ?>" placeholder="admin@empresa.com">
                    <small>E-mail que receberá as notificações de backup</small>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Configurações
                </button>
            </form>
        </div>
    </div>
    
    <!-- Configuração do CRON -->
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-terminal"></i> Configuração do CRON</h3>
        </div>
        <div class="form-body">
            <p>Para backups automáticos, configure o CRON do servidor:</p>
            <div class="cron-box">
                <strong>Via linha de comando (Linux):</strong><br>
                <code>0 2 * * * php <?php echo realpath(__DIR__); ?>/agendador.php</code>
                <br><br>
                <strong>Via Web (a cada hora):</strong><br>
                <code>0 * * * * wget -q -O - "<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/modules/backup/agendador.php?token=' . $cron_token; ?>"</code>
            </div>
            <div class="alert alert-info" style="margin-top: 16px;">
                <i class="fas fa-info-circle"></i>
                Token para execução via web: <strong><?php echo $cron_token; ?></strong>
            </div>
        </div>
    </div>
    
    <!-- Botão Voltar -->
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
</div>

<?php require_once '../../includes/footer.php'; ?>

