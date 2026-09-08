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
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// PARA GESTOR E SUPERVISOR: Só podem ver funcionários da sua filial
// PARA FUNCIONÁRIO: Não deve acessar esta página (redirect)
if ($usuario_tipo === 'funcionario') {
    header('Location: ' . BASE_URL . '/index.php');
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
<!-- PAGE HEADER -->
<div class="pf-page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-users me-2 text-primary"></i>Funcionários</h1>
        <p class="text-muted">Gerencie os funcionários da empresa</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="importar.php" class="btn btn-outline-secondary">
            <i class="fas fa-file-import me-1"></i>Importar
        </a>
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>Exportar
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="exportar.php?formato=excel"><i class="fas fa-file-excel me-2"></i>Excel</a></li>
                <li><a class="dropdown-item" href="exportar.php?formato=pdf"><i class="fas fa-file-pdf me-2"></i>PDF</a></li>
            </ul>
        </div>
        <?php if (in_array($usuario_tipo, ['super_admin','admin_empresa','gestor'])): ?>
        <a href="cadastrar.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Novo Funcionário
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card pf-stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="pf-stat-icon" style="background:var(--pf-gradient);"><i class="fas fa-users text-white"></i></div>
                <div>
                    <div class="fs-3 fw-bold"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="text-muted small">Total</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card pf-stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="pf-stat-icon" style="background:linear-gradient(135deg,#10b981,#059669);"><i class="fas fa-user-check text-white"></i></div>
                <div>
                    <div class="fs-3 fw-bold"><?php echo $stats['ativos'] ?? 0; ?></div>
                    <div class="text-muted small">Ativos</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card pf-stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="pf-stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);"><i class="fas fa-umbrella-beach text-white"></i></div>
                <div>
                    <div class="fs-3 fw-bold"><?php echo ($stats['ferias'] ?? 0) + ($stats['licenca'] ?? 0); ?></div>
                    <div class="text-muted small">Afastados</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card pf-stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="pf-stat-icon" style="background:linear-gradient(135deg,#ef4444,#dc2626);"><i class="fas fa-user-times text-white"></i></div>
                <div>
                    <div class="fs-3 fw-bold"><?php echo $stats['desligados'] ?? 0; ?></div>
                    <div class="text-muted small">Desligados</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FILTROS -->
<div class="card pf-table-card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-sm-6 col-lg-3">
                <label class="form-label">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" name="busca" class="form-control" placeholder="Nome, email, matrícula..." value="<?php echo htmlspecialchars($_GET['busca'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="ativo" <?php echo ($_GET['status'] ?? '') === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                    <option value="ferias" <?php echo ($_GET['status'] ?? '') === 'ferias' ? 'selected' : ''; ?>>Férias</option>
                    <option value="licenca" <?php echo ($_GET['status'] ?? '') === 'licenca' ? 'selected' : ''; ?>>Licença</option>
                    <option value="desligado" <?php echo ($_GET['status'] ?? '') === 'desligado' ? 'selected' : ''; ?>>Desligado</option>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label">Departamento</label>
                <input type="text" name="departamento" class="form-control" placeholder="Dep." value="<?php echo htmlspecialchars($_GET['departamento'] ?? ''); ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <a href="?" class="btn btn-outline-secondary ms-1"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- TABELA -->
<div class="card pf-table-card">
    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
            <i class="fas fa-list me-2 text-primary"></i>Lista de Funcionários
            <span class="badge bg-primary ms-2"><?php echo count($funcionarios); ?></span>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table pf-table-card mb-0">
                <thead>
                    <tr>
                        <th>Funcionário</th>
                        <th class="d-none d-md-table-cell">Matrícula</th>
                        <th class="d-none d-lg-table-cell">Cargo</th>
                        <th class="d-none d-lg-table-cell">Filial</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($funcionarios as $func): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:38px;height:38px;border-radius:50%;background:var(--pf-gradient);display:grid;place-items:center;flex-shrink:0;">
                                    <i class="fas fa-user text-white small"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($func['nome']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($func['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell text-muted"><?php echo htmlspecialchars($func['matricula'] ?? '-'); ?></td>
                        <td class="d-none d-lg-table-cell text-muted"><?php echo htmlspecialchars($func['cargo'] ?? '-'); ?></td>
                        <td class="d-none d-lg-table-cell text-muted"><?php echo htmlspecialchars($func['filial_nome'] ?? '-'); ?></td>
                        <td>
                            <?php
                            $sc = ['ativo'=>'success','ferias'=>'info','licenca'=>'warning','desligado'=>'danger'];
                            $s  = $func['status'] ?? 'ativo';
                            ?>
                            <span class="badge bg-<?php echo $sc[$s] ?? 'secondary'; ?>"><?php echo ucfirst($s); ?></span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="visualizar.php?id=<?php echo $func['id']; ?>" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Visualizar">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (in_array($usuario_tipo, ['super_admin','admin_empresa'], true)): ?>
                                <a href="cadastro_facial.php?id=<?php echo $func['id']; ?>" class="btn btn-outline-success" data-bs-toggle="tooltip" title="Cadastrar biometria facial">
                                    <i class="fas fa-face-smile"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (in_array($usuario_tipo, ['super_admin','admin_empresa','gestor'])): ?>
                                <a href="editar.php?id=<?php echo $func['id']; ?>" class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="excluir.php?id=<?php echo $func['id']; ?>" class="btn btn-outline-danger" data-bs-toggle="tooltip" title="Excluir"
                                   onclick="return confirm('Excluir funcionário?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($funcionarios)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">
                        <i class="fas fa-users fa-2x d-block mb-2 opacity-25"></i>Nenhum funcionário encontrado
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
