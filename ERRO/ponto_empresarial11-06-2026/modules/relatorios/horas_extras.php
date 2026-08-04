<?php
// modules/relatorios/horas_extras.php - Relatório de Horas Extras (Diário/Semanal/Mensal)
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

$pageTitle = 'Relatório de Horas Extras';
$activePage = 'relatorios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;

// Parâmetros
$periodo = $_GET['periodo'] ?? 'diario';
$data_referencia = $_GET['data_referencia'] ?? date('Y-m-d');
$funcionario_id = $_GET['funcionario_id'] ?? '';
$ano = $_GET['ano'] ?? date('Y');
$mes = $_GET['mes'] ?? date('m');
$semana = $_GET['semana'] ?? date('W');
$carga_diaria = 8; // 8 horas por dia

// Definir período
switch ($periodo) {
    case 'diario':
        $data_inicio = $data_referencia;
        $data_fim = $data_referencia;
        $titulo_periodo = 'Dia ' . date('d/m/Y', strtotime($data_referencia));
        break;
    case 'semanal':
        $data_inicio = date('Y-m-d', strtotime($ano . '-W' . str_pad($semana, 2, '0', STR_PAD_LEFT) . '-1'));
        $data_fim = date('Y-m-d', strtotime($ano . '-W' . str_pad($semana, 2, '0', STR_PAD_LEFT) . '-7'));
        $titulo_periodo = 'Semana ' . $semana . ' de ' . $ano;
        break;
    case 'mensal':
        $data_inicio = date('Y-m-01', strtotime($ano . '-' . $mes . '-01'));
        $data_fim = date('Y-m-t', strtotime($ano . '-' . $mes . '-01'));
        $nomes_meses = [
            '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
            '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
            '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
        ];
        $titulo_periodo = $nomes_meses[$mes] . ' de ' . $ano;
        break;
}

// Buscar funcionários
$stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios 
                      WHERE empresa_id = :empresa_id AND status = 'ativo' 
                      ORDER BY nome");
$stmt->execute([':empresa_id' => $empresa_id]);
$funcionarios = $stmt->fetchAll();

// Buscar horas trabalhadas
$query = "SELECT 
            f.id as funcionario_id,
            f.nome as funcionario_nome,
            f.matricula,
            DATE(p.data_hora) as data,
            MAX(CASE WHEN p.tipo = 'entrada' THEN TIME(p.data_hora) END) as entrada,
            MAX(CASE WHEN p.tipo = 'saida_almoco' THEN TIME(p.data_hora) END) as saida_almoco,
            MAX(CASE WHEN p.tipo = 'volta_almoco' THEN TIME(p.data_hora) END) as volta_almoco,
            MAX(CASE WHEN p.tipo = 'saida' THEN TIME(p.data_hora) END) as saida
          FROM pontos p
          JOIN funcionarios f ON p.funcionario_id = f.id
          WHERE p.empresa_id = :empresa_id
            AND DATE(p.data_hora) BETWEEN :data_inicio AND :data_fim
            AND f.status = 'ativo'
          GROUP BY f.id, DATE(p.data_hora)";

$params = [
    ':empresa_id' => $empresa_id,
    ':data_inicio' => $data_inicio,
    ':data_fim' => $data_fim
];

if ($funcionario_id) {
    $query .= " AND f.id = :funcionario_id";
    $params[':funcionario_id'] = $funcionario_id;
}

$query .= " ORDER BY f.nome, data ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$registros = $stmt->fetchAll();

