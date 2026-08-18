<?php
// modules/relatorios/extrato_funcionario.php - Extrato Individual Avançado
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

$pageTitle = 'Extrato Individual';
$activePage = 'relatorios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;

// Parâmetros
$funcionario_id = $_GET['funcionario_id'] ?? '';
$periodo = $_GET['periodo'] ?? 'mensal'; // 'semanal' ou 'mensal'
if ($periodo == 'semanal') {
    $data_referencia = $_GET['data_referencia_semana'] ?? ($_GET['data_referencia'] ?? date('Y-\WW'));
} else {
    $data_referencia = $_GET['data_referencia_mes'] ?? ($_GET['data_referencia'] ?? date('Y-m'));
}

// Buscar lista de funcionários
$stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios 
                      WHERE empresa_id = :empresa_id AND status = 'ativo' 
                      ORDER BY nome");
$stmt->execute([':empresa_id' => $empresa_id]);
$funcionarios = $stmt->fetchAll();

// Se não selecionou funcionário, pegar o primeiro
if (!$funcionario_id && !empty($funcionarios)) {
    $funcionario_id = $funcionarios[0]['id'];
}

// Buscar dados do funcionário
$stmt = $db->prepare("SELECT f.*, 
                      fi.nome_fantasia as filial_nome,
                      c.nome as cargo_nome,
                      e.nome_empresa
                      FROM funcionarios f
                      LEFT JOIN filiais fi ON f.filial_id = fi.id
                      LEFT JOIN cargos c ON f.cargo_id = c.id
                      LEFT JOIN empresa e ON f.empresa_id = e.id
                      WHERE f.id = :id");
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

if (!$funcionario && !empty($funcionarios)) {
    $funcionario_id = $funcionarios[0]['id'];
    $stmt->execute([':id' => $funcionario_id]);
    $funcionario = $stmt->fetch();
}

// Definir período
if ($periodo == 'semanal') {
    // Semana atual (segunda a domingo)
    $data_inicio = date('Y-m-d', strtotime('monday this week', strtotime($data_referencia)));
    $data_fim = date('Y-m-d', strtotime('sunday this week', strtotime($data_referencia)));
    $titulo_periodo = 'Semana de ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim));
} else {
    // Mensal
    $data_inicio = date('Y-m-01', strtotime($data_referencia));
    $data_fim = date('Y-m-t', strtotime($data_referencia));
    $titulo_periodo = strftime('%B de %Y', strtotime($data_referencia));
}

// Buscar pontos do período
$query = "SELECT 
            DATE(p.data_hora) as data,
            DAYOFWEEK(p.data_hora) as dia_semana_num,
            MAX(CASE WHEN p.tipo = 'entrada' THEN TIME(p.data_hora) END) as entrada,
            MAX(CASE WHEN p.tipo = 'saida_almoco' THEN TIME(p.data_hora) END) as saida_almoco,
            MAX(CASE WHEN p.tipo = 'volta_almoco' THEN TIME(p.data_hora) END) as volta_almoco,
            MAX(CASE WHEN p.tipo = 'saida' THEN TIME(p.data_hora) END) as saida,
            MAX(CASE WHEN p.tipo = 'extra_entrada' THEN TIME(p.data_hora) END) as extra_entrada,
            MAX(CASE WHEN p.tipo = 'extra_saida' THEN TIME(p.data_hora) END) as extra_saida
          FROM pontos p
          WHERE p.funcionario_id = :funcionario_id
          AND DATE(p.data_hora) BETWEEN :data_inicio AND :data_fim
          GROUP BY DATE(p.data_hora)
          ORDER BY data ASC";

$stmt = $db->prepare($query);
$stmt->execute([
    ':funcionario_id' => $funcionario_id,
    ':data_inicio' => $data_inicio,
    ':data_fim' => $data_fim
]);
$pontos = $stmt->fetchAll();

// Configurações de carga horária
$stmt = $db->prepare("SELECT carga_horaria_diaria, carga_horaria_semanal FROM config_horarios WHERE empresa_id = :empresa_id");
$stmt->execute([':empresa_id' => $empresa_id]);
$config = $stmt->fetch();
$carga_horaria_diaria = 7 + (20/60); // 7:20h
$carga_horaria_semanal = 44;

// Processar cada dia
$dias = [];
$total_horas_normais = 0;
$total_horas_extras_50 = 0;
$total_horas_extras_100 = 0;
$total_faltas = 0;
$total_atrasos = 0;

foreach ($pontos as $p) {
    $entrada = $p['entrada'];
    $saida_almoco = $p['saida_almoco'];
    $volta_almoco = $p['volta_almoco'];
    $saida = $p['saida'];
    $extra_entrada = $p['extra_entrada'];
    $extra_saida = $p['extra_saida'];
    
    $calculo = calcularHorasDia($entrada, $saida_almoco, $volta_almoco, $saida, $extra_entrada, $extra_saida, $carga_horaria_diaria);
    
    $dias[] = [
        'data' => $p['data'],
        'data_formatada' => date('d/m/Y', strtotime($p['data'])),
        'dia_semana' => retornarDiaSemana($p['dia_semana_num']),
        'entrada' => $entrada ? substr($entrada, 0, 5) : '--:--',
        'saida_almoco' => $saida_almoco ? substr($saida_almoco, 0, 5) : '--:--',
        'volta_almoco' => $volta_almoco ? substr($volta_almoco, 0, 5) : '--:--',
        'saida' => $saida ? substr($saida, 0, 5) : '--:--',
        'extra_entrada' => $extra_entrada ? substr($extra_entrada, 0, 5) : '--:--',
        'extra_saida' => $extra_saida ? substr($extra_saida, 0, 5) : '--:--',
        'horas_trabalhadas' => $calculo['horas_trabalhadas'],
        'horas_extras_50' => $calculo['horas_extras_50'],
        'horas_extras_100' => $calculo['horas_extras_100'],
        'saldo' => $calculo['saldo'],
        'falta' => $calculo['falta'],
        'atraso' => $calculo['atraso'],
        'status' => $calculo['status']
    ];
    
    $total_horas_normais += $calculo['horas_trabalhadas_num'];
    $total_horas_extras_50 += $calculo['horas_extras_50_num'];
    $total_horas_extras_100 += $calculo['horas_extras_100_num'];
    $total_faltas += $calculo['falta'] ? 1 : 0;
    $total_atrasos += $calculo['atraso'] ? 1 : 0;
}

// Calcular saldo final
$saldo_final = $total_horas_normais + $total_horas_extras_50 + $total_horas_extras_100 - ($carga_horaria_semanal * 4);

function formatarHoras($horas_decimais, $com_sinal = false) {
    if ($horas_decimais == 0) return ($com_sinal ? '+' : '') . '00:00';
    $sinal = $horas_decimais < 0 ? '-' : ($com_sinal ? '+' : '');
    $abs = abs($horas_decimais);
    $h = floor($abs);
    $m = round(($abs - $h) * 60);
    if ($m >= 60) {
        $h += 1;
        $m -= 60;
    }
    return $sinal . str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT);
}

