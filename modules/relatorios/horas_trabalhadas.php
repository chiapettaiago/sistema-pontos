<?php
// modules/relatorios/horas_trabalhadas.php - Relatório de Horas Trabalhadas (CORRIGIDO)
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

$pageTitle = 'Relatório de Horas Trabalhadas';
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

// Carga horária padrão
$carga_diaria = 8;
$carga_semanal = 44;
$carga_mensal = 220;

// Buscar configurações da empresa
$stmt_config = $db->prepare("SELECT carga_horaria_diaria, carga_horaria_semanal FROM config_horarios WHERE empresa_id = :empresa_id");
$stmt_config->execute([':empresa_id' => $empresa_id]);
$config = $stmt_config->fetch();
if ($config) {
    $carga_diaria = $config['carga_horaria_diaria'] ?: 8;
    $carga_semanal = $config['carga_horaria_semanal'] ?: 44;
    $carga_mensal = $carga_semanal * 4.33;
}

// Definir período
switch ($periodo) {
    case 'diario':
        $data_inicio = $data_referencia;
        $data_fim = $data_referencia;
        $titulo_periodo = 'Diário - ' . date('d/m/Y', strtotime($data_referencia));
        $carga_periodo = $carga_diaria;
        break;
    case 'semanal':
        $data_inicio = date('Y-m-d', strtotime($ano . '-W' . str_pad($semana, 2, '0', STR_PAD_LEFT) . '-1'));
        $data_fim = date('Y-m-d', strtotime($ano . '-W' . str_pad($semana, 2, '0', STR_PAD_LEFT) . '-7'));
        $titulo_periodo = 'Semanal - Semana ' . $semana . ' de ' . $ano;
        $carga_periodo = $carga_semanal;
        break;
    case 'mensal':
        $data_inicio = date('Y-m-01', strtotime($ano . '-' . $mes . '-01'));
        $data_fim = date('Y-m-t', strtotime($ano . '-' . $mes . '-01'));
        $nomes_meses = [
            '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
            '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
            '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
        ];
        $titulo_periodo = 'Mensal - ' . $nomes_meses[$mes] . ' de ' . $ano;
        $carga_periodo = $carga_mensal;
        break;
    default:
        $data_inicio = $data_referencia;
        $data_fim = $data_referencia;
        $titulo_periodo = 'Diário - ' . date('d/m/Y', strtotime($data_referencia));
        $carga_periodo = $carga_diaria;
}