// Função para calcular horas extras
function calcularHorasExtras($entrada, $saida_almoco, $volta_almoco, $saida, $carga_diaria) {
    if (!$entrada || !$saida) {
        return ['horas_trabalhadas' => 0, 'horas_extras' => 0, 'horas_extras_formatado' => '00:00'];
    }
    
    $entrada_ts = strtotime($entrada);
    $saida_ts = strtotime($saida);
    $total_segundos = $saida_ts - $entrada_ts;
    
    if ($saida_almoco && $volta_almoco) {
        $almoco_ts = strtotime($saida_almoco);
        $volta_ts = strtotime($volta_almoco);
        $total_segundos -= ($volta_ts - $almoco_ts);
    }
    
    $horas_trabalhadas = $total_segundos / 3600;
    $horas_extras = max(0, $horas_trabalhadas - $carga_diaria);
    
    $horas = floor($horas_extras);
    $minutos = round(($horas_extras - $horas) * 60);
    
    return [
        'horas_trabalhadas' => $horas_trabalhadas,
        'horas_extras' => $horas_extras,
        'horas_extras_formatado' => sprintf("%02d:%02d", $horas, $minutos)
    ];
}

// Agrupar por funcionário
$funcionarios_horas = [];
foreach ($registros as $reg) {
    $id = $reg['funcionario_id'];
    if (!isset($funcionarios_horas[$id])) {
        $funcionarios_horas[$id] = [
            'nome' => $reg['funcionario_nome'],
            'matricula' => $reg['matricula'],
            'dias' => [],
            'total_horas_trabalhadas' => 0,
            'total_horas_extras' => 0
        ];
    }
    
    $calculo = calcularHorasExtras($reg['entrada'], $reg['saida_almoco'], $reg['volta_almoco'], $reg['saida'], $carga_diaria);
    
    $funcionarios_horas[$id]['dias'][] = [
        'data' => $reg['data'],
        'data_formatada' => date('d/m/Y', strtotime($reg['data'])),
        'entrada' => $reg['entrada'] ? substr($reg['entrada'], 0, 5) : '--:--',
        'saida' => $reg['saida'] ? substr($reg['saida'], 0, 5) : '--:--',
        'horas_trabalhadas' => $calculo['horas_trabalhadas'],
        'horas_extras' => $calculo['horas_extras'],
        'horas_extras_formatado' => $calculo['horas_extras_formatado']
    ];
    
    $funcionarios_horas[$id]['total_horas_trabalhadas'] += $calculo['horas_trabalhadas'];
    $funcionarios_horas[$id]['total_horas_extras'] += $calculo['horas_extras'];
}

// Totais gerais
$total_horas_extras_geral = 0;
foreach ($funcionarios_horas as $f) {
    $total_horas_extras_geral += $f['total_horas_extras'];
}

function formatarHorasExtras($horas) {
    $h = floor($horas);
    $m = round(($horas - $h) * 60);
    return sprintf("%02d:%02d", $h, $m);
}
?>

