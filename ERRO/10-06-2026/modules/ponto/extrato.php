<?php
// modules/ponto/extrato.php - Meu Extrato (COM STATUS CORRIGIDO)
session_start();

// Verificar se está logado
if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Meu Extrato';
$activePage = 'extrato';

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Pega o ID do funcionário
$funcionario_id = $_SESSION['funcionario_id'] ?? null;

if (!$funcionario_id && isset($_SESSION['usuario_id'])) {
    $stmt = $db->prepare("SELECT id FROM funcionarios WHERE email = :email");
    $stmt->execute([':email' => $_SESSION['usuario_email']]);
    $func = $stmt->fetch();
    if ($func) {
        $funcionario_id = $func['id'];
        $_SESSION['funcionario_id'] = $funcionario_id;
    }
}

if (!$funcionario_id) {
    echo "<div style='text-align: center; padding: 50px;'>
            <h2>Perfil não encontrado</h2>
            <p>Contacte o administrador.</p>
            <a href='../../logout.php'>Sair</a>
          </div>";
    exit;
}

// Buscar dados do funcionário
$stmt = $db->prepare("SELECT f.*, fi.nome_fantasia as filial_nome 
                      FROM funcionarios f
                      LEFT JOIN filiais fi ON f.filial_id = fi.id
                      WHERE f.id = :id");
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    session_destroy();
    header('Location: ../../login.php');
    exit;
}

// Parâmetros de filtro
$mes = $_GET['mes'] ?? date('Y-m');
$ano = substr($mes, 0, 4);
$mes_num = substr($mes, 5, 2);

// Buscar pontos do mês
$query = "SELECT 
            DATE(data_hora) as data,
            MAX(CASE WHEN tipo = 'entrada' THEN TIME(data_hora) END) as entrada,
            MAX(CASE WHEN tipo = 'saida_almoco' THEN TIME(data_hora) END) as saida_almoco,
            MAX(CASE WHEN tipo = 'volta_almoco' THEN TIME(data_hora) END) as volta_almoco,
            MAX(CASE WHEN tipo = 'saida' THEN TIME(data_hora) END) as saida
          FROM pontos 
          WHERE funcionario_id = :id 
            AND MONTH(data_hora) = :mes 
            AND YEAR(data_hora) = :ano
            AND TIME(data_hora) >= '06:00:00'
          GROUP BY DATE(data_hora)
          ORDER BY data DESC";

$stmt = $db->prepare($query);
$stmt->execute([
    ':id' => $funcionario_id,
    ':mes' => $mes_num,
    ':ano' => $ano
]);
$registros = $stmt->fetchAll();

// Função dia da semana
function retornarDiaSemana($numero) {
    $dias = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado'
    ];
    return $dias[$numero] ?? '';
}

$extrato = [];
$hoje = date('Y-m-d');