// Buscar funcionários para o filtro
$stmt_funcionarios = $db->prepare("SELECT id, nome, matricula FROM funcionarios 
                                   WHERE empresa_id = :empresa_id AND status = 'ativo' 
                                   ORDER BY nome");
$stmt_funcionarios->execute([':empresa_id' => $empresa_id]);
$funcionarios = $stmt_funcionarios->fetchAll();

// ============================================
// FUNÇÕES AUXILIARES
// ============================================
function formatarHorasRel($horas) {
    $h = floor($horas);
    $m = round(($horas - $h) * 60);
    return sprintf("%02d:%02d", $h, $m);
}

function calcularHorasDiaRel($entrada, $saida_almoco, $volta_almoco, $saida, $carga_diaria) {
    $resultado = [
        'horas_trabalhadas' => 0,
        'horas_extras' => 0,
        'horas_compensar' => 0,
        'horas_trabalhadas_formatado' => '00:00',
        'horas_extras_formatado' => '00:00',
        'horas_compensar_formatado' => '00:00',
        'status' => 'Pendente'
    ];
    
    if (!$entrada || !$saida) {
        $resultado['status'] = 'Incompleto';
        return $resultado;
    }
    
    $entrada_ts = strtotime($entrada);
    $saida_ts = strtotime($saida);
    $total_segundos = $saida_ts - $entrada_ts;
    
    if ($saida_almoco && $volta_almoco) {
        $almoco_ts = strtotime($saida_almoco);
        $volta_ts = strtotime($volta_almoco);
        $total_segundos -= ($volta_ts - $almoco_ts);
    }
    
    $horas_trab = $total_segundos / 3600;
    $resultado['horas_trabalhadas'] = $horas_trab;
    $resultado['horas_trabalhadas_formatado'] = formatarHorasRel($horas_trab);
    
    // Verificar atraso na entrada
    if ($entrada > '08:00:00') {
        $resultado['status'] = 'Atraso';
    } else {
        $resultado['status'] = 'Normal';
    }
    
    // Calcular saldo (horas extras ou horas a compensar)
    $saldo = $horas_trab - $carga_diaria;
    
    if ($saldo > 0) {
        $resultado['horas_extras'] = $saldo;
        $resultado['horas_extras_formatado'] = formatarHorasRel($saldo);
        $resultado['horas_compensar'] = 0;
    } elseif ($saldo < 0) {
        $resultado['horas_extras'] = 0;
        $resultado['horas_compensar'] = abs($saldo);
        $resultado['horas_compensar_formatado'] = formatarHorasRel(abs($saldo));
    }
    
    return $resultado;
}

// ============================================
// BUSCAR DADOS - CORRIGIDO
// ============================================
$query = "SELECT 
            f.id as funcionario_id,
            f.nome as funcionario_nome,
            f.matricula,
            DATE(p.data_hora) as data,
            MAX(CASE WHEN p.tipo = 'entrada' THEN TIME(p.data_hora) END) as entrada,
            MAX(CASE WHEN p.tipo = 'saida_almoco' THEN TIME(p.data_hora) END) as saida_almoco,
            MAX(CASE WHEN p.tipo = 'volta_almoco' THEN TIME(p.data_hora) END) as volta_almoco,
            MAX(CASE WHEN p.tipo = 'saida' THEN TIME(p.data_hora) END) as saida
          FROM funcionarios f
          LEFT JOIN pontos p ON f.id = p.funcionario_id AND DATE(p.data_hora) BETWEEN :data_inicio AND :data_fim
          WHERE f.empresa_id = :empresa_id AND f.status = 'ativo'";

$params = [
    ':data_inicio' => $data_inicio,
    ':data_fim' => $data_fim,
    ':empresa_id' => $empresa_id
];

if ($funcionario_id) {
    $query .= " AND f.id = :funcionario_id";
    $params[':funcionario_id'] = $funcionario_id;
}

$query .= " GROUP BY f.id, DATE(p.data_hora) ORDER BY f.nome, data ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$registros = $stmt->fetchAll();

// Processar dados
$funcionarios_dados = [];
$total_geral_normal = 0;
$total_geral_extras = 0;
$total_geral_compensar = 0;

foreach ($registros as $reg) {
    $id = $reg['funcionario_id'];
    if (!isset($funcionarios_dados[$id])) {
        $funcionarios_dados[$id] = [
            'nome' => $reg['funcionario_nome'],
            'matricula' => $reg['matricula'],
            'dias' => [],
            'total_normal' => 0,
            'total_extras' => 0,
            'total_compensar' => 0,
            'dias_trabalhados' => 0
        ];
    }
    
    $calculo = calcularHorasDiaRel(
        $reg['entrada'], $reg['saida_almoco'], $reg['volta_almoco'], $reg['saida'], $carga_diaria
    );
    
    $funcionarios_dados[$id]['dias'][] = [
        'data' => $reg['data'],
        'data_formatada' => date('d/m/Y', strtotime($reg['data'])),
        'entrada' => $reg['entrada'] ? substr($reg['entrada'], 0, 5) : '--:--',
        'saida_almoco' => $reg['saida_almoco'] ? substr($reg['saida_almoco'], 0, 5) : '--:--',
        'volta_almoco' => $reg['volta_almoco'] ? substr($reg['volta_almoco'], 0, 5) : '--:--',
        'saida' => $reg['saida'] ? substr($reg['saida'], 0, 5) : '--:--',
        'horas_trabalhadas' => $calculo['horas_trabalhadas_formatado'],
        'horas_extras' => $calculo['horas_extras_formatado'],
        'horas_compensar' => $calculo['horas_compensar_formatado'],
        'status' => $calculo['status']
    ];
    
    $funcionarios_dados[$id]['total_normal'] += $calculo['horas_trabalhadas'];
    $funcionarios_dados[$id]['total_extras'] += $calculo['horas_extras'];
    $funcionarios_dados[$id]['total_compensar'] += $calculo['horas_compensar'];
    if ($calculo['horas_trabalhadas'] > 0) {
        $funcionarios_dados[$id]['dias_trabalhados']++;
    }
    
    $total_geral_normal += $calculo['horas_trabalhadas'];
    $total_geral_extras += $calculo['horas_extras'];
    $total_geral_compensar += $calculo['horas_compensar'];
}
?>

<style>
.relatorio-container { max-width: 1400px; margin: 0 auto; }
.module-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
.module-title h2 { margin: 0; font-size: 24px; }
.module-title p { margin: 8px 0 0; color: var(--text-secondary); }
.filters-card { background: var(--bg-primary); border-radius: 16px; padding: 20px; margin-bottom: 24px; border: 1px solid var(--border-color); }
.filters-form { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; }
.filter-group { display: flex; flex-direction: column; gap: 5px; }
.filter-group label { font-size: 12px; color: var(--text-secondary); }
.filter-group select, .filter-group input { padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 12px; background: var(--bg-primary); color: var(--text-primary); min-width: 150px; }
.btn-filter { padding: 10px 24px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 12px; cursor: pointer; }
.resumo-card { background: linear-gradient(135deg, #667eea20, #764ba220); border-radius: 16px; padding: 20px; margin-bottom: 24px; }
.resumo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
.resumo-item { text-align: center; background: var(--bg-primary); border-radius: 12px; padding: 16px; }
.resumo-valor { font-size: 28px; font-weight: 700; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; background-clip: text; color: transparent; }
.resumo-valor.positivo { color: #10b981; background: none; }
.resumo-valor.negativo { color: #ef4444; background: none; }
.resumo-label { font-size: 12px; color: var(--text-secondary); margin-top: 5px; }
.table-card { background: var(--bg-primary); border-radius: 16px; border: 1px solid var(--border-color); overflow: auto; margin-bottom: 24px; }
.table-header { padding: 16px 20px; background: var(--bg-secondary); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
.data-table { width: 100%; border-collapse: collapse; min-width: 1000px; }
.data-table th, .data-table td { padding: 10px 12px; text-align: center; border-bottom: 1px solid var(--border-color); font-size: 12px; }
.data-table th { background: var(--bg-secondary); font-weight: 600; font-size: 12px; }
.data-table tfoot td { background: var(--bg-secondary); font-weight: 700; }
.hora-normal { color: #667eea; font-weight: 500; }
.hora-extra { color: #10b981; font-weight: 500; }
.hora-compensar { color: #f59e0b; font-weight: 500; }
.status-normal { color: #10b981; }
.status-atraso { color: #f59e0b; }
.status-incompleto { color: #ef4444; }
.btn-exportar { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 12px; text-decoration: none; font-weight: 500; }
.btn-excel { background: #10b981; color: white; }
.btn-pdf { background: #ef4444; color: white; }
@media (max-width: 768px) { .filters-form { flex-direction: column; align-items: stretch; } .resumo-grid { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="relatorio-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-chart-line"></i> Relatório de Horas Trabalhadas</h2>
            <p><?php echo $titulo_periodo; ?> | Carga: <?php echo number_format($carga_periodo, 2, ',', '.'); ?>h</p>
        </div>
        <div class="module-actions">
            <a href="exportar_excel.php?tipo=horas_trabalhadas&periodo=<?php echo $periodo; ?>&data_referencia=<?php echo $data_referencia; ?>&funcionario_id=<?php echo $funcionario_id; ?>&ano=<?php echo $ano; ?>&mes=<?php echo $mes; ?>&semana=<?php echo $semana; ?>" class="btn-exportar btn-excel">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </a>
            <a href="exportar_pdf.php?tipo=horas_trabalhadas&periodo=<?php echo $periodo; ?>&data_referencia=<?php echo $data_referencia; ?>&funcionario_id=<?php echo $funcionario_id; ?>&ano=<?php echo $ano; ?>&mes=<?php echo $mes; ?>&semana=<?php echo $semana; ?>" class="btn-exportar btn-pdf" target="_blank">
                <i class="fas fa-file-pdf"></i> Exportar PDF
            </a>
        </div>
    </div>

    <!-- Filtros -->
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

    <!-- Resumo Geral -->
    <div class="resumo-card">
        <div class="resumo-grid">
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo formatarHorasRel($total_geral_normal); ?></div>
                <div class="resumo-label">Total Horas Normais</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor positivo">+ <?php echo formatarHorasRel($total_geral_extras); ?></div>
                <div class="resumo-label">Total Horas Extras</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor negativo">- <?php echo formatarHorasRel($total_geral_compensar); ?></div>
                <div class="resumo-label">Total Horas a Compensar</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo number_format($carga_periodo, 2, ',', '.'); ?>h</div>
                <div class="resumo-label">Carga Horária do Período</div>
            </div>
        </div>
    </div>

    <!-- Tabela Detalhada por Funcionário -->
    <?php foreach ($funcionarios_dados as $func): ?>
    <div class="table-card">
        <div class="table-header">
            <h3><strong><?php echo htmlspecialchars($func['nome']); ?></strong> (<?php echo htmlspecialchars($func['matricula']); ?>)</h3>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Entrada</th>
                    <th>Saída Almoço</th>
                    <th>Volta Almoço</th>
                    <th>Saída</th>
                    <th>Horas Normais</th>
                    <th>Horas Extras</th>
                    <th>Horas a Compensar</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($func['dias'] as $dia): ?>
                <tr class="fade-in">
                    <td><?php echo $dia['data_formatada']; ?></td>
                    <td><?php echo $dia['entrada']; ?></td>
                    <td><?php echo $dia['saida_almoco']; ?></td>
                    <td><?php echo $dia['volta_almoco']; ?></td>
                    <td><?php echo $dia['saida']; ?></td>
                    <td class="hora-normal"><?php echo $dia['horas_trabalhadas']; ?></td>
                    <td class="hora-extra"><?php echo $dia['horas_extras']; ?></td>
                    <td class="hora-compensar"><?php echo $dia['horas_compensar']; ?></td>
                    <td class="status-<?php echo strtolower($dia['status']); ?>"><?php echo $dia['status']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align: right;"><strong>TOTAIS:</strong></td>
                    <td class="hora-normal"><strong><?php echo formatarHorasRel($func['total_normal']); ?></strong></td>
                    <td class="hora-extra"><strong><?php echo formatarHorasRel($func['total_extras']); ?></strong></td>
                    <td class="hora-compensar"><strong><?php echo formatarHorasRel($func['total_compensar']); ?></strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endforeach; ?>

    <?php if (empty($funcionarios_dados)): ?>
    <div class="empty-state" style="text-align: center; padding: 60px;">
        <i class="fas fa-chart-line" style="font-size: 48px; color: #ccc;"></i>
        <p>Nenhum registro encontrado no período</p>
    </div>
    <?php endif; ?>
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