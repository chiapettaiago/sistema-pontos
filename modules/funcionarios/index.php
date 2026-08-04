<?php
// modules/funcionarios/index.php - Lista de Funcionários (COM PERMISSÕES)
$pageTitle = 'Funcionários';
$activePage = 'funcionarios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

// Verificar permissão para acessar o módulo
checkModuleAccess('funcionarios');

$database = new Database();
$db = $database->getConnection();

// Obter informações do usuário logado
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$usuario_filial_id = $_SESSION['usuario_filial_id'] ?? null;
$empresa_id = getCurrentEmpresaId();
if ($empresa_id === null || $empresa_id === '') {
    $empresa_id = $_SESSION['empresa_id'] ?? null;
}
if ($empresa_id === null || $empresa_id === '') {
    header('Location: /index.php');
    exit;
}

// PARA GESTOR E SUPERVISOR: Só podem ver funcionários da sua filial
// PARA FUNCIONÁRIO: Não deve acessar esta página (redirect)
if ($usuario_tipo === 'funcionario') {
    header('Location: /index.php');
    exit;
}

// Buscar parâmetros de filtro
$search = $_GET['search'] ?? '';
$filial_id = $_GET['filial_id'] ?? '';
$status = $_GET['status'] ?? 'ativo';
$tipo_usuario = $_GET['tipo_usuario'] ?? '';

// Query base
$query = "SELECT f.*, 
          fil.nome_fantasia as filial_nome, 
          c.nome as cargo_nome, 
          d.nome as departamento_nome
          FROM funcionarios f
          LEFT JOIN filiais fil ON f.filial_id = fil.id
          LEFT JOIN cargos c ON f.cargo_id = c.id
          LEFT JOIN departamentos d ON f.departamento_id = d.id
          WHERE f.empresa_id = :empresa_id";

$params = [':empresa_id' => $empresa_id];

if ($search) {
    $query .= " AND (f.nome LIKE :search OR f.email LIKE :search OR f.matricula LIKE :search OR f.cpf LIKE :search)";
    $params[':search'] = "%$search%";
}

// Filtro por filial baseado no tipo de usuário
if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa') {
    // Super Admin e Admin Empresa podem filtrar por qualquer filial
    if ($filial_id) {
        $query .= " AND f.filial_id = :filial_id";
        $params[':filial_id'] = $filial_id;
    }
} elseif ($usuario_tipo === 'gestor' || $usuario_tipo === 'supervisor') {
    // Gestor e Supervisor só veem funcionários da sua filial
    if ($usuario_filial_id) {
        $query .= " AND f.filial_id = :filial_id";
        $params[':filial_id'] = $usuario_filial_id;
    }
}

if ($status !== 'todos') {
    $query .= " AND f.status = :status";
    $params[':status'] = $status;
}

