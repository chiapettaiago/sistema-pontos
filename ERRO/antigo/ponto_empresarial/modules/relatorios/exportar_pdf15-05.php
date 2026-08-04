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

// ============================================
// FUNÇÃO PARA CALCULAR ATRASO (CORRIGIDA)
// ============================================
function calcularMinutosAtrasoPDF($hora_registrada) {
    $hora_limite = '08:00:00';
    if ($hora_registrada <= $hora_limite) {
        return 0;
    }
    $registro_ts = strtotime($hora_registrada);
    $limite_ts = strtotime($hora_limite);
    return floor(($registro_ts - $limite_ts) / 60);
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
        <!-- Código do extrato_funcionario (manter o existente) -->
    <?php elseif ($tipo == 'extrato_coletivo'): ?>
        <!-- Código do extrato_coletivo (manter o existente) -->
    <?php elseif ($tipo == 'pontos_funcionario'): ?>
        <!-- Código do pontos_funcionario (manter o existente) -->
    
    <?php elseif ($tipo == 'atrasos_faltas'): ?>
        <?php
        $funcionario_id = $_GET['funcionario_id'] ?? '';
        $mes = $_GET['mes'] ?? date('Y-m');
        $ano = substr($mes, 0, 4);
        $mes_num = substr($mes, 5, 2);
        
        $nomes_meses = [
            '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
            '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
            '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
        ];
        $nome_mes = $nomes_meses[$mes_num] . ' de ' . $ano;
        ?>
        
        <div class="info">Relatório de Atrasos e Faltas - <?php echo $nome_mes; ?></div>
        
        <?php
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
        $atrasos = $stmt->fetchAll();
        ?>
        
        <?php if (empty($atrasos)): ?>
            <div style="text-align: center; padding: 40px;">Nenhum atraso registrado no período.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Funcionário</th>
                        <th>Matrícula</th>
                        <th>Data</th>
                        <th>Horário Entrada</th>
                        <th>Atraso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($atrasos as $atraso): 
                        $minutos = calcularMinutosAtrasoPDF($atraso['hora_entrada']);
                        $horas = floor($minutos / 60);
                        $resto = $minutos % 60;
                        $texto_atraso = $horas > 0 ? $horas . 'h ' . $resto . 'min' : $minutos . ' minutos';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($atraso['nome']); ?></td>
                        <td><?php echo htmlspecialchars($atraso['matricula']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($atraso['data'])); ?></td>
                        <td><?php echo substr($atraso['hora_entrada'], 0, 5); ?></td>
                        <td><?php echo $texto_atraso; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <div class="footer">
        <p>Relatório gerado automaticamente pelo sistema PontoFácil</p>
        <p>Data de emissão: <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>
    
    <script>window.print();</script>
</body>
</html>