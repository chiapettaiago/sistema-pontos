<?php
// modules/relatorios/banco_horas.php - Relatório de Banco de Horas (CORRIGIDO)
$pageTitle = 'Banco de Horas';
$activePage = 'relatorios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

checkModuleAccess('relatorios');

$database = new Database();
$db = $database->getConnection();

// Obter valores da sessão com fallback
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';
$usuario_filial_id = $_SESSION['usuario_filial_id'] ?? null;
$empresa_id = getCurrentEmpresaId();
if ($empresa_id === null || $empresa_id === '') {
    $empresa_id = $_SESSION['empresa_id'] ?? null;
}
if ($empresa_id === null || $empresa_id === '') {
    header('Location: /index.php');
    exit;
}

// Parâmetros de filtro
$funcionario_id = $_GET['funcionario_id'] ?? '';

// Buscar funcionários para o filtro
if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa') {
    $stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios WHERE status = 'ativo' AND empresa_id = :empresa_id ORDER BY nome");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $funcionarios = $stmt->fetchAll();
} else {
    // Para gestor/supervisor, mostrar apenas funcionários da sua filial
    if ($usuario_filial_id) {
        $stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios WHERE status = 'ativo' AND empresa_id = :empresa_id AND filial_id = :filial_id ORDER BY nome");
        $stmt->execute([':empresa_id' => $empresa_id, ':filial_id' => $usuario_filial_id]);
    } else {
        $stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios WHERE status = 'ativo' AND empresa_id = :empresa_id ORDER BY nome");
        $stmt->execute([':empresa_id' => $empresa_id]);
    }
    $funcionarios = $stmt->fetchAll();
}

// Buscar saldo de banco de horas
$query = "SELECT bh.*, f.nome, f.matricula
          FROM banco_horas bh
          JOIN funcionarios f ON bh.funcionario_id = f.id
          WHERE f.empresa_id = :empresa_id";

$params = [':empresa_id' => $empresa_id];

if ($funcionario_id) {
    $query .= " AND bh.funcionario_id = :funcionario_id";
    $params[':funcionario_id'] = $funcionario_id;
}

if ($usuario_tipo !== 'super_admin' && $usuario_tipo !== 'admin_empresa' && $usuario_filial_id) {
    $query .= " AND f.filial_id = :filial_id";
    $params[':filial_id'] = $usuario_filial_id;
}

$query .= " ORDER BY bh.mes_referencia DESC, f.nome ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$saldos = $stmt->fetchAll();

// Função para formatar minutos em horas
function formatarHorasBanco($minutos) {
    $horas = floor(abs($minutos) / 60);
    $mins = abs($minutos) % 60;
    $sinal = $minutos < 0 ? '-' : '';
    return $sinal . sprintf("%02d:%02d", $horas, $mins);
}

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

// Função para cor do saldo
function getSaldoColorBanco($minutos) {
    if ($minutos > 0) return '#10b981';
    if ($minutos < 0) return '#ef4444';
    return '#6b7280';
}
?>

<style>
.resumo-card {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
}

.resumo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 16px;
}

.resumo-item {
    text-align: center;
    padding: 16px;
    background: var(--bg-secondary);
    border-radius: 16px;
}

.resumo-valor {
    font-size: 28px;
    font-weight: bold;
    color: var(--primary);
}

.resumo-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 4px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table th {
    background: var(--bg-secondary);
    font-weight: 600;
    font-size: 13px;
}

.text-center {
    text-align: center;
}

.badge-info {
    background: #e0e7ff;
    color: #4338ca;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.saldo-positivo {
    font-weight: bold;
}

.saldo-negativo {
    font-weight: bold;
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-piggy-bank"></i> Banco de Horas</h2>
        <p>Relatório de saldo de horas extras e banco de horas</p>
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
                <tr class="fade-in">
                    <td><strong><?php echo htmlspecialchars($func['nome']); ?></strong></td>
                    <td><?php echo htmlspecialchars($func['matricula']); ?></td>
                    <td class="text-center"><?php echo formatarHorasBanco($func['horas_extras_50']); ?>h</td>
                    <td class="text-center"><?php echo formatarHorasBanco($func['horas_extras_100']); ?>h</td>
                    <td class="text-center"><?php echo formatarHorasBanco($func['faltas']); ?>h</td>
                    <td class="text-center">
                        <span class="saldo-<?php echo $func['saldo_total'] >= 0 ? 'positivo' : 'negativo'; ?>" 
                              style="color: <?php echo getSaldoColorBanco($func['saldo_total']); ?>;">
                            <?php echo formatarHorasBanco($func['saldo_total']); ?>h
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($resumo_funcionarios)): ?>
                <tr class="fade-in">
                    <td colspan="6" style="text-align: center; padding: 60px;">
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
                <tr class="fade-in">
                    <td class="text-center"><?php echo date('F/Y', strtotime($saldo['mes_referencia'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($saldo['nome']); ?></strong></td>
                    <td><?php echo htmlspecialchars($saldo['matricula']); ?></td>
                    <td class="text-center"><?php echo formatarHorasBanco($saldo['horas_extras_50']); ?>h</td>
                    <td class="text-center"><?php echo formatarHorasBanco($saldo['horas_extras_100']); ?>h</td>
                    <td class="text-center"><?php echo formatarHorasBanco($saldo['faltas_minutos']); ?>h</td>
                    <td class="text-center">
                        <span style="color: <?php echo getSaldoColorBanco($saldo['saldo_minutos']); ?>; font-weight: bold;">
                            <?php echo formatarHorasBanco($saldo['saldo_minutos']); ?>h
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($saldos)): ?>
                <tr class="fade-in">
                    <td colspan="7" style="text-align: center; padding: 60px;">
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
    
    // Cabeçalho
    let cabecalho = [];
    tabela.querySelectorAll('thead th').forEach(th => {
        cabecalho.push('"' + th.innerText + '"');
    });
    csv.push(cabecalho.join(','));
    
    // Dados
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

<?php require_once '../../includes/footer.php'; ?>


