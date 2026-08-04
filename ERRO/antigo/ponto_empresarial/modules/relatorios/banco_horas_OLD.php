<?php
// modules/relatorios/banco_horas.php - Relatório de Banco de Horas
$pageTitle = 'Banco de Horas';
$activePage = 'relatorios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

checkModuleAccess('relatorios');

$database = new Database();
$db = $database->getConnection();

// Parâmetros de filtro
$funcionario_id = $_GET['funcionario_id'] ?? '';

// Buscar funcionários
if ($_SESSION['usuario_tipo'] === 'admin') {
    $stmt = $db->query("SELECT id, nome, matricula FROM funcionarios WHERE status = 'ativo' ORDER BY nome");
    $funcionarios = $stmt->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios WHERE status = 'ativo' AND filial_id = :filial_id ORDER BY nome");
    $stmt->execute([':filial_id' => $_SESSION['usuario_filial_id']]);
    $funcionarios = $stmt->fetchAll();
}

// Buscar saldo de banco de horas
$query = "SELECT bh.*, f.nome, f.matricula
          FROM banco_horas bh
          JOIN funcionarios f ON bh.funcionario_id = f.id
          WHERE 1=1";

$params = [];

if ($funcionario_id) {
    $query .= " AND bh.funcionario_id = :funcionario_id";
    $params[':funcionario_id'] = $funcionario_id;
}

if ($_SESSION['usuario_tipo'] !== 'admin') {
    $query .= " AND f.filial_id = :filial_id";
    $params[':filial_id'] = $_SESSION['usuario_filial_id'];
}

$query .= " ORDER BY bh.mes_referencia DESC, f.nome ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$saldos = $stmt->fetchAll();

// Agrupar por funcionário para resumo
$resumo_funcionarios = [];
foreach ($saldos as $saldo) {
    if (!isset($resumo_funcionarios[$saldo['funcionario_id']])) {
        $resumo_funcionarios[$saldo['funcionario_id']] = [
            'nome' => $saldo['nome'],
            'matricula' => $saldo['matricula'],
            'saldo_total' => 0,
            'horas_extras_50' => 0,
            'horas_extras_100' => 0,
            'faltas' => 0
        ];
    }
    $resumo_funcionarios[$saldo['funcionario_id']]['saldo_total'] += $saldo['saldo_minutos'];
    $resumo_funcionarios[$saldo['funcionario_id']]['horas_extras_50'] += $saldo['horas_extras_50'];
    $resumo_funcionarios[$saldo['funcionario_id']]['horas_extras_100'] += $saldo['horas_extras_100'];
    $resumo_funcionarios[$saldo['funcionario_id']]['faltas'] += $saldo['faltas_minutos'];
}

// Função para formatar minutos em horas
function formatarHoras($minutos) {
    $horas = floor(abs($minutos) / 60);
    $mins = abs($minutos) % 60;
    $sinal = $minutos < 0 ? '-' : '';
    return $sinal . sprintf("%02d:%02d", $horas, $mins);
}