<style>
.relatorio-container {
    max-width: 1300px;
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
    min-width: 150px;
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
    background: linear-gradient(135deg, #10b98120, #05966920);
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
    padding: 16px;
}

.resumo-valor {
    font-size: 28px;
    font-weight: 700;
    background: linear-gradient(135deg, #10b981, #059669);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.table-card {
    background: var(--bg-primary);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    overflow: auto;
    margin-bottom: 24px;
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
    min-width: 800px;
}

.data-table th,
.data-table td {
    padding: 12px 16px;
    text-align: center;
    border-bottom: 1px solid var(--border-color);
    font-size: 13px;
}

.data-table th {
    background: var(--bg-secondary);
    font-weight: 600;
}

.hora-extra-cell {
    color: #10b981;
    font-weight: 500;
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
</style>

<div class="relatorio-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-clock"></i> Relatório de Horas Extras</h2>
            <p><?php echo $titulo_periodo; ?></p>
        </div>
        <div class="module-actions">
            <a href="exportar_horas_extras.php?periodo=<?php echo $periodo; ?>&data_referencia=<?php echo $data_referencia; ?>&funcionario_id=<?php echo $funcionario_id; ?>&ano=<?php echo $ano; ?>&mes=<?php echo $mes; ?>&semana=<?php echo $semana; ?>" class="btn-exportar btn-excel">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </a>
        </div>
    </div>

    <!-- Filtros (mesmo do atrasos.php) -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-form" id="formFiltros">
            <div class="filter-group">
                <label>Funcionário</label>
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
                <label>Período</label>
                <select name="periodo" id="periodo">
                    <option value="diario" <?php echo $periodo == 'diario' ? 'selected' : ''; ?>>Diário</option>
                    <option value="semanal" <?php echo $periodo == 'semanal' ? 'selected' : ''; ?>>Semanal</option>
                    <option value="mensal" <?php echo $periodo == 'mensal' ? 'selected' : ''; ?>>Mensal</option>
                </select>
            </div>
            <div class="filter-group" id="grupo_data_diario" style="display: <?php echo $periodo == 'diario' ? 'flex' : 'none'; ?>;">
                <label>Data</label>
                <input type="date" name="data_referencia" value="<?php echo $data_referencia; ?>">
            </div>
            <div class="filter-group" id="grupo_data_semanal" style="display: <?php echo $periodo == 'semanal' ? 'flex' : 'none'; ?>;">
                <label>Semana</label>
                <input type="week" name="semana" value="<?php echo $ano . '-W' . str_pad($semana, 2, '0', STR_PAD_LEFT); ?>">
                <input type="hidden" name="ano" value="<?php echo $ano; ?>">
            </div>
            <div class="filter-group" id="grupo_data_mensal" style="display: <?php echo $periodo == 'mensal' ? 'flex' : 'none'; ?>;">
                <label>Mês</label>
                <input type="month" name="mes" value="<?php echo $ano . '-' . $mes; ?>">
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
                <div class="resumo-valor"><?php echo count($funcionarios_horas); ?></div>
                <div class="resumo-label">Funcionários com HE</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo formatarHorasExtras($total_horas_extras_geral); ?></div>
                <div class="resumo-label">Total de HE</div>
            </div>
        </div>
    </div>

    <!-- Tabela Detalhada -->
    <div class="table-card">
        <div class="table-header">
            <h3>Detalhamento das Horas Extras</h3>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Matrícula</th>
                    <th>Data</th>
                    <th>Entrada</th>
                    <th>Saída</th>
                    <th>Horas Extras</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($funcionarios_horas)): ?>
                    <tr><td colspan="6" style="text-align: center;">Nenhuma hora extra registrada no período</td></tr>
                <?php else: ?>
                    <?php foreach ($funcionarios_horas as $func): ?>
                        <?php foreach ($func['dias'] as $dia): ?>
                            <?php if ($dia['horas_extras'] > 0): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($func['nome']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($func['matricula']); ?></td>
                                    <td><?php echo $dia['data_formatada']; ?></td>
                                    <td><?php echo $dia['entrada']; ?></td>
                                    <td><?php echo $dia['saida']; ?></td>
                                    <td class="hora-extra-cell"><?php echo $dia['horas_extras_formatado']; ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align: right;"><strong>Total:</strong></td>
                    <td class="total-cell"><strong><?php echo formatarHorasExtras($total_horas_extras_geral); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Resumo por Funcionário -->
    <div class="table-card">
        <div class="table-header">
            <h3>Resumo por Funcionário</h3>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Matrícula</th>
                    <th>Dias c/ HE</th>
                    <th>Total HE</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($funcionarios_horas as $func): 
                    $dias_com_he = 0;
                    foreach ($func['dias'] as $dia) {
                        if ($dia['horas_extras'] > 0) $dias_com_he++;
                    }
                ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($func['nome']); ?></strong></td>
                        <td><?php echo htmlspecialchars($func['matricula']); ?></td>
                        <td><?php echo $dias_com_he; ?></td>
                        <td class="hora-extra-cell"><?php echo formatarHorasExtras($func['total_horas_extras']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById('periodo')?.addEventListener('change', function() {
    document.getElementById('grupo_data_diario').style.display = 'none';
    document.getElementById('grupo_data_semanal').style.display = 'none';
    document.getElementById('grupo_data_mensal').style.display = 'none';
    
    if (this.value === 'diario') {
        document.getElementById('grupo_data_diario').style.display = 'flex';
    } else if (this.value === 'semanal') {
        document.getElementById('grupo_data_semanal').style.display = 'flex';
    } else if (this.value === 'mensal') {
        document.getElementById('grupo_data_mensal').style.display = 'flex';
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>