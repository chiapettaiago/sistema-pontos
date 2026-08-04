<?php
// modules/filiais/index.php - Lista de Filiais (COM FILTRO POR EMPRESA)
$pageTitle = 'Filiais';
$activePage = 'filiais';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

// Verificar permissão (apenas admin)
redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

// Obter empresa do usuário logado
$empresa_id = getCurrentEmpresaId() ?: 1;
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';

// Buscar parâmetros de filtro
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'todos';
$tipo_ramo = $_GET['tipo_ramo'] ?? '';

// Query base - FILTRADA POR EMPRESA
$query = "SELECT f.*, 
          (SELECT COUNT(*) FROM funcionarios WHERE filial_id = f.id AND status = 'ativo') as total_funcionarios,
          (SELECT COUNT(*) FROM pontos WHERE filial_id = f.id AND DATE(data_hora) = CURDATE()) as pontos_hoje
          FROM filiais f
          WHERE f.empresa_id = :empresa_id";

$params = [':empresa_id' => $empresa_id];

if ($search) {
    $query .= " AND (f.nome_fantasia LIKE :search OR f.cnpj LIKE :search OR f.codigo LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($status !== 'todos') {
    $query .= " AND f.ativo = :status";
    $params[':status'] = $status === 'ativo' ? 1 : 0;
}

if ($tipo_ramo) {
    $query .= " AND f.tipo_ramo = :tipo_ramo";
    $params[':tipo_ramo'] = $tipo_ramo;
}

$query .= " ORDER BY f.nome_fantasia ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$filiais = $stmt->fetchAll();

// Buscar estatísticas - FILTRADAS POR EMPRESA
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as ativas,
    SUM(CASE WHEN tipo_ramo = 'matriz' THEN 1 ELSE 0 END) as matriz,
    SUM(CASE WHEN tipo_ramo = 'filial' THEN 1 ELSE 0 END) as filiais,
    SUM(CASE WHEN tipo_ramo = 'loja' THEN 1 ELSE 0 END) as lojas,
    SUM(CASE WHEN tipo_ramo = 'deposito' THEN 1 ELSE 0 END) as depositos,
    SUM(CASE WHEN tipo_ramo = 'escritorio' THEN 1 ELSE 0 END) as escritorios
    FROM filiais
    WHERE empresa_id = :empresa_id";