if ($tipo_usuario) {
    $query .= " AND f.tipo_usuario = :tipo_usuario";
    $params[':tipo_usuario'] = $tipo_usuario;
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

// Buscar estatísticas (apenas da filial do usuário se for gestor/supervisor)
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as ativos,
    SUM(CASE WHEN status = 'ferias' THEN 1 ELSE 0 END) as ferias,
    SUM(CASE WHEN status = 'licenca' THEN 1 ELSE 0 END) as licenca,
    SUM(CASE WHEN status = 'desligado' THEN 1 ELSE 0 END) as desligados,
    SUM(CASE WHEN tipo_usuario = 'admin_empresa' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN tipo_usuario = 'gestor' THEN 1 ELSE 0 END) as gestores,
    SUM(CASE WHEN tipo_usuario = 'funcionario' THEN 1 ELSE 0 END) as funcionarios
    FROM funcionarios
    WHERE empresa_id = :empresa_id";

$statsParams = [':empresa_id' => $empresa_id];

if ($usuario_tipo === 'gestor' || $usuario_tipo === 'supervisor') {
    if ($usuario_filial_id) {
        $statsQuery .= " AND filial_id = :filial_id";
        $statsParams[':filial_id'] = $usuario_filial_id;
    }
}

$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute($statsParams);
$stats = $statsStmt->fetch();
?>

<style>
.btn-group {
    position: relative;
    display: inline-block;
}

.dropdown-toggle {
    cursor: pointer;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    z-index: 1000;
    min-width: 160px;
    padding: 8px 0;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.dropdown-item {
    display: block;
    padding: 8px 16px;
    text-decoration: none;
    color: var(--text-primary);
    font-size: 14px;
    transition: all 0.2s;
}

.dropdown-item:hover {
    background: var(--bg-secondary);
}

.dropdown-item i {
    width: 20px;
    margin-right: 8px;
}

.module-actions {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    overflow: hidden;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.role-admin_empresa { background: #e0e7ff; color: #4338ca; }
.role-gestor { background: #d1fae5; color: #059669; }
.role-supervisor { background: #fed7aa; color: #c2410c; }
.role-funcionario { background: #f3f4f6; color: #4b5563; }
.status-ferias { background: #fed7aa; color: #c2410c; }
.status-licenca { background: #bfdbfe; color: #1e40af; }
.status-desligado { background: #fee2e2; color: #dc2626; }
.status-afastado { background: #fef3c7; color: #d97706; }

.bio-status-inline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.bio-status-ok {
    background: #d1fae5;
    color: #065f46;
}

.bio-status-no {
    background: #fee2e2;
    color: #991b1b;
}

.fade-in {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-users"></i> Funcionários</h2>
        <p>Gerencie todos os funcionários da empresa</p>
    </div>
    <div class="module-actions">
        <!-- Botão Exportar (apenas admin) -->
        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
        <div class="btn-group">
            <button class="btn btn-secondary dropdown-toggle" onclick="toggleExportMenu()">
                <i class="fas fa-download"></i> Exportar <i class="fas fa-chevron-down"></i>
            </button>
            <div id="exportMenu" class="dropdown-menu" style="display: none;">
                <a href="exportar.php?formato=csv&<?php echo htmlspecialchars(http_build_query($_GET)); ?>" class="dropdown-item">
                    <i class="fas fa-file-csv"></i> CSV
                </a>
                <a href="exportar.php?formato=excel&<?php echo htmlspecialchars(http_build_query($_GET)); ?>" class="dropdown-item">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
            </div>
        </div>
        
        <!-- Botão Importar (apenas admin) -->
        <a href="importar.php" class="btn btn-secondary">
            <i class="fas fa-file-import"></i> Importar
        </a>
        <?php endif; ?>
        
        <!-- Botão Novo Funcionário (apenas quem pode cadastrar) -->
        <?php if (hasPermission('cadastrar_funcionarios') || $usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa' || $usuario_tipo === 'gestor'): ?>
        <a href="cadastrar.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Novo Funcionário
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card fade-in">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['total'] ?? 0; ?></h3>
            <p>Total de Funcionários</p>
        </div>
    </div>
    <div class="stat-card fade-in">
        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['ativos'] ?? 0; ?></h3>
            <p>Funcionários Ativos</p>
        </div>
    </div>
    <div class="stat-card fade-in">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class="fas fa-umbrella-beach"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['ferias'] ?? 0; ?></h3>
            <p>Em Férias</p>
        </div>
    </div>
    <div class="stat-card fade-in">
        <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <i class="fas fa-user-slash"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['desligados'] ?? 0; ?></h3>
            <p>Desligados</p>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="filters-card">
    <form method="GET" action="" class="filters-form">
        <div class="filter-group">
            <label><i class="fas fa-search"></i> Buscar</label>
            <input type="text" name="search" placeholder="Nome, email, matrícula ou CPF" 
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
                <option value="ferias" <?php echo $status == 'ferias' ? 'selected' : ''; ?>>Férias</option>
                <option value="licenca" <?php echo $status == 'licenca' ? 'selected' : ''; ?>>Licença</option>
                <option value="desligado" <?php echo $status == 'desligado' ? 'selected' : ''; ?>>Desligados</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label><i class="fas fa-user-tag"></i> Tipo</label>
            <select name="tipo_usuario">
                <option value="">Todos</option>
                <option value="admin_empresa" <?php echo $tipo_usuario == 'admin_empresa' ? 'selected' : ''; ?>>Administrador</option>
                <option value="gestor" <?php echo $tipo_usuario == 'gestor' ? 'selected' : ''; ?>>Gestor</option>
                <option value="supervisor" <?php echo $tipo_usuario == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                <option value="funcionario" <?php echo $tipo_usuario == 'funcionario' ? 'selected' : ''; ?>>Funcionário</option>
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

<!-- Lista de Funcionários -->
<div class="table-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Matrícula</th>
                    <th>Funcionário</th>
                    <th>Filial</th>
                    <th>Cargo</th>
                    <th>Departamento</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th width="160">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($funcionarios as $func): ?>
                <tr class="fade-in">
                    <td>
                        <strong><?php echo htmlspecialchars($func['matricula']); ?></strong>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div class="avatar">
                                <?php if ($func['foto'] && file_exists('../../' . $func['foto'])): ?>
                                    <img src="../../<?php echo $func['foto']; ?>" alt="Foto de <?php echo htmlspecialchars($func['nome']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-user" style="font-size: 20px;"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($func['nome']); ?></strong><br>
                                <small><?php echo htmlspecialchars($func['email']); ?></small>
                                <?php
                                $bioFacial = !empty($func['tem_facial']) ? true : false;
                                $bioDigital = !empty($func['tem_digital']) ? true : false;
                                ?>
                                <div class="bio-status-inline <?php echo ($bioFacial || $bioDigital) ? 'bio-status-ok' : 'bio-status-no'; ?>">
                                    <i class="fas fa-fingerprint"></i>
                                    <?php echo ($bioFacial || $bioDigital) ? 'Biometria pronta' : 'Biometria pendente'; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($func['filial_nome']); ?></td>
                    <td><?php echo htmlspecialchars($func['cargo_nome'] ?? '--'); ?></td>
                    <td><?php echo htmlspecialchars($func['departamento_nome'] ?? '--'); ?></td>
                    <td>
                        <span class="role-badge role-<?php echo $func['tipo_usuario']; ?>">
                            <?php 
                            $tipos = [
                                'admin_empresa' => '👑 Admin',
                                'gestor' => '📊 Gestor',
                                'supervisor' => '👁️ Supervisor',
                                'funcionario' => '👤 Funcionário'
                            ];
                            echo $tipos[$func['tipo_usuario']] ?? $func['tipo_usuario'];
                            ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo $func['status']; ?>">
                            <?php 
                            $statusLabels = [
                                'ativo' => '✅ Ativo',
                                'ferias' => '🏖️ Férias',
                                'licenca' => '📋 Licença',
                                'desligado' => '❌ Desligado',
                                'afastado' => '⚠️ Afastado'
                            ];
                            echo $statusLabels[$func['status']] ?? $func['status'];
                            ?>
                        </span>
                    </td>
                    <td class="actions">
                        <!-- Visualizar - todos podem ver (se tiver acesso à lista) -->
                        <a href="visualizar.php?id=<?php echo $func['id']; ?>" class="btn-icon" title="Visualizar">
                            <i class="fas fa-eye"></i>
                        </a>
                        
                        <!-- Editar - apenas quem tem permissão -->
                        <?php if (canEditFuncionario($func['id'])): ?>
                        <a href="editar.php?id=<?php echo $func['id']; ?>" class="btn-icon" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php endif; ?>
                        
                        <!-- Alterar Senha - apenas quem tem permissão -->
                        <?php if (canEditFuncionario($func['id'])): ?>
                        <a href="alterar_senha.php?id=<?php echo $func['id']; ?>" class="btn-icon" title="Alterar Senha">
                            <i class="fas fa-key"></i>
                        </a>
                        <?php endif; ?>
                        
                        <!-- Desligar/Reativar - apenas admin e super admin -->
                        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
                            <?php if ($func['status'] != 'desligado'): ?>
                                <a href="excluir.php?id=<?php echo $func['id']; ?>" class="btn-icon btn-danger" 
                                   onclick="return confirm('Tem certeza que deseja desligar este funcionário?')" title="Desligar">
                                    <i class="fas fa-user-slash"></i>
                                </a>
                            <?php else: ?>
                                <a href="excluir.php?id=<?php echo $func['id']; ?>&reativar=1" class="btn-icon btn-success" 
                                   onclick="return confirm('Tem certeza que deseja reativar este funcionário?')" title="Reativar">
                                    <i class="fas fa-check-circle"></i>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($funcionarios)): ?>
                <tr class="fade-in">
                    <td colspan="8" style="text-align: center; padding: 60px;">
                        <i class="fas fa-user-slash" style="font-size: 48px; color: #ccc;"></i>
                        <p style="margin-top: 10px;">Nenhum funcionário encontrado</p>
                        <?php if (hasPermission('cadastrar_funcionarios') || $usuario_tipo === 'gestor'): ?>
                        <a href="cadastrar.php" class="btn btn-primary" style="margin-top: 10px;">
                            <i class="fas fa-plus"></i> Cadastrar primeiro funcionário
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleExportMenu() {
    const menu = document.getElementById('exportMenu');
    if (menu.style.display === 'none' || menu.style.display === '') {
        menu.style.display = 'block';
    } else {
        menu.style.display = 'none';
    }
}

document.addEventListener('click', function(event) {
    const menu = document.getElementById('exportMenu');
    const btn = document.querySelector('.dropdown-toggle');
    if (menu && !menu.contains(event.target) && !btn?.contains(event.target)) {
        menu.style.display = 'none';
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stat-card, .table-card');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.add('fade-in');
        }, index * 50);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>



