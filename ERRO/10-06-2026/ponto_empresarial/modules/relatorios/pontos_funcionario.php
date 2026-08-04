<?php
// modules/relatorios/pontos_funcionario.php - Relatório de Pontos por Funcionário
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

$pageTitle = 'Relatório de Pontos';
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

// Buscar lista de funcionários para o filtro
$stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios WHERE empresa_id = :empresa_id AND status = 'ativo' ORDER BY nome");
$stmt->execute([':empresa_id' => $empresa_id]);
$funcionarios = $stmt->fetchAll();

// Buscar dados do funcionário selecionado
$funcionario_nome = 'Todos os funcionários';
$funcionario_matricula = '';
if ($funcionario_id) {
    $stmt = $db->prepare("SELECT nome, matricula FROM funcionarios WHERE id = :id AND empresa_id = :empresa_id");
    $stmt->execute([':id' => $funcionario_id, ':empresa_id' => $empresa_id]);
    $func = $stmt->fetch();
    if ($func) {
        $funcionario_nome = $func['nome'];
        $funcionario_matricula = $func['matricula'];
    }
}

// Buscar pontos - CORREÇÃO para LEFT JOIN funcionar corretamente
$query = "SELECT 
            f.id as funcionario_id,
            f.nome as funcionario_nome,
            f.matricula,
            DATE(p.data_hora) as data,
            DAYOFWEEK(p.data_hora) as dia_semana,
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

// Calcular horas trabalhadas
function calcularHorasPonto($entrada, $saida_almoco, $volta_almoco, $saida) {
    if (!$entrada || !$saida) return '--:--';
    $entrada_ts = strtotime($entrada);
    $saida_ts = strtotime($saida);
    $total = $saida_ts - $entrada_ts;
    
    if ($saida_almoco && $volta_almoco) {
        $almoco_ts = strtotime($saida_almoco);
        $volta_ts = strtotime($volta_almoco);
        $total -= ($volta_ts - $almoco_ts);
    }
    
    if ($total > 0) {
        $horas = floor($total / 3600);
        $minutos = floor(($total % 3600) / 60);
        return sprintf("%02d:%02d", $horas, $minutos);
    }
    return '--:--';
}

foreach ($registros as &$reg) {
    $reg['horas_trabalhadas'] = calcularHorasPonto($reg['entrada'], $reg['saida_almoco'], $reg['volta_almoco'], $reg['saida']);
}

$dias_semana = ['', 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$nomes_meses = [
    '01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
    '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'
];
?>

<style>
.relatorio-container { max-width: 1200px; margin: 0 auto; }
.module-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
.module-title h2 { margin: 0; font-size: 24px; }
.module-title p { margin: 8px 0 0; color: var(--text-secondary); }
.filters-card { background: var(--bg-primary); border-radius: 16px; padding: 20px; margin-bottom: 24px; border: 1px solid var(--border-color); }
.filters-form { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; }
.filter-group { display: flex; flex-direction: column; gap: 5px; }
.filter-group label { font-size: 12px; color: var(--text-secondary); }
.filter-group select, .filter-group input { padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 12px; background: var(--bg-primary); color: var(--text-primary); min-width: 200px; }
.btn-filter { padding: 10px 24px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 12px; cursor: pointer; font-weight: 500; }
.table-card { background: var(--bg-primary); border-radius: 16px; overflow: auto; border: 1px solid var(--border-color); }
.data-table { width: 100%; border-collapse: collapse; min-width: 800px; }
.data-table th, .data-table td { padding: 12px 16px; text-align: center; border-bottom: 1px solid var(--border-color); }
.data-table th { background: var(--bg-secondary); font-weight: 600; font-size: 13px; }
.data-table tr:hover { background: var(--bg-secondary); }
.status-normal { color: #10b981; }
.status-atraso { color: #f59e0b; }
.status-falta { color: #ef4444; }
.empty-state { text-align: center; padding: 60px; background: var(--bg-primary); border-radius: 16px; border: 1px solid var(--border-color); }
.empty-state i { font-size: 64px; color: #ccc; margin-bottom: 16px; }
.btn-exportar { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 12px; text-decoration: none; font-weight: 500; }
.btn-excel { background: #10b981; color: white; }
.btn-pdf { background: #ef4444; color: white; }
@media (max-width: 768px) { .filters-form { flex-direction: column; align-items: stretch; } }
</style>

<div class="relatorio-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-user-clock"></i> Relatório de Pontos por Funcionário</h2>
            <p><?php echo htmlspecialchars($funcionario_nome); ?> - <?php echo $nomes_meses[$mes_num] . ' de ' . $ano; ?></p>
        </div>
        <div class="module-actions">
            <a href="exportar_excel.php?tipo=pontos_funcionario&funcionario_id=<?php echo $funcionario_id; ?>&mes=<?php echo $mes; ?>" class="btn-exportar btn-excel">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </a>
            <a href="exportar_pdf.php?tipo=pontos_funcionario&funcionario_id=<?php echo $funcionario_id; ?>&mes=<?php echo $mes; ?>" class="btn-exportar btn-pdf" target="_blank">
                <i class="fas fa-file-pdf"></i> Exportar PDF
            </a>
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

    <!-- Tabela de Resultados -->
    <?php if (empty($registros)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <p>Nenhum registro encontrado neste período</p>
        </div>
    <?php else: ?>
        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Funcionário</th>
                        <th>Matrícula</th>
                        <th>Data</th>
                        <th>Dia</th>
                        <th>Entrada</th>
                        <th>Saída Almoço</th>
                        <th>Volta Almoço</th>
                        <th>Saída</th>
                        <th>Horas</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $reg): 
                        $status = 'Normal';
                        $status_class = 'status-normal';
                        if ($reg['entrada'] && $reg['entrada'] > '08:00:00') {
                            $status = 'Atraso';
                            $status_class = 'status-atraso';
                        } elseif (!$reg['entrada']) {
                            $status = 'Falta';
                            $status_class = 'status-falta';
                        }
                    ?>
                        <tr class="fade-in">
                            <td><strong><?php echo htmlspecialchars($reg['funcionario_nome']); ?></strong></td>
                            <td><?php echo htmlspecialchars($reg['matricula']); ?></td>
                            <td><?php echo $reg['data'] ? date('d/m/Y', strtotime($reg['data'])) : '--/--/----'; ?></td>
                            <td><?php echo $reg['dia_semana'] ? ($dias_semana[$reg['dia_semana']] ?? '--') : '--'; ?></td>
                            <td><?php echo $reg['entrada'] ? substr($reg['entrada'], 0, 5) : '--:--'; ?></td>
                            <td><?php echo $reg['saida_almoco'] ? substr($reg['saida_almoco'], 0, 5) : '--:--'; ?></td>
                            <td><?php echo $reg['volta_almoco'] ? substr($reg['volta_almoco'], 0, 5) : '--:--'; ?></td>
                            <td><?php echo $reg['saida'] ? substr($reg['saida'], 0, 5) : '--:--'; ?></td>
                            <td><?php echo $reg['horas_trabalhadas']; ?></td>
                            <td class="<?php echo $status_class; ?>"><?php echo $status; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>