$statsResult = $db->prepare($statsQuery);
$statsResult->execute([':empresa_id' => $empresa_id]);
$stats = $statsResult->fetch();
?>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-store"></i> Filiais / Lojas</h2>
    </div>
    <div class="module-actions">
        <a href="cadastrar.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nova Filial
        </a>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
            <i class="fas fa-store"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['total']; ?></h3>
            <p>Total de Filiais</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['ativas']; ?></h3>
            <p>Filiais Ativas</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class="fas fa-building"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['lojas'] + $stats['filiais']; ?></h3>
            <p>Lojas + Filiais</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <i class="fas fa-star"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['matriz']; ?></h3>
            <p>Matriz</p>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="filters-card">
    <form method="GET" action="" class="filters-form">
        <div class="filter-group">
            <label><i class="fas fa-search"></i> Buscar</label>
            <input type="text" name="search" placeholder="Nome, CNPJ ou Código" 
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>
        
        <div class="filter-group">
            <label><i class="fas fa-filter"></i> Tipo</label>
            <select name="tipo_ramo">
                <option value="">Todos os tipos</option>
                <option value="matriz" <?php echo $tipo_ramo == 'matriz' ? 'selected' : ''; ?>>Matriz</option>
                <option value="filial" <?php echo $tipo_ramo == 'filial' ? 'selected' : ''; ?>>Filial</option>
                <option value="loja" <?php echo $tipo_ramo == 'loja' ? 'selected' : ''; ?>>Loja</option>
                <option value="deposito" <?php echo $tipo_ramo == 'deposito' ? 'selected' : ''; ?>>Depósito</option>
                <option value="escritorio" <?php echo $tipo_ramo == 'escritorio' ? 'selected' : ''; ?>>Escritório</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label><i class="fas fa-circle"></i> Status</label>
            <select name="status">
                <option value="todos" <?php echo $status == 'todos' ? 'selected' : ''; ?>>Todos</option>
                <option value="ativo" <?php echo $status == 'ativo' ? 'selected' : ''; ?>>Ativas</option>
                <option value="inativo" <?php echo $status == 'inativo' ? 'selected' : ''; ?>>Inativas</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label>&nbsp;</label>
            <div>
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-search"></i> Filtrar
                </button>
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Limpar
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Lista de Filiais -->
<div class="table-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Filial / Loja</th>
                    <th>Tipo</th>
                    <th>CNPJ</th>
                    <th>Responsável</th>
                    <th>Funcionários</th>
                    <th>Pontos Hoje</th>
                    <th>Status</th>
                    <th width="120">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filiais as $filial): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($filial['codigo']); ?></strong>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($filial['nome_fantasia']); ?></strong><br>
                        <small><?php echo htmlspecialchars($filial['cidade'] . '/' . $filial['estado']); ?></small>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $filial['tipo_ramo']; ?>">
                            <?php 
                            $tipos = [
                                'matriz' => '🏢 Matriz',
                                'filial' => '📌 Filial',
                                'loja' => '🛍️ Loja',
                                'deposito' => '📦 Depósito',
                                'escritorio' => '💼 Escritório'
                            ];
                            echo $tipos[$filial['tipo_ramo']] ?? $filial['tipo_ramo'];
                            ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($filial['cnpj'] ?: '--'); ?></td>
                    <td>
                        <?php if ($filial['responsavel_nome']): ?>
                            <strong><?php echo htmlspecialchars($filial['responsavel_nome']); ?></strong><br>
                            <small><?php echo htmlspecialchars($filial['responsavel_email']); ?></small>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-info">
                            <i class="fas fa-users"></i> <?php echo $filial['total_funcionarios']; ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge <?php echo $filial['pontos_hoje'] > 0 ? 'badge-success' : 'badge-secondary'; ?>">
                            <i class="fas fa-fingerprint"></i> <?php echo $filial['pontos_hoje']; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($filial['ativo']): ?>
                            <span class="status-badge status-ativo">
                                <i class="fas fa-circle"></i> Ativa
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-inativo">
                                <i class="fas fa-circle"></i> Inativa
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a href="visualizar.php?id=<?php echo $filial['id']; ?>" class="btn-icon" title="Visualizar">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="editar.php?id=<?php echo $filial['id']; ?>" class="btn-icon" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php if ($filial['ativo']): ?>
                            <a href="excluir.php?id=<?php echo $filial['id']; ?>" class="btn-icon btn-danger" 
                               onclick="return confirm('Tem certeza que deseja desativar esta filial?')" title="Desativar">
                                <i class="fas fa-ban"></i>
                            </a>
                        <?php else: ?>
                            <a href="excluir.php?id=<?php echo $filial['id']; ?>&reativar=1" class="btn-icon btn-success" 
                               onclick="return confirm('Tem certeza que deseja reativar esta filial?')" title="Reativar">
                                <i class="fas fa-check-circle"></i>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($filiais)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px;">
                        <i class="fas fa-store-slash" style="font-size: 48px; color: #ccc;"></i>
                        <p style="margin-top: 10px;">Nenhuma filial encontrada</p>
                        <a href="cadastrar.php" class="btn btn-primary" style="margin-top: 10px;">
                            <i class="fas fa-plus"></i> Cadastrar primeira filial
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.badge-matriz { background: #e0e7ff; color: #4338ca; }
.badge-filial { background: #d1fae5; color: #059669; }
.badge-loja { background: #fed7aa; color: #c2410c; }
.badge-deposito { background: #bfdbfe; color: #1e40af; }
.badge-escritorio { background: #f3e8ff; color: #6b21a5; }
.badge-success { background: #d1fae5; color: #059669; }
.badge-secondary { background: #f3f4f6; color: #6b7280; }
.status-inativo { background: #fee2e2; color: #dc2626; }
.text-center { text-align: center; }
</style>

<?php require_once '../../includes/footer.php'; ?>