foreach ($registros as $reg) {
    $data = $reg['data'];
    $eh_dia_atual = ($data == $hoje);
    
    // Formatar horários
    $entrada = !empty($reg['entrada']) ? substr($reg['entrada'], 0, 5) : null;
    $saida_almoco = !empty($reg['saida_almoco']) ? substr($reg['saida_almoco'], 0, 5) : null;
    $volta_almoco = !empty($reg['volta_almoco']) ? substr($reg['volta_almoco'], 0, 5) : null;
    $saida = !empty($reg['saida']) ? substr($reg['saida'], 0, 5) : null;
    
    // Calcular horas trabalhadas
    $horas_formatado = '--:--';
    if ($entrada && $saida) {
        $entrada_ts = strtotime($entrada . ':00');
        $saida_ts = strtotime($saida . ':00');
        $total_segundos = $saida_ts - $entrada_ts;
        
        if ($saida_almoco && $volta_almoco) {
            $almoco_ts = strtotime($saida_almoco . ':00');
            $volta_ts = strtotime($volta_almoco . ':00');
            $total_segundos -= ($volta_ts - $almoco_ts);
        }
        
        if ($total_segundos > 0) {
            $horas = floor($total_segundos / 3600);
            $minutos = floor(($total_segundos % 3600) / 60);
            $horas_formatado = sprintf("%02d:%02d", $horas, $minutos);
        }
    }
    
    // ============================================
    // STATUS CORRIGIDO
    // ============================================
    if ($entrada && $saida && $saida_almoco && $volta_almoco) {
        // Dia completo (todos os 4 pontos)
        $status_texto = '✅ Completo';
        $status_cor = 'complete';
    } 
    elseif ($entrada && $saida) {
        // Tem entrada e saída, mas sem almoço completo
        $status_texto = '⚠️ Sem almoço';
        $status_cor = 'incomplete';
    }
    elseif ($entrada && !$saida) {
        // Apenas entrada registrada
        if ($eh_dia_atual) {
            $status_texto = '⏳ Em andamento';
            $status_cor = 'inprogress';
        } else {
            // Dia passado com apenas entrada = incompleto
            $status_texto = '❌ Incompleto';
            $status_cor = 'incomplete';
        }
    }
    elseif (!$entrada && ($saida_almoco || $volta_almoco || $saida)) {
        // Pontos sem entrada (anomalia)
        $status_texto = '⚠️ Anômalo';
        $status_cor = 'incomplete';
    }
    else {
        // Nenhum ponto
        if ($eh_dia_atual) {
            $status_texto = '⏳ Aguardando';
            $status_cor = 'pending';
        } else {
            $status_texto = '⚪ Falta';
            $status_cor = 'pending';
        }
    }
    
    $extrato[] = [
        'data' => $data,
        'data_formatada' => date('d/m/Y', strtotime($data)),
        'dia_semana' => retornarDiaSemana(date('w', strtotime($data))),
        'entrada' => $entrada ?: '--:--',
        'saida_almoco' => $saida_almoco ?: '--:--',
        'volta_almoco' => $volta_almoco ?: '--:--',
        'saida' => $saida ?: '--:--',
        'horas' => $horas_formatado,
        'status_texto' => $status_texto,
        'status_cor' => $status_cor
    ];
}

// Calcular estatísticas
$stats = [
    'dias_trabalhados' => 0,
    'total_horas' => 0,
    'total_minutos' => 0,
    'media_diaria' => 0,
    'dias_completos' => 0
];

foreach ($extrato as $dia) {
    if ($dia['horas'] != '--:--') {
        $stats['dias_trabalhados']++;
        
        $partes = explode(':', $dia['horas']);
        if (count($partes) == 2) {
            $stats['total_horas'] += (int)$partes[0];
            $stats['total_minutos'] += (int)$partes[1];
        }
        
        if ($dia['status_cor'] == 'complete') {
            $stats['dias_completos']++;
        }
    }
}

$stats['total_horas'] += floor($stats['total_minutos'] / 60);
$stats['total_minutos'] = $stats['total_minutos'] % 60;
$stats['media_diaria'] = $stats['dias_trabalhados'] > 0 
    ? round(($stats['total_horas'] * 60 + $stats['total_minutos']) / $stats['dias_trabalhados'] / 60, 2) 
    : 0;

// Nome do mês
$nomes_meses = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];
$nome_mes = $nomes_meses[$mes_num] . ' de ' . $ano;

require_once '../../includes/header.php';
?>

<style>
.extrato-container {
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
    margin: 0 0 5px 0;
    font-size: 24px;
}

.module-title p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 14px;
}

.filters-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.filter-group label {
    font-weight: 500;
    font-size: 14px;
}

.filtro-mes input {
    padding: 10px 16px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: 14px;
}

.resumo-card {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
}

.resumo-card h3 {
    margin-bottom: 16px;
    font-size: 18px;
}

.resumo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
}

.resumo-item {
    text-align: center;
    padding: 16px;
    background: var(--bg-secondary);
    border-radius: 16px;
}

