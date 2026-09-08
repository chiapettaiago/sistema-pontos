<?php
// modules/relatorios/atrasos.php - Relatório de Atrasos (Diário/Semanal/Mensal)
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

$pageTitle = 'Relatório de Atrasos';
$activePage = 'relatorios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;

// Parâmetros
$periodo = $_GET['periodo'] ?? 'diario'; // diario, semanal, mensal
$data_referencia = $_GET['data_referencia'] ?? date('Y-m-d');
$funcionario_id = $_GET['funcionario_id'] ?? '';
$ano = $_GET['ano'] ?? date('Y');
$mes = $_GET['mes'] ?? date('m');
$semana = $_GET['semana'] ?? date('W');

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
        $titulo_periodo = 'Semana ' . $semana . ' de ' . $ano . ' (' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim)) . ')';
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
    default:
        $data_inicio = $data_referencia;
        $data_fim = $data_referencia;
        $titulo_periodo = date('d/m/Y', strtotime($data_referencia));
}

// Buscar funcionários
$stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios 
                      WHERE empresa_id = :empresa_id AND status = 'ativo' 
                      ORDER BY nome");
$stmt->execute([':empresa_id' => $empresa_id]);
$funcionarios = $stmt->fetchAll();

// Função para calcular atraso
function calcularAtraso($hora_registrada, $hora_limite = '08:00:00') {
    if ($hora_registrada <= $hora_limite) {
        return 0;
    }
    $registro = strtotime($hora_registrada);
    $limite = strtotime($hora_limite);
    return round(($registro - $limite) / 60);
}

// Buscar dados de atrasos
$query = "SELECT 
            f.id as funcionario_id,
            f.nome as funcionario_nome,
            f.matricula,
            DATE(p.data_hora) as data,
            TIME(p.data_hora) as hora_entrada
          FROM pontos p
          JOIN funcionarios f ON p.funcionario_id = f.id
          WHERE p.empresa_id = :empresa_id
            AND p.tipo = 'entrada'
            AND TIME(p.data_hora) > '08:00:00'
            AND DATE(p.data_hora) BETWEEN :data_inicio AND :data_fim
            AND f.status = 'ativo'";

$params = [
    ':empresa_id' => $empresa_id,
    ':data_inicio' => $data_inicio,
    ':data_fim' => $data_fim
];

if ($funcionario_id) {
    $query .= " AND f.id = :funcionario_id";
    $params[':funcionario_id'] = $funcionario_id;
}

$query .= " ORDER BY f.nome, p.data_hora ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$atrasos = $stmt->fetchAll();

// Agrupar por funcionário
$funcionarios_atrasos = [];
foreach ($atrasos as $atraso) {
    $id = $atraso['funcionario_id'];
    if (!isset($funcionarios_atrasos[$id])) {
        $funcionarios_atrasos[$id] = [
            'nome' => $atraso['funcionario_nome'],
            'matricula' => $atraso['matricula'],
            'atrasos' => [],
            'total_atrasos' => 0,
            'total_minutos' => 0,
            'dias_com_atraso' => 0
        ];
    }
    
    $minutos = calcularAtraso($atraso['hora_entrada']);
    $funcionarios_atrasos[$id]['atrasos'][] = [
        'data' => $atraso['data'],
        'data_formatada' => date('d/m/Y', strtotime($atraso['data'])),
        'hora' => substr($atraso['hora_entrada'], 0, 5),
        'minutos' => $minutos,
        'minutos_formatado' => formatarMinutos($minutos)
    ];
    $funcionarios_atrasos[$id]['total_atrasos']++;
    $funcionarios_atrasos[$id]['total_minutos'] += $minutos;
    $funcionarios_atrasos[$id]['dias_com_atraso'] = count(array_unique(array_column($funcionarios_atrasos[$id]['atrasos'], 'data')));
}

// Totais gerais
$total_atrasos_geral = 0;
$total_minutos_geral = 0;
$funcionarios_com_atraso = count($funcionarios_atrasos);

foreach ($funcionarios_atrasos as $f) {
    $total_atrasos_geral += $f['total_atrasos'];
    $total_minutos_geral += $f['total_minutos'];
}

