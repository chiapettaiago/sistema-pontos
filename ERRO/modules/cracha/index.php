<?php
// modules/cracha/index.php - Lista de funcionários para gerar crachá (CORRIGIDO)
$pageTitle = 'Gerar Crachá';
$activePage = 'cracha';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

// Verificar permissão
checkModuleAccess('funcionarios');

$database = new Database();
$db = $database->getConnection();

// Obter informações do usuário logado
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';
$usuario_filial_id = $_SESSION['usuario_filial_id'] ?? null;
$empresa_id = getCurrentEmpresaId();
if (!$empresa_id) {
    $empresa_id = $_SESSION['empresa_id'] ?? null;
}
if (!$empresa_id) {
    header('Location: /index.php');
    exit;
}

// Buscar parâmetros de filtro
$search = $_GET['search'] ?? '';
$filial_id = $_GET['filial_id'] ?? '';
$status = $_GET['status'] ?? 'ativo';

// Query base - CORRIGIDA
$query = "SELECT f.*, fil.nome_fantasia as filial_nome, c.nome as cargo_nome
          FROM funcionarios f
          LEFT JOIN filiais fil ON f.filial_id = fil.id
          LEFT JOIN cargos c ON f.cargo_id = c.id
          WHERE f.empresa_id = :empresa_id";

$params = [':empresa_id' => $empresa_id];

if ($search) {
    $query .= " AND (f.nome LIKE :search OR f.matricula LIKE :search)";
    $params[':search'] = "%$search%";
}

// Filtro por filial baseado no tipo de usuário
if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa') {
    if ($filial_id) {
        $query .= " AND f.filial_id = :filial_id";
        $params[':filial_id'] = $filial_id;
    }
} else {
    // Para gestor/supervisor, filtrar pela filial dele
    if ($usuario_filial_id) {
        $query .= " AND f.filial_id = :filial_id";
        $params[':filial_id'] = $usuario_filial_id;
    }
}

if ($status !== 'todos') {
    $query .= " AND f.status = :status";
    $params[':status'] = $status;
}

$query .= " ORDER BY f.nome ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$funcionarios = $stmt->fetchAll();

// Buscar filiais para filtro (apenas admin)
$filiais = [];
if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa') {
    $stmt = $db->prepare("SELECT id, nome_fantasia FROM filiais WHERE empresa_id = :empresa_id AND ativo = 1 ORDER BY nome_fantasia");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $filiais = $stmt->fetchAll();
}
?>

<style>
.cracha-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
    margin-top: 24px;
}

.cracha-card {
    background: var(--bg-primary);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--shadow-md);
    transition: var(--transition);
    border: 1px solid var(--border-color);
    cursor: pointer;
}

.cracha-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.cracha-preview {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    padding: 20px;
    text-align: center;
    position: relative;
}

.cracha-foto {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    margin: 0 auto 16px;
    overflow: hidden;
    border: 3px solid white;
    box-shadow: var(--shadow-md);
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cracha-foto img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cracha-foto i {
    font-size: 60px;
    color: #ccc;
}

.cracha-nome {
    color: white;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 4px;
}

.cracha-cargo {
    color: rgba(255,255,255,0.9);
    font-size: 14px;
}

.cracha-info {
    padding: 16px;
}

.cracha-info-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border-color);
}

.cracha-info-label {
    font-size: 12px;
    color: var(--text-secondary);
}

.cracha-info-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
}

.cracha-actions {
    display: flex;
    gap: 12px;
    padding: 16px;
    border-top: 1px solid var(--border-color);
}

.btn-cracha {
    flex: 1;
    padding: 10px;
    border-radius: 10px;
    text-align: center;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: var(--transition);
}

.btn-pdf {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
}

.btn-pdf:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
}

.btn-view {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.btn-view:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

@media (max-width: 768px) {
    .cracha-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-id-card"></i> Gerar Crachá</h2>
        <p>Selecione um funcionário para gerar o crachá em PDF</p>
    </div>
</div>

<!-- Filtros -->
<div class="filters-card">
    <form method="GET" action="" class="filters-form">
        <div class="filter-group">
            <label><i class="fas fa-search"></i> Buscar</label>
            <input type="text" name="search" placeholder="Nome ou matrícula" 
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>
        
        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
        <div class="filter-group">
            <label><i class="fas fa-store"></i> Filial</label>
            <select name="filial_id">
                <option value="">Todas as filiais</option>
                <?php foreach ($filiais as $filial): ?>
                <option value="<?php echo $filial['id']; ?>" <?php echo $filial_id == $filial['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($filial['nome_fantasia']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <div class="filter-group">
            <label><i class="fas fa-filter"></i> Status</label>
            <select name="status">
                <option value="todos" <?php echo $status == 'todos' ? 'selected' : ''; ?>>Todos</option>
                <option value="ativo" <?php echo $status == 'ativo' ? 'selected' : ''; ?>>Ativos</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filtrar
            </button>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Limpar
            </a>
        </div>
    </form>
</div>

<!-- Lista de Funcionários em Grid -->
<div class="cracha-grid">
    <?php foreach ($funcionarios as $func): ?>
    <div class="cracha-card">
        <div class="cracha-preview">
            <div class="cracha-foto">
                <?php if ($func['foto'] && file_exists('../../' . $func['foto'])): ?>
                    <img src="../../<?php echo $func['foto']; ?>" alt="Foto">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
            </div>
            <div class="cracha-nome"><?php echo htmlspecialchars($func['nome']); ?></div>
            <div class="cracha-cargo"><?php echo htmlspecialchars($func['cargo_nome'] ?? 'Colaborador'); ?></div>
        </div>
        <div class="cracha-info">
            <div class="cracha-info-item">
                <span class="cracha-info-label">Matrícula:</span>
                <span class="cracha-info-value"><?php echo htmlspecialchars($func['matricula']); ?></span>
            </div>
            <div class="cracha-info-item">
                <span class="cracha-info-label">Filial:</span>
                <span class="cracha-info-value"><?php echo htmlspecialchars($func['filial_nome']); ?></span>
            </div>
            <div class="cracha-info-item">
                <span class="cracha-info-label">E-mail:</span>
                <span class="cracha-info-value"><?php echo htmlspecialchars($func['email']); ?></span>
            </div>
        </div>
        <div class="cracha-actions">
            <a href="visualizar.php?id=<?php echo $func['id']; ?>" class="btn-cracha btn-view" target="_blank">
                <i class="fas fa-eye"></i> Visualizar
            </a>
            <a href="gerar.php?id=<?php echo $func['id']; ?>" class="btn-cracha btn-pdf">
                <i class="fas fa-file-pdf"></i> Baixar PDF
            </a>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($funcionarios)): ?>
    <div style="grid-column: 1/-1; text-align: center; padding: 60px;">
        <i class="fas fa-id-card" style="font-size: 64px; color: #ccc;"></i>
        <p style="margin-top: 16px;">Nenhum funcionário encontrado</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
