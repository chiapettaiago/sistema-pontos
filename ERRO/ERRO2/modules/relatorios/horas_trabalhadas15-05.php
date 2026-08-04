<?php
// modules/relatorios/horas_trabalhadas.php - Relatório de Horas Trabalhadas (CORRIGIDO)
$pageTitle = 'Horas Trabalhadas';
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
$mes = $_GET['mes'] ?? date('Y-m');
$ano = substr($mes, 0, 4);
$mes_num = substr($mes, 5, 2);

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
    
    // Calcular total de minutos
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
        'media_diaria' => $dias_trabalhados > 0 ? sprintf("%02d:%02d", floor($total_minutos / $dias_trabalhados / 60), ($total_minutos / $dias_trabalhados) % 60) : '00:00',
        'total_minutos' => $total_minutos
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

.badge-primary {
    background: #e0e7ff;
    color: #4338ca;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-clock"></i> Relatório de Horas Trabalhadas</h2>
        <p>Total de horas trabalhadas por funcionário no período</p>
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
<div class="resumo-card">
    <h3><i class="fas fa-chart-line"></i> Resumo do Período</h3>
    <div class="resumo-grid">
        <div class="resumo-item">
            <div class="resumo-valor"><?php echo count($resultados); ?></div>
            <div class="resumo-label">Funcionários</div>
        </div>
        <div class="resumo-item">
            <div class="resumo-valor"><?php echo $total_dias; ?></div>
            <div class="resumo-label">Dias Trabalhados</div>
        </div>
        <div class="resumo-item">
            <div class="resumo-valor"><?php echo $total_horas_formatado; ?>h</div>
            <div class="resumo-label">Total de Horas</div>
        </div>
    </div>
</div>

<!-- Tabela de Resultados -->
<div class="table-card">
    <div class="table-header">
        <h3><i class="fas fa-table"></i> Horas Trabalhadas por Funcionário</h3>
        <p>Período: <?php echo strftime('%B de %Y', strtotime($mes . '-01')); ?></p>
    </div>
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
                <tr class="fade-in">
                    <td><strong><?php echo htmlspecialchars($r['nome']); ?></strong></td>
                    <td><?php echo htmlspecialchars($r['matricula']); ?></td>
                    <td><?php echo $r['dias_trabalhados']; ?> dias</td>
                    <td class="text-center"><span class="badge-primary"><?php echo $r['horas_totais']; ?>h</span></td>
                    <td class="text-center"><?php echo $r['media_diaria']; ?>h/dia</td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($resultados)): ?>
                <tr class="fade-in">
                    <td colspan="5" style="text-align: center; padding: 60px;">
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
    link.setAttribute('download', 'horas_trabalhadas.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
</script>

<?php require_once '../../includes/footer.php'; ?>


