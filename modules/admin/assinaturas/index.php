<?php
// modules/admin/assinaturas/index.php - Gerenciar Assinaturas (CORRIGIDO)
// NÃO PODE HAVER NADA ANTES DESTA LINHA

require_once '../../../includes/config.php';

// Verificar se é super admin
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_tipo'] ?? '') !== 'super_admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$pageTitle = 'Assinaturas';
$activePage = 'admin_assinaturas';
require_once '../../../includes/header.php';
require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Parâmetros de filtro
$status = $_GET['status'] ?? '';
$empresa_id = $_GET['empresa_id'] ?? '';

// Buscar assinaturas
$query = "SELECT a.*, e.nome as empresa_nome, e.status as empresa_status, p.nome as plano_nome
          FROM assinaturas a
          JOIN empresas e ON a.empresa_id = e.id
          JOIN planos p ON a.plano_id = p.id
          WHERE 1=1";

$params = [];

if ($status) {
    $query .= " AND a.status = :status";
    $params[':status'] = $status;
}

if ($empresa_id) {
    $query .= " AND a.empresa_id = :empresa_id";
    $params[':empresa_id'] = $empresa_id;
}

$query .= " ORDER BY a.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$assinaturas = $stmt->fetchAll();

// Buscar empresas para filtro
$empresas = $db->query("SELECT id, nome FROM empresas ORDER BY nome")->fetchAll();

// Processar renovação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'renovar') {
    $id = $_POST['id'];
    $meses = $_POST['meses'] ?? 12;
    
    $stmt = $db->prepare("SELECT * FROM assinaturas WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $assinatura = $stmt->fetch();
    
    if ($assinatura) {
        $data_fim = date('Y-m-d', strtotime("+$meses months"));
        
        $stmt = $db->prepare("UPDATE assinaturas SET 
                              data_fim = :data_fim,
                              status = 'ativa',
                              updated_at = NOW()
                              WHERE id = :id");
        $stmt->execute([':data_fim' => $data_fim, ':id' => $id]);
        
        $success = "Assinatura renovada com sucesso!";
    }
}
?>

<style>
.assinatura-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 16px;
    border: 1px solid var(--border-color);
    transition: var(--transition);
}

.assinatura-card:hover {
    box-shadow: var(--shadow-md);
}

.assinatura-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
}

.empresa-nome {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 4px;
}

.assinatura-detalhes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    padding: 12px;
    background: var(--bg-secondary);
    border-radius: 12px;
    margin: 12px 0;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--bg-primary);
    border-radius: 24px;
    padding: 32px;
    max-width: 400px;
    width: 90%;
}

