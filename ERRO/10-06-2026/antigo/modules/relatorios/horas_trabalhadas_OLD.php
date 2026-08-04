<?php
// modules/relatorios/horas_trabalhadas.php - Relatório de Horas Trabalhadas
$pageTitle = 'Horas Trabalhadas';
$activePage = 'relatorios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

checkModuleAccess('relatorios');

$database = new Database();
$db = $database->getConnection();

// Parâmetros de filtro
$funcionario_id = $_GET['funcionario_id'] ?? '';
$mes = $_GET['mes'] ?? date('Y-m');
$ano = substr($mes, 0, 4);
$mes_num = substr($mes, 5, 2);

// Buscar funcionários
if ($_SESSION['usuario_tipo'] === 'admin') {
    $stmt = $db->query("SELECT id, nome, matricula FROM funcionarios WHERE status = 'ativo' ORDER BY nome");
    $funcionarios = $stmt->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios WHERE status = 'ativo' AND filial_id = :filial_id ORDER BY nome");
    $stmt->execute([':filial_id' => $_SESSION['usuario_filial_id']]);
    $funcionarios = $stmt->fetchAll();
}

// Calcular horas trabalhadas por funcionário
$resultados = [];

foreach ($funcionarios as $func) {
    if ($funcionario_id && $func['id'] != $funcionario_id) continue;
    
    // Buscar pontos do mês
    $query = "SELECT tipo, data_hora 
              FROM pontos 
              WHERE funcionario_id = :funcionario_id 
              AND MONTH(data_hora) = :mes 
              AND YEAR(data_hora) = :ano
              ORDER BY data_hora ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':funcionario_id' => $func['id'],
        ':mes' => $mes_num,
        ':ano' => $ano
    ]);
    $pontos = $stmt->fetchAll();
    
    // Agrupar por dia
    $dias = [];
    foreach ($pontos as $ponto) {
        $data = date('Y-m-d', strtotime($ponto['data_hora']));
        if (!isset($dias[$data])) {
            $dias[$data] = [
                'entrada' => null,
                'saida_almoco' => null,
                'volta_almoco' => null,
                'saida' => null
            ];
        }
        $dias[$data][$ponto['tipo']] = $ponto['data_hora'];
    }
    
    // Calcular horas por dia
    $total_minutos = 0;
    $dias_trabalhados = 0;
    
    foreach ($dias as $dia) {
        if ($dia['entrada'] && $dia['saida']) {
            $entrada = strtotime($dia['entrada']);
            $saida = strtotime($dia['saida']);
            $minutos_dia = ($saida - $entrada) / 60;
            
            if ($dia['saida_almoco'] && $dia['volta_almoco']) {
                $saida_almoco = strtotime($dia['saida_almoco']);
                $volta_almoco = strtotime($dia['volta_almoco']);
                $minutos_dia -= ($volta_almoco - $saida_almoco) / 60;
            }
            
            $total_minutos += $minutos_dia;
            $dias_trabalhados++;
        }
    }
    
    $horas_totais = floor($total_minutos / 60);
    $minutos_totais = $total_minutos % 60;
    
    $resultados[] = [
        'id' => $func['id'],
        'nome' => $func['nome'],
        'matricula' => $func['matricula'],
        'dias_trabalhados' => $dias_trabalhados,
        'horas_totais' => sprintf("%02d:%02d", $horas_totais, $minutos_totais),
        'media_diaria' => $dias_trabalhados > 0 ? sprintf("%02d:%02d", floor($total_minutos / $dias_trabalhados / 60), ($total_minutos / $dias_trabalhados) % 60) : '00:00'
    ];
}

// Totais gerais
$total_horas = 0;
$total_dias = 0;
foreach ($resultados as $r) {
    $partes = explode(':', $r['horas_totais']);
    $total_horas += ($partes[0] * 60 + $partes[1]);
    $total_dias += $r['dias_trabalhados'];
}
$total_horas_formatado = sprintf("%02d:%02d", floor($total_horas / 60), $total_horas % 60);
?>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-clock"></i> Relatório de Horas Trabalhadas</h2>
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
            <label><i class="fas fa-calendar"></i> Mês/Ano</label>
            <input type="month" name="mes" value="<?php echo $mes; ?>">
        </div>
        
        <div class="filter-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filtrar
            </button>
            <a href="horas_trabalhadas.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Limpar
            </a>
        </div>
    </form>
</div>

<!-- Cards de Resumo -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count($resultados); ?></h3>
            <p>Funcionários</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_dias; ?></h3>
            <p>Dias Trabalhados</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_horas_formatado; ?></h3>
            <p>Total de Horas</p>
        </div>
    </div>
</div>

<!-- Tabela de Resultados -->
<div class="table-card">
    <div class="table-responsive">
        <table class="data-table" id="tabelaHoras">
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Matrícula</th>
                    <th>Dias Trabalhados</th>
                    <th>Total de Horas</th>
                    <th>Média Diária</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultados as $r): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($r['nome']); ?></strong></td>
                    <td><?php echo htmlspecialchars($r['matricula']); ?></td>
                    <td><?php echo $r['dias_trabalhados']; ?> dias</td>
                    <td class="text-center"><span class="badge badge-primary"><?php echo $r['horas_totais']; ?>h</span></td>
                    <td class="text-center"><?php echo $r['media_diaria']; ?>h/dia</td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($resultados)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px;">
                        <i class="fas fa-chart-line" style="font-size: 48px; color: #ccc;"></i>
                        <p style="margin-top: 10px;">Nenhum registro encontrado no período</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function exportarExcel() {
    const tabela = document.getElementById('tabelaHoras');
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
    link.setAttribute('download', 'horas_trabalhadas.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
</script>

<style>
.badge-primary {
    background: #e0e7ff;
    color: #4338ca;
    font-weight: 600;
}
.text-center {
    text-align: center;
}
</style>

<?php require_once '../../includes/footer.php'; ?>