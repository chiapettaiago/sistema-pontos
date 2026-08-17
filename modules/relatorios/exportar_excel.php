<?php
// modules/relatorios/exportar_excel.php - Exportar para Excel (CSV) - VERSÃO COMPLETA CORRIGIDA
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

$filename = 'relatorio_' . $tipo . '_' . date('Y-m-d_H-i-s');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Array de meses
$nomes_meses = [
    '01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
    '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'
];

function formatarHorasExcel($horas, $com_sinal = false) {
    if ($horas == 0) return ($com_sinal ? '+' : '') . '00:00';
    $sinal = $horas < 0 ? '-' : ($com_sinal ? '+' : '');
    $abs = abs($horas);
    $h = floor($abs);
    $m = round(($abs - $h) * 60);
    if ($m >= 60) { $h += 1; $m -= 60; }
    return $sinal . sprintf("%02d:%02d", $h, $m);
}

function formatarMinutosExcel($minutos) {
    $horas = floor($minutos / 60);
    $resto = $minutos % 60;
    if ($horas > 0) {
        return $horas . 'h ' . $resto . 'min';
    }
    return $resto . ' minutos';
}

function calcularHorasDiaExcel($entrada, $saida_almoco, $volta_almoco, $saida) {
    if (!$entrada || !$saida) return 0;
    $entrada_ts = strtotime($entrada);
    $saida_ts = strtotime($saida);
    $total = $saida_ts - $entrada_ts;
    if ($saida_almoco && $volta_almoco) $total -= (strtotime($volta_almoco) - strtotime($saida_almoco));
    return $total / 3600;
}

// ============================================
// TIPO: EXTRATO FUNCIONÁRIO
// ============================================
if ($tipo == 'extrato_funcionario') {
    $carga_diaria = 7 + (20/60); // 7:20h
    
    $stmt_func = $db->prepare("SELECT nome, matricula FROM funcionarios WHERE id = :id AND empresa_id = :empresa_id");
    $stmt_func->execute([':id' => $funcionario_id, ':empresa_id' => $empresa_id]);
    $func = $stmt_func->fetch();
    
    if (!$func) {
        fputcsv($output, ['ERRO: Funcionário não encontrado'], ';');
        fclose($output);
        exit;
    }
    
    fputcsv($output, ['EXTRATO DE PONTO - ' . strtoupper($func['nome'])], ';');
    fputcsv($output, ['Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim))], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Data', 'Dia da Semana', 'Entrada', 'Saída Almoço', 'Volta Almoço', 'Saída', 'Horas', 'Saldo', 'Status'], ';');
    
    $query = "SELECT DATE(p.data_hora) as data, DAYOFWEEK(p.data_hora) as dia_semana,
              MAX(CASE WHEN p.tipo='entrada' THEN TIME(p.data_hora) END) as entrada,
              MAX(CASE WHEN p.tipo='saida_almoco' THEN TIME(p.data_hora) END) as saida_almoco,
              MAX(CASE WHEN p.tipo='volta_almoco' THEN TIME(p.data_hora) END) as volta_almoco,
              MAX(CASE WHEN p.tipo='saida' THEN TIME(p.data_hora) END) as saida
              FROM pontos p WHERE p.funcionario_id = :id AND DATE(p.data_hora) BETWEEN :inicio AND :fim
              GROUP BY DATE(p.data_hora) ORDER BY data ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $funcionario_id, ':inicio' => $data_inicio, ':fim' => $data_fim]);
    $dias_semana = ['', 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    $rowCount = 0;
    while ($row = $stmt->fetch()) {
        $rowCount++;
        $horas = calcularHorasDiaExcel($row['entrada'], $row['saida_almoco'], $row['volta_almoco'], $row['saida']);
        $saldo = $horas - $carga_diaria;
        $status = ($row['entrada'] && $row['entrada'] > '08:00:00') ? 'Atraso' : (($row['entrada'] && $row['saida']) ? 'Normal' : 'Incompleto');
        if (!$row['entrada'] && !$row['saida']) $status = 'Falta';
        $saldo_texto = formatarHorasExcel($saldo, true);
        fputcsv($output, [
            date('d/m/Y', strtotime($row['data'])), $dias_semana[$row['dia_semana']],
            $row['entrada'] ? substr($row['entrada'],0,5) : '--:--',
            $row['saida_almoco'] ? substr($row['saida_almoco'],0,5) : '--:--',
            $row['volta_almoco'] ? substr($row['volta_almoco'],0,5) : '--:--',
            $row['saida'] ? substr($row['saida'],0,5) : '--:--',
            formatarHorasExcel($horas), $saldo_texto, $status
        ], ';');
    }
    if ($rowCount == 0) {
        fputcsv($output, ['Nenhum registro encontrado no período'], ';');
    }
}

