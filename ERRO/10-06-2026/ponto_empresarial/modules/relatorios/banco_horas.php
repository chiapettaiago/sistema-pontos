<?php
// modules/relatorios/banco_horas.php - Banco de Horas (CORRIGIDO - SEM WINDOW FUNCTIONS)
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

$pageTitle = 'Banco de Horas';
$activePage = 'relatorios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$funcionario_id = $_GET['funcionario_id'] ?? '';
$carga_diaria = 8;

// Buscar configuração da carga horária
$stmt_config = $db->prepare("SELECT carga_horaria_diaria FROM config_horarios WHERE empresa_id = :empresa_id");
$stmt_config->execute([':empresa_id' => $empresa_id]);
$config = $stmt_config->fetch();
if ($config) {
    $carga_diaria = $config['carga_horaria_diaria'] ?: 8;
}

// Buscar funcionários
$stmt_func = $db->prepare("SELECT id, nome, matricula FROM funcionarios 
                           WHERE empresa_id = :empresa_id AND status = 'ativo' 
                           ORDER BY nome");
$stmt_func->execute([':empresa_id' => $empresa_id]);
$funcionarios = $stmt_func->fetchAll();

// ============================================
// BUSCAR SALDO DE HORAS POR FUNCIONÁRIO
// ============================================
$resultados = [];
$total_saldo = 0;

foreach ($funcionarios as $func) {
    // Se filtrou por funcionário, pular os outros
    if ($funcionario_id && $func['id'] != $funcionario_id) {
        continue;
    }
    
    // Buscar todos os pontos do funcionário
    $stmt_pontos = $db->prepare("SELECT tipo, data_hora, DATE(data_hora) as data 
                                 FROM pontos 
                                 WHERE funcionario_id = :id 
                                 ORDER BY data_hora ASC");
    $stmt_pontos->execute([':id' => $func['id']]);
    $pontos = $stmt_pontos->fetchAll();
    
    // Agrupar por data
    $dias = [];
    foreach ($pontos as $ponto) {
        $data = $ponto['data'];
        if (!isset($dias[$data])) {
            $dias[$data] = [
                'entrada' => null,
                'saida_almoco' => null,
                'volta_almoco' => null,
                'saida' => null
            ];
        }
        
        switch ($ponto['tipo']) {
            case 'entrada':
                $dias[$data]['entrada'] = $ponto['data_hora'];
                break;
            case 'saida_almoco':
                $dias[$data]['saida_almoco'] = $ponto['data_hora'];
                break;
            case 'volta_almoco':
                $dias[$data]['volta_almoco'] = $ponto['data_hora'];
                break;
            case 'saida':
                $dias[$data]['saida'] = $ponto['data_hora'];
                break;
        }
    }
    
    // Calcular horas trabalhadas por dia
    $total_minutos = 0;
    $dias_trabalhados = 0;
    
    foreach ($dias as $data => $dia) {
        if (!$dia['entrada'] || !$dia['saida']) {
            continue;
        }
        
        $entrada_ts = strtotime($dia['entrada']);
        $saida_ts = strtotime($dia['saida']);
        $minutos_dia = ($saida_ts - $entrada_ts) / 60;
        
        if ($dia['saida_almoco'] && $dia['volta_almoco']) {
            $almoco_ts = strtotime($dia['saida_almoco']);
            $volta_ts = strtotime($dia['volta_almoco']);
            $minutos_dia -= ($volta_ts - $almoco_ts) / 60;
        }
        
        if ($minutos_dia > 0) {
            $total_minutos += $minutos_dia;
            $dias_trabalhados++;
        }
    }
    
    // Calcular saldo
    $horas_trab = $total_minutos / 60;
    $horas_esperadas = $dias_trabalhados * $carga_diaria;
    $saldo = $horas_trab - $horas_esperadas;
    
    $resultados[] = [
        'id' => $func['id'],
        'nome' => $func['nome'],
        'matricula' => $func['matricula'],
        'horas_trab' => $horas_trab,
        'saldo' => $saldo
    ];
    $total_saldo += $saldo;
}

function formatarHorasBanco($horas) {
    $h = floor($horas);
    $m = round(($horas - $h) * 60);
    return sprintf("%02d:%02d", $h, $m);
}
?>

<style>
.relatorio-container {
    max-width: 1000px;
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

.filter-group select {
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
    min-width: 200px;
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
    text-align: center;
}

.resumo-valor {
    font-size: 32px;
    font-weight: 700;
}

.resumo-valor.positivo {
    color: #10b981;
}

.resumo-valor.negativo {
    color: #ef4444;
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
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
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

.saldo-positivo {
    color: #10b981;
    font-weight: bold;
}

.saldo-negativo {
    color: #ef4444;
    font-weight: bold;
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

.btn-pdf {
    background: #ef4444;
    color: white;
}

@media (max-width: 768px) {
    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<div class="relatorio-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-piggy-bank"></i> Banco de Horas</h2>
            <p>Saldo de horas por funcionário</p>
        </div>
        <div class="module-actions">
            <a href="exportar_excel.php?tipo=banco_horas&funcionario_id=<?php echo $funcionario_id; ?>" class="btn-exportar btn-excel">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </a>
            <a href="exportar_pdf.php?tipo=banco_horas&funcionario_id=<?php echo $funcionario_id; ?>" class="btn-exportar btn-pdf" target="_blank">
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
                            <?php echo htmlspecialchars($func['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn-filter">Filtrar</button>
            </div>
        </form>
    </div>

    <!-- Resumo Total -->
    <div class="resumo-card">
        <div class="resumo-valor <?php echo $total_saldo >= 0 ? 'positivo' : 'negativo'; ?>">
            <?php echo ($total_saldo >= 0 ? '+' : '') . formatarHorasBanco(abs($total_saldo)); ?>
        </div>
        <div class="resumo-label">Saldo Total do Banco de Horas</div>
    </div>

    <!-- Tabela de Resultados -->
    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Matrícula</th>
                    <th>Horas Trabalhadas</th>
                    <th>Saldo</th>
                    <th>Situação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($resultados)): ?>
                    <tr class="fade-in">
                        <td colspan="5" style="text-align: center;">Nenhum registro encontrado</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($resultados as $r): ?>
                        <tr class="fade-in">
                            <td><strong><?php echo htmlspecialchars($r['nome']); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['matricula']); ?></td>
                            <td><?php echo formatarHorasBanco($r['horas_trab']); ?></td>
                            <td class="<?php echo $r['saldo'] >= 0 ? 'saldo-positivo' : 'saldo-negativo'; ?>">
                                <?php echo ($r['saldo'] >= 0 ? '+' : '') . formatarHorasBanco(abs($r['saldo'])); ?>
                            </td>
                            <td><?php echo $r['saldo'] >= 0 ? 'Crédito' : 'Débito'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($resultados)): ?>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align: right;"><strong>TOTAL GERAL:</strong></td>
                    <td><strong><?php echo formatarHorasBanco(array_sum(array_column($resultados, 'horas_trab'))); ?></strong></td>
                    <td class="<?php echo $total_saldo >= 0 ? 'saldo-positivo' : 'saldo-negativo'; ?>">
                        <strong><?php echo ($total_saldo >= 0 ? '+' : '') . formatarHorasBanco(abs($total_saldo)); ?></strong>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <!-- Informações Adicionais -->
    <div class="actions-card" style="margin-top: 24px; padding: 16px; background: var(--bg-secondary); border-radius: 16px;">
        <div style="font-size: 12px; color: var(--text-secondary);">
            <strong><i class="fas fa-info-circle"></i> Informações:</strong><br>
            • Carga horária diária considerada: <strong><?php echo $carga_diaria; ?>h</strong><br>
            • Saldo positivo = Horas extras a receber<br>
            • Saldo negativo = Horas a compensar
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>