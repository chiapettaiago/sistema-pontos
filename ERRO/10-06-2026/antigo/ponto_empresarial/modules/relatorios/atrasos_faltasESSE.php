<?php
// modules/relatorios/atrasos_faltas.php - Relatório de Atrasos e Faltas
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
$tipo_relatorio = $_GET['tipo'] ?? 'todos'; // todos, atrasos, faltas

// Buscar lista de funcionários
$stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios WHERE empresa_id = :empresa_id AND status = 'ativo' ORDER BY nome");
$stmt->execute([':empresa_id' => $empresa_id]);
$funcionarios = $stmt->fetchAll();

// Buscar dados de atrasos e faltas
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
          LEFT JOIN pontos p ON f.id = p.funcionario_id 
            AND MONTH(p.data_hora) = :mes 
            AND YEAR(p.data_hora) = :ano
          WHERE f.empresa_id = :empresa_id AND f.status = 'ativo'";

$params = [
    ':mes' => $mes_num,
    ':ano' => $ano,
    ':empresa_id' => $empresa_id
];

if ($funcionario_id) {
    $query .= " AND f.id = :funcionario_id";
    $params[':funcionario_id'] = $funcionario_id;
}

$query .= " GROUP BY f.id, DATE(p.data_hora) ORDER BY f.nome, data DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$registros = $stmt->fetchAll();

// Processar os registros para identificar atrasos e faltas
$atrasos = [];
$faltas = [];
$dias_uteis = 0;

// Calcular dias úteis do mês (segunda a sexta)
$data_inicio = new DateTime($ano . '-' . $mes_num . '-01');
$data_fim = new DateTime($ano . '-' . $mes_num . '-' . cal_days_in_month(CAL_GREGORIAN, $mes_num, $ano));
$interval = new DateInterval('P1D');
$periodo = new DatePeriod($data_inicio, $interval, $data_fim->modify('+1 day'));

foreach ($periodo as $data) {
    $dia_semana = $data->format('N');
    if ($dia_semana >= 1 && $dia_semana <= 5) {
        $dias_uteis++;
    }
}

foreach ($registros as $reg) {
    // Verificar se é atraso (entrada após 08:00)
    if ($reg['entrada'] && $reg['entrada'] > '08:00:00') {
        $atraso_minutos = 0;
        $entrada_ts = strtotime($reg['entrada']);
        $limite_ts = strtotime('08:00:00');
        $atraso_minutos = round(($entrada_ts - $limite_ts) / 60);
        
        $atrasos[] = [
            'funcionario_id' => $reg['funcionario_id'],
            'funcionario_nome' => $reg['funcionario_nome'],
            'matricula' => $reg['matricula'],
            'data' => $reg['data'],
            'entrada' => $reg['entrada'],
            'atraso_minutos' => $atraso_minutos
        ];
    }
    
    // Verificar se é falta (dia útil sem entrada)
    if (!$reg['entrada'] && $reg['data']) {
        $data_obj = new DateTime($reg['data']);
        $dia_semana = $data_obj->format('N');
        if ($dia_semana >= 1 && $dia_semana <= 5) {
            $faltas[] = [
                'funcionario_id' => $reg['funcionario_id'],
                'funcionario_nome' => $reg['funcionario_nome'],
                'matricula' => $reg['matricula'],
                'data' => $reg['data']
            ];
        }
    }
}

// Aplicar filtro de tipo
if ($tipo_relatorio == 'atrasos') {
    $faltas = [];
} elseif ($tipo_relatorio == 'faltas') {
    $atrasos = [];
}

// Estatísticas
$total_atrasos = count($atrasos);
$total_faltas = count($faltas);
$total_funcionarios_com_atraso = count(array_unique(array_column($atrasos, 'funcionario_id')));
$total_funcionarios_com_falta = count(array_unique(array_column($faltas, 'funcionario_id')));
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