function formatarMinutos($minutos) {
    $horas = floor($minutos / 60);
    $resto = $minutos % 60;
    if ($horas > 0) {
        return $horas . 'h ' . $resto . 'min';
    }
    return $minutos . 'min';
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

.module-title p {
    margin: 8px 0 0;
    color: var(--text-secondary);
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
    background: linear-gradient(135deg, #667eea20, #764ba220);
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
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.resumo-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 5px;
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
    flex-wrap: wrap;
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
    font-size: 12px;
}

.data-table tfoot td {
    background: var(--bg-secondary);
    font-weight: 600;
}

.atraso-cell {
    color: #f59e0b;
    font-weight: 500;
}

.total-cell {
    font-weight: 700;
    background: var(--bg-secondary);
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

.btn-excel:hover {
    background: #059669;
}

@media (max-width: 768px) {
    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .resumo-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="relatorio-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-exclamation-triangle"></i> Relatório de Atrasos</h2>
            <p><?php echo $titulo_periodo; ?></p>
        </div>
        <div class="module-actions">
            <a href="#" onclick="exportarTabelasCsv('relatorio_atrasos.csv'); return false;" class="btn-exportar btn-excel">
                <i class="fas fa-file-excel"></i> Exportar Excel
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
                <input type="week" name="semana" value="<?php echo $ano . '-W' . str_pad($semana, 2, '0', STR_PAD_LEFT); ?>" id="semanaInput">
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
                <div class="resumo-valor"><?php echo $funcionarios_com_atraso; ?></div>
                <div class="resumo-label">Funcionários com Atraso</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo $total_atrasos_geral; ?></div>
                <div class="resumo-label">Total de Atrasos</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo formatarMinutos($total_minutos_geral); ?></div>
                <div class="resumo-label">Total de Minutos Atrasados</div>
            </div>
        </div>
    </div>

    <!-- Tabela Detalhada -->
    <div class="table-card">
        <div class="table-header">
            <h3>Detalhamento dos Atrasos</h3>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Matrícula</th>
                    <th>Data</th>
                    <th>Horário</th>
                    <th>Atraso</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($funcionarios_atrasos)): ?>
                    <tr class="fade-in">
                        <td colspan="5" style="text-align: center;">Nenhum atraso registrado no período</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($funcionarios_atrasos as $func): ?>
                        <?php foreach ($func['atrasos'] as $atraso): ?>
                            <tr class="fade-in">
                                <td><strong><?php echo htmlspecialchars($func['nome']); ?></strong></td>
                                <td><?php echo htmlspecialchars($func['matricula']); ?></td>
                                <td><?php echo $atraso['data_formatada']; ?></td>
                                <td><?php echo $atraso['hora']; ?></td>
                                <td class="atraso-cell"><?php echo $atraso['minutos_formatado']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align: right;"><strong>Total Geral:</strong></td>
                    <td class="total-cell"><strong><?php echo $total_atrasos_geral; ?> atrasos (<?php echo formatarMinutos($total_minutos_geral); ?>)</strong></td>
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
                    <th>Dias com Atraso</th>
                    <th>Total Atrasos</th>
                    <th>Minutos Atrasados</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($funcionarios_atrasos)): ?>
                    <tr><td colspan="5" style="text-align: center;">Nenhum dado</td></tr>
                <?php else: ?>
                    <?php foreach ($funcionarios_atrasos as $func): ?>
                        <tr class="fade-in">
                            <td><strong><?php echo htmlspecialchars($func['nome']); ?></strong></td>
                            <td><?php echo htmlspecialchars($func['matricula']); ?></td>
                            <td><?php echo $func['dias_com_atraso']; ?></td>
                            <td class="atraso-cell"><?php echo $func['total_atrasos']; ?></td>
                            <td class="atraso-cell"><?php echo formatarMinutos($func['total_minutos']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function exportarTabelasCsv(nomeArquivo) {
    const linhas = [];
    document.querySelectorAll('.data-table').forEach(function (tabela, indice) {
        if (indice > 0) linhas.push([]);
        tabela.querySelectorAll('tr').forEach(function (linha) {
            linhas.push(Array.from(linha.querySelectorAll('th,td')).map(function (celula) {
                return '"' + celula.innerText.trim().replace(/"/g, '""') + '"';
            }));
        });
    });
    const blob = new Blob(['\uFEFF' + linhas.map(linha => linha.join(';')).join('\n')], {type: 'text/csv;charset=utf-8'});
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = nomeArquivo;
    link.click();
    URL.revokeObjectURL(link.href);
}

document.getElementById('periodo')?.addEventListener('change', function() {
    const grupoDiario = document.getElementById('grupo_data_diario');
    const grupoSemanal = document.getElementById('grupo_data_semanal');
    const grupoMensal = document.getElementById('grupo_data_mensal');
    
    grupoDiario.style.display = 'none';
    grupoSemanal.style.display = 'none';
    grupoMensal.style.display = 'none';
    
    if (this.value === 'diario') {
        grupoDiario.style.display = 'flex';
    } else if (this.value === 'semanal') {
        grupoSemanal.style.display = 'flex';
    } else if (this.value === 'mensal') {
        grupoMensal.style.display = 'flex';
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
