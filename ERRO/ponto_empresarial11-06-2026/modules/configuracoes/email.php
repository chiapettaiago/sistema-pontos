<?php
// modules/configuracoes/email.php - Configurações de E-mail (SMTP)
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

$pageTitle = 'Configurações de E-mail';
$activePage = 'configuracoes';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$success = '';
$error = '';
$test_result = '';

// Buscar configurações atuais
$stmt = $db->prepare("SELECT * FROM config_email WHERE empresa_id = :empresa_id");
$stmt->execute([':empresa_id' => $empresa_id]);
$config = $stmt->fetch();

if (!$config) {
    // Criar configuração padrão
    $stmt = $db->prepare("INSERT INTO config_email (empresa_id) VALUES (:empresa_id)");
    $stmt->execute([':empresa_id' => $empresa_id]);
    
    $stmt = $db->prepare("SELECT * FROM config_email WHERE empresa_id = :empresa_id");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $config = $stmt->fetch();
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['salvar'])) {
        $smtp_host = trim($_POST['smtp_host'] ?? 'smtp.gmail.com');
        $smtp_port = (int)($_POST['smtp_port'] ?? 587);
        $smtp_user = trim($_POST['smtp_user'] ?? '');
        $smtp_password = $_POST['smtp_password'] ?? '';
        $smtp_secure = $_POST['smtp_secure'] ?? 'tls';
        $email_remetente = trim($_POST['email_remetente'] ?? '');
        $nome_remetente = trim($_POST['nome_remetente'] ?? 'PontoFácil');
        $notificacoes_ativas = isset($_POST['notificacoes_ativas']) ? 1 : 0;
        
        // Se a senha não foi alterada, manter a existente
        if (empty($smtp_password)) {
            $smtp_password = $config['smtp_password'];
        } else {
            // Criptografar a senha
            $smtp_password = base64_encode($smtp_password);
        }
        
        try {
            $stmt = $db->prepare("UPDATE config_email SET 
                smtp_host = :smtp_host,
                smtp_port = :smtp_port,
                smtp_user = :smtp_user,
                smtp_password = :smtp_password,
                smtp_secure = :smtp_secure,
                email_remetente = :email_remetente,
                nome_remetente = :nome_remetente,
                notificacoes_ativas = :notificacoes_ativas
                WHERE empresa_id = :empresa_id");
            
            $stmt->execute([
                ':smtp_host' => $smtp_host,
                ':smtp_port' => $smtp_port,
                ':smtp_user' => $smtp_user,
                ':smtp_password' => $smtp_password,
                ':smtp_secure' => $smtp_secure,
                ':email_remetente' => $email_remetente,
                ':nome_remetente' => $nome_remetente,
                ':notificacoes_ativas' => $notificacoes_ativas,
                ':empresa_id' => $empresa_id
            ]);
            
            $success = 'Configurações de e-mail salvas com sucesso!';
            
            // Recarregar configurações
            $stmt = $db->prepare("SELECT * FROM config_email WHERE empresa_id = :empresa_id");
            $stmt->execute([':empresa_id' => $empresa_id]);
            $config = $stmt->fetch();
            
        } catch (Exception $e) {
            $error = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
    
    // Testar conexão SMTP
    if (isset($_POST['testar'])) {
        $email_teste = trim($_POST['email_teste'] ?? $_SESSION['usuario_email'] ?? '');
        
        if (empty($email_teste)) {
            $error = 'Digite um e-mail para teste';
        } else {
            require_once '../../includes/mail.php';
            
            $assunto = '🧪 Teste de E-mail - PontoFácil';
            $mensagem = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h2>✅ Teste de E-mail</h2>
                    </div>
                    <div class="content">
                        <p>Este é um e-mail de teste do sistema <strong>PontoFácil</strong>.</p>
                        <p>Se você está recebendo esta mensagem, as configurações de e-mail estão funcionando corretamente!</p>
                        <p>Data do teste: ' . date('d/m/Y H:i:s') . '</p>
                        <p>Configurações utilizadas:</p>
                        <ul>
                            <li>SMTP Host: ' . $config['smtp_host'] . '</li>
                            <li>SMTP Port: ' . $config['smtp_port'] . '</li>
                            <li>SMTP Secure: ' . strtoupper($config['smtp_secure']) . '</li>
                            <li>Remetente: ' . $config['email_remetente'] . '</li>
                        </ul>
                    </div>
                    <div class="footer">
                        <p>&copy; ' . date('Y') . ' PontoFácil - Sistema de Ponto Eletrônico</p>
                    </div>
                </div>
            </body>
            </html>';
            
            if (enviarEmail($email_teste, $assunto, $mensagem)) {
                $test_result = 'success';
                $success = "E-mail de teste enviado com sucesso para <strong>{$email_teste}</strong>! Verifique sua caixa de entrada.";
            } else {
                $test_result = 'error';
                $error = "Falha ao enviar e-mail de teste. Verifique as configurações de SMTP.";
            }
        }
    }
}

// Provedores SMTP comuns
$smtp_providers = [
    'Gmail' => ['host' => 'smtp.gmail.com', 'port' => 587, 'secure' => 'tls'],
    'Outlook/Hotmail' => ['host' => 'smtp-mail.outlook.com', 'port' => 587, 'secure' => 'tls'],
    'Yahoo' => ['host' => 'smtp.mail.yahoo.com', 'port' => 587, 'secure' => 'tls'],
    'UOL' => ['host' => 'smtp.uol.com.br', 'port' => 587, 'secure' => 'tls'],
    'Terra' => ['host' => 'smtp.terra.com.br', 'port' => 587, 'secure' => 'tls'],
    'Bol' => ['host' => 'smtp.bol.com.br', 'port' => 587, 'secure' => 'tls'],
    'Amazon SES' => ['host' => 'email-smtp.us-east-1.amazonaws.com', 'port' => 587, 'secure' => 'tls'],
    'SendGrid' => ['host' => 'smtp.sendgrid.net', 'port' => 587, 'secure' => 'tls'],
    'Mailgun' => ['host' => 'smtp.mailgun.org', 'port' => 587, 'secure' => 'tls'],
];
?>

<style>
.config-container {
    max-width: 800px;
    margin: 0 auto;
}

.module-header {
    margin-bottom: 24px;
}

.module-title h2 {
    margin: 0;
    font-size: 24px;
}

.module-title p {
    margin: 8px 0 0 0;
    color: var(--text-secondary);
    font-size: 14px;
}

.config-card {
    background: var(--bg-primary);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    margin-bottom: 24px;
    overflow: hidden;
}

.config-header {
    padding: 16px 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.config-header i {
    margin-right: 8px;
}

.config-header h3 {
    margin: 0;
    font-size: 18px;
}

.config-body {
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
    transition: all 0.3s;
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

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-row .form-group {
    margin-bottom: 0;
}

.switch-group {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 20px;
}

.switch-group:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
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

.btn-secondary:hover {
    background: var(--bg-tertiary);
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
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

.form-actions {
    display: flex;
    gap: 16px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}

.info-box {
    background: #e0e7ff;
    border-left: 4px solid #667eea;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.info-box p {
    margin: 0;
    font-size: 13px;
    color: #1e40af;
}

.test-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    
    .switch-group {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        justify-content: center;
    }
}
</style>

<div class="config-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-envelope"></i> Configurações de E-mail</h2>
            <p>Configure o servidor SMTP para envio de notificações por e-mail</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-server"></i>
                <h3>Configuração SMTP</h3>
            </div>
            <div class="config-body">
                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> Configure as informações do seu servidor de e-mail para enviar notificações, lembretes e alertas para os usuários.</p>
                </div>

                <!-- Provedor Rápido -->
                <div class="form-group">
                    <label><i class="fas fa-cloud"></i> Provedor Rápido</label>
                    <select id="smtpProvider" onchange="carregarProvedor(this.value)">
                        <option value="">Selecione um provedor...</option>
                        <?php foreach ($smtp_providers as $nome => $dados): ?>
                            <option value="<?php echo $nome; ?>"><?php echo $nome; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Selecione um provedor para preencher automaticamente as configurações</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-globe"></i> Servidor SMTP</label>
                        <input type="text" name="smtp_host" id="smtp_host" value="<?php echo htmlspecialchars($config['smtp_host']); ?>" placeholder="smtp.gmail.com">
                        <small>Endereço do servidor SMTP</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-plug"></i> Porta SMTP</label>
                        <input type="number" name="smtp_port" id="smtp_port" value="<?php echo $config['smtp_port']; ?>" placeholder="587">
                        <small>Normalmente 587 (TLS) ou 465 (SSL)</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Tipo de Segurança</label>
                        <select name="smtp_secure" id="smtp_secure">
                            <option value="tls" <?php echo $config['smtp_secure'] == 'tls' ? 'selected' : ''; ?>>TLS (Recomendado)</option>
                            <option value="ssl" <?php echo $config['smtp_secure'] == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        </select>
                        <small>TLS geralmente usa porta 587, SSL usa porta 465</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Usuário SMTP</label>
                        <input type="email" name="smtp_user" id="smtp_user" value="<?php echo htmlspecialchars($config['smtp_user']); ?>" placeholder="seu@email.com">
                        <small>Seu e-mail completo</small>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-key"></i> Senha SMTP</label>
                    <input type="password" name="smtp_password" id="smtp_password" placeholder="••••••••">
                    <small>Deixe em branco para manter a senha atual</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-paper-plane"></i> E-mail Remetente</label>
                        <input type="email" name="email_remetente" id="email_remetente" value="<?php echo htmlspecialchars($config['email_remetente']); ?>" placeholder="noreply@pontofacil.com">
                        <small>E-mail que aparecerá como remetente</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Nome do Remetente</label>
                        <input type="text" name="nome_remetente" id="nome_remetente" value="<?php echo htmlspecialchars($config['nome_remetente']); ?>" placeholder="PontoFácil">
                        <small>Nome que aparecerá como remetente</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configurações de Notificações -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-bell"></i>
                <h3>Notificações por E-mail</h3>
            </div>
            <div class="config-body">
                <div class="switch-group">
                    <div class="switch-label">
                        Ativar Notificações por E-mail
                        <small>Enviar notificações automáticas por e-mail</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="notificacoes_ativas" value="1" <?php echo $config['notificacoes_ativas'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="test-section">
                    <h4 style="margin-bottom: 16px;"><i class="fas fa-vial"></i> Testar Configuração</h4>
                    <div class="form-group">
                        <label>E-mail para teste</label>
                        <input type="email" name="email_teste" placeholder="seu@email.com" value="<?php echo $_SESSION['usuario_email'] ?? ''; ?>">
                        <small>Digite um e-mail para receber o teste</small>
                    </div>
                    <button type="submit" name="testar" class="btn btn-success">
                        <i class="fas fa-paper-plane"></i> Enviar E-mail de Teste
                    </button>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="salvar" class="btn btn-primary">
                <i class="fas fa-save"></i> Salvar Configurações
            </button>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </form>

    <!-- Dicas para Configuração -->
    <div class="config-card">
        <div class="config-header">
            <i class="fas fa-lightbulb"></i>
            <h3>Dicas para Configuração</h3>
        </div>
        <div class="config-body">
            <ul style="margin: 0; padding-left: 20px; color: var(--text-secondary);">
                <li><strong>Gmail:</strong> 
                    <ul>
                        <li>Ative "Acesso a aplicativos menos seguros" ou crie uma "Senha de App"</li>
                        <li>Servidor: smtp.gmail.com | Porta: 587 | TLS</li>
                    </ul>
                </li>
                <li><strong>Outlook/Hotmail/Live:</strong> 
                    <ul>
                        <li>Servidor: smtp-mail.outlook.com | Porta: 587 | TLS</li>
                    </ul>
                </li>
                <li><strong>Yahoo:</strong> 
                    <ul>
                        <li>Servidor: smtp.mail.yahoo.com | Porta: 587 | TLS</li>
                    </ul>
                </li>
                <li><strong>Provedores Brasileiros:</strong>
                    <ul>
                        <li>UOL: smtp.uol.com.br | Porta: 587 | TLS</li>
                        <li>Terra: smtp.terra.com.br | Porta: 587 | TLS</li>
                        <li>Bol: smtp.bol.com.br | Porta: 587 | TLS</li>
                    </ul>
                </li>
                <li><strong>Amazon SES / SendGrid / Mailgun:</strong> Consulte a documentação do provedor</li>
            </ul>
        </div>
    </div>
</div>

<script>
function carregarProvedor(provedor) {
    const providers = <?php echo json_encode($smtp_providers); ?>;
    
    if (providers[provedor]) {
        document.getElementById('smtp_host').value = providers[provedor].host;
        document.getElementById('smtp_port').value = providers[provedor].port;
        document.getElementById('smtp_secure').value = providers[provedor].secure;
        document.getElementById('smtp_user').focus();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>