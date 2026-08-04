<?php
// modules/relatorios/atrasos_faltas.php - Relatório de Atrasos e Faltas (CORRIGIDO)
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

$pageTitle = 'Relatório de Atrasos e Faltas';
$activePage = 'relatorios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;

// Parâmetros de filtro
$funcionario_id = $_GET['funcionario_id'] ?? '';
$mes = $_GET['mes'] ?? date('Y-m');
$ano = substr($mes, 0, 4);
$mes_num = substr($mes, 5, 2);
$tipo_relatorio = $_GET['tipo'] ?? 'todos';

// Buscar lista de funcionários
$stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios 
                      WHERE empresa_id = :empresa_id AND status = 'ativo' 
                      ORDER BY nome");
$stmt->execute([':empresa_id' => $empresa_id]);
$funcionarios = $stmt->fetchAll();

// ============================================
// FUNÇÃO CORRIGIDA PARA CALCULAR ATRASO
// ============================================
function calcularMinutosAtraso($hora_registrada) {
    $hora_limite = '08:00:00';
    if ($hora_registrada <= $hora_limite) {
        return 0;
    }
    $registro_ts = strtotime($hora_registrada);
    $limite_ts = strtotime($hora_limite);
    $diferenca_segundos = $registro_ts - $limite_ts;
    $minutos = floor($diferenca_segundos / 60);
    return $minutos;
}

// ============================================
// BUSCAR DADOS DE ATRASOS
// ============================================
$query = "SELECT 
            f.id as funcionario_id,
            f.nome as funcionario_nome,
            f.matricula,
            p.id as ponto_id,
            DATE(p.data_hora) as data,
            TIME(p.data_hora) as hora_entrada,
            p.data_hora as data_hora_completa
          FROM pontos p
          JOIN funcionarios f ON p.funcionario_id = f.id
          WHERE p.empresa_id = :empresa_id
            AND p.tipo = 'entrada'
            AND MONTH(p.data_hora) = :mes
            AND YEAR(p.data_hora) = :ano
            AND TIME(p.data_hora) > '08:00:00'
            AND f.status = 'ativo'";

$params = [
    ':empresa_id' => $empresa_id,
    ':mes' => $mes_num,
    ':ano' => $ano
];

if ($funcionario_id) {
    $query .= " AND f.id = :funcionario_id";
    $params[':funcionario_id'] = $funcionario_id;
}

$query .= " ORDER BY f.nome, p.data_hora ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$atrasos = $stmt->fetchAll();

// ============================================
// BUSCAR FALTAS (dias úteis sem entrada)
// ============================================
// Calcular dias úteis do mês
$data_inicio = new DateTime($ano . '-' . $mes_num . '-01');
$data_fim = new DateTime($ano . '-' . $mes_num . '-' . cal_days_in_month(CAL_GREGORIAN, $mes_num, $ano));
$dias_uteis = [];
$periodo = new DatePeriod($data_inicio, new DateInterval('P1D'), $data_fim->modify('+1 day'));

foreach ($periodo as $data) {
    $dia_semana = $data->format('N');
    if ($dia_semana >= 1 && $dia_semana <= 5) {
        $dias_uteis[] = $data->format('Y-m-d');
    }
}

// Buscar funcionários e verificar faltas
$queryFaltas = "SELECT f.id, f.nome, f.matricula
                FROM funcionarios f
                WHERE f.empresa_id = :empresa_id AND f.status = 'ativo'";
if ($funcionario_id) {
    $queryFaltas .= " AND f.id = :funcionario_id";
}
$stmt = $db->prepare($queryFaltas);
$stmt->execute([':empresa_id' => $empresa_id]);
$todos_funcionarios = $stmt->fetchAll();

$faltas = [];
foreach ($todos_funcionarios as $func) {
    // Buscar dias que o funcionário trabalhou
    $stmt = $db->prepare("SELECT DISTINCT DATE(data_hora) as data FROM pontos 
                          WHERE funcionario_id = :funcionario_id 
                          AND MONTH(data_hora) = :mes 
                          AND YEAR(data_hora) = :ano
                          AND tipo = 'entrada'");
    $stmt->execute([
        ':funcionario_id' => $func['id'],
        ':mes' => $mes_num,
        ':ano' => $ano
    ]);
    $dias_trabalhados = [];
    while ($row = $stmt->fetch()) {
        $dias_trabalhados[] = $row['data'];
    }
    
    // Verificar dias úteis sem ponto
    foreach ($dias_uteis as $data) {
        if (!in_array($data, $dias_trabalhados)) {
            $faltas[] = [
                'funcionario_id' => $func['id'],
                'funcionario_nome' => $func['nome'],
                'matricula' => $func['matricula'],
                'data' => $data,
                'data_formatada' => date('d/m/Y', strtotime($data))
            ];
        }
    }
}

// ============================================
// ESTATÍSTICAS
// ============================================
$total_atrasos = count($atrasos);
$total_faltas = count($faltas);
$total_funcionarios_com_atraso = count(array_unique(array_column($atrasos, 'funcionario_id')));
$total_funcionarios_com_falta = count(array_unique(array_column($faltas, 'funcionario_id')));

// Nome do mês em português
$nomes_meses = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];
$nome_mes = $nomes_meses[$mes_num] . ' de ' . $ano;
?>

