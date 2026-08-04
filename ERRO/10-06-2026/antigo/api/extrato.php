<?php
// api/extrato.php - Consulta de extrato de ponto
require_once 'config.php';

$user = authenticate();
$db = getDB();

// Buscar funcionário_id
$funcionario_id = $user['funcionario_id'] ?? null;
if (!$funcionario_id) {
    $stmt = $db->prepare("SELECT id FROM funcionarios WHERE email = :email");
    $stmt->execute([':email' => $user['email']]);
    $func = $stmt->fetch();
    $funcionario_id = $func ? $func['id'] : null;
}

if (!$funcionario_id) {
    jsonError('Perfil de funcionário não encontrado', 'FUNCIONARIO_NOT_FOUND', 404);
}

$mes = $_GET['mes'] ?? date('Y-m');
$ano = substr($mes, 0, 4);
$mes_num = substr($mes, 5, 2);

// Buscar pontos do mês
$query = "SELECT tipo, data_hora, DATE(data_hora) as data
          FROM pontos 
          WHERE funcionario_id = :id 
          AND MONTH(data_hora) = :mes 
          AND YEAR(data_hora) = :ano
          ORDER BY data_hora ASC";

$stmt = $db->prepare($query);
$stmt->execute([
    ':id' => $funcionario_id,
    ':mes' => $mes_num,
    ':ano' => $ano
]);
$pontos = $stmt->fetchAll();

// Agrupar por dia
$extrato = [];
foreach ($pontos as $ponto) {
    $data = $ponto['data'];
    if (!isset($extrato[$data])) {
        $extrato[$data] = [
            'data' => $data,
            'data_formatada' => date('d/m/Y', strtotime($data)),
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

// Calcular horas trabalhadas
foreach ($extrato as &$dia) {
    $horas = null;
    if ($dia['entrada'] && $dia['saida']) {
        $entrada = strtotime($dia['entrada']);
        $saida = strtotime($dia['saida']);
        $total = $saida - $entrada;
        
        if ($dia['saida_almoco'] && $dia['volta_almoco']) {
            $almoco = strtotime($dia['saida_almoco']);
            $volta = strtotime($dia['volta_almoco']);
            $total -= ($volta - $almoco);
        }
        
        if ($total > 0) {
            $horas = floor($total / 3600);
            $minutos = floor(($total % 3600) / 60);
            $dia['horas'] = sprintf("%02d:%02d", $horas, $minutos);
            $dia['horas_decimal'] = round($total / 3600, 2);
        } else {
            $dia['horas'] = '00:00';
            $dia['horas_decimal'] = 0;
        }
    } else {
        $dia['horas'] = '00:00';
        $dia['horas_decimal'] = 0;
    }
    
    // Status
    if ($dia['entrada'] && $dia['saida'] && $dia['saida_almoco'] && $dia['volta_almoco']) {
        $dia['status'] = 'complete';
        $dia['status_texto'] = 'Completo';
    } elseif ($dia['entrada'] && $dia['saida']) {
        $dia['status'] = 'incomplete';
        $dia['status_texto'] = 'Sem almoço';
    } elseif ($dia['entrada']) {
        $dia['status'] = 'inprogress';
        $dia['status_texto'] = 'Em andamento';
    } else {
        $dia['status'] = 'pending';
        $dia['status_texto'] = 'Pendente';
    }
}

// Estatísticas
$stats = [
    'dias_trabalhados' => 0,
    'total_horas' => 0,
    'media_diaria' => 0,
    'dias_completos' => 0
];

foreach ($extrato as $dia) {
    if ($dia['horas'] != '00:00') {
        $stats['dias_trabalhados']++;
        $parts = explode(':', $dia['horas']);
        $stats['total_horas'] += (int)$parts[0];
        $stats['total_minutos'] = ($stats['total_minutos'] ?? 0) + (int)$parts[1];
        
        if ($dia['status'] == 'complete') {
            $stats['dias_completos']++;
        }
    }
}

$stats['total_horas'] += floor(($stats['total_minutos'] ?? 0) / 60);
$stats['total_minutos'] = ($stats['total_minutos'] ?? 0) % 60;
$stats['media_diaria'] = $stats['dias_trabalhados'] > 0 
    ? round($stats['total_horas'] / $stats['dias_trabalhados'], 2) 
    : 0;

jsonSuccess([
    'mes' => $mes,
    'ano' => $ano,
    'dias' => array_values($extrato),
    'estatisticas' => [
        'dias_trabalhados' => $stats['dias_trabalhados'],
        'total_horas' => sprintf("%02d:%02d", $stats['total_horas'], $stats['total_minutos'] ?? 0),
        'media_diaria' => number_format($stats['media_diaria'], 2, ',', '.'),
        'dias_completos' => $stats['dias_completos']
    ]
]);
?>