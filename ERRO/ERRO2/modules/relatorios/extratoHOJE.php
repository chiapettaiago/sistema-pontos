<?php
// modules/relatorios/extrato.php - Extrato de Ponto (CORRIGIDO)
$pageTitle = 'Extrato de Ponto';
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
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

// Buscar funcionários para o filtro
if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa') {
    $stmt = $db->prepare("SELECT id, nome, matricula, filial_id FROM funcionarios WHERE status = 'ativo' AND empresa_id = :empresa_id ORDER BY nome");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $funcionarios = $stmt->fetchAll();
} else {
    // Para gestor/supervisor, mostrar apenas funcionários da sua filial
    if ($usuario_filial_id) {
        $stmt = $db->prepare("SELECT id, nome, matricula, filial_id FROM funcionarios WHERE status = 'ativo' AND empresa_id = :empresa_id AND filial_id = :filial_id ORDER BY nome");
        $stmt->execute([':empresa_id' => $empresa_id, ':filial_id' => $usuario_filial_id]);
    } else {
        $stmt = $db->prepare("SELECT id, nome, matricula, filial_id FROM funcionarios WHERE status = 'ativo' AND empresa_id = :empresa_id ORDER BY nome");
        $stmt->execute([':empresa_id' => $empresa_id]);
    }
    $funcionarios = $stmt->fetchAll();
}

// Buscar pontos
$query = "SELECT p.*, f.nome as funcionario_nome, f.matricula, fil.nome_fantasia as filial_nome
          FROM pontos p
          JOIN funcionarios f ON p.funcionario_id = f.id
          JOIN filiais fil ON p.filial_id = fil.id
          WHERE DATE(p.data_hora) BETWEEN :data_inicio AND :data_fim
          AND f.empresa_id = :empresa_id";

$params = [
    ':data_inicio' => $data_inicio,
    ':data_fim' => $data_fim,
    ':empresa_id' => $empresa_id
];

if ($funcionario_id) {
    $query .= " AND p.funcionario_id = :funcionario_id";
    $params[':funcionario_id'] = $funcionario_id;
}

if ($usuario_tipo !== 'super_admin' && $usuario_tipo !== 'admin_empresa' && $usuario_filial_id) {
    $query .= " AND f.filial_id = :filial_id";
    $params[':filial_id'] = $usuario_filial_id;
}

$query .= " ORDER BY p.data_hora DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$pontos = $stmt->fetchAll();

// Agrupar pontos por data
$extrato = [];
foreach ($pontos as $ponto) {
    $data = date('Y-m-d', strtotime($ponto['data_hora']));
    if (!isset($extrato[$data])) {
        $extrato[$data] = [
            'data' => $data,
            'funcionario_nome' => $ponto['funcionario_nome'],
            'funcionario_id' => $ponto['funcionario_id'],
            'matricula' => $ponto['matricula'],
            'filial_nome' => $ponto['filial_nome'],
            'entrada' => null,
            'saida_almoco' => null,
            'volta_almoco' => null,
            'saida' => null
        ];
    }
    
    switch ($ponto['tipo']) {
        case 'entrada':
            $extrato[$data]['entrada'] = date('H:i', strtotime($ponto['data_hora']));
            break;
        case 'saida_almoco':
            $extrato[$data]['saida_almoco'] = date('H:i', strtotime($ponto['data_hora']));
            break;
        case 'volta_almoco':
            $extrato[$data]['volta_almoco'] = date('H:i', strtotime($ponto['data_hora']));
            break;
        case 'saida':
            $extrato[$data]['saida'] = date('H:i', strtotime($ponto['data_hora']));
            break;
    }
}

// Calcular horas trabalhadas para cada dia
function calcularHorasExtrato($entrada, $saida_almoco, $volta_almoco, $saida) {
    if (!$entrada || !$saida) return '--:--';
    
    $entrada_ts = strtotime($entrada);
    $saida_almoco_ts = $saida_almoco ? strtotime($saida_almoco) : null;
    $volta_almoco_ts = $volta_almoco ? strtotime($volta_almoco) : null;
    $saida_ts = strtotime($saida);
    
    $total_minutos = ($saida_ts - $entrada_ts) / 60;
    
    if ($saida_almoco_ts && $volta_almoco_ts) {
        $total_minutos -= ($volta_almoco_ts - $saida_almoco_ts) / 60;
    }
    
    $horas = floor($total_minutos / 60);
    $minutos = $total_minutos % 60;
    
    return sprintf("%02d:%02d", $horas, $minutos);
}

foreach ($extrato as &$dia) {
    $dia['horas'] = calcularHorasExtrato($dia['entrada'], $dia['saida_almoco'], $dia['volta_almoco'], $dia['saida']);
}
?>

<style>
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
            <h2><i class="fas fa-calendar-alt"></i> Extrato de Ponto</h2>
            <p>Consulta detalhada de registros de ponto</p>
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
                        <?php echo htmlspecialchars($func['nome'] . ' (' . $func['matricula'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Data Início</label>
                <input type="date" name="data_inicio" value="<?php echo $data_inicio; ?>">
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Data Fim</label>
                <input type="date" name="data_fim" value="<?php echo $data_fim; ?>">
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filtrar
                </button>
                <a href="extrato.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Limpar
                </a>
            </div>
        </form>
    </div>

    <!-- Tabela de Extrato -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Registros de Ponto</h3>
            <p>Total de registros: <?php echo count($extrato); ?></p>
        </div>
        <div class="table-responsive">
            <table class="extrato-table" id="tabelaExtrato">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Funcionário</th>
                        <th>Matrícula</th>
                        <th>Filial</th>
                        <th>Entrada</th>
                        <th>Saída Almoço</th>
                        <th>Volta Almoço</th>
                        <th>Saída</th>
                        <th>Horas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($extrato as $dia): ?>
                    <tr class="fade-in">
                        <td><?php echo date('d/m/Y', strtotime($dia['data'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($dia['funcionario_nome']); ?></strong></td>
                        <td><?php echo htmlspecialchars($dia['matricula']); ?></td>
                        <td><?php echo htmlspecialchars($dia['filial_nome']); ?></td>
                        <td><?php echo $dia['entrada'] ?? '--:--'; ?></td>
                        <td><?php echo $dia['saida_almoco'] ?? '--:--'; ?></td>
                        <td><?php echo $dia['volta_almoco'] ?? '--:--'; ?></td>
                        <td><?php echo $dia['saida'] ?? '--:--'; ?></td>
                        <td class="horas-positivo"><?php echo $dia['horas']; ?>h</td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($extrato)): ?>
                    <tr class="fade-in">
                        <td colspan="9" style="text-align: center; padding: 60px;">
                            <i class="fas fa-calendar-times" style="font-size: 48px; color: #ccc;"></i>
                            <p style="margin-top: 10px;">Nenhum registro encontrado no período selecionado</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($extrato)): ?>
                <tfoot>
                    <tr style="background: var(--bg-secondary); font-weight: 600;">
                        <td colspan="8" style="text-align: right;">Total de Registros:</td>
                        <td class="horas-positivo"><?php echo count($extrato); ?> dias</td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
function exportarExcel() {
    const tabela = document.getElementById('tabelaExtrato');
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
    link.setAttribute('download', 'extrato_ponto.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
</script>

<?php require_once '../../includes/footer.php'; ?>