<style>
.relatorio-container {
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
    margin: 0;
    font-size: 24px;
}

.module-title p {
    margin: 8px 0 0 0;
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
    min-width: 180px;
}

.btn-filter {
    padding: 10px 24px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 500;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    border: 1px solid var(--border-color);
}

.stat-card .stat-number {
    font-size: 32px;
    font-weight: 700;
}

.stat-card .stat-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 5px;
}

.stat-card.atrasos .stat-number { color: #f59e0b; }
.stat-card.faltas .stat-number { color: #ef4444; }

.tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 8px;
}

.tab-btn {
    padding: 10px 24px;
    background: none;
    border: none;
    border-radius: 30px;
    cursor: pointer;
    font-weight: 500;
    color: var(--text-secondary);
}

.tab-btn.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

.table-card {
    background: var(--bg-primary);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    overflow: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.data-table th,
.data-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table th {
    background: var(--bg-secondary);
    font-weight: 600;
    font-size: 12px;
}

.atraso-cell {
    color: #f59e0b;
    font-weight: 500;
}

.falta-cell {
    color: #ef4444;
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

.btn-excel { background: #10b981; color: white; }
.btn-pdf { background: #ef4444; color: white; }

.empty-state {
    text-align: center;
    padding: 60px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    display: block;
}

.status-normal { color: #10b981; }
.status-atencao { color: #f59e0b; }
.status-critico { color: #ef4444; }

@media (max-width: 768px) {
    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="relatorio-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-exclamation-triangle"></i> Relatório de Atrasos e Faltas</h2>
            <p><?php echo $nome_mes; ?></p>
        </div>
        <div class="module-actions">
            <a href="exportar_excel.php?tipo=atrasos_faltas&funcionario_id=<?php echo $funcionario_id; ?>&mes=<?php echo $mes; ?>" class="btn-exportar btn-excel">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </a>
            <a href="exportar_pdf.php?tipo=atrasos_faltas&funcionario_id=<?php echo $funcionario_id; ?>&mes=<?php echo $mes; ?>" class="btn-exportar btn-pdf" target="_blank">
                <i class="fas fa-file-pdf"></i> Exportar PDF
            </a>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card atrasos">
            <div class="stat-number"><?php echo $total_atrasos; ?></div>
            <div class="stat-label">Total de Atrasos</div>
        </div>
        <div class="stat-card faltas">
            <div class="stat-number"><?php echo $total_faltas; ?></div>
            <div class="stat-label">Total de Faltas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_funcionarios_com_atraso; ?></div>
            <div class="stat-label">Funcionários com Atraso</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_funcionarios_com_falta; ?></div>
            <div class="stat-label">Funcionários com Falta</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-form">
            <div class="filter-group">
                <label>Funcionário</label>
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
                <label>Mês/Ano</label>
                <input type="month" name="mes" value="<?php echo $mes; ?>">
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn-filter">Filtrar</button>
            </div>
        </form>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" data-tab="atrasos">📋 Atrasos (<?php echo $total_atrasos; ?>)</button>
        <button class="tab-btn" data-tab="faltas">❌ Faltas (<?php echo $total_faltas; ?>)</button>
        <button class="tab-btn" data-tab="resumo">📊 Resumo por Funcionário</button>
    </div>

    <!-- Tab Atrasos -->
    <div id="tab-atrasos" class="tab-pane active">
        <?php if (empty($atrasos)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: #10b981;"></i>
                <p>Nenhum atraso registrado neste período!</p>
            </div>
        <?php else: ?>
            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Matrícula</th>
                            <th>Data</th>
                            <th>Horário de Entrada</th>
                            <th>Tempo de Atraso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($atrasos as $atraso): ?>
                            <?php $minutos_atraso = calcularMinutosAtraso($atraso['hora_entrada']); ?>
                            <tr class="fade-in">
                                <td><strong><?php echo htmlspecialchars($atraso['funcionario_nome']); ?></strong></td>
                                <td><?php echo htmlspecialchars($atraso['matricula']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($atraso['data'])); ?></td>
                                <td class="atraso-cell"><?php echo substr($atraso['hora_entrada'], 0, 5); ?></td>
                                <td class="atraso-cell">
                                    <?php 
                                    $horas = floor($minutos_atraso / 60);
                                    $minutos = $minutos_atraso % 60;
                                    if ($horas > 0) {
                                        echo $horas . 'h ' . $minutos . 'min';
                                    } else {
                                        echo $minutos . ' minutos';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab Faltas -->
    <div id="tab-faltas" class="tab-pane">
        <?php if (empty($faltas)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: #10b981;"></i>
                <p>Nenhuma falta registrada neste período!</p>
            </div>
        <?php else: ?>
            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Matrícula</th>
                            <th>Data da Falta</th>
                            <th>Dia da Semana</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faltas as $falta): 
                            $dias_semana = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira'];
                            $dia_nome = $dias_semana[date('N', strtotime($falta['data'])) - 1];
                        ?>
                            <tr class="fade-in">
                                <td><strong><?php echo htmlspecialchars($falta['funcionario_nome']); ?></strong></td>
                                <td><?php echo htmlspecialchars($falta['matricula']); ?></td>
                                <td class="falta-cell"><?php echo $falta['data_formatada']; ?></td>
                                <td><?php echo $dia_nome; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab Resumo -->
    <div id="tab-resumo" class="tab-pane">
        <?php
        $resumo_funcionarios = [];
        foreach ($atrasos as $atraso) {
            $id = $atraso['funcionario_id'];
            if (!isset($resumo_funcionarios[$id])) {
                $resumo_funcionarios[$id] = [
                    'nome' => $atraso['funcionario_nome'],
                    'matricula' => $atraso['matricula'],
                    'total_atrasos' => 0,
                    'total_minutos_atraso' => 0,
                    'total_faltas' => 0
                ];
            }
            $resumo_funcionarios[$id]['total_atrasos']++;
            $resumo_funcionarios[$id]['total_minutos_atraso'] += calcularMinutosAtraso($atraso['hora_entrada']);
        }
        foreach ($faltas as $falta) {
            $id = $falta['funcionario_id'];
            if (!isset($resumo_funcionarios[$id])) {
                $resumo_funcionarios[$id] = [
                    'nome' => $falta['funcionario_nome'],
                    'matricula' => $falta['matricula'],
                    'total_atrasos' => 0,
                    'total_minutos_atraso' => 0,
                    'total_faltas' => 0
                ];
            }
            $resumo_funcionarios[$id]['total_faltas']++;
        }
        uasort($resumo_funcionarios, function($a, $b) { return $b['total_minutos_atraso'] - $a['total_minutos_atraso']; });
        ?>
        
        <?php if (empty($resumo_funcionarios)): ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <p>Nenhum dado para exibir!</p>
            </div>
        <?php else: ?>
            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Matrícula</th>
                            <th>Atrasos</th>
                            <th>Minutos Atrasados</th>
                            <th>Faltas</th>
                            <th>Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumo_funcionarios as $resumo): 
                            $horas_atraso = floor($resumo['total_minutos_atraso'] / 60);
                            $minutos_atraso = $resumo['total_minutos_atraso'] % 60;
                            $minutos_texto = $horas_atraso > 0 ? $horas_atraso . 'h ' . $minutos_atraso . 'min' : $minutos_atraso . ' min';
                            
                            if ($resumo['total_faltas'] > 3) {
                                $situacao = 'Crítico';
                                $situacao_class = 'status-critico';
                            } elseif ($resumo['total_faltas'] > 1 || $resumo['total_minutos_atraso'] > 60) {
                                $situacao = 'Atenção';
                                $situacao_class = 'status-atencao';
                            } else {
                                $situacao = 'Normal';
                                $situacao_class = 'status-normal';
                            }
                        ?>
                            <tr class="fade-in">
                                <td><strong><?php echo htmlspecialchars($resumo['nome']); ?></strong></td>
                                <td><?php echo htmlspecialchars($resumo['matricula']); ?></td>
                                <td class="atraso-cell"><?php echo $resumo['total_atrasos']; ?></td>
                                <td class="atraso-cell"><?php echo $minutos_texto; ?></td>
                                <td class="falta-cell"><?php echo $resumo['total_faltas']; ?></td>
                                <td class="<?php echo $situacao_class; ?>"><?php echo $situacao; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tabId = this.getAttribute('data-tab');
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
        document.getElementById('tab-' + tabId).classList.add('active');
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>