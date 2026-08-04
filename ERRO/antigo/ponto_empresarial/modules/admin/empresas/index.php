<?php
// modules/admin/empresas/index.php - Lista de Empresas (COMPLETO)
// NÃO PODE HAVER NADA ANTES DESTA LINHA

session_start();

// Verificar se está logado e é super admin
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_tipo'] ?? '') !== 'super_admin') {
    header('Location: /ponto_empresarial/login.php');
    exit;
}

$pageTitle = 'Empresas';
$activePage = 'admin_empresas';
require_once '../../../includes/header.php';
require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Exibir mensagem se existir
if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    $tipo_mensagem = $_SESSION['tipo_mensagem'] ?? 'info';
    echo "<div class='alert alert-{$tipo_mensagem}' style='margin-bottom: 20px;'>
            <i class='fas " . ($tipo_mensagem == 'success' ? 'fa-check-circle' : 'fa-info-circle') . "'></i> 
            {$mensagem}
          </div>";
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);
}

// Buscar empresas
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$query = "SELECT e.*, 
          (SELECT COUNT(*) FROM funcionarios WHERE empresa_id = e.id) as total_funcionarios,
          (SELECT COUNT(*) FROM assinaturas WHERE empresa_id = e.id AND status = 'ativa') as tem_assinatura
          FROM empresas e
          WHERE 1=1";

$params = [];

if ($search) {
    $query .= " AND (e.nome LIKE :search OR e.email LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($status) {
    $query .= " AND e.status = :status";
    $params[':status'] = $status;
}

$query .= " ORDER BY e.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$empresas = $stmt->fetchAll();
?>

<style>
.empresa-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 16px;
    border: 1px solid var(--border-color);
    transition: var(--transition);
}

.empresa-card:hover {
    box-shadow: var(--shadow-md);
}

.empresa-header {
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

.empresa-detalhes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    padding: 12px;
    background: var(--bg-secondary);
    border-radius: 12px;
    margin: 12px 0;
}

.empresa-detalhe {
    display: flex;
    flex-direction: column;
}

.empresa-detalhe-label {
    font-size: 11px;
    color: var(--text-secondary);
}

