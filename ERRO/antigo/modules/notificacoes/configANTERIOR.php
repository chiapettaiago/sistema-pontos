<?php
// modules/notificacoes/config.php - Configuração do módulo
$pageTitle = 'Configurações de Notificações';
$activePage = 'notificacoes';
require_once '../../includes/header.php';
require_once '../../config/database.php';

checkModuleAccess('admin');

$database = new Database();
$db = $database->getConnection();

// Buscar configurações
$query = "SELECT * FROM configuracoes WHERE grupo = 'notificacoes'";
$stmt = $db->query($query);
$configs = [];
while ($row = $stmt->fetch()) {
    $configs[$row['chave']] = $row['valor'];
}

// Salvar configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_notificacoes = $_POST['email_notificacoes'] ?? '0';
    $email_lembretes = $_POST['email_lembretes'] ?? '0';
    $email_atrasos = $_POST['email_atrasos'] ?? '0';
    $email_aprovacoes = $_POST['email_aprovacoes'] ?? '0';
    $cron_token = $_POST['cron_token'] ?? '';
    
    $query = "INSERT INTO configuracoes (chave, valor, grupo) VALUES 
              ('email_notificacoes', :email_notificacoes, 'notificacoes'),
              ('email_lembretes', :email_lembretes, 'notificacoes'),
              ('email_atrasos', :email_atrasos, 'notificacoes'),
              ('email_aprovacoes', :email_aprovacoes, 'notificacoes'),
              ('cron_token', :cron_token, 'notificacoes')
              ON DUPLICATE KEY UPDATE valor = VALUES(valor)";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':email_notificacoes' => $email_notificacoes,
        ':email_lembretes' => $email_lembretes,
        ':email_atrasos' => $email_atrasos,
        ':email_aprovacoes' => $email_aprovacoes,
        ':cron_token' => $cron_token
    ]);
    
    $success = "Configurações salvas com sucesso!";
}
?>

<style>
.config-section {
    margin-bottom: 24px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border-color);
}

.config-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
}

.config-item label {
    font-weight: 500;
    cursor: pointer;
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

.cron-info {
    background: var(--bg-secondary);
    padding: 16px;
    border-radius: 12px;
    margin-top: 20px;
}

.cron-info code {
    display: block;
    background: var(--bg-primary);
    padding: 10px;
    border-radius: 6px;
    margin-top: 8px;
    font-family: monospace;
    font-size: 12px;
}
</style>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-bell"></i> Configurações de Notificações</h3>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="form-main">
            <div class="config-section">
                <h4><i class="fas fa-envelope"></i> Notificações por E-mail</h4>
                
                <div class="config-item">
                    <label>Ativar notificações por e-mail</label>
                    <label class="switch">
                        <input type="checkbox" name="email_notificacoes" value="1" 
                               <?php echo ($configs['email_notificacoes'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="config-item">
                    <label>Enviar lembretes de ponto</label>
                    <label class="switch">
                        <input type="checkbox" name="email_lembretes" value="1"
                               <?php echo ($configs['email_lembretes'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="config-item">
                    <label>Alertas de atraso</label>
                    <label class="switch">
                        <input type="checkbox" name="email_atrasos" value="1"
                               <?php echo ($configs['email_atrasos'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="config-item">
                    <label>Notificações de aprovação/rejeição</label>
                    <label class="switch">
                        <input type="checkbox" name="email_aprovacoes" value="1"
                               <?php echo ($configs['email_aprovacoes'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
            
            <div class="config-section">
                <h4><i class="fas fa-clock"></i> Agendador (CRON)</h4>
                
                <div class="form-group">
                    <label>Token de Segurança</label>
                    <input type="text" name="cron_token" value="<?php echo htmlspecialchars($configs['cron_token'] ?? ''); ?>">
                    <small>Token para execução segura do agendador via web</small>
                </div>
                
                <div class="cron-info">
                    <strong><i class="fas fa-terminal"></i> Configuração do CRON:</strong>
                    <p>Para executar o agendador automaticamente, adicione esta linha ao seu crontab:</p>
                    <code>* * * * * php /caminho/para/ponto_empresarial/modules/notificacoes/agendador.php</code>
                    <p style="margin-top: 10px;">Ou via web (a cada 5 minutos):</p>
                    <code>*/5 * * * * wget -q -O - "http://localhost/ponto_empresarial/modules/notificacoes/agendador.php?token=<?php echo $configs['cron_token'] ?? ''; ?>"</code>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Configurações
                </button>
                <a href="testar.php" class="btn btn-secondary">
                    <i class="fas fa-paper-plane"></i> Testar Envio
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>