// Função para cor do saldo
function getSaldoColor($minutos) {
    if ($minutos > 0) return '#10b981';
    if ($minutos < 0) return '#ef4444';
    return '#6b7280';
}
?>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-piggy-bank"></i> Banco de Horas</h2>
    </div>
    <div class="module-actions">
        <button onclick="window.print()" class="btn btn-secondary">
            <i class="fas fa-print"></i> Imprimir
        </button>
        <button onclick="exportarExcel()" class="btn btn-primary">
            <i class="fas fa-file-excel"></i> Exportar Excel
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="filters-card">
    <form method="GET" action="" class="filters-form">
        <div class="filter-group">
            <label><i class="fas fa-user"></i> Funcionário</label>
            <select name="funcionario_id">
                <option value="">Todos os funcionários</option>
                <?php foreach ($funcionarios as $func): ?>
                <option value="<?php echo $func['id']; ?>" <?php echo $funcionario_id == $func['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($func['nome']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filtrar
            </button>
            <a href="banco_horas.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Limpar
            </a>
        </div>
    </form>
</div>

<!-- Resumo por Funcionário -->
<div class="table-card">
    <div class="table-header">
        <h3><i class="fas fa-chart-simple"></i> Resumo de Saldo por Funcionário</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table" id="tabelaResumo">
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Matrícula</th>
                    <th>Horas Extras 50%</th>
                    <th>Horas Extras 100%</th>
                    <th>Faltas</th>
                    <th>Saldo Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resumo_funcionarios as $func): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($func['nome']); ?></strong></td>
                    <td><?php echo htmlspecialchars($func['matricula']); ?></td>
                    <td class="text-center"><?php echo formatarHoras($func['horas_extras_50']); ?>h</td>
                    <td class="text-center"><?php echo formatarHoras($func['horas_extras_100']); ?>h</td>
                    <td class="text-center"><?php echo formatarHoras($func['faltas']); ?>h</td>
                    <td class="text-center">
                        <span style="color: <?php echo getSaldoColor($func['saldo_total']); ?>; font-weight: bold;">
                            <?php echo formatarHoras($func['saldo_total']); ?>h
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($resumo_funcionarios)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px;">
                        <i class="fas fa-chart-line" style="font-size: 48px; color: #ccc;"></i>
                        <p style="margin-top: 10px;">Nenhum registro encontrado</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Detalhamento Mensal -->
<div class="table-card">
    <div class="table-header">
        <h3><i class="fas fa-calendar-alt"></i> Detalhamento Mensal</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table" id="tabelaDetalhada">
            <thead>
                <tr>
                    <th>Mês/Ano</th>
                    <th>Funcionário</th>
                    <th>Matrícula</th>
                    <th>Horas Extras 50%</th>
                    <th>Horas Extras 100%</th>
                    <th>Faltas</th>
                    <th>Saldo do Mês</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($saldos as $saldo): ?>
                <tr>
                    <td><?php echo date('F/Y', strtotime($saldo['mes_referencia'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($saldo['nome']); ?></strong></td>
                    <td><?php echo htmlspecialchars($saldo['matricula']); ?></td>
                    <td class="text-center"><?php echo formatarHoras($saldo['horas_extras_50']); ?>h</td>
                    <td class="text-center"><?php echo formatarHoras($saldo['horas_extras_100']); ?>h</td>
                    <td class="text-center"><?php echo formatarHoras($saldo['faltas_minutos']); ?>h</td>
                    <td class="text-center">
                        <span style="color: <?php echo getSaldoColor($saldo['saldo_minutos']); ?>; font-weight: bold;">
                            <?php echo formatarHoras($saldo['saldo_minutos']); ?>h
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($saldos)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px;">
                        <i class="fas fa-chart-line" style="font-size: 48px; color: #ccc;"></i>
                        <p style="margin-top: 10px;">Nenhum registro encontrado</p>
                     </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function exportarExcel() {
    const tabela = document.getElementById('tabelaDetalhada');
    const linhas = tabela.querySelectorAll('tr');
    let csv = [];
    
    let cabecalho = [];
    tabela.querySelectorAll('thead th').forEach(th => {
        cabecalho.push('"' + th.innerText + '"');
    });
    csv.push(cabecalho.join(','));
    
    linhas.forEach(linha => {
        const linhaDados = [];
        linha.querySelectorAll('td').forEach(td => {
            linhaDados.push('"' + td.innerText.replace(/"/g, '""') + '"');
        });
        if (linhaDados.length) {
            csv.push(linhaDados.join(','));
        }
    });
    
    const blob = new Blob(["\uFEFF" + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.href = url;
    link.setAttribute('download', 'banco_horas.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
</script>

<style>
.text-center {
    text-align: center;
}
</style>

<?php require_once '../../includes/footer.php'; ?>