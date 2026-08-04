<?php
// modules/notificacoes/config.php - Configurações de Notificações
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Configurações de Notificações';
$activePage = 'notificacoes';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$usuario_id = $_SESSION['usuario_id'];
$success = '';
$error = '';

// Processar configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notificar_ponto = isset($_POST['notificar_ponto']) ? 1 : 0;
    $notificar_ponto_lembrete = isset($_POST['notificar_ponto_lembrete']) ? 1 : 0;
    $notificar_ponto_facial = isset($_POST['notificar_ponto_facial']) ? 1 : 0;
    $notificar_solicitacao = isset($_POST['notificar_solicitacao']) ? 1 : 0;
    $notificar_aprovacao = isset($_POST['notificar_aprovacao']) ? 1 : 0;
    $notificar_sistema = isset($_POST['notificar_sistema']) ? 1 : 0;
    $notificar_atraso = isset($_POST['notificar_atraso']) ? 1 : 0;
    $lembretes_email = isset($_POST['lembretes_email']) ? 1 : 0;
    $lembrete_entrada = $_POST['lembrete_entrada'] ?? '07:45:00';
    $lembrete_almoco = $_POST['lembrete_almoco'] ?? '11:45:00';
    $lembrete_saida = $_POST['lembrete_saida'] ?? '17:45:00';
    
    try {
        $stmt = $db->prepare("INSERT INTO notificacoes_config 
            (usuario_id, notificar_ponto, notificar_ponto_lembrete, notificar_ponto_facial,
             notificar_solicitacao, notificar_aprovacao, notificar_sistema, notificar_atraso,
             lembretes_email, lembrete_entrada, lembrete_almoco, lembrete_saida)
            VALUES (:usuario_id, :ponto, :lembrete, :facial, :solicitacao, :aprovacao, 
                    :sistema, :atraso, :email, :entrada, :almoco, :saida)
            ON DUPLICATE KEY UPDATE
            notificar_ponto = VALUES(notificar_ponto),
            notificar_ponto_lembrete = VALUES(notificar_ponto_lembrete),
            notificar_ponto_facial = VALUES(notificar_ponto_facial),
            notificar_solicitacao = VALUES(notificar_solicitacao),
            notificar_aprovacao = VALUES(notificar_aprovacao),
            notificar_sistema = VALUES(notificar_sistema),
            notificar_atraso = VALUES(notificar_atraso),
            lembretes_email = VALUES(lembretes_email),
            lembrete_entrada = VALUES(lembrete_entrada),
            lembrete_almoco = VALUES(lembrete_almoco),
            lembrete_saida = VALUES(lembrete_saida)");
        
        $stmt->execute([
            ':usuario_id' => $usuario_id,
            ':ponto' => $notificar_ponto,
            ':lembrete' => $notificar_ponto_lembrete,
            ':facial' => $notificar_ponto_facial,
            ':solicitacao' => $notificar_solicitacao,
            ':aprovacao' => $notificar_aprovacao,
            ':sistema' => $notificar_sistema,
            ':atraso' => $notificar_atraso,
            ':email' => $lembretes_email,
            ':entrada' => $lembrete_entrada,
            ':almoco' => $lembrete_almoco,
            ':saida' => $lembrete_saida
        ]);
        
        $success = 'Configurações salvas com sucesso!';
        
    } catch (Exception $e) {
        $error = 'Erro ao salvar configurações: ' . $e->getMessage();
    }
}

// Buscar configurações atuais
$stmt = $db->prepare("SELECT * FROM notificacoes_config WHERE usuario_id = :usuario_id");
$stmt->execute([':usuario_id' => $usuario_id]);
$config = $stmt->fetch();

// Valores padrão se não existir
if (!$config) {
    $config = [
        'notificar_ponto' => 1,
        'notificar_ponto_lembrete' => 1,
        'notificar_ponto_facial' => 1,
        'notificar_solicitacao' => 1,
        'notificar_aprovacao' => 1,
        'notificar_sistema' => 1,
        'notificar_atraso' => 1,
        'lembretes_email' => 0,
        'lembrete_entrada' => '07:45:00',
        'lembrete_almoco' => '11:45:00',
        'lembrete_saida' => '17:45:00'
    ];
}
?>

<style>
.config-container {
    max-width: 800px;
    margin: 0 auto;
}

.module-header {
    margin-bottom: 24px;
}

