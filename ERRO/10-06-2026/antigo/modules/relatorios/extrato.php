<?php
// modules/relatorios/extrato.php - Extrato Detalhado de Ponto
$pageTitle = 'Extrato de Ponto';
$activePage = 'relatorios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

checkModuleAccess('relatorios');

$database = new Database();
$db = $database->getConnection();

// Parâmetros de filtro
$funcionario_id = $_GET['funcionario_id'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

// Buscar funcionários para o filtro
if ($_SESSION['usuario_tipo'] === 'admin') {
    $stmt = $db->query("SELECT id, nome, matricula, filial_id FROM funcionarios WHERE status = 'ativo' ORDER BY nome");
    $funcionarios = $stmt->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, nome, matricula, filial_id FROM funcionarios WHERE status = 'ativo' AND filial_id = :filial_id ORDER BY nome");
    $stmt->execute([':filial_id' => $_SESSION['usuario_filial_id']]);
    $funcionarios = $stmt->fetchAll();
}

// Buscar pontos
$query = "SELECT p.*, f.nome as funcionario_nome, f.matricula, fil.nome_fantasia as filial_nome
          FROM pontos p
          JOIN funcionarios f ON p.funcionario_id = f.id
          JOIN filiais fil ON p.filial_id = fil.id
          WHERE DATE(p.data_hora) BETWEEN :data_inicio AND :data_fim";

$params = [
    ':data_inicio' => $data_inicio,
    ':data_fim' => $data_fim
];

if ($funcionario_id) {
    $query .= " AND p.funcionario_id = :funcionario_id";
    $params[':funcionario_id'] = $funcionario_id;
}

if ($_SESSION['usuario_tipo'] !== 'admin') {
    $query .= " AND f.filial_id = :filial_id";
    $params[':filial_id'] = $_SESSION['usuario_filial_id'];
}

$query .= " ORDER BY p.data_hora DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$pontos = $stmt->fetchAll();

// Agrupar pontos por data para extrato
$extrato = [];
foreach ($pontos as $ponto) {
    $data = date('Y-m-d', strtotime($ponto['data_hora']));
    if (!isset($extrato[$data])) {
        $extrato[$data] = [
            'data' => $data,
            'funcionario_nome' => $ponto['funcionario_nome'],
            'funcionario_id' => $ponto['funcionario_id'],
            'matricula' => $ponto['matricula'],
            'filial_nome' => $ponto['filial_nome'],
            'entrada' => null,
            'saida_almoco' => null,
            'volta_almoco' => null,
            'saida' => null
        ];
    }
    
    switch ($ponto['tipo']) {
        case 'entrada':
            $extrato[$data]['entrada'] = date('H:i', strtotime($ponto['data_hora']));
            break;
        case 'saida_almoco':
            $extrato[$data]['saida_almoco'] = date('H:i', strtotime($ponto['data_hora']));
            break;
        case 'volta_almoco':
            $extrato[$data]['volta_almoco'] = date('H:i', strtotime($ponto['data_hora']));
            break;
        case 'saida':
            $extrato[$data]['saida'] = date('H:i', strtotime($ponto['data_hora']));
            break;
    }
}

// Calcular horas trabalhadas para cada dia
function calcularHoras($entrada, $saida_almoco, $volta_almoco, $saida) {
    if (!$entrada || !$saida) return '--:--';
    
    $entrada_ts = strtotime($entrada);
    $saida_almoco_ts = $saida_almoco ? strtotime($saida_almoco) : null;
    $volta_almoco_ts = $volta_almoco ? strtotime($volta_almoco) : null;
    $saida_ts = strtotime($saida);
    
    $total_minutos = ($saida_ts - $entrada_ts) / 60;
    
    if ($saida_almoco_ts && $volta_almoco_ts) {
        $total_minutos -= ($volta_almoco_ts - $saida_almoco_ts) / 60;
    }
    
    $horas = floor($total_minutos / 60);
    $minutos = $total_minutos % 60;
    
    return sprintf("%02d:%02d", $horas, $minutos);
}

foreach ($extrato as &$dia) {
    $dia['horas'] = calcularHoras($dia['entrada'], $dia['saida_almoco'], $dia['volta_almoco'], $dia['saida']);
}
?>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-calendar-alt"></i> Extrato de Ponto</h2>
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
                    <?php echo htmlspecialchars($func['nome'] . ' (' . $func['matricula'] . ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <label><i class="fas fa-calendar"></i> Data Início</label>
            <input type="date" name="data_inicio" value="<?php echo $data_inicio; ?>">
        </div>
        
        <div class="filter-group">
            <label><i class="fas fa-calendar"></i> Data Fim</label>
            <input type="date" name="data_fim" value="<?php echo $data_fim; ?>">
        </div>
        
        <div class="filter-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filtrar
            </button>
            <a href="extrato.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Limpar
            </a>
        </div>
    </form>
</div>

<!-- Tabela de Extrato -->
<div class="table-card">
    <div class="table-responsive">
        <table class="data-table" id="tabelaExtrato">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Funcionário</th>
                    <th>Matrícula</th>
                    <th>Filial</th>
                    <th>Entrada</th>
                    <th>Saída Almoço</th>
                    <th>Volta Almoço</th>
                    <th>Saída</th>
                    <th>Horas Trabalhadas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($extrato as $dia): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($dia['data'])); ?></td>
                    <td><?php echo htmlspecialchars($dia['funcionario_nome']); ?></td>
                    <td><?php echo htmlspecialchars($dia['matricula']); ?></td>
                    <td><?php echo htmlspecialchars($dia['filial_nome']); ?></td>
                    <td><?php echo $dia['entrada'] ?? '--:--'; ?></td>
                    <td><?php echo $dia['saida_almoco'] ?? '--:--'; ?></td>
                    <td><?php echo $dia['volta_almoco'] ?? '--:--'; ?></td>
                    <td><?php echo $dia['saida'] ?? '--:--'; ?></td>
                    <td class="text-center">
                        <span class="badge badge-info"><?php echo $dia['horas']; ?>h</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($extrato)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px;">
                        <i class="fas fa-calendar-times" style="font-size: 48px; color: #ccc;"></i>
                        <p style="margin-top: 10px;">Nenhum registro encontrado no período selecionado</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($extrato)): ?>
            <tfoot>
                <tr>
                    <td colspan="8" style="text-align: right; font-weight: 600;">Total de Registros:</td>
                    <td class="text-center"><strong><?php echo count($extrato); ?> dias</strong></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<script>
function exportarExcel() {
    const tabela = document.getElementById('tabelaExtrato');
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
    link.setAttribute('download', 'extrato_ponto.csv');
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
.badge-info {
    background: #d1fae5;
    color: #059669;
}
tfoot td {
    background: #f8f9fa;
    font-weight: 600;
}
</style>

<?php require_once '../../includes/footer.php'; ?>