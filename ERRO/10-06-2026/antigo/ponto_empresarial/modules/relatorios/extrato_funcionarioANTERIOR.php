<?php
// modules/relatorios/extrato_funcionario.php - Extrato de Funcionário (Admin)
$pageTitle = 'Extrato do Funcionário';
$activePage = 'relatorios';

// ============================================
// VERIFICAÇÕES ANTES DO HEADER
// ============================================
session_start();

// Verificar se está logado e é admin
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa', 'gestor'])) {
    header('Location: ../../index.php');
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;

// ============================================
// PARÂMETROS
// ============================================
$funcionario_id = $_GET['funcionario_id'] ?? null;
$mes = $_GET['mes'] ?? date('Y-m');
$ano = substr($mes, 0, 4);
$mes_num = substr($mes, 5, 2);

// ============================================
// BUSCAR FUNCIONÁRIOS DA EMPRESA
// ============================================
$query = "SELECT f.id, f.nome, f.matricula, f.cpf, fil.nome_fantasia as filial_nome
          FROM funcionarios f
          LEFT JOIN filiais fil ON f.filial_id = fil.id
          WHERE f.empresa_id = :empresa_id
          ORDER BY f.nome ASC";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$funcionarios = $stmt->fetchAll();

// ============================================
// BUSCAR DADOS DO FUNCIONÁRIO SELECIONADO
// ============================================
$funcionario = null;
$extrato = [];
$stats = [];

if ($funcionario_id) {
    // Buscar dados do funcionário
    $query = "SELECT f.*, fil.nome_fantasia as filial_nome, c.nome as cargo_nome
              FROM funcionarios f
              LEFT JOIN filiais fil ON f.filial_id = fil.id
              LEFT JOIN cargos c ON f.cargo_id = c.id
              WHERE f.id = :id AND f.empresa_id = :empresa_id";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':id' => $funcionario_id,
        ':empresa_id' => $empresa_id
    ]);
    $funcionario = $stmt->fetch();
    
    if ($funcionario) {
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
                    'dia_semana' => retornarDiaSemana(date('w', strtotime($data))),
                    'entrada' => null,
                    'saida_almoco' => null,
                    'volta_almoco' => null,
                    'saida' => null
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
        }
        
        // Calcular horas
        foreach ($extrato as &$dia) {
            $dia['horas'] = calcularHoras(
                $dia['entrada'], 
                $dia['saida_almoco'], 
                $dia['volta_almoco'], 
                $dia['saida']
            );
        }
        
        // Calcular estatísticas
        $stats = calcularEstatisticas($extrato);
    }
}

// Funções auxiliares
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

function calcularEstatisticas($extrato) {
    $stats = [
        'dias_trabalhados' => 0,
        'total_horas' => 0,
        'total_minutos' => 0,
        'media_diaria' => 0,
        'dias_completos' => 0
    ];
    
    foreach ($extrato as $dia) {
        if ($dia['horas']) {
            $stats['dias_trabalhados']++;
            $stats['total_horas'] += $dia['horas']['horas'];
            $stats['total_minutos'] += $dia['horas']['minutos'];
            
            if ($dia['entrada'] && $dia['saida'] && $dia['saida_almoco'] && $dia['volta_almoco']) {
                $stats['dias_completos']++;
            }
        }
    }
    
    $stats['total_horas'] += floor($stats['total_minutos'] / 60);
    $stats['total_minutos'] = $stats['total_minutos'] % 60;
    $stats['media_diaria'] = $stats['dias_trabalhados'] > 0 
        ? round(($stats['total_horas'] * 60 + $stats['total_minutos']) / $stats['dias_trabalhados'] / 60, 2) 
        : 0;
    
    return $stats;
}

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

.filters-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
}

.filters-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-group label {
    font-weight: 500;
    font-size: 13px;
    color: var(--text-secondary);
}

.filter-group select,
.filter-group input {
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: 14px;
}

.btn-filter {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 500;
    height: 42px;
}

.btn-filter:hover {
    opacity: 0.9;
}

.funcionario-info {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    color: white;
}

.funcionario-info h3 {
    margin: 0 0 8px 0;
}

.funcionario-info p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

.resumo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.resumo-item {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 16px;
    text-align: center;
}

.resumo-valor {
    font-size: 28px;
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
    border-radius: 16px;
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

.horas-positivo {
    color: #10b981;
    font-weight: 600;
}

.status-completo {
    color: #10b981;
    font-size: 12px;
}

.status-incompleto {
    color: #f59e0b;
    font-size: 12px;
}

.dia-semana {
    font-size: 11px;
    color: var(--text-secondary);
    display: block;
}

.btn-print {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    color: var(--text-primary);
    cursor: pointer;
    font-size: 13px;
}

.btn-print:hover {
    background: var(--bg-tertiary);
}

.empty-state {
    text-align: center;
    padding: 60px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}
</style>

<div class="extrato-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-chart-line"></i> Extrato do Funcionário</h2>
            <p>Visualize os registros de ponto de qualquer funcionário</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-row">
            <div class="filter-group">
                <label><i class="fas fa-user"></i> Funcionário</label>
                <select name="funcionario_id" required>
                    <option value="">Selecione um funcionário</option>
                    <?php foreach ($funcionarios as $func): ?>
                    <option value="<?php echo $func['id']; ?>" <?php echo $funcionario_id == $func['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($func['nome']); ?> (<?php echo htmlspecialchars($func['matricula']); ?>)
                                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Mês/Ano</label>
                <input type="month" name="mes" value="<?php echo $mes; ?>">
            </div>
            <div class="filter-group">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-search"></i> Filtrar
                </button>
            </div>
        </form>
    </div>

    <?php if ($funcionario_id && !$funcionario): ?>
        <div class="empty-state">
            <i class="fas fa-user-slash"></i>
            <p>Funcionário não encontrado ou não pertence à sua empresa</p>
        </div>
    <?php elseif ($funcionario_id && $funcionario): ?>
        <!-- Informações do Funcionário -->
        <div class="funcionario-info">
            <h3><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($funcionario['nome']); ?></h3>
            <p>
                Matrícula: <?php echo htmlspecialchars($funcionario['matricula']); ?> | 
                CPF: <?php echo htmlspecialchars($funcionario['cpf']); ?> | 
                Filial: <?php echo htmlspecialchars($funcionario['filial_nome']); ?> | 
                Cargo: <?php echo htmlspecialchars($funcionario['cargo_nome'] ?? 'Não definido'); ?>
            </p>
        </div>

        <!-- Resumo -->
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

        <!-- Tabela de Extrato -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Registros de <?php echo $nome_mes; ?></h3>
                <button onclick="window.print()" class="btn-print" style="float: right; margin-top: -30px;">
                    <i class="fas fa-print"></i> Imprimir
                </button>
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
                            <td colspan="7" class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>Nenhum ponto registrado neste período</p>
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
                                        <span class="status-incompleto">⚠️ Sem almoço</span>
                                    <?php elseif ($dia['entrada']): ?>
                                        <span class="status-incompleto">⏳ Em andamento</span>
                                    <?php else: ?>
                                        <span class="status-incompleto">⚪ Pendente</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($extrato) && $stats['dias_trabalhados'] > 0): ?>
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
    <?php elseif (!$funcionario_id): ?>
        <div class="empty-state">
            <i class="fas fa-user-search"></i>
            <p>Selecione um funcionário para visualizar o extrato</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>