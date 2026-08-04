<?php
// modules/relatorios/exportar_pdf.php - Exportar para PDF (via HTML para impressão)
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

// Buscar nome da empresa
$stmt = $db->prepare("SELECT nome_empresa FROM empresa WHERE id = :id");
$stmt->execute([':id' => $empresa_id]);
$empresa = $stmt->fetch();
$empresa_nome = $empresa['nome_empresa'] ?? 'Empresa';

function calcularHorasPDF($entrada, $saida_almoco, $volta_almoco, $saida) {
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
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 11px; }
        th { background: #667eea; color: white; font-weight: bold; text-align: center; }
        td { text-align: center; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #999; padding-top: 10px; border-top: 1px solid #ddd; }
        .status-normal { color: #10b981; }
        .status-atraso { color: #f59e0b; }
        .status-falta { color: #ef4444; }
        .status-critico { color: #ef4444; font-weight: bold; }
        .status-atencao { color: #f59e0b; }
        .btn-print { position: fixed; bottom: 20px; right: 20px; background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; z-index: 1000; }
        @media print { .btn-print { display: none; } body { margin: 0; padding: 10px; } }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print();"><i class="fas fa-print"></i> Imprimir / Salvar PDF</button>
    
    <div class="header">
        <h1>PontoFácil - <?php echo htmlspecialchars($empresa_nome); ?></h1>
        <div class="empresa">Sistema de Ponto Eletrônico</div>
        <div class="info">Gerado em: <?php echo date('d/m/Y H:i:s'); ?></div>
    </div>

    <?php if ($tipo == 'extrato_funcionario'): ?>
        <?php
        if ($tipo_periodo == 'diario') {
            $data_inicio = $data_referencia; $data_fim = $data_referencia;
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
        
        $stmt = $db->prepare("SELECT nome, matricula FROM funcionarios WHERE id = :id");
        $stmt->execute([':id' => $funcionario_id]);
        $func = $stmt->fetch();
        ?>
        <div class="info">Funcionário: <?php echo htmlspecialchars($func['nome']); ?> (Matrícula: <?php echo htmlspecialchars($func['matricula']); ?>)</div>
        <div class="info">Período: <?php echo $titulo_periodo; ?></div>
        <table><thead><tr><th>Data</th><th>Dia da Semana</th><th>Entrada</th><th>Saída Almoço</th><th>Volta Almoço</th><th>Saída</th><th>Horas</th><th>Status</th></tr></thead><tbody>
        <?php
        $query = "SELECT DATE(p.data_hora) as data, DAYOFWEEK(p.data_hora) as dia_semana_num,
                  MAX(CASE WHEN p.tipo = 'entrada' THEN TIME(p.data_hora) END) as entrada,
                  MAX(CASE WHEN p.tipo = 'saida_almoco' THEN TIME(p.data_hora) END) as saida_almoco,
                  MAX(CASE WHEN p.tipo = 'volta_almoco' THEN TIME(p.data_hora) END) as volta_almoco,
                  MAX(CASE WHEN p.tipo = 'saida' THEN TIME(p.data_hora) END) as saida
                  FROM pontos p WHERE p.funcionario_id = :id AND DATE(p.data_hora) BETWEEN :inicio AND :fim
                  GROUP BY DATE(p.data_hora) ORDER BY data ASC";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $funcionario_id, ':inicio' => $data_inicio, ':fim' => $data_fim]);
        $dias_semana = ['', 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        while ($row = $stmt->fetch()) {
            $horas = calcularHorasPDF($row['entrada'], $row['saida_almoco'], $row['volta_almoco'], $row['saida']);
            $status = ($row['entrada'] && $row['entrada'] > '08:00:00') ? 'Atraso' : (($row['entrada'] && $row['saida']) ? 'Normal' : 'Incompleto');
            if (!$row['entrada'] && !$row['saida']) $status = 'Falta';
            echo "<tr><td>" . date('d/m/Y', strtotime($row['data'])) . "</td><td>{$dias_semana[$row['dia_semana_num']]}</td>
                  <td>" . ($row['entrada'] ? substr($row['entrada'], 0, 5) : '--:--') . "</td>
                  <td>" . ($row['saida_almoco'] ? substr($row['saida_almoco'], 0, 5) : '--:--') . "</td>
                  <td>" . ($row['volta_almoco'] ? substr($row['volta_almoco'], 0, 5) : '--:--') . "</td>
                  <td>" . ($row['saida'] ? substr($row['saida'], 0, 5) : '--:--') . "</td>
                  <td>{$horas}</td><td class='status-" . strtolower($status) . "'>{$status}</td></tr>";
        }
        ?></tbody></table>

    <?php elseif ($tipo == 'extrato_coletivo'): ?>
        <?php
        $tipo_periodo = $_GET['periodo'] ?? 'mensal';
        $data_referencia = $_GET['data_referencia'] ?? date('Y-m-d');
        if ($tipo_periodo == 'semanal') {
            $data_inicio = date('Y-m-d', strtotime('monday this week', strtotime($data_referencia)));
            $data_fim = date('Y-m-d', strtotime('sunday this week', strtotime($data_referencia)));
            $titulo_periodo = 'Semana de ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim));
        } else {
            $data_inicio = date('Y-m-01', strtotime($data_referencia));
            $data_fim = date('Y-m-t', strtotime($data_referencia));
            $titulo_periodo = strftime('%B de %Y', strtotime($data_referencia));
        }
        $dias_uteis = 0;
        $data_atual = new DateTime($data_inicio);
        $data_fim_obj = new DateTime($data_fim);
        while ($data_atual <= $data_fim_obj) { if ($data_atual->format('N') <= 5) $dias_uteis++; $data_atual->modify('+1 day'); }
        ?>
        <div class="info">Período: <?php echo $titulo_periodo; ?> (<?php echo $dias_uteis; ?> dias úteis)</div>
        <table><thead><tr><th>Funcionário</th><th>Matrícula</th><th>Filial</th><th>Dias Trabalhados</th><th>Faltas</th><th>Atrasos</th><th>Presença</th><th>Situação</th></tr></thead><tbody>
        <?php
        $query = "SELECT f.id, f.nome, f.matricula, fi.nome_fantasia as filial_nome,
                  COUNT(DISTINCT DATE(p.data_hora)) as dias_trabalhados,
                  SUM(CASE WHEN p.tipo = 'entrada' AND TIME(p.data_hora) > '08:00:00' THEN 1 ELSE 0 END) as total_atrasos
                  FROM funcionarios f LEFT JOIN filiais fi ON f.filial_id = fi.id
                  LEFT JOIN pontos p ON f.id = p.funcionario_id AND DATE(p.data_hora) BETWEEN :inicio AND :fim
                  WHERE f.empresa_id = :empresa_id AND f.status = 'ativo'";
        $params = [':inicio' => $data_inicio, ':fim' => $data_fim, ':empresa_id' => $empresa_id];
        if ($filial_id) { $query .= " AND f.filial_id = :filial_id"; $params[':filial_id'] = $filial_id; }
        $query .= " GROUP BY f.id ORDER BY f.nome";
        $stmt = $db->prepare($query); $stmt->execute($params);
        while ($func = $stmt->fetch()) {
            $faltas = $dias_uteis - $func['dias_trabalhados'];
            $presenca = $dias_uteis > 0 ? round((($dias_uteis - $faltas) / $dias_uteis) * 100, 1) : 0;
            $situacao = ($faltas > 3) ? 'Crítico' : (($faltas > 1 || $func['total_atrasos'] > 5) ? 'Atenção' : 'Normal');
            echo "<tr><td>{$func['nome']}</td><td>{$func['matricula']}</td><td>{$func['filial_nome']}</td>
                  <td>{$func['dias_trabalhados']}</td><td class='status-falta'>{$faltas}</td>
                  <td class='status-atraso'>{$func['total_atrasos']}</td><td>{$presenca}%</td>
                  <td class='status-" . strtolower($situacao) . "'>{$situacao}</td></tr>";
        }
        ?></tbody></table>
    <?php endif; ?>

    <div class="footer"><p>Relatório gerado automaticamente pelo sistema PontoFácil</p><p>Data de emissão: <?php echo date('d/m/Y H:i:s'); ?></p></div>
    <script>window.print();</script>
</body>
</html>