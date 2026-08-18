<?php
// modules/relatorios/exportar_pdf.php - Exportar para PDF (via HTML para impressão) - VERSÃO COMPLETA CORRIGIDA
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

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$tipo = $_GET['tipo'] ?? 'pontos_funcionario';
$funcionario_id = $_GET['funcionario_id'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$mes = $_GET['mes'] ?? date('Y-m');
$ano = substr($mes, 0, 4);
$mes_num = substr($mes, 5, 2);
$periodo = $_GET['periodo'] ?? 'diario';
$data_referencia = $_GET['data_referencia'] ?? date('Y-m-d');
$semana = $_GET['semana'] ?? date('W');
$filial_id = $_GET['filial_id'] ?? '';

$stmt = $db->prepare("SELECT nome_empresa FROM empresa WHERE id = :id");
$stmt->execute([':id' => $empresa_id]);
$empresa = $stmt->fetch();
$empresa_nome = $empresa['nome_empresa'] ?? 'Empresa';

$nomes_meses = [
    '01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
    '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'
];

function formatarHorasPDF($horas, $com_sinal = false) { 
    if ($horas == 0) return ($com_sinal ? '+' : '') . '00:00';
    $sinal = $horas < 0 ? '-' : ($com_sinal ? '+' : '');
    $abs = abs($horas);
    $h = floor($abs);
    $m = round(($abs - $h) * 60);
    if ($m >= 60) { $h += 1; $m -= 60; }
    return $sinal . sprintf("%02d:%02d", $h, $m); 
}

function formatarMinutosPDF($minutos) {
    $horas = floor($minutos / 60);
    $resto = $minutos % 60;
    if ($horas > 0) {
        return $horas . 'h ' . $resto . 'min';
    }
    return $resto . ' minutos';
}

function calcularHorasDiaPDF($entrada, $saida_almoco, $volta_almoco, $saida, $extra_entrada = null, $extra_saida = null) {
    if (!$entrada || !$saida) return 0;
    $total = strtotime($saida) - strtotime($entrada);
    if ($saida_almoco && $volta_almoco) $total -= (strtotime($volta_almoco) - strtotime($saida_almoco));
    if ($extra_entrada && $extra_saida) $total += (strtotime($extra_saida) - strtotime($extra_entrada));
    return $total / 3600;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório - PontoFácil</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; background: white; }
        .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #667eea; }
        .header h1 { color: #667eea; font-size: 24px; margin-bottom: 5px; }
        .header .empresa { font-size: 14px; color: #666; margin-bottom: 5px; }
        .header .info { font-size: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; font-size: 11px; }
        th { background: #667eea; color: white; font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #999; padding-top: 10px; border-top: 1px solid #ddd; }
        .btn-print { position: fixed; bottom: 20px; right: 20px; background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; z-index: 1000; }
        @media print { .btn-print { display: none; } body { margin: 0; padding: 10px; } }
        .status-normal { color: #10b981; }
        .status-atraso { color: #f59e0b; }
        .status-falta { color: #ef4444; }
        .hora-normal { color: #667eea; }
        .hora-extra { color: #10b981; }
        .hora-compensar { color: #f59e0b; }
        .saldo-positivo { color: #10b981; font-weight: bold; }
        .saldo-negativo { color: #ef4444; font-weight: bold; }
        .positivo { color: #10b981; }
        .negativo { color: #ef4444; }
        .atraso-cell { color: #f59e0b; font-weight: bold; }
        .falta-cell { color: #ef4444; font-weight: bold; }
        h3 { margin-top: 20px; margin-bottom: 10px; }
        .error-message { color: #ef4444; text-align: center; padding: 40px; font-size: 14px; }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print();"><i class="fas fa-print"></i> Imprimir / Salvar PDF</button>
    
    <div class="header">
        <h1>PontoFácil - <?php echo htmlspecialchars($empresa_nome); ?></h1>
        <div class="empresa">Sistema de Ponto Eletrônico</div>
        <div class="info">Gerado em: <?php echo date('d/m/Y H:i:s'); ?></div>
    </div>

    <!-- ============================================ -->
    <!-- TIPO: EXTRATO FUNCIONÁRIO -->
    <!-- ============================================ -->
    <?php if ($tipo == 'extrato_funcionario'): ?>
        <?php
        $carga_diaria = 7 + (20/60); // 7:20h
        
        $stmt_func = $db->prepare("SELECT nome, matricula FROM funcionarios WHERE id = :id AND empresa_id = :empresa_id");
        $stmt_func->execute([':id' => $funcionario_id, ':empresa_id' => $empresa_id]);
        $func = $stmt_func->fetch();
        
        if (!$func):
        ?>
            <div class="error-message">ERRO: Funcionário não encontrado</div>
        <?php else: ?>
            <div class="info">Funcionário: <?php echo htmlspecialchars($func['nome']); ?> (Matrícula: <?php echo htmlspecialchars($func['matricula']); ?>)</div>
            <div class="info">Período: <?php echo date('d/m/Y', strtotime($data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($data_fim)); ?></div>
            <table>
                <thead>
                    <tr><th>Data</th><th>Dia</th><th>Entrada</th><th>Saída Almoço</th><th>Volta Almoço</th><th>Saída</th><th>Entrada Extra</th><th>Saída Extra</th><th>Horas</th><th>Saldo</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php
                $query = "SELECT DATE(p.data_hora) as data, DAYOFWEEK(p.data_hora) as dia,
                          MAX(CASE WHEN p.tipo='entrada' THEN TIME(p.data_hora) END) as entrada,
                          MAX(CASE WHEN p.tipo='saida_almoco' THEN TIME(p.data_hora) END) as saida_almoco,
                          MAX(CASE WHEN p.tipo='volta_almoco' THEN TIME(p.data_hora) END) as volta_almoco,
                          MAX(CASE WHEN p.tipo='saida' THEN TIME(p.data_hora) END) as saida,
                          MAX(CASE WHEN p.tipo='extra_entrada' THEN TIME(p.data_hora) END) as extra_entrada,
                          MAX(CASE WHEN p.tipo='extra_saida' THEN TIME(p.data_hora) END) as extra_saida
                          FROM pontos p WHERE p.funcionario_id = :id AND DATE(p.data_hora) BETWEEN :inicio AND :fim
                          GROUP BY DATE(p.data_hora) ORDER BY data ASC";
                $stmt = $db->prepare($query);
                $stmt->execute([':id' => $funcionario_id, ':inicio' => $data_inicio, ':fim' => $data_fim]);
                $dias_semana = ['','Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
                $rowCount = 0;
                while ($row = $stmt->fetch()):
                    $rowCount++;
                    $horas = calcularHorasDiaPDF($row['entrada'], $row['saida_almoco'], $row['volta_almoco'], $row['saida'], $row['extra_entrada'], $row['extra_saida']);
                    $saldo = $horas - $carga_diaria;
                    $saldo_class = $saldo >= 0 ? 'saldo-positivo' : 'saldo-negativo';
                    $saldo_texto = formatarHorasPDF($saldo, true);
                    $status = ($row['entrada'] && $row['entrada'] > '08:00:00') ? 'Atraso' : (($row['entrada'] && $row['saida']) ? 'Normal' : 'Incompleto');
                    if (!$row['entrada'] && !$row['saida']) $status = 'Falta';
                ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($row['data'])); ?></td>
                        <td><?php echo $dias_semana[$row['dia']]; ?></td>
                        <td><?php echo $row['entrada'] ? substr($row['entrada'],0,5) : '--:--'; ?></td>
                        <td><?php echo $row['saida_almoco'] ? substr($row['saida_almoco'],0,5) : '--:--'; ?></td>
                        <td><?php echo $row['volta_almoco'] ? substr($row['volta_almoco'],0,5) : '--:--'; ?></td>
                        <td><?php echo $row['saida'] ? substr($row['saida'],0,5) : '--:--'; ?></td>
                        <td><?php echo $row['extra_entrada'] ? substr($row['extra_entrada'],0,5) : '--:--'; ?></td>
                        <td><?php echo $row['extra_saida'] ? substr($row['extra_saida'],0,5) : '--:--'; ?></td>
                        <td><?php echo formatarHorasPDF($horas); ?></td>
                        <td class="<?php echo $saldo_class; ?>"><?php echo $saldo_texto; ?>h</td>
                        <td class="status-<?php echo strtolower($status); ?>"><?php echo $status; ?></td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($rowCount == 0): ?>
                    <tr><td colspan="11" style="text-align: center;">Nenhum registro encontrado no período</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <!-- ============================================ -->
    <!-- TIPO: EXTRATO GERAL -->
    <!-- ============================================ -->
    <?php elseif ($tipo == 'extrato_geral'): ?>
        <?php
        $dias_uteis = 0;
        $data_inicio_calc = new DateTime($ano . '-' . $mes_num . '-01');
        $data_fim_calc = new DateTime($ano . '-' . $mes_num . '-' . cal_days_in_month(CAL_GREGORIAN, $mes_num, $ano));
        $periodo_calc = new DatePeriod($data_inicio_calc, new DateInterval('P1D'), $data_fim_calc->modify('+1 day'));
        foreach ($periodo_calc as $data) { if ($data->format('N') <= 5) $dias_uteis++; }
        
        $query = "SELECT f.nome, f.matricula, COALESCE(fi.nome_fantasia, 'Matriz') as filial_nome, COALESCE(c.nome, 'Não definido') as cargo_nome,
                  COUNT(DISTINCT DATE(p.data_hora)) as dias_trab,
                  SUM(CASE WHEN p.tipo='entrada' AND TIME(p.data_hora)>'08:00:00' THEN 1 ELSE 0 END) as atrasos
                  FROM funcionarios f
                  LEFT JOIN filiais fi ON f.filial_id = fi.id
                  LEFT JOIN cargos c ON f.cargo_id = c.id
                  LEFT JOIN pontos p ON f.id = p.funcionario_id AND MONTH(p.data_hora)=:mes AND YEAR(p.data_hora)=:ano
                  WHERE f.empresa_id = :empresa_id AND f.status='ativo'";
        if ($filial_id) $query .= " AND f.filial_id = :filial_id";
        $query .= " GROUP BY f.id ORDER BY f.nome";
        $stmt = $db->prepare($query);
        $stmt->execute([':mes' => $mes_num, ':ano' => $ano, ':empresa_id' => $empresa_id]);
        ?>
        <div class="info">Período: <?php echo $nomes_meses[$mes_num] . '/' . $ano; ?> (<?php echo $dias_uteis; ?> dias úteis)</div>
        <table>
            <thead>
                <tr><th>Funcionário</th><th>Matrícula</th><th>Filial</th><th>Cargo</th><th>Dias Trabalhados</th><th>Faltas</th><th>Atrasos</th><th>Presença</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php 
            $rowCount = 0;
            while ($row = $stmt->fetch()):
                $rowCount++;
                $faltas = $dias_uteis - $row['dias_trab'];
                $presenca = $dias_uteis > 0 ? round((($dias_uteis - max(0, $faltas)) / $dias_uteis) * 100, 1) : 0;
                $status = ($faltas > 3) ? 'Crítico' : (($faltas > 1 || $row['atrasos'] > 5) ? 'Atenção' : 'Normal');
                $status_class = ($faltas > 3) ? 'negativo' : (($faltas > 1 || $row['atrasos'] > 5) ? 'status-atraso' : 'positivo');
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['nome']); ?></td>
                    <td><?php echo htmlspecialchars($row['matricula']); ?></td>
                    <td><?php echo htmlspecialchars($row['filial_nome']); ?></td>
                    <td><?php echo htmlspecialchars($row['cargo_nome']); ?></td>
                    <td><?php echo $row['dias_trab']; ?>/<?php echo $dias_uteis; ?></td>
                    <td class="negativo"><?php echo max(0, $faltas); ?></td>
                    <td class="status-atraso"><?php echo $row['atrasos']; ?></td>
                    <td><?php echo $presenca; ?>%</td>
                    <td class="<?php echo $status_class; ?>"><?php echo $status; ?></td>
                </tr>
            <?php endwhile; ?>
            <?php if ($rowCount == 0): ?>
                <tr><td colspan="9" style="text-align: center;">Nenhum funcionário encontrado</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

    <!-- ============================================ -->
    <!-- TIPO: PONTOS POR FUNCIONÁRIO -->
    <!-- ============================================ -->
    <?php elseif ($tipo == 'pontos_funcionario'): ?>
        <?php
        if (empty($funcionario_id)) {
            echo '<div class="error-message">ERRO: Nenhum funcionário selecionado</div>';
        } else {
            $stmt_func = $db->prepare("SELECT nome, matricula FROM funcionarios WHERE id = :id AND empresa_id = :empresa_id");
            $stmt_func->execute([':id' => $funcionario_id, ':empresa_id' => $empresa_id]);
            $func = $stmt_func->fetch();
            
            if (!$func) {
                echo '<div class="error-message">ERRO: Funcionário não encontrado</div>';
            } else {
        ?>
            <div class="info">Funcionário: <?php echo htmlspecialchars($func['nome']); ?> (Matrícula: <?php echo htmlspecialchars($func['matricula']); ?>)</div>
            <div class="info">Período: <?php echo ($nomes_meses[$mes_num] ?? $mes_num) . '/' . $ano; ?></div>
            <table>
                <thead>
                    <tr><th>Data</th><th>Entrada</th><th>Saída Almoço</th><th>Volta Almoço</th><th>Saída</th><th>Entrada Extra</th><th>Saída Extra</th><th>Horas</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php
                $query = "SELECT DATE(p.data_hora) as data,
                          MAX(CASE WHEN p.tipo='entrada' THEN TIME(p.data_hora) END) as entrada,
                          MAX(CASE WHEN p.tipo='saida_almoco' THEN TIME(p.data_hora) END) as saida_almoco,
                          MAX(CASE WHEN p.tipo='volta_almoco' THEN TIME(p.data_hora) END) as volta_almoco,
                          MAX(CASE WHEN p.tipo='saida' THEN TIME(p.data_hora) END) as saida,
                          MAX(CASE WHEN p.tipo='extra_entrada' THEN TIME(p.data_hora) END) as extra_entrada,
                          MAX(CASE WHEN p.tipo='extra_saida' THEN TIME(p.data_hora) END) as extra_saida
                          FROM pontos p 
                          WHERE p.funcionario_id = :id 
                            AND MONTH(p.data_hora) = :mes 
                            AND YEAR(p.data_hora) = :ano
                          GROUP BY DATE(p.data_hora) 
                          ORDER BY data ASC";
                $stmt = $db->prepare($query);
                $stmt->execute([':id' => $funcionario_id, ':mes' => $mes_num, ':ano' => $ano]);
                
                $rowCount = 0;
                while ($row = $stmt->fetch()):
                    $rowCount++;
                    $horas = calcularHorasDiaPDF($row['entrada'], $row['saida_almoco'], $row['volta_almoco'], $row['saida'], $row['extra_entrada'], $row['extra_saida']);
                    $status = ($row['entrada'] && $row['entrada'] > '08:00:00') ? 'Atraso' : (($row['entrada'] && $row['saida']) ? 'Normal' : 'Falta');
                ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($row['data'])); ?></td>
                        <td><?php echo $row['entrada'] ? substr($row['entrada'],0,5) : '--:--'; ?></td>
                        <td><?php echo $row['saida_almoco'] ? substr($row['saida_almoco'],0,5) : '--:--'; ?></td>
                        <td><?php echo $row['volta_almoco'] ? substr($row['volta_almoco'],0,5) : '--:--'; ?></td>
                        <td><?php echo $row['saida'] ? substr($row['saida'],0,5) : '--:--'; ?></td>
                        <td><?php echo $row['extra_entrada'] ? substr($row['extra_entrada'],0,5) : '--:--'; ?></td>
                        <td><?php echo $row['extra_saida'] ? substr($row['extra_saida'],0,5) : '--:--'; ?></td>
                        <td><?php echo formatarHorasPDF($horas); ?></td>
                        <td class="status-<?php echo strtolower($status); ?>"><?php echo $status; ?></td>
                    </tr>
                <?php 
                endwhile;
                if ($rowCount == 0):
                ?>
                    <tr><td colspan="9" style="text-align: center;">Nenhum registro encontrado no período</td></tr>
                <?php 
                endif;
                ?>
                </tbody>
            </table>
        <?php 
            }
        }
        ?>

    <!-- ============================================ -->
    <!-- TIPO: ATRASOS E FALTAS -->
    <!-- ============================================ -->
    <?php elseif ($tipo == 'atrasos_faltas'): ?>
        <?php
        $query = "SELECT f.nome, f.matricula, DATE(p.data_hora) as data, TIME(p.data_hora) as hora_entrada,
                  TIMESTAMPDIFF(MINUTE, '08:00:00', TIME(p.data_hora)) as minutos
                  FROM pontos p 
                  JOIN funcionarios f ON p.funcionario_id = f.id
                  WHERE f.empresa_id = :empresa_id 
                    AND p.tipo='entrada' 
                    AND TIME(p.data_hora)>'08:00:00'
                    AND MONTH(p.data_hora)=:mes 
                    AND YEAR(p.data_hora)=:ano";
        if ($funcionario_id) $query .= " AND f.id = :funcionario_id";
        $query .= " ORDER BY f.nome, p.data_hora ASC";
        $stmt = $db->prepare($query);
        $stmt->execute([':empresa_id' => $empresa_id, ':mes' => $mes_num, ':ano' => $ano]);
        $atrasos = $stmt->fetchAll();
        ?>
        <div class="info">Relatório de Atrasos - <?php echo $nomes_meses[$mes_num] . '/' . $ano; ?></div>
        <table>
            <thead>
                <tr><th>Funcionário</th><th>Matrícula</th><th>Data</th><th>Horário Entrada</th><th>Atraso</th></tr>
            </thead>
            <tbody>
            <?php foreach ($atrasos as $a): ?>
            <tr>
                <td><?php echo htmlspecialchars($a['nome']); ?></td>
                <td><?php echo htmlspecialchars($a['matricula']); ?></td>
                <td><?php echo date('d/m/Y', strtotime($a['data'])); ?></td>
                <td><?php echo substr($a['hora_entrada'], 0, 5); ?></td>
                <td class="atraso-cell"><?php echo formatarMinutosPDF($a['minutos']); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($atrasos)): ?>
                <tr><td colspan="5" style="text-align: center;">Nenhum atraso registrado</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

    <!-- ============================================ -->
    <!-- TIPO: BANCO DE HORAS -->
    <!-- ============================================ -->
    <?php elseif ($tipo == 'banco_horas'): ?>
        <?php
        $carga_diaria = 7 + (20/60); // 7:20h
        
        $query_func = "SELECT id, nome, matricula FROM funcionarios 
                       WHERE empresa_id = :empresa_id AND status = 'ativo' 
                       ORDER BY nome";
        $params_func = [':empresa_id' => $empresa_id];
        if ($funcionario_id) {
            $query_func .= " AND id = :funcionario_id";
            $params_func[':funcionario_id'] = $funcionario_id;
        }
        $stmt_func = $db->prepare($query_func);
        $stmt_func->execute($params_func);
        $funcionarios = $stmt_func->fetchAll();
        
        $resultados = [];
        $total_saldo = 0;
        
        foreach ($funcionarios as $func) {
            $stmt_pontos = $db->prepare("SELECT tipo, data_hora, DATE(data_hora) as data 
                                         FROM pontos 
                                         WHERE funcionario_id = :id 
                                         ORDER BY data_hora ASC");
            $stmt_pontos->execute([':id' => $func['id']]);
            $pontos = $stmt_pontos->fetchAll();
            
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
                    case 'entrada': $dias[$data]['entrada'] = $ponto['data_hora']; break;
                    case 'saida_almoco': $dias[$data]['saida_almoco'] = $ponto['data_hora']; break;
                    case 'volta_almoco': $dias[$data]['volta_almoco'] = $ponto['data_hora']; break;
                    case 'saida': $dias[$data]['saida'] = $ponto['data_hora']; break;
                }
            }
            
            $total_minutos = 0;
            $dias_trabalhados = 0;
            foreach ($dias as $dia) {
                if (!$dia['entrada'] || !$dia['saida']) continue;
                $minutos_dia = (strtotime($dia['saida']) - strtotime($dia['entrada'])) / 60;
                if ($dia['saida_almoco'] && $dia['volta_almoco']) {
                    $minutos_dia -= (strtotime($dia['volta_almoco']) - strtotime($dia['saida_almoco'])) / 60;
                }
                if ($minutos_dia > 0) {
                    $total_minutos += $minutos_dia;
                    $dias_trabalhados++;
                }
            }
            
            $horas_trab = $total_minutos / 60;
            $horas_esperadas = $dias_trabalhados * $carga_diaria;
            $saldo = $horas_trab - $horas_esperadas;
            $total_saldo += $saldo;
            
            $resultados[] = [
                'nome' => $func['nome'],
                'matricula' => $func['matricula'],
                'horas_trab' => $horas_trab,
                'horas_esp' => $horas_esperadas,
                'saldo' => $saldo
            ];
        }
        ?>
        <div class="info">Carga Horária Diária: <?php echo $carga_diaria; ?>h</div>
        <div class="info">Banco de Horas - <?php echo $funcionario_id ? 'Funcionário específico' : 'Todos os funcionários'; ?></div>
        <table>
            <thead>
                <tr><th>Funcionário</th><th>Matrícula</th><th>Horas Trabalhadas</th><th>Horas Esperadas</th><th>Saldo</th><th>Situação</th></tr>
            </thead>
            <tbody>
            <?php if (empty($resultados)): ?>
                <tr><td colspan="6" style="text-align: center;">Nenhum funcionário encontrado</td></tr>
            <?php else: ?>
                <?php foreach ($resultados as $r): 
                    $saldo_class = $r['saldo'] >= 0 ? 'saldo-positivo' : 'saldo-negativo';
                    $saldo_texto = formatarHorasPDF($r['saldo'], true);
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['nome']); ?></td>
                        <td><?php echo htmlspecialchars($r['matricula']); ?></td>
                        <td><?php echo formatarHorasPDF($r['horas_trab']); ?></td>
                        <td><?php echo formatarHorasPDF($r['horas_esp']); ?></td>
                        <td class="<?php echo $saldo_class; ?>"><?php echo $saldo_texto; ?></td>
                        <td><?php echo $r['saldo'] >= 0 ? 'Crédito' : 'Débito'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <?php if (!empty($resultados)): ?>
            <tfoot>
                <tr style="background:#f8f9fa;">
                    <td colspan="2"><strong>TOTAL GERAL:</strong></td>
                    <td><strong><?php echo formatarHorasPDF(array_sum(array_column($resultados, 'horas_trab'))); ?></strong></td>
                    <td><strong><?php echo formatarHorasPDF(array_sum(array_column($resultados, 'horas_esp'))); ?></strong></td>
                    <td class="<?php echo $total_saldo >= 0 ? 'saldo-positivo' : 'saldo-negativo'; ?>">
                        <strong><?php echo formatarHorasPDF($total_saldo, true); ?></strong>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>

    <!-- ============================================ -->
    <!-- TIPO: HORAS TRABALHADAS - CORRIGIDO -->
    <!-- ============================================ -->
    <?php elseif ($tipo == 'horas_trabalhadas'): ?>
        <?php
        $carga_diaria = 7 + (20/60); // 7:20h
        
        switch ($periodo) {
            case 'diario': 
                $data_inicio = $data_referencia; 
                $data_fim = $data_referencia; 
                break;
            case 'semanal': 
                $data_inicio = date('Y-m-d', strtotime($ano . '-W' . str_pad($semana,2,'0',STR_PAD_LEFT) . '-1'));
                $data_fim = date('Y-m-d', strtotime($ano . '-W' . str_pad($semana,2,'0',STR_PAD_LEFT) . '-7')); 
                break;
            default: 
                $data_inicio = date('Y-m-01', strtotime($ano . '-' . $mes . '-01'));
                $data_fim = date('Y-m-t', strtotime($ano . '-' . $mes . '-01'));
        }
        ?>
        <div class="info">Relatório de Horas Trabalhadas - <?php echo date('d/m/Y', strtotime($data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($data_fim)); ?></div>
        <div class="info">Carga Horária Diária: <?php echo $carga_diaria; ?>h</div>
        
        <?php
        $query = "SELECT f.nome, f.matricula, DATE(p.data_hora) as data,
                  MAX(CASE WHEN p.tipo='entrada' THEN TIME(p.data_hora) END) as entrada,
                  MAX(CASE WHEN p.tipo='saida' THEN TIME(p.data_hora) END) as saida,
                  MAX(CASE WHEN p.tipo='saida_almoco' THEN TIME(p.data_hora) END) as saida_almoco,
                  MAX(CASE WHEN p.tipo='volta_almoco' THEN TIME(p.data_hora) END) as volta_almoco
                  FROM funcionarios f 
                  LEFT JOIN pontos p ON f.id = p.funcionario_id AND DATE(p.data_hora) BETWEEN :inicio AND :fim
                  WHERE f.empresa_id = :empresa_id AND f.status='ativo'";
        
        if ($funcionario_id) {
            $query .= " AND f.id = :funcionario_id";
        }
        
        $query .= " GROUP BY f.id, DATE(p.data_hora) ORDER BY f.nome, data ASC";
        
        $stmt = $db->prepare($query);
        
        $params = [
            ':empresa_id' => $empresa_id,
            ':inicio' => $data_inicio,
            ':fim' => $data_fim
        ];
        
        if ($funcionario_id) {
            $params[':funcionario_id'] = $funcionario_id;
        }
        
        $stmt->execute($params);
        
        $total_n = $total_e = $total_c = 0;
        $rowCount = 0;
        ?>
        
        <table>
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Data</th>
                    <th>Entrada</th>
                    <th>Saída</th>
                    <th>Horas Normais</th>
                    <th>Horas Extras</th>
                    <th>Horas a Compensar</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $stmt->fetch()):
                if (!$row['data']) continue;
                $rowCount++;
                $horas = calcularHorasDiaPDF($row['entrada'], $row['saida_almoco'], $row['volta_almoco'], $row['saida']);
                $saldo = $horas - $carga_diaria;
                $extras = max(0, $saldo);
                $compensar = max(0, -$saldo);
                $status = ($row['entrada'] && $row['entrada'] > '08:00:00') ? 'Atraso' : 'Normal';
                if (!$row['entrada'] || !$row['saida']) $status = 'Incompleto';
                $total_n += $horas; $total_e += $extras; $total_c += $compensar;
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['nome']); ?> (<?php echo $row['matricula']; ?>)</td>
                    <td><?php echo date('d/m/Y', strtotime($row['data'])); ?></td>
                    <td><?php echo $row['entrada'] ? substr($row['entrada'],0,5) : '--:--'; ?></td>
                    <td><?php echo $row['saida'] ? substr($row['saida'],0,5) : '--:--'; ?></td>
                    <td class="hora-normal"><?php echo formatarHorasPDF($horas); ?></td>
                    <td class="hora-extra"><?php echo formatarHorasPDF($extras); ?></td>
                    <td class="hora-compensar"><?php echo formatarHorasPDF($compensar); ?></td>
                    <td><?php echo $status; ?></td>
                </tr>
            <?php endwhile; ?>
            <?php if ($rowCount == 0): ?>
                <tr><td colspan="8" style="text-align: center;">Nenhum registro encontrado no período</td></tr>
            <?php endif; ?>
            </tbody>
            <?php if ($rowCount > 0): ?>
            <tfoot>
                <tr style="background:#f8f9fa;">
                    <td colspan="4"><strong>TOTAIS GERAIS:</strong></td>
                    <td class="hora-normal"><strong><?php echo formatarHorasPDF($total_n); ?></strong></td>
                    <td class="hora-extra"><strong><?php echo formatarHorasPDF($total_e); ?></strong></td>
                    <td class="hora-compensar"><strong><?php echo formatarHorasPDF($total_c); ?></strong></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="4"><strong>SALDO FINAL</strong></td>
                    <td colspan="3">
                        <strong class="<?php echo ($total_e - $total_c) >= 0 ? 'saldo-positivo' : 'saldo-negativo'; ?>">
                            <?php echo formatarHorasPDF($total_e - $total_c, true); ?>
                        </strong>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
        
    <?php endif; ?>

    <div class="footer">
        <p>Relatório gerado automaticamente pelo sistema PontoFácil - Todos os direitos reservados</p>
        <p>Data de emissão: <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>
    
    <script>
        setTimeout(function() {
            window.print();
        }, 500);
    </script>
</body>
</html>