.resumo-valor {
    font-size: 32px;
    font-weight: bold;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.resumo-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 8px;
}

.table-card {
    background: var(--bg-primary);
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.table-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-secondary);
}

.table-header h3 {
    margin: 0;
    font-size: 16px;
}

.table-responsive {
    overflow-x: auto;
}

.extrato-table {
    width: 100%;
    border-collapse: collapse;
}

.extrato-table th,
.extrato-table td {
    padding: 12px 16px;
    text-align: center;
    border-bottom: 1px solid var(--border-color);
}

.extrato-table th {
    background: var(--bg-secondary);
    font-weight: 600;
    font-size: 13px;
}

.extrato-table tfoot td {
    font-weight: 600;
    background: var(--bg-secondary);
}

.horas-positivo {
    color: #10b981;
    font-weight: 600;
}

.status-complete {
    color: #10b981;
    font-size: 12px;
}

.status-incomplete {
    color: #ef4444;
    font-size: 12px;
}

.status-inprogress {
    color: #3b82f6;
    font-size: 12px;
}

.status-pending {
    color: #9ca3af;
    font-size: 12px;
}

.dia-semana {
    font-size: 11px;
    color: var(--text-secondary);
    display: block;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s;
    border: none;
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--bg-tertiary);
}

.empty-state {
    text-align: center;
    padding: 60px;
}

.empty-state i {
    font-size: 48px;
    color: #ccc;
    margin-bottom: 16px;
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
    
    .resumo-valor {
        font-size: 24px;
    }
}
</style>

<div class="extrato-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-calendar-alt"></i> Meu Extrato de Ponto</h2>
            <p><?php echo htmlspecialchars($funcionario['nome']); ?> - Matrícula: <?php echo htmlspecialchars($funcionario['matricula']); ?></p>
        </div>
        <div class="module-actions">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>

    <div class="filters-card">
        <form method="GET" action="" class="filters-form">
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Período:</label>
                <div class="filtro-mes">
                    <input type="month" name="mes" value="<?php echo $mes; ?>" onchange="this.form.submit()">
                </div>
            </div>
        </form>
    </div>

    <div class="resumo-card">
        <h3><i class="fas fa-chart-line"></i> Resumo de <?php echo $nome_mes; ?></h3>
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

    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Registros de <?php echo $nome_mes; ?></h3>
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
                    <tr class="fade-in">
                        <td colspan="7" class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>Nenhum registro encontrado neste período</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($extrato as $dia): ?>
                        <tr class="fade-in">
                            <td>
                                <?php echo $dia['data_formatada']; ?>
                                <span class="dia-semana"><?php echo $dia['dia_semana']; ?></span>
                            </td>
                            <td><?php echo $dia['entrada']; ?></td>
                            <td><?php echo $dia['saida_almoco']; ?></td>
                            <td><?php echo $dia['volta_almoco']; ?></td>
                            <td><?php echo $dia['saida']; ?></td>
                            <td class="<?php echo $dia['horas'] != '--:--' ? 'horas-positivo' : ''; ?>">
                                <?php echo $dia['horas'] != '--:--' ? $dia['horas'] . 'h' : '--:--'; ?>
                            </td>
                            <td>
                                <span class="status-<?php echo $dia['status_cor']; ?>">
                                    <?php echo $dia['status_texto']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($extrato) && $stats['dias_trabalhados'] > 0): ?>
                <tfoot>
                    <tr style="background: var(--bg-secondary);">
                        <td colspan="5" style="text-align: right; font-weight: 600;">Total do mês:</td>
                        <td style="font-weight: 600;"><?php echo sprintf("%02d:%02d", $stats['total_horas'], $stats['total_minutos']); ?>h</td>
                        <td style="font-weight: 600;"><?php echo $stats['dias_trabalhados']; ?> dias</td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('input[type="month"]').forEach(input => {
    input.addEventListener('change', function() {
        this.form.submit();
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>