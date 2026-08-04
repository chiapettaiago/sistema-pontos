<?php
// modules/relatorios/extrato_coletivo.php - Extrato Coletivo (Todos funcionários)
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

$pageTitle = 'Extrato Coletivo';
$activePage = 'relatorios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;

// Parâmetros
$periodo = $_GET['periodo'] ?? 'mensal';
$data_referencia = $_GET['data_referencia'] ?? date('Y-m-d');
$filial_id = $_GET['filial_id'] ?? '';

// Definir período
if ($periodo == 'semanal') {
    $data_inicio = date('Y-m-d', strtotime('monday this week', strtotime($data_referencia)));
    $data_fim = date('Y-m-d', strtotime('sunday this week', strtotime($data_referencia)));
    $titulo_periodo = 'Semana de ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim));
} else {
    $data_inicio = date('Y-m-01', strtotime($data_referencia));
    $data_fim = date('Y-m-t', strtotime($data_referencia));
    $titulo_periodo = strftime('%B de %Y', strtotime($data_referencia));
}

// Buscar filiais
$stmt = $db->prepare("SELECT id, nome_fantasia FROM filiais WHERE empresa_id = :empresa_id AND ativo = 1 ORDER BY nome_fantasia");
$stmt->execute([':empresa_id' => $empresa_id]);
$filiais = $stmt->fetchAll();

// Buscar funcionários agrupados
$query = "SELECT 
            f.id,
            f.nome,
            f.matricula,
            fi.nome_fantasia as filial_nome,
            c.nome as cargo_nome,
            COUNT(DISTINCT DATE(p.data_hora)) as dias_trabalhados,
            SUM(CASE WHEN p.tipo = 'entrada' AND TIME(p.data_hora) > '08:00:00' THEN 1 ELSE 0 END) as total_atrasos,
            COUNT(CASE WHEN p.tipo = 'entrada' THEN 1 END) as total_entradas
          FROM funcionarios f
          LEFT JOIN filiais fi ON f.filial_id = fi.id
          LEFT JOIN cargos c ON f.cargo_id = c.id
          LEFT JOIN pontos p ON f.id = p.funcionario_id 
            AND DATE(p.data_hora) BETWEEN :data_inicio AND :data_fim
          WHERE f.empresa_id = :empresa_id AND f.status = 'ativo'";

$params = [
    ':data_inicio' => $data_inicio,
    ':data_fim' => $data_fim,
    ':empresa_id' => $empresa_id
];

if ($filial_id) {
    $query .= " AND f.filial_id = :filial_id";
    $params[':filial_id'] = $filial_id;
}

$query .= " GROUP BY f.id ORDER BY f.nome";

$stmt = $db->prepare($query);
$stmt->execute($params);
$funcionarios = $stmt->fetchAll();

// Calcular dias úteis do período
$dias_uteis = 0;
$data_atual = new DateTime($data_inicio);
$data_fim_obj = new DateTime($data_fim);
while ($data_atual <= $data_fim_obj) {
    $dia_semana = $data_atual->format('N');
    if ($dia_semana >= 1 && $dia_semana <= 5) {
        $dias_uteis++;
    }
    $data_atual->modify('+1 day');
}
?>

