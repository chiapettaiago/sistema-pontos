<?php
// modules/relatorios/extrato_geral.php - Extrato Geral da Empresa
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

$pageTitle = 'Extrato Geral';
$activePage = 'relatorios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;

// Parâmetros de filtro
$mes = $_GET['mes'] ?? date('Y-m');
$ano = substr($mes, 0, 4);
$mes_num = substr($mes, 5, 2);

// Buscar resumo por funcionário
$query = "SELECT 
            f.id,
            f.nome,
            f.matricula,
            COUNT(DISTINCT DATE(p.data_hora)) as dias_trabalhados,
            SUM(CASE WHEN p.tipo = 'entrada' AND TIME(p.data_hora) > '08:00:00' THEN 1 ELSE 0 END) as total_atrasos,
            COUNT(CASE WHEN p.tipo = 'entrada' THEN 1 END) as total_entradas
          FROM funcionarios f
          LEFT JOIN pontos p ON f.id = p.funcionario_id 
            AND MONTH(p.data_hora) = :mes 
            AND YEAR(p.data_hora) = :ano
          WHERE f.empresa_id = :empresa_id AND f.status = 'ativo'
          GROUP BY f.id
          ORDER BY f.nome";

$stmt = $db->prepare($query);
$stmt->execute([
    ':mes' => $mes_num,
    ':ano' => $ano,
    ':empresa_id' => $empresa_id
]);
$funcionarios = $stmt->fetchAll();

// Totais gerais
$total_funcionarios = count($funcionarios);
$total_dias_trabalhados = array_sum(array_column($funcionarios, 'dias_trabalhados'));
$total_atrasos = array_sum(array_column($funcionarios, 'total_atrasos'));
?>

<style>
.relatorio-container {
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
    margin: 8px 0 0 0;
    color: var(--text-secondary);
    font-size: 14px;
}

.resumo-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
}

.resumo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.resumo-item {
    text-align: center;
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
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 5px;
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
    font-weight: 500;
}

.table-card {
    background: var(--bg-primary);
    border-radius: 16px;
    overflow: auto;
    border: 1px solid var(--border-color);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
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
    font-size: 13px;
}

.data-table tr:hover {
    background: var(--bg-secondary);
}

.btn-exportar {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 500;
}

.btn-excel {
    background: #10b981;
    color: white;
}

.btn-pdf {
    background: #ef4444;
    color: white;
}

@media (max-width: 768px) {
    .resumo-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="relatorio-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-building"></i> Extrato Geral da Empresa</h2>
            <p><?php echo strftime('%B de %Y', strtotime($mes . '-01')); ?></p>
        </div>
        <div class="module-actions">
            <a href="exportar_excel.php?tipo=extrato_geral&mes=<?php echo $mes; ?>" class="btn-exportar btn-excel">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </a>
            <a href="exportar_pdf.php?tipo=extrato_geral&mes=<?php echo $mes; ?>" class="btn-exportar btn-pdf">
                <i class="fas fa-file-pdf"></i> Exportar PDF
            </a>
        </div>
    </div>

    <!-- Resumo -->
    <div class="resumo-card">
        <div class="resumo-grid">
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo $total_funcionarios; ?></div>
                <div class="resumo-label">Funcionários Ativos</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo $total_dias_trabalhados; ?></div>
                <div class="resumo-label">Dias Trabalhados</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo $total_atrasos; ?></div>
                <div class="resumo-label">Total de Atrasos</div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-form">
            <div class="filter-group">
                <label>Mês/Ano</label>
                <input type="month" name="mes" value="<?php echo $mes; ?>">
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn-filter">
                    <i class="fas fa-search"></i> Filtrar
                </button>
            </div>
        </form>
    </div>

    <!-- Tabela -->
    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Matrícula</th>
                    <th>Dias Trabalhados</th>
                    <th>Total de Atrasos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($funcionarios as $func): ?>
                    <?php
                    $status = 'Normal';
                    $status_class = 'normal';
                    if ($func['total_atrasos'] > 5) {
                        $status = 'Crítico';
                        $status_class = 'critico';
                    } elseif ($func['total_atrasos'] > 2) {
                        $status = 'Atenção';
                        $status_class = 'atencao';
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($func['nome']); ?></td>
                        <td><?php echo htmlspecialchars($func['matricula']); ?></td>
                        <td><?php echo $func['dias_trabalhados']; ?></td>
                        <td><?php echo $func['total_atrasos']; ?> min</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
