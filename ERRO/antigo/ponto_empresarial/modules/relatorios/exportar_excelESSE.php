<?php
// modules/relatorios/exportar_excel.php - Exportar para Excel (CSV)
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
$mes = $_GET['mes'] ?? date('Y-m');
$ano = substr($mes, 0, 4);
$mes_num = substr($mes, 5, 2);

// Parâmetros para extrato_funcionario
$tipo_periodo = $_GET['tipo_periodo'] ?? 'personalizado';
$data_referencia = $_GET['data_referencia'] ?? date('Y-m-d');
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$filial_id = $_GET['filial_id'] ?? '';

// Nome do arquivo
$filename = 'relatorio_' . $tipo . '_' . date('Y-m-d_H-i-s');

// Configurar cabeçalhos para download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// ============================================
// FUNÇÃO AUXILIAR - Calcular horas
// ============================================
function calcularHorasExport($entrada, $saida_almoco, $volta_almoco, $saida) {
    if (!$entrada || !$saida) return '--:--';
    $entrada_ts = strtotime($entrada);
    $saida_ts = strtotime($saida);
    $total = $saida_ts - $entrada_ts;
    if ($saida_almoco && $volta_almoco) {
        $almoco_ts = strtotime($saida_almoco);
        $volta_ts = strtotime($volta_almoco);
        $total -= ($volta_ts - $almoco_ts);
    }
    if ($total <= 0) return '00:00';
    $horas = floor($total / 3600);
    $minutos = floor(($total % 3600) / 60);
    return sprintf("%02d:%02d", $horas, $minutos);
}

