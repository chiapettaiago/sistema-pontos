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
// FUNÇÃO PARA CALCULAR ATRASO (CORRIGIDA)
// ============================================
function calcularMinutosAtrasoExcel($hora_registrada) {
    $hora_limite = '08:00:00';
    if ($hora_registrada <= $hora_limite) {
        return 0;
    }
    $registro_ts = strtotime($hora_registrada);
    $limite_ts = strtotime($hora_limite);
    $diferenca_segundos = $registro_ts - $limite_ts;
    return floor($diferenca_segundos / 60);
}

// ============================================
// TIPO: EXTRATO FUNCIONÁRIO
// ============================================
if ($tipo == 'extrato_funcionario') {
    // ... (código já existente - manter)
}

// ============================================
// TIPO: EXTRATO COLETIVO
// ============================================
elseif ($tipo == 'extrato_coletivo') {
    // ... (código já existente - manter)
}

// ============================================
// TIPO: PONTOS POR FUNCIONÁRIO
// ============================================
elseif ($tipo == 'pontos_funcionario') {
    // ... (código já existente - manter)
}

// ============================================
// TIPO: ATRASOS E FALTAS (CORRIGIDO)
// ============================================
elseif ($tipo == 'atrasos_faltas') {
    $funcionario_id = $_GET['funcionario_id'] ?? '';
    $mes = $_GET['mes'] ?? date('Y-m');
    $ano = substr($mes, 0, 4);
    $mes_num = substr($mes, 5, 2);
    
    // Cabeçalhos
    fputcsv($output, ['RELATÓRIO DE ATRASOS E FALTAS'], ';');
    fputcsv($output, ['Período: ' . strftime('%B de %Y', strtotime($mes . '-01'))], ';');
    fputcsv($output, ['Gerado em: ' . date('d/m/Y H:i:s')], ';');
    fputcsv($output, [], ';');
    fputcsv($output, ['Funcionário', 'Matrícula', 'Data', 'Horário Entrada', 'Minutos de Atraso', 'Horas de Atraso'], ';');
    
    // Buscar atrasos
    $query = "SELECT f.nome, f.matricula, DATE(p.data_hora) as data, TIME(p.data_hora) as hora_entrada
              FROM pontos p 
              JOIN funcionarios f ON p.funcionario_id = f.id
              WHERE p.empresa_id = :empresa_id 
                AND p.tipo = 'entrada'
                AND MONTH(p.data_hora) = :mes 
                AND YEAR(p.data_hora) = :ano
                AND TIME(p.data_hora) > '08:00:00'
                AND f.status = 'ativo'";
    
    $params = [
        ':empresa_id' => $empresa_id,
        ':mes' => $mes_num,
        ':ano' => $ano
    ];
    
    if ($funcionario_id) {
        $query .= " AND f.id = :funcionario_id";
        $params[':funcionario_id'] = $funcionario_id;
    }
    
    $query .= " ORDER BY f.nome, p.data_hora ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch()) {
        $minutos = calcularMinutosAtrasoExcel($row['hora_entrada']);
        $horas = floor($minutos / 60);
        $resto_minutos = $minutos % 60;
        $horas_texto = $horas > 0 ? $horas . 'h ' . $resto_minutos . 'min' : $minutos . ' minutos';
        
        fputcsv($output, [
            $row['nome'],
            $row['matricula'],
            date('d/m/Y', strtotime($row['data'])),
            substr($row['hora_entrada'], 0, 5),
            $minutos,
            $horas_texto
        ], ';');
    }
}

// ============================================
// TIPO: HORAS TRABALHADAS
// ============================================
elseif ($tipo == 'horas_trabalhadas') {
    // ... (código já existente - manter)
}

// ============================================
// TIPO: EXTRATO GERAL
// ============================================
elseif ($tipo == 'extrato_geral') {
    // ... (código já existente - manter)
}

fclose($output);
exit;
?>