<style>
.relatorio-container {
    max-width: 1400px;
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

.filter-group select,
.filter-group input {
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
}

.btn-filter {
    padding: 10px 24px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
}

.resumo-card {
    background: linear-gradient(135deg, #667eea20, #764ba220);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
}

.resumo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
}

.resumo-item {
    text-align: center;
    background: var(--bg-primary);
    border-radius: 12px;
    padding: 12px;
}

.resumo-valor {
    font-size: 28px;
    font-weight: 700;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.resumo-label {
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 5px;
}

.table-card {
    background: var(--bg-primary);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    overflow: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

.data-table th,
.data-table td {
    padding: 10px 12px;
    text-align: center;
    border-bottom: 1px solid var(--border-color);
    font-size: 12px;
}

.data-table th {
    background: var(--bg-secondary);
    font-weight: 600;
    position: sticky;
    top: 0;
}

.status-normal { color: #10b981; }
.status-atencao { color: #f59e0b; }
.status-critico { color: #ef4444; }

.btn-exportar {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 500;
}

.btn-excel { background: #10b981; color: white; }
.btn-pdf { background: #ef4444; color: white; }

@media (max-width: 768px) {
    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<div class="relatorio-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-users"></i> Extrato Coletivo</h2>
            <p>Resumo de todos os funcionários</p>
        </div>
        <div class="module-actions">
            <a href="exportar_excel.php?tipo=extrato_coletivo&periodo=<?php echo $periodo; ?>&data_referencia=<?php echo $data_referencia; ?>&filial_id=<?php echo $filial_id; ?>" class="btn-exportar btn-excel">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </a>
            <a href="exportar_pdf.php?tipo=extrato_coletivo&periodo=<?php echo $periodo; ?>&data_referencia=<?php echo $data_referencia; ?>&filial_id=<?php echo $filial_id; ?>" class="btn-exportar btn-pdf" target="_blank">
                <i class="fas fa-file-pdf"></i> Exportar PDF
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-form">
            <div class="filter-group">
                <label>Período</label>
                <select name="periodo" id="periodo">
                    <option value="semanal" <?php echo $periodo == 'semanal' ? 'selected' : ''; ?>>Semanal</option>
                    <option value="mensal" <?php echo $periodo == 'mensal' ? 'selected' : ''; ?>>Mensal</option>
                </select>
            </div>
            <div class="filter-group" id="grupo_data_semanal" style="display: <?php echo $periodo == 'semanal' ? 'flex' : 'none'; ?>;">
                <label>Semana</label>
                <input type="week" name="data_referencia" value="<?php echo date('Y-\WW', strtotime($data_referencia)); ?>">
            </div>
            <div class="filter-group" id="grupo_data_mensal" style="display: <?php echo $periodo == 'mensal' ? 'flex' : 'none'; ?>;">
                <label>Mês</label>
                <input type="month" name="data_referencia" value="<?php echo substr($data_referencia, 0, 7); ?>">
            </div>
            <div class="filter-group">
                <label>Filial</label>
                <select name="filial_id">
                    <option value="">Todas as filiais</option>
                    <?php foreach ($filiais as $filial): ?>
                        <option value="<?php echo $filial['id']; ?>" <?php echo $filial_id == $filial['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($filial['nome_fantasia']); ?>
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

    <!-- Resumo -->
    <div class="resumo-card">
        <div class="resumo-grid">
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo count($funcionarios); ?></div>
                <div class="resumo-label">Total Funcionários</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo $dias_uteis; ?></div>
                <div class="resumo-label">Dias Úteis</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo $titulo_periodo; ?></div>
                <div class="resumo-label">Período</div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Matrícula</th>
                    <th>Filial</th>
                    <th>Cargo</th>
                    <th>Dias Trabalhados</th>
                    <th>Faltas</th>
                    <th>Atrasos</th>
                    <th>Presença</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($funcionarios)): ?>
                    <tr class="fade-in"><td colspan="9" style="text-align: center;">Nenhum funcionário encontrado</td></tr>
                <?php else: ?>
                    <?php foreach ($funcionarios as $func): ?>
                        <?php
                        $faltas = $dias_uteis - $func['dias_trabalhados'];
                        $presenca = $dias_uteis > 0 ? round((($dias_uteis - $faltas) / $dias_uteis) * 100, 1) : 0;
                        
                        if ($faltas > 3) {
                            $status = 'Crítico';
                            $status_class = 'status-critico';
                        } elseif ($faltas > 1 || $func['total_atrasos'] > 5) {
                            $status = 'Atenção';
                            $status_class = 'status-atencao';
                        } else {
                            $status = 'Normal';
                            $status_class = 'status-normal';
                        }
                        ?>
                        <tr class="fade-in">
                            <td><strong><?php echo htmlspecialchars($func['nome']); ?></strong></td>
                            <td><?php echo htmlspecialchars($func['matricula']); ?></td>
                            <td><?php echo htmlspecialchars($func['filial_nome'] ?? 'Matriz'); ?></td>
                            <td><?php echo htmlspecialchars($func['cargo_nome'] ?? 'Não definido'); ?></td>
                            <td><?php echo $func['dias_trabalhados']; ?>/<?php echo $dias_uteis; ?></td>
                            <td><?php echo $faltas; ?></td>
                            <td><?php echo $func['total_atrasos']; ?></td>
                            <td><?php echo $presenca; ?>%</td>
                            <td class="<?php echo $status_class; ?>"><?php echo $status; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById('periodo')?.addEventListener('change', function() {
    const grupoSemanal = document.getElementById('grupo_data_semanal');
    const grupoMensal = document.getElementById('grupo_data_mensal');
    
    if (this.value === 'semanal') {
        grupoSemanal.style.display = 'flex';
        grupoMensal.style.display = 'none';
    } else {
        grupoSemanal.style.display = 'none';
        grupoMensal.style.display = 'flex';
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>