function retornarDiaSemana($numero) {
    $dias = [
        1 => 'Domingo',
        2 => 'Segunda-feira',
        3 => 'Terça-feira',
        4 => 'Quarta-feira',
        5 => 'Quinta-feira',
        6 => 'Sexta-feira',
        7 => 'Sábado'
    ];
    return $dias[$numero] ?? '';
}

function calcularHorasDia($entrada, $saida_almoco, $volta_almoco, $saida, $extra_entrada, $extra_saida, $carga_diaria) {
    $result = [
        'horas_trabalhadas' => '00:00',
        'horas_trabalhadas_num' => 0,
        'horas_extras_50' => '00:00',
        'horas_extras_50_num' => 0,
        'horas_extras_100' => '00:00',
        'horas_extras_100_num' => 0,
        'saldo' => '00:00',
        'falta' => false,
        'atraso' => false,
        'status' => 'Pendente'
    ];
    
    if (!$entrada && !$saida) {
        $result['status'] = 'Falta';
        $result['falta'] = true;
        return $result;
    }
    
    if (!$entrada) {
        $result['status'] = 'Sem entrada';
        return $result;
    }
    
    if (!$saida) {
        $result['status'] = 'Incompleto';
        return $result;
    }
    
    // Calcular horas trabalhadas
    $entrada_ts = strtotime($entrada);
    $saida_ts = strtotime($saida);
    $total_segundos = $saida_ts - $entrada_ts;
    
    if ($saida_almoco && $volta_almoco) {
        $almoco_ts = strtotime($saida_almoco);
        $volta_ts = strtotime($volta_almoco);
        $total_segundos -= ($volta_ts - $almoco_ts);
    }
    
    if ($extra_entrada && $extra_saida) {
        $total_segundos += strtotime($extra_saida) - strtotime($extra_entrada);
    }
    
    $horas_trab = $total_segundos / 3600;
    $result['horas_trabalhadas_num'] = $horas_trab;
    $result['horas_trabalhadas'] = floor($horas_trab) . ':' . str_pad(round(($horas_trab - floor($horas_trab)) * 60), 2, '0', STR_PAD_LEFT);
    
    // Calcular saldo
    $saldo = $horas_trab - $carga_diaria;
    $result['saldo'] = formatarHoras($saldo, true);
    
    // Verificar atraso (entrada após 08:00)
    if ($entrada > '08:00:00') {
        $result['atraso'] = true;
        $result['status'] = 'Atraso';
    } else {
        $result['status'] = 'Normal';
    }
    
    // Calcular horas extras
    if ($saldo > 0) {
        // Verificar se é domingo ou sábado para horas extras 100%
        // Simplificado: sábado=7, domingo=1
        $result['horas_extras_50_num'] = $saldo;
        $result['horas_extras_50'] = formatarHoras($saldo);
    }
    
    return $result;
}
?>