/* Cards de Estatísticas */
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
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
.stat-card.funcionarios-atraso .stat-number { color: #f59e0b; }
.stat-card.funcionarios-falta .stat-number { color: #ef4444; }

/* Filtros */
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

/* Tabs */
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
    transition: all 0.3s;
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

/* Tabelas */
.table-card {
    background: var(--bg-primary);
    border-radius: 16px;
    overflow: auto;
    border: 1px solid var(--border-color);
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
    font-size: 13px;
    position: sticky;
    top: 0;
}

.data-table tr:hover {
    background: var(--bg-secondary);
}

.atraso-cell {
    color: #f59e0b;
    font-weight: 500;
}

.falta-cell {
    color: #ef4444;
    font-weight: 500;
}

.empty-state {
    text-align: center;
    padding: 60px;
    background: var(--bg-primary);
    border-radius: 16px;
    border: 1px solid var(--border-color);
}

.empty-state i {
    font-size: 64px;
    color: #ccc;
    margin-bottom: 16px;
}

.btn-exportar {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-excel {
    background: #10b981;
    color: white;
}

.btn-excel:hover {
    background: #059669;
}

.btn-pdf {
    background: #ef4444;
    color: white;
}

.btn-pdf:hover {
    background: #dc2626;
}

@media (max-width: 768px) {
    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group select,
    .filter-group input {
        width: 100%;
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
            <p><?php echo strftime('%B de %Y', strtotime($mes . '-01')); ?></p>
        </div>
        <div class="module-actions">
            <a href="exportar_excel.php?tipo=atrasos_faltas&funcionario_id=<?php echo $funcionario_id; ?>&mes=<?php echo $mes; ?>" class="btn-exportar btn-excel">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </a>
            <a href="exportar_pdf.php?tipo=atrasos_faltas&funcionario_id=<?php echo $funcionario_id; ?>&mes=<?php echo $mes; ?>" class="btn-exportar btn-pdf">
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
        <div class="stat-card funcionarios-atraso">
            <div class="stat-number"><?php echo $total_funcionarios_com_atraso; ?></div>
            <div class="stat-label">Funcionários com Atraso</div>
        </div>
        <div class="stat-card funcionarios-falta">
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
                <button type="submit" class="btn-filter">
                    <i class="fas fa-search"></i> Filtrar
                </button>
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
                            <tr>
                                <td><?php echo htmlspecialchars($atraso['funcionario_nome']); ?></td>
                                <td><?php echo htmlspecialchars($atraso['matricula']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($atraso['data'])); ?></td>
                                <td class="atraso-cell"><?php echo substr($atraso['entrada'], 0, 5); ?></td>
                                <td class="atraso-cell">
                                    <?php 
                                    $horas = floor($atraso['atraso_minutos'] / 60);
                                    $minutos = $atraso['atraso_minutos'] % 60;
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
                        <?php foreach ($faltas as $falta): ?>
                            <?php
                            $dias_semana = [
                                'Monday' => 'Segunda-feira',
                                'Tuesday' => 'Terça-feira',
                                'Wednesday' => 'Quarta-feira',
                                'Thursday' => 'Quinta-feira',
                                'Friday' => 'Sexta-feira',
                                'Saturday' => 'Sábado',
                                'Sunday' => 'Domingo'
                            ];
                            $dia_nome = $dias_semana[date('l', strtotime($falta['data']))] ?? date('l', strtotime($falta['data']));
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($falta['funcionario_nome']); ?></td>
                                <td><?php echo htmlspecialchars($falta['matricula']); ?></td>
                                <td class="falta-cell"><?php echo date('d/m/Y', strtotime($falta['data'])); ?></td>
                                <td><?php echo $dia_nome; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab Resumo por Funcionário -->
    <div id="tab-resumo" class="tab-pane">
        <?php
        // Agrupar atrasos e faltas por funcionário
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
            $resumo_funcionarios[$id]['total_minutos_atraso'] += $atraso['atraso_minutos'];
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
        
        // Ordenar por total de minutos de atraso (decrescente)
        uasort($resumo_funcionarios, function($a, $b) {
            return $b['total_minutos_atraso'] - $a['total_minutos_atraso'];
        });
        ?>
        
        <?php if (empty($resumo_funcionarios)): ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <p>Nenhum dado para exibir no resumo!</p>
            </div>
        <?php else: ?>
            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Matrícula</th>
                            <th>Total de Atrasos</th>
                            <th>Total de Minutos Atrasados</th>
                            <th>Total de Faltas</th>
                            <th>Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumo_funcionarios as $resumo): ?>
                            <?php
                            $horas_atraso = floor($resumo['total_minutos_atraso'] / 60);
                            $minutos_atraso = $resumo['total_minutos_atraso'] % 60;
                            $minutos_texto = '';
                            if ($horas_atraso > 0) {
                                $minutos_texto = $horas_atraso . 'h ' . $minutos_atraso . 'min';
                            } else {
                                $minutos_texto = $minutos_atraso . ' minutos';
                            }
                            
                            // Determinar situação
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
                            <tr>
                                <td><?php echo htmlspecialchars($resumo['nome']); ?></td>
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
// Tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tabId = this.getAttribute('data-tab');
        
        // Remover active de todos os botões
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        // Esconder todos os panes
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
        
        // Mostrar o pane selecionado
        document.getElementById('tab-' + tabId).classList.add('active');
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>