// ============================================
// TIPO: EXTRATO FUNCIONÁRIO
// ============================================
if ($tipo == 'extrato_funcionario') {
    // Definir datas
    if ($tipo_periodo == 'diario') {
        $data_inicio = $data_referencia;
        $data_fim = $data_referencia;
        $titulo_periodo = 'Dia ' . date('d/m/Y', strtotime($data_referencia));
    } elseif ($tipo_periodo == 'semanal') {
        $data_inicio = date('Y-m-d', strtotime('monday this week', strtotime($data_referencia)));
        $data_fim = date('Y-m-d', strtotime('sunday this week', strtotime($data_referencia)));
        $titulo_periodo = 'Semana de ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim));
    } elseif ($tipo_periodo == 'mensal') {
        $data_inicio = date('Y-m-01', strtotime($data_referencia));
        $data_fim = date('Y-m-t', strtotime($data_referencia));
        $titulo_periodo = strftime('%B de %Y', strtotime($data_referencia));
    } else {
        $titulo_periodo = 'Período de ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim));
    }
    
    // Buscar funcionário
    $stmt = $db->prepare("SELECT nome, matricula, cpf FROM funcionarios WHERE id = :id");
    $stmt->execute([':id' => $funcionario_id]);
    $funcionario = $stmt->fetch();
    
    if (!$funcionario) {
        fputcsv($output, ['Funcionário não encontrado'], ';');
        fclose($output);
        exit;
    }
    
    // Cabeçalhos
    fputcsv($output, ['EXTRATO DE PONTO - ' . strtoupper($funcionario['nome'])], ';');
    fputcsv($output, ['Período: ' . $titulo_periodo], ';');
    fputcsv($output, ['Funcionário: ' . $funcionario['nome'] . ' - Matrícula: ' . $funcionario['matricula']], ';');
    fputcsv($output, ['Gerado em: ' . date('d/m/Y H:i:s')], ';');
    fputcsv($output, [], ';');
    fputcsv($output, [
        'Data', 'Dia da Semana', 'Entrada', 'Saída Almoço', 'Volta Almoço', 
        'Saída', 'Horas Trabalhadas', 'Saldo (h)', 'Status'
    ], ';');
    
    // Buscar pontos
    $query = "SELECT 
                DATE(p.data_hora) as data,
                DAYOFWEEK(p.data_hora) as dia_semana_num,
                MAX(CASE WHEN p.tipo = 'entrada' THEN TIME(p.data_hora) END) as entrada,
                MAX(CASE WHEN p.tipo = 'saida_almoco' THEN TIME(p.data_hora) END) as saida_almoco,
                MAX(CASE WHEN p.tipo = 'volta_almoco' THEN TIME(p.data_hora) END) as volta_almoco,
                MAX(CASE WHEN p.tipo = 'saida' THEN TIME(p.data_hora) END) as saida
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
    
    $dias_semana = ['', 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    $carga_diaria = 8;
    $total_horas = 0;
    
    // Criar array com todos os dias do período
    $periodo = new DatePeriod(new DateTime($data_inicio), new DateInterval('P1D'), (new DateTime($data_fim))->modify('+1 day'));
    $dias_processados = [];
    
    foreach ($periodo as $data) {
        $data_str = $data->format('Y-m-d');
        $encontrado = false;
        foreach ($pontos as $p) {
            if ($p['data'] == $data_str) {
                $horas = calcularHorasExport($p['entrada'], $p['saida_almoco'], $p['volta_almoco'], $p['saida']);
                $saldo = $horas != '--:--' ? round((strtotime($horas . ':00') - strtotime('00:00:00')) / 3600 - $carga_diaria, 2) : 0;
                $status = ($p['entrada'] && $p['entrada'] > '08:00:00') ? 'Atraso' : (($p['entrada'] && $p['saida']) ? 'Normal' : 'Incompleto');
                if (!$p['entrada'] && !$p['saida']) $status = 'Falta';
                
                fputcsv($output, [
                    $data->format('d/m/Y'),
                    $dias_semana[$p['dia_semana_num']],
                    $p['entrada'] ? substr($p['entrada'], 0, 5) : '--:--',
                    $p['saida_almoco'] ? substr($p['saida_almoco'], 0, 5) : '--:--',
                    $p['volta_almoco'] ? substr($p['volta_almoco'], 0, 5) : '--:--',
                    $p['saida'] ? substr($p['saida'], 0, 5) : '--:--',
                    $horas,
                    ($saldo >= 0 ? '+' : '') . number_format($saldo, 2, ',', '.'),
                    $status
                ], ';');
                $encontrado = true;
                break;
            }
        }
        if (!$encontrado) {
            $dia_semana = $data->format('N');
            if ($dia_semana >= 1 && $dia_semana <= 5) {
                fputcsv($output, [
                    $data->format('d/m/Y'),
                    $dias_semana[$dia_semana],
                    '--:--', '--:--', '--:--', '--:--', '00:00',
                    '-' . $carga_diaria,
                    'Falta'
                ], ';');
            }
        }
    }
}

// ============================================
// TIPO: EXTRATO COLETIVO
// ============================================
elseif ($tipo == 'extrato_coletivo') {
    $tipo_periodo = $_GET['periodo'] ?? 'mensal';
    $data_referencia = $_GET['data_referencia'] ?? date('Y-m-d');
    $filial_id = $_GET['filial_id'] ?? '';
    
    if ($tipo_periodo == 'semanal') {
        $data_inicio = date('Y-m-d', strtotime('monday this week', strtotime($data_referencia)));
        $data_fim = date('Y-m-d', strtotime('sunday this week', strtotime($data_referencia)));
        $titulo_periodo = 'Semana de ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim));
    } else {
        $data_inicio = date('Y-m-01', strtotime($data_referencia));
        $data_fim = date('Y-m-t', strtotime($data_referencia));
        $titulo_periodo = strftime('%B de %Y', strtotime($data_referencia));
    }
    
    // Calcular dias úteis
    $dias_uteis = 0;
    $data_atual = new DateTime($data_inicio);
    $data_fim_obj = new DateTime($data_fim);
    while ($data_atual <= $data_fim_obj) {
        if ($data_atual->format('N') <= 5) $dias_uteis++;
        $data_atual->modify('+1 day');
    }
    
    fputcsv($output, ['EXTRATO COLETIVO - ' . strtoupper($titulo_periodo)], ';');
    fputcsv($output, ['Gerado em: ' . date('d/m/Y H:i:s')], ';');
    fputcsv($output, [], ';');
    fputcsv($output, [
        'Funcionário', 'Matrícula', 'Filial', 'Cargo', 'Dias Trabalhados', 
        'Faltas', 'Atrasos', 'Presença (%)', 'Situação'
    ], ';');
    
    $query = "SELECT 
                f.id, f.nome, f.matricula, fi.nome_fantasia as filial_nome, c.nome as cargo_nome,
                COUNT(DISTINCT DATE(p.data_hora)) as dias_trabalhados,
                SUM(CASE WHEN p.tipo = 'entrada' AND TIME(p.data_hora) > '08:00:00' THEN 1 ELSE 0 END) as total_atrasos
              FROM funcionarios f
              LEFT JOIN filiais fi ON f.filial_id = fi.id
              LEFT JOIN cargos c ON f.cargo_id = c.id
              LEFT JOIN pontos p ON f.id = p.funcionario_id 
                AND DATE(p.data_hora) BETWEEN :data_inicio AND :data_fim
              WHERE f.empresa_id = :empresa_id AND f.status = 'ativo'";
    
    $params = [':data_inicio' => $data_inicio, ':data_fim' => $data_fim, ':empresa_id' => $empresa_id];
    if ($filial_id) {
        $query .= " AND f.filial_id = :filial_id";
        $params[':filial_id'] = $filial_id;
    }
    $query .= " GROUP BY f.id ORDER BY f.nome";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $funcionarios = $stmt->fetchAll();
    
    foreach ($funcionarios as $func) {
        $faltas = $dias_uteis - $func['dias_trabalhados'];
        $presenca = $dias_uteis > 0 ? round((($dias_uteis - $faltas) / $dias_uteis) * 100, 1) : 0;
        $situacao = ($faltas > 3) ? 'Crítico' : (($faltas > 1 || $func['total_atrasos'] > 5) ? 'Atenção' : 'Normal');
        
        fputcsv($output, [
            $func['nome'], $func['matricula'], $func['filial_nome'] ?? 'Matriz',
            $func['cargo_nome'] ?? 'Não definido', $func['dias_trabalhados'],
            $faltas, $func['total_atrasos'], $presenca . '%', $situacao
        ], ';');
    }
}

// ============================================
// TIPO: PONTOS POR FUNCIONÁRIO
// ============================================
elseif ($tipo == 'pontos_funcionario') {
    $funcionario_id = $_GET['funcionario_id'] ?? 0;
    $mes = $_GET['mes'] ?? date('Y-m');
    
    $stmt = $db->prepare("SELECT nome, matricula FROM funcionarios WHERE id = :id");
    $stmt->execute([':id' => $funcionario_id]);
    $funcionario = $stmt->fetch();
    
    fputcsv($output, ['RELATÓRIO DE PONTOS - ' . strtoupper($funcionario['nome'])], ';');
    fputcsv($output, ['Mês/Ano: ' . strftime('%B de %Y', strtotime($mes . '-01'))], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Data', 'Entrada', 'Saída Almoço', 'Volta Almoço', 'Saída', 'Horas', 'Status'], ';');
    
    $query = "SELECT 
                DATE(p.data_hora) as data,
                MAX(CASE WHEN p.tipo = 'entrada' THEN TIME(p.data_hora) END) as entrada,
                MAX(CASE WHEN p.tipo = 'saida_almoco' THEN TIME(p.data_hora) END) as saida_almoco,
                MAX(CASE WHEN p.tipo = 'volta_almoco' THEN TIME(p.data_hora) END) as volta_almoco,
                MAX(CASE WHEN p.tipo = 'saida' THEN TIME(p.data_hora) END) as saida
              FROM pontos p
              WHERE p.funcionario_id = :id AND MONTH(p.data_hora) = :mes AND YEAR(p.data_hora) = :ano
              GROUP BY DATE(p.data_hora) ORDER BY data ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $funcionario_id, ':mes' => substr($mes, 5, 2), ':ano' => substr($mes, 0, 4)]);
    $registros = $stmt->fetchAll();
    
    foreach ($registros as $reg) {
        $horas = calcularHorasExport($reg['entrada'], $reg['saida_almoco'], $reg['volta_almoco'], $reg['saida']);
        $status = ($reg['entrada'] && $reg['entrada'] > '08:00:00') ? 'Atraso' : (($reg['entrada'] && $reg['saida']) ? 'Normal' : 'Incompleto');
        
        fputcsv($output, [
            date('d/m/Y', strtotime($reg['data'])),
            $reg['entrada'] ? substr($reg['entrada'], 0, 5) : '--:--',
            $reg['saida_almoco'] ? substr($reg['saida_almoco'], 0, 5) : '--:--',
            $reg['volta_almoco'] ? substr($reg['volta_almoco'], 0, 5) : '--:--',
            $reg['saida'] ? substr($reg['saida'], 0, 5) : '--:--',
            $horas, $status
        ], ';');
    }
}

