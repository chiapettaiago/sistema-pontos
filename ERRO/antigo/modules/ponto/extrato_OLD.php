<?php
// modules/ponto/extrato.php - Meu Extrato (Visualização do funcionário)
$pageTitle = 'Meu Extrato';
$activePage = 'extrato';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$funcionario_id = getCurrentUserId();

// Parâmetros de filtro
$mes = $_GET['mes'] ?? date('Y-m');
$ano = substr($mes, 0, 4);
$mes_num = substr($mes, 5, 2);

// Buscar dados do funcionário
$query = "SELECT f.*, fil.nome_fantasia as filial_nome, c.nome as cargo_nome
          FROM funcionarios f
          LEFT JOIN filiais fil ON f.filial_id = fil.id
          LEFT JOIN cargos c ON f.cargo_id = c.id
          WHERE f.id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

// Buscar pontos do mês
$query = "SELECT tipo, data_hora, latitude, longitude, origem 
          FROM pontos 
          WHERE funcionario_id = :id 
          AND MONTH(data_hora) = :mes 
          AND YEAR(data_hora) = :ano
          ORDER BY data_hora ASC";
$stmt = $db->prepare($query);
$stmt->execute([
    ':id' => $funcionario_id,
    ':mes' => $mes_num,
    ':ano' => $ano
]);
$pontos = $stmt->fetchAll();

// Agrupar por dia
$extrato = [];
foreach ($pontos as $ponto) {
    $data = date('Y-m-d', strtotime($ponto['data_hora']));
    if (!isset($extrato[$data])) {
        $extrato[$data] = [
            'data' => $data,
            'data_formatada' => date('d/m/Y', strtotime($data)),
            'dia_semana' => date('l', strtotime($data)),
            'entrada' => null,
            'saida_almoco' => null,
            'volta_almoco' => null,
            'saida' => null,
            'origem' => []
        ];
    }
    
    switch ($ponto['tipo']) {
        case 'entrada':
            $extrato[$data]['entrada'] = date('H:i:s', strtotime($ponto['data_hora']));
            break;
        case 'saida_almoco':
            $extrato[$data]['saida_almoco'] = date('H:i:s', strtotime($ponto['data_hora']));
            break;
        case 'volta_almoco':
            $extrato[$data]['volta_almoco'] = date('H:i:s', strtotime($ponto['data_hora']));
            break;
        case 'saida':
            $extrato[$data]['saida'] = date('H:i:s', strtotime($ponto['data_hora']));
            break;
    }
    $extrato[$data]['origem'][] = $ponto['origem'];
}

// Calcular horas trabalhadas para cada dia
function calcularHoras($entrada, $saida_almoco, $volta_almoco, $saida) {
    if (!$entrada || !$saida) return null;
    
    $entrada_ts = strtotime($entrada);
    $saida_almoco_ts = $saida_almoco ? strtotime($saida_almoco) : null;
    $volta_almoco_ts = $volta_almoco ? strtotime($volta_almoco) : null;
    $saida_ts = strtotime($saida);
    
    $total_segundos = ($saida_ts - $entrada_ts);
    
    if ($saida_almoco_ts && $volta_almoco_ts) {
        $total_segundos -= ($volta_almoco_ts - $saida_almoco_ts);
    }
    
    $horas = floor($total_segundos / 3600);
    $minutos = floor(($total_segundos % 3600) / 60);
    
    return [
        'horas' => $horas,
        'minutos' => $minutos,
        'total' => sprintf("%02d:%02d", $horas, $minutos),
        'decimal' => round($total_segundos / 3600, 2)
    ];
}

// Adicionar horas calculadas
foreach ($extrato as &$dia) {
    $dia['horas'] = calcularHoras(
        $dia['entrada'], 
        $dia['saida_almoco'], 
        $dia['volta_almoco'], 
        $dia['saida']
    );
}

// Calcular estatísticas do mês
$stats = [
    'dias_trabalhados' => 0,
    'total_horas' => 0,
    'total_minutos' => 0,
    'media_diaria' => 0,
    'dias_completos' => 0,
    'dias_incompletos' => 0
];

foreach ($extrato as $dia) {
    if ($dia['horas']) {
        $stats['dias_trabalhados']++;
        $stats['total_horas'] += $dia['horas']['horas'];
        $stats['total_minutos'] += $dia['horas']['minutos'];
        
        if ($dia['entrada'] && $dia['saida'] && $dia['saida_almoco'] && $dia['volta_almoco']) {
            $stats['dias_completos']++;
        } else {
            $stats['dias_incompletos']++;
        }
    }
}

// Ajustar minutos
$stats['total_horas'] += floor($stats['total_minutos'] / 60);
$stats['total_minutos'] = $stats['total_minutos'] % 60;
$stats['media_diaria'] = $stats['dias_trabalhados'] > 0 
    ? round(($stats['total_horas'] * 60 + $stats['total_minutos']) / $stats['dias_trabalhados'] / 60, 2) 
    : 0;