<style>
.positivo {
    color: var(--bs-success) !important;
    font-weight: bold;
}
.negativo {
    color: var(--bs-danger) !important;
    font-weight: bold;
}
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

.resumo-card {
    background: linear-gradient(135deg, #667eea20, #764ba220);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
}

.resumo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
}

.resumo-item {
    text-align: center;
    background: var(--bg-primary);
    border-radius: 12px;
    padding: 12px;
}

.resumo-valor {
    font-size: 24px;
    font-weight: 700;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.resumo-label {
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 5px;
}

.resumo-valor.positivo { color: #10b981; background: none; -webkit-background-clip: unset; }
.resumo-valor.negativo { color: #ef4444; background: none; -webkit-background-clip: unset; }

.table-card {
    background: var(--bg-primary);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    overflow: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

.data-table th,
.data-table td {
    padding: 10px 12px;
    text-align: center;
    border-bottom: 1px solid var(--border-color);
    font-size: 12px;
}

.data-table th {
    background: var(--bg-secondary);
    font-weight: 600;
    position: sticky;
    top: 0;
}

.status-normal { color: #10b981; }
.status-atraso { color: #f59e0b; }
.status-falta { color: #ef4444; }
.status-incompleto { color: #6b7280; }

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
    
    .resumo-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="relatorio-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-user-clock"></i> Extrato Individual</h2>
            <p>Consulta detalhada de ponto por funcionário</p>
        </div>
        <div class="module-actions">
            <a href="exportar_excel.php?tipo=extrato_funcionario&funcionario_id=<?php echo $funcionario_id; ?>&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>" class="btn-exportar btn-excel">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </a>
            <a href="exportar_pdf.php?tipo=extrato_funcionario&funcionario_id=<?php echo $funcionario_id; ?>&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>" class="btn-exportar btn-pdf" target="_blank">
                <i class="fas fa-file-pdf"></i> Exportar PDF
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-form">
            <div class="filter-group">
                <label>Funcionário</label>
                <select name="funcionario_id" required>
                    <?php foreach ($funcionarios as $func): ?>
                        <option value="<?php echo $func['id']; ?>" <?php echo $funcionario_id == $func['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($func['nome'] . ' (' . $func['matricula'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Período</label>
                <select name="periodo" id="periodo">
                    <option value="semanal" <?php echo $periodo == 'semanal' ? 'selected' : ''; ?>>Semanal</option>
                    <option value="mensal" <?php echo $periodo == 'mensal' ? 'selected' : ''; ?>>Mensal</option>
                </select>
            </div>
            <div class="filter-group" id="grupo_data_semanal" style="display: <?php echo $periodo == 'semanal' ? 'flex' : 'none'; ?>;">
                <label>Semana</label>
                <input type="week" name="data_referencia_semana" value="<?php echo date('Y-\WW', strtotime($data_referencia)); ?>">
            </div>
            <div class="filter-group" id="grupo_data_mensal" style="display: <?php echo $periodo == 'mensal' ? 'flex' : 'none'; ?>;">
                <label>Mês</label>
                <input type="month" name="data_referencia_mes" value="<?php echo substr($data_referencia, 0, 7); ?>">
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn-filter">Filtrar</button>
            </div>
        </form>
    </div>

    <!-- Informações do Funcionário -->
    <?php if ($funcionario): ?>
    <div class="resumo-card">
        <div style="margin-bottom: 16px;">
            <strong><?php echo htmlspecialchars($funcionario['nome']); ?></strong><br>
            <small>Matrícula: <?php echo htmlspecialchars($funcionario['matricula']); ?> | 
            Cargo: <?php echo htmlspecialchars($funcionario['cargo_nome'] ?? 'Não definido'); ?> | 
            Filial: <?php echo htmlspecialchars($funcionario['filial_nome'] ?? 'Matriz'); ?></small>
        </div>
        <div class="resumo-grid">
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo formatarHoras($total_horas_normais); ?></div>
                <div class="resumo-label">Horas Normais</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo formatarHoras($total_horas_extras_50); ?></div>
                <div class="resumo-label">Horas Extras 50%</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo formatarHoras($total_horas_extras_100); ?></div>
                <div class="resumo-label">Horas Extras 100%</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor <?php echo $saldo_final >= 0 ? 'positivo' : 'negativo'; ?>">
                    <?php echo formatarHoras($saldo_final, true); ?>
                </div>
                <div class="resumo-label">Saldo Final</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo $total_faltas; ?></div>
                <div class="resumo-label">Faltas</div>
            </div>
            <div class="resumo-item">
                <div class="resumo-valor"><?php echo $total_atrasos; ?></div>
                <div class="resumo-label">Atrasos</div>
            </div>
        </div>
        <div style="margin-top: 16px; text-align: center; font-size: 12px; color: var(--text-secondary);">
            Período: <?php echo $titulo_periodo; ?>
        </div>
    </div>

    <!-- Tabela Extrato -->
    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Dia da Semana</th>
                    <th>Entrada</th>
                    <th>Saída Almoço</th>
                    <th>Volta Almoço</th>
                    <th>Saída</th>
                    <th>Entrada Extra</th>
                    <th>Saída Extra</th>
                    <th>Horas</th>
                    <th>Saldo</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dias)): ?>
                    <tr class="fade-in"><td colspan="11" style="text-align: center;">Nenhum registro encontrado</td></tr>
                <?php else: ?>
                    <?php foreach ($dias as $dia): ?>
                        <tr class="fade-in">
                            <td><?php echo $dia['data_formatada']; ?></td>
                            <td><?php echo $dia['dia_semana']; ?></td>
                            <td><?php echo $dia['entrada']; ?></td>
                            <td><?php echo $dia['saida_almoco']; ?></td>
                            <td><?php echo $dia['volta_almoco']; ?></td>
                            <td><?php echo $dia['saida']; ?></td>
                            <td><?php echo $dia['extra_entrada']; ?></td>
                            <td><?php echo $dia['extra_saida']; ?></td>
                            <td><?php echo $dia['horas_trabalhadas']; ?></td>
                            <td class="<?php echo strpos($dia['saldo'], '-') !== false ? 'negativo' : 'positivo'; ?>"><?php echo $dia['saldo']; ?></td>
                            <td class="status-<?php echo strtolower($dia['status']); ?>"><?php echo $dia['status']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('periodo')?.addEventListener('change', function() {
    const grupoSemanal = document.getElementById('grupo_data_semanal');
    const grupoMensal = document.getElementById('grupo_data_mensal');
    
    if (this.value === 'semanal') {
        grupoSemanal.style.display = 'flex';
        grupoMensal.style.display = 'none';
    } else {
        grupoSemanal.style.display = 'none';
        grupoMensal.style.display = 'flex';
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>