.assinatura-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-ativa { background: #d1fae5; color: #059669; }
.status-cancelada { background: #fee2e2; color: #dc2626; }
.status-suspensa { background: #fef3c7; color: #d97706; }

.vencer-badge {
    background: #fef3c7;
    color: #d97706;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    margin-left: 8px;
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-receipt"></i> Assinaturas</h2>
        <p>Gerencie todas as assinaturas da plataforma</p>
    </div>
</div>

<!-- Filtros -->
<div class="filters-card">
    <form method="GET" action="" class="filters-form">
        <div class="filter-group">
            <label>Status</label>
            <select name="status">
                <option value="">Todos</option>
                <option value="ativa" <?php echo $status == 'ativa' ? 'selected' : ''; ?>>Ativas</option>
                <option value="cancelada" <?php echo $status == 'cancelada' ? 'selected' : ''; ?>>Canceladas</option>
                <option value="suspensa" <?php echo $status == 'suspensa' ? 'selected' : ''; ?>>Suspensas</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Empresa</label>
            <select name="empresa_id">
                <option value="">Todas</option>
                <?php foreach ($empresas as $emp): ?>
                <option value="<?php echo $emp['id']; ?>" <?php echo $empresa_id == $emp['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($emp['nome']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="index.php" class="btn btn-secondary">Limpar</a>
        </div>
    </form>
</div>

<!-- Lista de Assinaturas -->
<?php foreach ($assinaturas as $assinatura): ?>
<div class="assinatura-card">
    <div class="assinatura-header">
        <div>
            <div class="empresa-nome"><?php echo htmlspecialchars($assinatura['empresa_nome']); ?></div>
            <div style="font-size: 13px; color: var(--text-secondary);">
                <i class="fas fa-crown"></i> <?php echo htmlspecialchars($assinatura['plano_nome']); ?>
            </div>
        </div>
        <div>
            <span class="assinatura-status status-<?php echo $assinatura['status']; ?>">
                <?php echo ucfirst($assinatura['status']); ?>
            </span>
        </div>
    </div>
    
    <div class="assinatura-detalhes">
        <div>
            <div style="font-size: 11px; color: var(--text-secondary);">Valor</div>
            <div style="font-weight: 600;">R$ <?php echo number_format($assinatura['valor'], 2, ',', '.'); ?></div>
            <div style="font-size: 11px;">/ <?php echo $assinatura['ciclo']; ?></div>
        </div>
        <div>
            <div style="font-size: 11px; color: var(--text-secondary);">Início</div>
            <div><?php echo date('d/m/Y', strtotime($assinatura['data_inicio'])); ?></div>
        </div>
        <div>
            <div style="font-size: 11px; color: var(--text-secondary);">Fim</div>
            <div>
                <?php echo $assinatura['data_fim'] ? date('d/m/Y', strtotime($assinatura['data_fim'])) : '--'; ?>
                <?php if ($assinatura['data_fim'] && strtotime($assinatura['data_fim']) < strtotime('+30 days')): ?>
                    <span class="vencer-badge">Vence em breve</span>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <div style="font-size: 11px; color: var(--text-secondary);">Cadastro</div>
            <div><?php echo date('d/m/Y', strtotime($assinatura['created_at'])); ?></div>
        </div>
    </div>
    
    <?php if ($assinatura['status'] == 'ativa' && $assinatura['data_fim'] && strtotime($assinatura['data_fim']) < strtotime('+60 days')): ?>
    <div style="margin-top: 16px;">
        <button onclick="abrirModalRenovar(<?php echo $assinatura['id']; ?>)" class="btn btn-primary" style="width: 100%;">
            <i class="fas fa-sync-alt"></i> Renovar Assinatura
        </button>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (empty($assinaturas)): ?>
<div class="empty-state" style="text-align: center; padding: 60px;">
    <i class="fas fa-receipt" style="font-size: 48px; color: #ccc;"></i>
    <p style="margin-top: 16px;">Nenhuma assinatura encontrada</p>
</div>
<?php endif; ?>

<!-- Modal Renovação -->
<div id="modalRenovar" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-sync-alt"></i> Renovar Assinatura</h3>
        <form method="POST" action="">
            <input type="hidden" name="acao" value="renovar">
            <input type="hidden" name="id" id="renovarId">
            
            <div class="form-group">
                <label>Período de renovação</label>
                <select name="meses">
                    <option value="1">1 mês</option>
                    <option value="3">3 meses</option>
                    <option value="6">6 meses</option>
                    <option value="12" selected>12 meses (Recomendado)</option>
                </select>
            </div>
            
            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Confirmar Renovação</button>
                <button type="button" onclick="fecharModal()" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalRenovar(id) {
    document.getElementById('renovarId').value = id;
    document.getElementById('modalRenovar').style.display = 'flex';
}

<?php if (isset($_GET['renovar']) && ctype_digit((string) $_GET['renovar'])): ?>
document.addEventListener('DOMContentLoaded', function () {
    abrirModalRenovar(<?php echo (int) $_GET['renovar']; ?>);
});
<?php endif; ?>

function fecharModal() {
    document.getElementById('modalRenovar').style.display = 'none';
}
</script>

<?php require_once '../../../includes/footer.php'; ?>