// ============================================
// TIPO: ATRASOS E FALTAS
// ============================================
elseif ($tipo == 'atrasos_faltas') {
    $mes = $_GET['mes'] ?? date('Y-m');
    $ano = substr($mes, 0, 4);
    $mes_num = substr($mes, 5, 2);
    
    fputcsv($output, ['RELATÓRIO DE ATRASOS E FALTAS - ' . strftime('%B de %Y', strtotime($mes . '-01'))], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Funcionário', 'Matrícula', 'Data', 'Horário Entrada', 'Situação'], ';');
    
    $query = "SELECT f.nome, f.matricula, DATE(p.data_hora) as data, TIME(p.data_hora) as hora_entrada
              FROM pontos p JOIN funcionarios f ON p.funcionario_id = f.id
              WHERE p.tipo = 'entrada' AND MONTH(p.data_hora) = :mes AND YEAR(p.data_hora) = :ano
              AND TIME(p.data_hora) > '08:00:00'
              ORDER BY f.nome, data ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':mes' => $mes_num, ':ano' => $ano]);
    $atrasos = $stmt->fetchAll();
    
    foreach ($atrasos as $a) {
        $atraso_minutos = (strtotime($a['hora_entrada']) - strtotime('08:00:00')) / 60;
        fputcsv($output, [$a['nome'], $a['matricula'], date('d/m/Y', strtotime($a['data'])), substr($a['hora_entrada'], 0, 5), $atraso_minutos . ' min de atraso'], ';');
    }
}

fclose($output);
exit;
?>