.module-header h2 {
    margin: 0 0 5px 0;
    font-size: 24px;
}

.module-header p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 14px;
}

.config-card {
    background: var(--bg-primary);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    margin-bottom: 24px;
    overflow: hidden;
}

.config-header {
    padding: 16px 20px;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
    font-weight: 600;
    font-size: 16px;
}

.config-header i {
    margin-right: 8px;
    color: #667eea;
}

.config-body {
    padding: 20px;
}

.config-group {
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
}

.config-group:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.config-label {
    font-weight: 500;
}

.config-label small {
    font-size: 11px;
    color: var(--text-secondary);
    display: block;
    margin-top: 2px;
}

.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
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
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
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
    transform: translateX(26px);
}

.time-input {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: 14px;
}

.btn-salvar {
    padding: 12px 24px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 500;
    width: 100%;
}

.btn-salvar:hover {
    transform: translateY(-2px);
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

hr {
    margin: 20px 0;
    border-color: var(--border-color);
}
</style>

<div class="config-container">
    <div class="module-header">
        <h2><i class="fas fa-sliders-h"></i> Configurações de Notificações</h2>
        <p>Personalize como e quando você recebe notificações</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <!-- Tipos de Notificação -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-bell"></i> Tipos de Notificação
            </div>
            <div class="config-body">
                <div class="config-group">
                    <div class="config-label">
                        Notificações de Ponto
                        <small>Alertas ao bater ponto</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="notificar_ponto" value="1" <?php echo $config['notificar_ponto'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="config-group">
                    <div class="config-label">
                        Lembretes de Ponto
                        <small>Lembretes para bater ponto</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="notificar_ponto_lembrete" value="1" <?php echo $config['notificar_ponto_lembrete'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="config-group">
                    <div class="config-label">
                        Confirmação Facial
                        <small>Confirmação ao usar reconhecimento facial</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="notificar_ponto_facial" value="1" <?php echo $config['notificar_ponto_facial'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="config-group">
                    <div class="config-label">
                        Solicitações
                        <small>Status de solicitações (pendentes/aprovadas)</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="notificar_solicitacao" value="1" <?php echo $config['notificar_solicitacao'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="config-group">
                    <div class="config-label">
                        Aprovações/Rejeições
                        <small>Quando sua solicitação for respondida</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="notificar_aprovacao" value="1" <?php echo $config['notificar_aprovacao'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="config-group">
                    <div class="config-label">
                        Alertas de Atraso
                        <small>Notificações quando você atrasar</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="notificar_atraso" value="1" <?php echo $config['notificar_atraso'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="config-group">
                    <div class="config-label">
                        Notificações do Sistema
                        <small>Atualizações e comunicados do sistema</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="notificar_sistema" value="1" <?php echo $config['notificar_sistema'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Lembretes por E-mail -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-envelope"></i> Lembretes por E-mail
            </div>
            <div class="config-body">
                <div class="config-group">
                    <div class="config-label">
                        Enviar lembretes por e-mail
                        <small>Receba lembretes também no seu e-mail</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="lembretes_email" value="1" <?php echo $config['lembretes_email'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Horários dos Lembretes -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-clock"></i> Horários dos Lembretes
            </div>
            <div class="config-body">
                <div class="config-group">
                    <div class="config-label">
                        Lembrete de Entrada
                        <small>Horário para lembrar de bater o ponto de entrada</small>
                    </div>
                    <input type="time" name="lembrete_entrada" class="time-input" value="<?php echo substr($config['lembrete_entrada'], 0, 5); ?>">
                </div>
                
                <div class="config-group">
                    <div class="config-label">
                        Lembrete de Almoço
                        <small>Horário para lembrar de bater o ponto de almoço</small>
                    </div>
                    <input type="time" name="lembrete_almoco" class="time-input" value="<?php echo substr($config['lembrete_almoco'], 0, 5); ?>">
                </div>
                
                <div class="config-group">
                    <div class="config-label">
                        Lembrete de Saída
                        <small>Horário para lembrar de bater o ponto de saída</small>
                    </div>
                    <input type="time" name="lembrete_saida" class="time-input" value="<?php echo substr($config['lembrete_saida'], 0, 5); ?>">
                </div>
            </div>
        </div>

        <button type="submit" class="btn-salvar">
            <i class="fas fa-save"></i> Salvar Configurações
        </button>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>