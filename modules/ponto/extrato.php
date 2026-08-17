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
<div class="pf-page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-list-alt me-2 text-primary"></i>Meu Extrato de Ponto</h1>
        <p class="text-muted">Histórico completo dos seus registros</p>
    </div>
</div>

<!-- Filtros -->
<div class="card pf-table-card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-sm-4 col-lg-3">
                <label class="form-label">Mês</label>
                <select name="mes" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo ($mes_atual == $m) ? 'selected' : ''; ?>>
                        <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-sm-4 col-lg-2">
                <label class="form-label">Ano</label>
                <select name="ano" class="form-select">
                    <?php for ($a = date('Y'); $a >= date('Y')-3; $a--): ?>
                    <option value="<?php echo $a; ?>" <?php echo ($ano_atual == $a) ? 'selected' : ''; ?>><?php echo $a; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card pf-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Horário</th>
                        <th class="d-none d-md-table-cell">Origem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pontos as $ponto): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($ponto['data_hora'])); ?></td>
                        <td>
                            <?php
                            $tipoMap = ['entrada'=>['Entrada','success'],'saida_almoco'=>['Saída Almoço','warning'],'volta_almoco'=>['Volta Almoço','info'],'saida'=>['Saída','danger']];
                            $t = $tipoMap[$ponto['tipo']] ?? [ucfirst($ponto['tipo']),'secondary'];
                            ?>
                            <span class="badge bg-<?php echo $t[1]; ?>"><?php echo $t[0]; ?></span>
                        </td>
                        <td class="fw-semibold"><?php echo date('H:i', strtotime($ponto['data_hora'])); ?></td>
                        <td class="d-none d-md-table-cell text-muted"><?php echo htmlspecialchars($ponto['origem'] ?? 'web'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pontos)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">
                        <i class="fas fa-clock fa-2x d-block mb-2 opacity-25"></i>Nenhum registro neste período
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