.empresa-detalhe-valor {
    font-size: 14px;
    font-weight: 500;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-ativa { background: #d1fae5; color: #059669; }
.status-inativa { background: #fee2e2; color: #dc2626; }
.status-suspensa { background: #fef3c7; color: #d97706; }
.status-teste { background: #bfdbfe; color: #1e40af; }

.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    min-width: 160px;
    z-index: 100;
    box-shadow: var(--shadow-md);
}

.dropdown-item {
    display: block;
    padding: 8px 16px;
    text-decoration: none;
    color: var(--text-primary);
    font-size: 13px;
    transition: var(--transition);
}

.dropdown-item:hover {
    background: var(--bg-secondary);
}

.text-danger {
    color: #dc2626 !important;
}

.text-success {
    color: #10b981 !important;
}

.btn-reciclar {
    background: #667eea;
    color: white;
    margin-left: 8px;
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-building"></i> Empresas</h2>
        <p>Gerencie todas as empresas cadastradas na plataforma</p>
    </div>
    <div class="module-actions">
        <a href="cadastrar.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nova Empresa
        </a>
        <a href="recuperar.php" class="btn btn-secondary btn-reciclar">
            <i class="fas fa-trash-restore"></i> Reciclagem
        </a>
    </div>
</div>

<!-- Filtros -->
<div class="filters-card">
    <form method="GET" action="" class="filters-form">
        <div class="filter-group">
            <label><i class="fas fa-search"></i> Buscar</label>
            <input type="text" name="search" placeholder="Nome ou e-mail" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-group">
            <label><i class="fas fa-filter"></i> Status</label>
            <select name="status">
                <option value="">Todos</option>
                <option value="ativa" <?php echo $status == 'ativa' ? 'selected' : ''; ?>>Ativas</option>
                <option value="inativa" <?php echo $status == 'inativa' ? 'selected' : ''; ?>>Inativas</option>
                <option value="suspensa" <?php echo $status == 'suspensa' ? 'selected' : ''; ?>>Suspensas</option>
            </select>
        </div>
        <div class="filter-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="index.php" class="btn btn-secondary">Limpar</a>
        </div>
    </form>
</div>

<!-- Lista de Empresas -->
<?php foreach ($empresas as $empresa): ?>
<div class="empresa-card">
    <div class="empresa-header">
        <div>
            <div class="empresa-nome"><?php echo htmlspecialchars($empresa['nome']); ?></div>
            <div style="font-size: 13px; color: var(--text-secondary);">
                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($empresa['email']); ?>
            </div>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <span class="status-badge status-<?php echo $empresa['status']; ?>">
                <?php echo ucfirst($empresa['status']); ?>
            </span>
            <div class="dropdown">
                <button class="btn-icon dropdown-toggle" onclick="toggleMenu(this)">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <div class="dropdown-menu">
                    <a href="visualizar.php?id=<?php echo $empresa['id']; ?>" class="dropdown-item">
                        <i class="fas fa-eye"></i> Visualizar
                    </a>
                    <a href="editar.php?id=<?php echo $empresa['id']; ?>" class="dropdown-item">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <?php if ($empresa['status'] == 'ativa'): ?>
                    <a href="suspender.php?id=<?php echo $empresa['id']; ?>" class="dropdown-item text-warning" onclick="return confirm('Tem certeza que deseja suspender esta empresa?')">
                        <i class="fas fa-pause-circle"></i> Suspender
                    </a>
                    <?php elseif ($empresa['status'] == 'suspensa'): ?>
                    <a href="ativar.php?id=<?php echo $empresa['id']; ?>" class="dropdown-item text-success" onclick="return confirm('Tem certeza que deseja ativar esta empresa?')">
                        <i class="fas fa-play-circle"></i> Ativar
                    </a>
                    <?php endif; ?>
                    <!-- Apenas Super Admin pode excluir -->
                    <a href="excluir.php?id=<?php echo $empresa['id']; ?>" class="dropdown-item text-danger" onclick="return confirm('ATENÇÃO! Esta ação moverá a empresa para a lixeira. Os funcionários serão preservados no histórico. Deseja continuar?')">
                        <i class="fas fa-trash"></i> Mover para Lixeira
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="empresa-detalhes">
        <div class="empresa-detalhe">
            <span class="empresa-detalhe-label">Funcionários</span>
            <span class="empresa-detalhe-valor"><?php echo $empresa['total_funcionarios']; ?></span>
        </div>
        <div class="empresa-detalhe">
            <span class="empresa-detalhe-label">Cadastro</span>
            <span class="empresa-detalhe-valor"><?php echo date('d/m/Y', strtotime($empresa['created_at'])); ?></span>
        </div>
        <div class="empresa-detalhe">
            <span class="empresa-detalhe-label">Domínio</span>
            <span class="empresa-detalhe-valor"><?php echo htmlspecialchars($empresa['dominio']); ?>.pontofacil.com</span>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($empresas)): ?>
<div class="empty-state" style="text-align: center; padding: 60px;">
    <i class="fas fa-building" style="font-size: 48px; color: #ccc;"></i>
    <p style="margin-top: 16px;">Nenhuma empresa encontrada</p>
    <a href="cadastrar.php" class="btn btn-primary" style="margin-top: 16px;">
        <i class="fas fa-plus"></i> Cadastrar primeira empresa
    </a>
</div>
<?php endif; ?>

<script>
function toggleMenu(btn) {
    const menu = btn.nextElementSibling;
    if (menu.style.display === 'none' || menu.style.display === '') {
        // Fechar todos os outros menus
        document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
        menu.style.display = 'block';
    } else {
        menu.style.display = 'none';
    }
}

document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.style.display = 'none';
        });
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>