// ============================================
// TIPO: EXTRATO GERAL
// ============================================
elseif ($tipo == 'extrato_geral') {
    $dias_uteis = 0;
    $data_inicio_calc = new DateTime($ano . '-' . $mes_num . '-01');
    $data_fim_calc = new DateTime($ano . '-' . $mes_num . '-' . cal_days_in_month(CAL_GREGORIAN, $mes_num, $ano));
    $periodo_calc = new DatePeriod($data_inicio_calc, new DateInterval('P1D'), $data_fim_calc->modify('+1 day'));
    foreach ($periodo_calc as $data) { if ($data->format('N') <= 5) $dias_uteis++; }
    
    fputcsv($output, ['EXTRATO GERAL DA EMPRESA - ' . $nomes_meses[$mes_num] . ' de ' . $ano], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Funcionário', 'Matrícula', 'Filial', 'Cargo', 'Dias Trabalhados', 'Faltas', 'Atrasos', 'Presença', 'Situação'], ';');
    
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
    
    $rowCount = 0;
    while ($row = $stmt->fetch()) {
        $rowCount++;
        $faltas = $dias_uteis - $row['dias_trab'];
        $presenca = $dias_uteis > 0 ? round((($dias_uteis - max(0, $faltas)) / $dias_uteis) * 100, 1) : 0;
        $situacao = ($faltas > 3) ? 'Crítico' : (($faltas > 1 || $row['atrasos'] > 5) ? 'Atenção' : 'Normal');
        fputcsv($output, [
            $row['nome'], $row['matricula'], $row['filial_nome'], $row['cargo_nome'],
            $row['dias_trab'], max(0, $faltas), $row['atrasos'], $presenca . '%', $situacao
        ], ';');
    }
    if ($rowCount == 0) {
        fputcsv($output, ['Nenhum funcionário encontrado'], ';');
    }
}

// ============================================
// TIPO: PONTOS POR FUNCIONÁRIO
// ============================================
elseif ($tipo == 'pontos_funcionario') {
    if (empty($funcionario_id)) {
        fputcsv($output, ['ERRO: Nenhum funcionário selecionado'], ';');
        fclose($output);
        exit;
    }
    
    $stmt_func = $db->prepare("SELECT nome, matricula FROM funcionarios WHERE id = :id AND empresa_id = :empresa_id");
    $stmt_func->execute([':id' => $funcionario_id, ':empresa_id' => $empresa_id]);
    $func = $stmt_func->fetch();
    
    if (!$func) {
        fputcsv($output, ['ERRO: Funcionário não encontrado'], ';');
        fclose($output);
        exit;
    }
    
    fputcsv($output, ['PONTOS POR FUNCIONÁRIO - ' . strtoupper($func['nome'])], ';');
    fputcsv($output, ['Período: ' . ($nomes_meses[$mes_num] ?? $mes_num) . ' de ' . $ano], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Data', 'Entrada', 'Saída Almoço', 'Volta Almoço', 'Saída', 'Horas', 'Status'], ';');
    
    $query = "SELECT DATE(p.data_hora) as data,
              MAX(CASE WHEN p.tipo='entrada' THEN TIME(p.data_hora) END) as entrada,
              MAX(CASE WHEN p.tipo='saida_almoco' THEN TIME(p.data_hora) END) as saida_almoco,
              MAX(CASE WHEN p.tipo='volta_almoco' THEN TIME(p.data_hora) END) as volta_almoco,
              MAX(CASE WHEN p.tipo='saida' THEN TIME(p.data_hora) END) as saida
              FROM pontos p 
              WHERE p.funcionario_id = :id 
                AND MONTH(p.data_hora) = :mes 
                AND YEAR(p.data_hora) = :ano
              GROUP BY DATE(p.data_hora) 
              ORDER BY data ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $funcionario_id, ':mes' => $mes_num, ':ano' => $ano]);
    
    $rowCount = 0;
    while ($row = $stmt->fetch()) {
        $rowCount++;
        $horas = calcularHorasDiaExcel($row['entrada'], $row['saida_almoco'], $row['volta_almoco'], $row['saida']);
        $status = ($row['entrada'] && $row['entrada'] > '08:00:00') ? 'Atraso' : (($row['entrada'] && $row['saida']) ? 'Normal' : 'Falta');
        fputcsv($output, [
            date('d/m/Y', strtotime($row['data'])),
            $row['entrada'] ? substr($row['entrada'],0,5) : '--:--',
            $row['saida_almoco'] ? substr($row['saida_almoco'],0,5) : '--:--',
            $row['volta_almoco'] ? substr($row['volta_almoco'],0,5) : '--:--',
            $row['saida'] ? substr($row['saida'],0,5) : '--:--',
            formatarHorasExcel($horas), $status
        ], ';');
    }
    
    if ($rowCount == 0) {
        fputcsv($output, ['Nenhum registro encontrado no período'], ';');
    }
}

