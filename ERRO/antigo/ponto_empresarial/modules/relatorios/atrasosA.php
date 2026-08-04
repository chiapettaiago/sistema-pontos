<?php
// modules/relatorios/atrasos.php - Relatório de Atrasos e Faltas
$pageTitle = 'Atrasos e Faltas';
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

// Buscar funcionários
if ($_SESSION['usuario_tipo'] === 'admin') {
    $stmt = $db->query("SELECT id, nome, matricula FROM funcionarios WHERE status = 'ativo' ORDER BY nome");
    $funcionarios = $stmt->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios WHERE status = 'ativo' AND filial_id = :filial_id ORDER BY nome");
    $stmt->execute([':filial_id' => $_SESSION['usuario_filial_id']]);
    $funcionarios = $stmt->fetchAll();
}

// Buscar jornadas para calcular horário esperado
$jornadas = [];
$stmt = $db->query("SELECT id, segunda_inicio, terca_inicio, quarta_inicio, quinta_inicio, sexta_inicio, sabado_inicio, domingo_inicio FROM jornadas");
while ($j = $stmt->fetch()) {
    $jornadas[$j['id']] = $j;
}

// Analisar atrasos
$resultados = [];

foreach ($funcionarios as $func) {
    if ($funcionario_id && $func['id'] != $funcionario_id) continue;
    
    // Buscar jornada do funcionário
    $stmt = $db->prepare("SELECT jornada_id FROM funcionario_jornada WHERE funcionario_id = :id AND data_inicio <= :data_fim AND (data_fim IS NULL OR data_fim >= :data_inicio)");
    $stmt->execute([':id' => $func['id'], ':data_inicio' => $data_inicio, ':data_fim' => $data_fim]);
    $jornada_rel = $stmt->fetch();
    
    if (!$jornada_rel) continue;
    
    $jornada = $jornadas[$jornada_rel['jornada_id']] ?? null;
    if (!$jornada) continue;
    
    // Buscar pontos do período
    $query = "SELECT tipo, data_hora 
              FROM pontos 
              WHERE funcionario_id = :funcionario_id 
              AND DATE(data_hora) BETWEEN :data_inicio AND :data_fim
              ORDER BY data_hora ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':funcionario_id' => $func['id'],
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim
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
    
    // Analisar cada dia
    foreach ($dias as $data => $dia) {
        $dia_semana = date('w', strtotime($data));
        $dias_semana = ['domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado'];
        $campo = $dias_semana[$dia_semana] . '_inicio';
        
        $horario_esperado = $jornada[$campo] ?? null;
        
        if ($horario_esperado && $dia['entrada']) {
            $entrada = date('H:i', strtotime($dia['entrada']));
            $atraso_minutos = (strtotime($entrada) - strtotime($horario_esperado)) / 60;
            
            if ($atraso_minutos > 5) { // Tolerância de 5 minutos
                $resultados[] = [
                    'data' => $data,
                    'funcionario_nome' => $func['nome'],
                    'funcionario_id' => $func['id'],
                    'matricula' => $func['matricula'],
                    'horario_esperado' => substr($horario_esperado, 0, 5),
                    'horario_registrado' => $entrada,
                    'atraso_minutos' => round($atraso_minutos),
                    'tipo' => 'Atraso'
                ];
            }
        }
        
        // Verificar falta (se não tem entrada)
        if (!$dia['entrada']) {
            $resultados[] = [
                'data' => $data,
                'funcionario_nome' => $func['nome'],
                'funcionario_id' => $func['id'],
                'matricula' => $func['matricula'],
                'horario_esperado' => $horario_esperado ? substr($horario_esperado, 0, 5) : '--:--',
                'horario_registrado' => '--:--',
                'atraso_minutos' => 0,
                'tipo' => 'Falta'
            ];
        }
    }
}

// Ordenar por data
usort($resultados, function($a, $b) {
    return strtotime($b['data']) - strtotime($a['data']);
});

// Totais
$total_atrasos = count(array_filter($resultados, function($r) { return $r['tipo'] == 'Atraso'; }));
$total_faltas = count(array_filter($resultados, function($r) { return $r['tipo'] == 'Falta'; }));
?>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-exclamation-triangle"></i> Relatório de Atrasos e Faltas</h2>
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
            <a href="atrasos.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Limpar
            </a>
        </div>
    </form>
</div>

<!-- Cards de Resumo -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_atrasos; ?></h3>
            <p>Total de Atrasos</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <i class="fas fa-calendar-times"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_faltas; ?></h3>
            <p>Total de Faltas</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count($resultados); ?></h3>
            <p>Total de Ocorrências</p>
        </div>
    </div>
</div>

<!-- Tabela de Resultados -->
<div class="table-card">
    <div class="table-responsive">
        <table class="data-table" id="tabelaAtrasos">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Funcionário</th>
                    <th>Matrícula</th>
                    <th>Tipo</th>
                    <th>Horário Esperado</th>
                    <th>Horário Registrado</th>
                    <th>Atraso</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultados as $r): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($r['data'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($r['funcionario_nome']); ?></strong></td>
                    <td><?php echo htmlspecialchars($r['matricula']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $r['tipo'] == 'Atraso' ? 'warning' : 'danger'; ?>">
                            <?php echo $r['tipo']; ?>
                        </span>
                    </td>
                    <td><?php echo $r['horario_esperado']; ?>h</td>
                    <td><?php echo $r['horario_registrado']; ?>h</td>
                    <td class="text-center">
                        <?php if ($r['tipo'] == 'Atraso'): ?>
                            <span class="badge badge-warning"><?php echo $r['atraso_minutos']; ?> minutos</span>
                        <?php else: ?>
                            <span class="badge badge-danger">--</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($resultados)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px;">
                        <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981;"></i>
                        <p style="margin-top: 10px;">Nenhum atraso ou falta encontrado no período</p>
                        <p style="font-size: 12px; color: #666;">Todos os funcionários estão em dia!</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function exportarExcel() {
    const tabela = document.getElementById('tabelaAtrasos');
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
    link.setAttribute('download', 'atrasos_faltas.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
</script>

<style>
.badge-warning {
    background: #fed7aa;
    color: #c2410c;
}
.badge-danger {
    background: #fee2e2;
    color: #dc2626;
}
.text-center {
    text-align: center;
}
</style>

<?php require_once '../../includes/footer.php'; ?>