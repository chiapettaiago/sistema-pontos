<?php
// modules/escala/index.php - Dashboard de Escalas
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa', 'gestor'])) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Escala de Trabalho';
$activePage = 'escala';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$ano_atual = date('Y');
$mes_atual = date('m');

// Parâmetros
$ano = $_GET['ano'] ?? $ano_atual;
$mes = $_GET['mes'] ?? $mes_atual;
$funcionario_id = $_GET['funcionario_id'] ?? '';
$tipo_escala = $_GET['tipo'] ?? '';

// Buscar funcionários
$stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios 
                      WHERE empresa_id = :empresa_id AND status = 'ativo' 
                      ORDER BY nome");
$stmt->execute([':empresa_id' => $empresa_id]);
$funcionarios = $stmt->fetchAll();

// Buscar tipos de escala
$stmt = $db->prepare("SELECT * FROM escala_tipos WHERE empresa_id = :empresa_id AND ativo = 1");
$stmt->execute([':empresa_id' => $empresa_id]);
$tipos_escala = $stmt->fetchAll();

// Buscar escalas dos funcionários
$query = "SELECT fe.*, f.nome as funcionario_nome, f.matricula,
          et.nome as escala_nome, et.tipo as escala_tipo
          FROM funcionario_escala fe
          JOIN funcionarios f ON fe.funcionario_id = f.id
          JOIN escala_tipos et ON fe.escala_tipo_id = et.id
          WHERE f.empresa_id = :empresa_id AND fe.ativo = 1";

$params = [':empresa_id' => $empresa_id];

if ($funcionario_id) {
    $query .= " AND fe.funcionario_id = :funcionario_id";
    $params[':funcionario_id'] = $funcionario_id;
}

if ($tipo_escala) {
    $query .= " AND et.id = :tipo_escala";
    $params[':tipo_escala'] = $tipo_escala;
}

$query .= " ORDER BY f.nome";

$stmt = $db->prepare($query);
$stmt->execute($params);
$escalas = $stmt->fetchAll();

// Estatísticas
$stmt = $db->prepare("SELECT et.tipo, COUNT(*) as total
                      FROM funcionario_escala fe
                      JOIN escala_tipos et ON fe.escala_tipo_id = et.id
                      WHERE fe.ativo = 1
                      GROUP BY et.tipo");
$stmt->execute();
$stats = $stmt->fetchAll();
?>

<style>
.escala-container {
    max-width: 1200px;
    margin: 0 auto;
}

.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.module-title h2 {
    margin: 0;
    font-size: 24px;
}

.module-title p {
    margin: 8px 0 0;
    color: var(--text-secondary);
}

.filters-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
}

.filters-form {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-size: 12px;
    color: var(--text-secondary);
}

.filter-group select {
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--bg-primary);
    color: var(--text-primary);
    min-width: 180px;
}

.btn-filter {
    padding: 10px 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 16px;
    text-align: center;
    border: 1px solid var(--border-color);
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
}

.stat-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 5px;
}

.table-card {
    background: var(--bg-primary);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.table-header {
    padding: 16px 20px;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table th {
    background: var(--bg-secondary);
    font-weight: 600;
    font-size: 12px;
}

.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

.badge-5x2 { background: #d1fae5; color: #059669; }
.badge-6x1 { background: #bfdbfe; color: #1e40af; }
.badge-12x36 { background: #fed7aa; color: #c2410c; }
.badge-plantao { background: #fef3c7; color: #d97706; }

.btn {
    padding: 6px 12px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.btn-edit { background: #f59e0b; color: white; }
.btn-delete { background: #ef4444; color: white; }

.empty-state {
    text-align: center;
    padding: 60px;
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }
    .data-table {
        font-size: 12px;
    }
}
</style>

<div class="escala-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-calendar-alt"></i> Escala de Trabalho</h2>
            <p>Gerencie as escalas dos funcionários</p>
        </div>
        <div class="module-actions">
            <a href="configurar.php" class="btn btn-primary">
                <i class="fas fa-cog"></i> Configurar Tipos
            </a>
            <a href="cadastrar.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nova Escala
            </a>
            <a href="calendario.php" class="btn btn-secondary">
                <i class="fas fa-calendar-alt"></i> Calendário
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-form">
            <div class="filter-group">
                <label>Funcionário</label>
                <select name="funcionario_id">
                    <option value="">Todos</option>
                    <?php foreach ($funcionarios as $func): ?>
                        <option value="<?php echo $func['id']; ?>" <?php echo $funcionario_id == $func['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($func['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Tipo de Escala</label>
                <select name="tipo">
                    <option value="">Todos</option>
                    <?php foreach ($tipos_escala as $tipo): ?>
                        <option value="<?php echo $tipo['id']; ?>" <?php echo $tipo_escala == $tipo['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tipo['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn-filter">Filtrar</button>
            </div>
        </form>
    </div>

    <!-- Estatísticas -->
    <div class="stats-grid">
        <?php foreach ($stats as $stat): ?>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stat['total']; ?></div>
                <div class="stat-label">
                    <?php 
                    $labels = ['5x2' => '5x2', '6x1' => '6x1', '12x36' => '12x36', 'plantao' => 'Plantão'];
                    echo $labels[$stat['tipo']] ?? $stat['tipo'];
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Lista de Escalas -->
    <div class="table-card">
        <div class="table-header">
            <h3>Escalas Cadastradas</h3>
        </div>
        <div class="table-responsive">
            <?php if (empty($escalas)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>Nenhuma escala cadastrada</p>
                    <a href="cadastrar.php" class="btn btn-primary">Cadastrar primeira escala</a>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Matrícula</th>
                            <th>Escala</th>
                            <th>Início</th>
                            <th>Fim</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($escalas as $escala): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($escala['funcionario_nome']); ?></strong></td>
                                <td><?php echo htmlspecialchars($escala['matricula']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $escala['escala_tipo']; ?>">
                                        <?php echo htmlspecialchars($escala['escala_nome']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($escala['data_inicio'])); ?></td>
                                <td><?php echo $escala['data_fim'] ? date('d/m/Y', strtotime($escala['data_fim'])) : 'Atual'; ?></td>
                                <td>
                                    <a href="editar.php?id=<?php echo $escala['id']; ?>" class="btn btn-edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="excluir.php?id=<?php echo $escala['id']; ?>" class="btn btn-delete" onclick="return confirm('Tem certeza?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                    <a href="calendario.php?funcionario=<?php echo $escala['funcionario_id']; ?>" class="btn btn-secondary">
                                        <i class="fas fa-calendar"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>