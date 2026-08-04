<?php
// modules/relatorios/extrato_funcionario.php - Extrato de Funcionário (CORRIGIDO - SEM DUPLICIDADE)
session_start();

// Verificar permissão
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_tipo'] !== 'super_admin' && $_SESSION['usuario_tipo'] !== 'admin_empresa' && $_SESSION['usuario_tipo'] !== 'gestor')) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Extrato do Funcionário';
$activePage = 'relatorios';

require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = getCurrentEmpresaId() ?: 1;
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';
$usuario_filial_id = $_SESSION['usuario_filial_id'] ?? null;

// Parâmetros de filtro
$funcionario_id = $_GET['funcionario_id'] ?? null;
$mes = $_GET['mes'] ?? date('Y-m');
$ano = substr($mes, 0, 4);
$mes_num = substr($mes, 5, 2);
$filial_id = $_GET['filial_id'] ?? null;

// ============================================
// BUSCAR FUNCIONÁRIOS (baseado no perfil)
// ============================================
$funcionarios = [];

if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa') {
    // Admin vê todos os funcionários da empresa
    $sql = "SELECT f.id, f.nome, f.matricula, fi.nome_fantasia as filial_nome 
            FROM funcionarios f
            LEFT JOIN filiais fi ON f.filial_id = fi.id
            WHERE f.empresa_id = :empresa_id AND f.status = 'ativo'";
    $params = [':empresa_id' => $empresa_id];
    
    if ($filial_id) {
        $sql .= " AND f.filial_id = :filial_id";
        $params[':filial_id'] = $filial_id;
    }
    
    $sql .= " ORDER BY f.nome ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $funcionarios = $stmt->fetchAll();
    
    // Buscar filiais para filtro
    $stmt = $db->prepare("SELECT id, nome_fantasia FROM filiais WHERE empresa_id = :empresa_id AND ativo = 1 ORDER BY nome_fantasia");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $filiais = $stmt->fetchAll();
    
} elseif ($usuario_tipo === 'gestor' && $usuario_filial_id) {
    // Gestor vê apenas funcionários da sua filial
    $stmt = $db->prepare("SELECT f.id, f.nome, f.matricula, fi.nome_fantasia as filial_nome 
                          FROM funcionarios f
                          LEFT JOIN filiais fi ON f.filial_id = fi.id
                          WHERE f.empresa_id = :empresa_id AND f.filial_id = :filial_id AND f.status = 'ativo'
                          ORDER BY f.nome ASC");
    $stmt->execute([
        ':empresa_id' => $empresa_id,
        ':filial_id' => $usuario_filial_id
    ]);
    $funcionarios = $stmt->fetchAll();
    
    $filiais = [];
}

// Se não há funcionários, mostrar mensagem
if (empty($funcionarios)) {
    echo '<div class="alert alert-warning">Nenhum funcionário encontrado.</div>';
    require_once '../../includes/footer.php';
    exit;
}

// Se não selecionou funcionário, pegar o primeiro
if (!$funcionario_id && !empty($funcionarios)) {
    $funcionario_id = $funcionarios[0]['id'];
}

// Buscar dados do funcionário selecionado
$stmt = $db->prepare("SELECT f.*, fi.nome_fantasia as filial_nome, c.nome as cargo_nome
                      FROM funcionarios f
                      LEFT JOIN filiais fi ON f.filial_id = fi.id
                      LEFT JOIN cargos c ON f.cargo_id = c.id
                      WHERE f.id = :id");
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    echo '<div class="alert alert-error">Funcionário não encontrado</div>';
    require_once '../../includes/footer.php';
    exit;
}

// ============================================
// BUSCAR PONTOS DO FUNCIONÁRIO - AGRUPADO POR DIA
// ============================================
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

// ============================================
// FORMATAR OS DADOS E CALCULAR HORAS
// ============================================
$extrato = [];

function retornarDiaSemanaExt($numero) {
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
    
    // Definir status
    if ($entrada && $saida && $saida_almoco && $volta_almoco) {
        $status_texto = '✅ Completo';
        $status_cor = 'complete';
    } elseif ($entrada && $saida) {
        $status_texto = '⚠️ Sem almoço';
        $status_cor = 'incomplete';
    } elseif ($entrada && !$saida) {
        if ($eh_dia_atual) {
            $status_texto = '⏳ Em andamento';
            $status_cor = 'inprogress';
        } else {
            $status_texto = '❌ Incompleto';
            $status_cor = 'incomplete';
        }
    } elseif (!$entrada && ($saida_almoco || $volta_almoco || $saida)) {
        $status_texto = '⚠️ Anômalo';
        $status_cor = 'incomplete';
    } else {
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
        'dia_semana' => retornarDiaSemanaExt(date('w', strtotime($data))),
        'entrada' => $entrada ?: '--:--',
        'saida_almoco' => $saida_almoco ?: '--:--',
        'volta_almoco' => $volta_almoco ?: '--:--',
        'saida' => $saida ?: '--:--',
        'horas' => $horas_formatado,
        'status_texto' => $status_texto,
        'status_cor' => $status_cor
    ];
}

// ============================================
// CALCULAR ESTATÍSTICAS
// ============================================
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

.filters-form {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-group label {
    font-size: 12px;
    font-weight: 500;
    color: var(--text-secondary);
}

.filter-group select, .filter-group input {
    padding: 10px 16px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: 14px;
    min-width: 180px;
}

.btn-filtrar {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 10px 24px;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
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
    color: #f59e0b;
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

.btn-print {
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
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.btn-print:hover {
    background: var(--bg-tertiary);
}

.info-funcionario {
    background: #f8f9fa;
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
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
    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group select, .filter-group input {
        min-width: auto;
    }
    
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

@media print {
    .filters-card, .btn-print, .module-actions, .top-bar, .sidebar {
        display: none;
    }
    
    .extrato-container {
        padding: 0;
    }
    
    body {
        background: white;
    }
}
</style>

<div class="extrato-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-calendar-alt"></i> Extrato de Ponto</h2>
            <p>Visualize os registros de ponto dos funcionários</p>
        </div>
        <div class="module-actions">
            <button onclick="window.print()" class="btn-print">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-form">
            <div class="filter-group">
                <label><i class="fas fa-user"></i> Funcionário</label>
                <select name="funcionario_id" onchange="this.form.submit()">
                    <?php foreach ($funcionarios as $func): ?>
                    <option value="<?php echo $func['id']; ?>" <?php echo $funcionario_id == $func['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($func['nome']); ?> (<?php echo htmlspecialchars($func['matricula']); ?>) - <?php echo htmlspecialchars($func['filial_nome']); ?>
                                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if (!empty($filiais)): ?>
            <div class="filter-group">
                <label><i class="fas fa-store"></i> Filial</label>
                <select name="filial_id" onchange="this.form.submit()">
                    <option value="">Todas as filiais</option>
                    <?php foreach ($filiais as $filial): ?>
                    <option value="<?php echo $filial['id']; ?>" <?php echo $filial_id == $filial['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($filial['nome_fantasia']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> Período</label>
                <input type="month" name="mes" value="<?php echo $mes; ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>

    <!-- Informações do Funcionário -->
    <div class="info-funcionario">
        <div>
            <strong><i class="fas fa-user"></i> Funcionário:</strong> <?php echo htmlspecialchars($funcionario['nome']); ?><br>
            <strong><i class="fas fa-id-badge"></i> Matrícula:</strong> <?php echo htmlspecialchars($funcionario['matricula']); ?>
        </div>
        <div>
            <strong><i class="fas fa-store"></i> Filial:</strong> <?php echo htmlspecialchars($funcionario['filial_nome']); ?><br>
            <strong><i class="fas fa-briefcase"></i> Cargo:</strong> <?php echo htmlspecialchars($funcionario['cargo_nome'] ?? 'Não definido'); ?>
        </div>
        <div>
            <strong><i class="fas fa-calendar-alt"></i> Período:</strong> <?php echo $nome_mes; ?>
        </div>
    </div>

    <!-- Resumo do Mês -->
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

    <!-- Tabela de Extrato -->
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
                    <tr>
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

<?php require_once '../../includes/footer.php'; ?>