// ============================================
// TIPO: ATRASOS E FALTAS
// ============================================
elseif ($tipo == 'atrasos_faltas') {
    fputcsv($output, ['RELATÓRIO DE ATRASOS E FALTAS - ' . $nomes_meses[$mes_num] . ' de ' . $ano], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Funcionário', 'Matrícula', 'Data', 'Horário Entrada', 'Minutos de Atraso'], ';');
    
    $query = "SELECT f.nome, f.matricula, DATE(p.data_hora) as data, TIME(p.data_hora) as hora_entrada,
              TIMESTAMPDIFF(MINUTE, '08:00:00', TIME(p.data_hora)) as minutos
              FROM pontos p 
              JOIN funcionarios f ON p.funcionario_id = f.id
              WHERE f.empresa_id = :empresa_id 
                AND p.tipo = 'entrada' 
                AND TIME(p.data_hora) > '08:00:00'
                AND MONTH(p.data_hora) = :mes 
                AND YEAR(p.data_hora) = :ano";
    
    $params = [':empresa_id' => $empresa_id, ':mes' => $mes_num, ':ano' => $ano];
    if ($funcionario_id) {
        $query .= " AND f.id = :funcionario_id";
        $params[':funcionario_id'] = $funcionario_id;
    }
    $query .= " ORDER BY f.nome, p.data_hora ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $rowCount = 0;
    while ($row = $stmt->fetch()) {
        $rowCount++;
        $texto = formatarMinutosExcel($row['minutos']);
        fputcsv($output, [
            $row['nome'], $row['matricula'], 
            date('d/m/Y', strtotime($row['data'])), 
            substr($row['hora_entrada'], 0, 5), 
            $texto
        ], ';');
    }
    
    if ($rowCount == 0) {
        fputcsv($output, ['Nenhum atraso registrado no período'], ';');
    }
}

// ============================================
// TIPO: BANCO DE HORAS
// ============================================
elseif ($tipo == 'banco_horas') {
    $carga_diaria = 7 + (20/60); // 7:20h
    
    fputcsv($output, ['RELATÓRIO DE BANCO DE HORAS'], ';');
    fputcsv($output, ['Carga Horária Diária: ' . $carga_diaria . 'h'], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Funcionário', 'Matrícula', 'Horas Trabalhadas', 'Horas Esperadas', 'Saldo', 'Situação'], ';');
    
    // Buscar funcionários
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
    
    $total_saldo = 0;
    $rowCount = 0;
    
    foreach ($funcionarios as $func) {
        // Buscar pontos do funcionário
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
        
        // Calcular horas trabalhadas
        $total_minutos = 0;
        $dias_trabalhados = 0;
        
        foreach ($dias as $dia) {
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
        $total_saldo += $saldo;
        
        $horas_trab_formatado = sprintf("%02d:%02d", floor($horas_trab), round(($horas_trab - floor($horas_trab)) * 60));
        $horas_esp_formatado = sprintf("%02d:%02d", floor($horas_esperadas), round(($horas_esperadas - floor($horas_esperadas)) * 60));
        $saldo_formatado = ($saldo >= 0 ? '+' : '') . sprintf("%02d:%02d", floor(abs($saldo)), round((abs($saldo) - floor(abs($saldo))) * 60));
        
        fputcsv($output, [
            $func['nome'],
            $func['matricula'],
            $horas_trab_formatado,
            $horas_esp_formatado,
            $saldo_formatado,
            $saldo >= 0 ? 'Crédito' : 'Débito'
        ], ';');
        $rowCount++;
    }
    
    if ($rowCount == 0) {
        fputcsv($output, ['Nenhum funcionário encontrado'], ';');
    } else {
        fputcsv($output, [], ';');
        $total_saldo_formatado = ($total_saldo >= 0 ? '+' : '') . sprintf("%02d:%02d", floor(abs($total_saldo)), round((abs($total_saldo) - floor(abs($total_saldo))) * 60));
        fputcsv($output, ['TOTAL GERAL', '', '', '', $total_saldo_formatado, ''], ';');
    }
}

// ============================================
// TIPO: HORAS TRABALHADAS
// ============================================
elseif ($tipo == 'horas_trabalhadas') {
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
    
    fputcsv($output, ['RELATÓRIO DE HORAS TRABALHADAS'], ';');
    fputcsv($output, ['Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim))], ';');
    fputcsv($output, ['Carga Horária Diária: ' . $carga_diaria . 'h'], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Funcionário', 'Matrícula', 'Data', 'Entrada', 'Saída', 'Horas Normais', 'Horas Extras', 'Horas a Compensar', 'Status'], ';');
    
    $query = "SELECT f.id, f.nome, f.matricula, DATE(p.data_hora) as data,
              MAX(CASE WHEN p.tipo='entrada' THEN TIME(p.data_hora) END) as entrada,
              MAX(CASE WHEN p.tipo='saida' THEN TIME(p.data_hora) END) as saida,
              MAX(CASE WHEN p.tipo='saida_almoco' THEN TIME(p.data_hora) END) as saida_almoco,
              MAX(CASE WHEN p.tipo='volta_almoco' THEN TIME(p.data_hora) END) as volta_almoco
              FROM funcionarios f 
              LEFT JOIN pontos p ON f.id = p.funcionario_id AND DATE(p.data_hora) BETWEEN :inicio AND :fim
              WHERE f.empresa_id = :empresa_id AND f.status='ativo'";
    if ($funcionario_id) $query .= " AND f.id = :funcionario_id";
    $query .= " GROUP BY f.id, DATE(p.data_hora) ORDER BY f.nome, data ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':empresa_id' => $empresa_id, ':inicio' => $data_inicio, ':fim' => $data_fim]);
    
    $total_n = $total_e = $total_c = 0;
    $rowCount = 0;
    while ($row = $stmt->fetch()) {
        if (!$row['data']) continue;
        $rowCount++;
        $horas = calcularHorasDiaExcel($row['entrada'], $row['saida_almoco'], $row['volta_almoco'], $row['saida']);
        $saldo = $horas - $carga_diaria;
        $extras = max(0, $saldo);
        $compensar = max(0, -$saldo);
        $status = ($row['entrada'] && $row['entrada'] > '08:00:00') ? 'Atraso' : 'Normal';
        if (!$row['entrada'] || !$row['saida']) $status = 'Incompleto';
        
        fputcsv($output, [
            $row['nome'], $row['matricula'], date('d/m/Y', strtotime($row['data'])),
            $row['entrada'] ? substr($row['entrada'],0,5) : '--:--',
            $row['saida'] ? substr($row['saida'],0,5) : '--:--',
            formatarHorasExcel($horas), formatarHorasExcel($extras), formatarHorasExcel($compensar), $status
        ], ';');
        $total_n += $horas; $total_e += $extras; $total_c += $compensar;
    }
    
    if ($rowCount == 0) {
        fputcsv($output, ['Nenhum registro encontrado no período'], ';');
    } else {
        fputcsv($output, [], ';');
        fputcsv($output, ['TOTAIS GERAIS', '', '', '', '', formatarHorasExcel($total_n), formatarHorasExcel($total_e), formatarHorasExcel($total_c), ''], ';');
        $saldo_final = $total_e - $total_c;
        $saldo_texto = formatarHorasExcel($saldo_final, true);
        fputcsv($output, ['SALDO FINAL', '', '', '', '', '', '', $saldo_texto, ''], ';');
    }
}

fclose($output);
exit;
?>