?>

<style>
/* Estilos do extrato */
.extrato-container {
    max-width: 1200px;
    margin: 0 auto;
}

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

.extrato-table {
    width: 100%;
    border-collapse: collapse;
}

.extrato-table th,
.extrato-table td {
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid var(--border-color);
}

.extrato-table th {
    background: var(--bg-secondary);
    font-weight: 600;
    font-size: 13px;
}

.horas-positivo {
    color: #10b981;
    font-weight: 600;
}

.horas-negativo {
    color: #ef4444;
    font-weight: 600;
}

.status-completo {
    color: #10b981;
}

.status-incompleto {
    color: #f59e0b;
}

.filtro-mes {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.filtro-mes input {
    padding: 10px 16px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
}

.dia-semana {
    font-size: 11px;
    color: var(--text-secondary);
    display: block;
}

.origem-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    background: var(--bg-secondary);
}

@media (max-width: 768px) {
    .extrato-table {
        font-size: 12px;
    }
    
    .extrato-table th,
    .extrato-table td {
        padding: 8px;
    }
    
    .resumo-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="extrato-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-calendar-alt"></i> Meu Extrato de Ponto</h2>
            <p><?php echo htmlspecialchars($funcionario['nome']); ?> - <?php echo htmlspecialchars($funcionario['matricula']); ?></p>
        </div>
        <div class="module-actions">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>

    <!-- Filtro por mês -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-form">
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Período</label>
                <div class="filtro-mes">
                    <input type="month" name="mes" value="<?php echo $mes; ?>" onchange="this.form.submit()">
                </div>
            </div>
        </form>
    </div>

    <!-- Resumo do Mês -->
    <div class="resumo-card">
        <h3><i class="fas fa-chart-line"></i> Resumo do Mês</h3>
        <div class="resumo-grid">
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo $stats['dias_trabalhados']; ?></div>
                <div class="resumo-label">Dias Trabalhados</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo sprintf("%02d:%02d", $stats['total_horas'], $stats['total_minutos']); ?></div>
                <div class="resumo-label">Total de Horas</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo number_format($stats['media_diaria'], 2, ',', '.'); ?>h</div>
                <div class="resumo-label">Média Diária</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo $stats['dias_completos']; ?></div>
                <div class="resumo-label">Dias Completos</div>
            </div>
        </div>
    </div>

    <!-- Tabela de Extrato -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Registros de <?php echo strftime('%B de %Y', strtotime($mes . '-01')); ?></h3>
        </div>
        <div class="table-responsive">
            <table class="extrato-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Entrada</th>
                        <th>Saída Almoço</th>
                        <th>Volta Almoço</th>
                        <th>Saída</th>
                        <th>Horas</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($extrato)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            <i class="fas fa-calendar-times" style="font-size: 48px; color: #ccc;"></i>
                            <p style="margin-top: 10px;">Nenhum registro encontrado neste período</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($extrato as $dia): ?>
                        <tr>
                            <td>
                                <?php echo $dia['data_formatada']; ?>
                                <span class="dia-semana"><?php echo $dia['dia_semana']; ?></span>
                            </td>
                            <td><?php echo $dia['entrada'] ? substr($dia['entrada'], 0, 5) : '--:--'; ?></td>
                            <td><?php echo $dia['saida_almoco'] ? substr($dia['saida_almoco'], 0, 5) : '--:--'; ?></td>
                            <td><?php echo $dia['volta_almoco'] ? substr($dia['volta_almoco'], 0, 5) : '--:--'; ?></td>
                            <td><?php echo $dia['saida'] ? substr($dia['saida'], 0, 5) : '--:--'; ?></td>
                            <td class="<?php echo $dia['horas'] ? 'horas-positivo' : ''; ?>">
                                <?php echo $dia['horas'] ? $dia['horas']['total'] . 'h' : '--:--'; ?>
                            </td>
                            <td>
                                <?php if ($dia['entrada'] && $dia['saida'] && $dia['saida_almoco'] && $dia['volta_almoco']): ?>
                                    <span class="status-completo">✅ Completo</span>
                                <?php elseif ($dia['entrada'] && $dia['saida']): ?>
                                    <span class="status-incompleto">⚠️ Incompleto</span>
                                <?php elseif ($dia['entrada']): ?>
                                    <span class="status-incompleto">⏳ Em andamento</span>
                                <?php else: ?>
                                    <span class="status-incompleto">❌ Pendente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($extrato)): ?>
                <tfoot>
                    <tr style="background: var(--bg-secondary); font-weight: 600;">
                        <td colspan="5" style="text-align: right;">Total do mês:</td>
                        <td><?php echo sprintf("%02d:%02d", $stats['total_horas'], $stats['total_minutos']); ?>h</td>
                        <td><?php echo $stats['dias_trabalhados']; ?> dias</td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
// Atualizar mês automaticamente
document.querySelectorAll('input[type="month"]').forEach(input => {
    input.addEventListener('change', function() {
        this.form.submit();
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>