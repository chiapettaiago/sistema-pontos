<?php
// modules/auditoria/limpar.php - Limpar logs antigos
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

$pageTitle = 'Limpar Logs Antigos';
$activePage = 'auditoria';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;

// Buscar configurações
$stmt = $db->prepare("SELECT dias_retencao FROM auditoria_config WHERE empresa_id = :empresa_id");
$stmt->execute([':empresa_id' => $empresa_id]);
$config = $stmt->fetch();
$dias_retencao = $config['dias_retencao'] ?? 90;

$success = '';
$error = '';
$total_afetados = 0;

// Processar limpeza
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dias = (int)($_POST['dias'] ?? $dias_retencao);
    $confirmar = isset($_POST['confirmar']) && $_POST['confirmar'] == '1';
    
    if (!$confirmar) {
        $error = 'Você precisa confirmar a limpeza marcando a opção acima.';
    } else {
        $data_limite = date('Y-m-d H:i:s', strtotime("-$dias days"));
        
        // Contar registros que serão removidos
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM logs_auditoria WHERE created_at < :data_limite");
        $stmt->execute([':data_limite' => $data_limite]);
        $total_afetados = $stmt->fetch()['total'];
        
        if ($total_afetados == 0) {
            $error = 'Não há registros antigos para remover.';
        } else {
            try {
                // Remover logs antigos
                $stmt = $db->prepare("DELETE FROM logs_auditoria WHERE created_at < :data_limite");
                $stmt->execute([':data_limite' => $data_limite]);
                
                // Registrar ação de limpeza
                $stmt = $db->prepare("INSERT INTO logs_auditoria 
                    (usuario_id, usuario_nome, usuario_email, usuario_tipo, acao, modulo, descricao, ip_address, created_at) 
                    VALUES 
                    (:usuario_id, :nome, :email, :tipo, 'DELETE', 'auditoria', :descricao, :ip, NOW())");
                $stmt->execute([
                    ':usuario_id' => $_SESSION['usuario_id'],
                    ':nome' => $_SESSION['usuario_nome'],
                    ':email' => $_SESSION['usuario_email'],
                    ':tipo' => $_SESSION['usuario_tipo'],
                    ':descricao' => "Limpeza de logs de auditoria: removidos {$total_afetados} registros com mais de {$dias} dias",
                    ':ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                $success = "{$total_afetados} registros de log removidos com sucesso!";
                
            } catch (Exception $e) {
                $error = 'Erro ao limpar logs: ' . $e->getMessage();
            }
        }
    }
}

// Contar logs por período
$stmt = $db->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as ultimos_30,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as entre_30_60,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 60 DAY) THEN 1 ELSE 0 END) as entre_60_90,
    SUM(CASE WHEN created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) as mais_90
FROM logs_auditoria");
$stmt->execute();
$stats = $stmt->fetch();
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

.stats-box {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
}

.stats-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid var(--border-color);
}

.stats-item:last-child {
    border-bottom: none;
}

.stats-label {
    font-weight: 500;
}

.stats-value {
    font-weight: 600;
}

.stats-value.old {
    color: #dc2626;
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

.form-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: 14px;
}

.form-group small {
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 4px;
    display: block;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 20px 0;
    padding: 12px;
    background: var(--bg-secondary);
    border-radius: 12px;
}

.checkbox-group input {
    width: 18px;
    height: 18px;
    margin: 0;
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

<div class="config-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-trash-alt"></i> Limpar Logs Antigos</h3>
            <p>Remova registros de auditoria antigos para liberar espaço</p>
        </div>
        
        <div class="form-body">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
                <a href="index.php" class="btn btn-secondary">Voltar para Auditoria</a>
            <?php elseif ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <a href="index.php" class="btn btn-secondary">Voltar</a>
            <?php else: ?>
                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Esta ação não pode ser desfeita!</strong><br>
                    Os logs removidos não poderão ser recuperados posteriormente.
                </div>
                
                <div class="stats-box">
                    <h4 style="margin-bottom: 12px;">📊 Estatísticas de Logs</h4>
                    <div class="stats-item">
                        <span class="stats-label">Total de registros:</span>
                        <span class="stats-value"><?php echo number_format($stats['total'] ?? 0); ?></span>
                    </div>
                    <div class="stats-item">
                        <span class="stats-label">Últimos 30 dias:</span>
                        <span class="stats-value"><?php echo number_format($stats['ultimos_30'] ?? 0); ?></span>
                    </div>
                    <div class="stats-item">
                        <span class="stats-label">Entre 30 e 60 dias:</span>
                        <span class="stats-value"><?php echo number_format($stats['entre_30_60'] ?? 0); ?></span>
                    </div>
                    <div class="stats-item">
                        <span class="stats-label">Entre 60 e 90 dias:</span>
                        <span class="stats-value"><?php echo number_format($stats['entre_60_90'] ?? 0); ?></span>
                    </div>
                    <div class="stats-item">
                        <span class="stats-label">Mais de 90 dias:</span>
                        <span class="stats-value old"><?php echo number_format($stats['mais_90'] ?? 0); ?></span>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Remover logs com mais de (dias):</label>
                        <input type="number" name="dias" value="<?php echo $dias_retencao; ?>" min="1" max="365" required>
                        <small>A configuração atual de retenção é de <?php echo $dias_retencao; ?> dias</small>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="confirmar" value="1" id="confirmar">
                        <label for="confirmar">Sim, tenho certeza que quero remover os logs antigos permanentemente.</label>
                    </div>
                    
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Limpar Logs Antigos
                    </button>
                </form>
                
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>