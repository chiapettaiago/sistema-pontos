<?php
// modules/auditoria/exportar.php - Exportar Logs para CSV/Excel
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa'])) {
    header('Location: ../../index.php');
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;

// Parâmetros de filtro
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$usuario_id = $_GET['usuario_id'] ?? '';
$acao = $_GET['acao'] ?? '';
$modulo = $_GET['modulo'] ?? '';
$formato = $_GET['formato'] ?? 'csv';

// Montar query
$query = "SELECT l.* 
          FROM logs_auditoria l
          WHERE l.created_at BETWEEN :data_inicio AND DATE_ADD(:data_fim, INTERVAL 1 DAY)";

$params = [
    ':data_inicio' => $data_inicio . ' 00:00:00',
    ':data_fim' => $data_fim . ' 23:59:59'
];

if ($usuario_id) {
    $query .= " AND l.usuario_id = :usuario_id";
    $params[':usuario_id'] = $usuario_id;
}

if ($acao) {
    $query .= " AND l.acao = :acao";
    $params[':acao'] = $acao;
}

if ($modulo) {
    $query .= " AND l.modulo = :modulo";
    $params[':modulo'] = $modulo;
}

$query .= " ORDER BY l.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$logs = $stmt->fetchAll();

// Nome do arquivo
$filename = 'logs_auditoria_' . date('Y-m-d_H-i-s');

if ($formato == 'csv') {
    // Exportar para CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Adicionar BOM para UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Cabeçalhos
    fputcsv($output, [
        'ID', 'Data/Hora', 'Usuário', 'E-mail', 'Tipo', 'Ação', 'Módulo', 
        'ID Registro', 'Descrição', 'IP Address', 'User Agent'
    ], ';');
    
    // Dados
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            date('d/m/Y H:i:s', strtotime($log['created_at'])),
            $log['usuario_nome'],
            $log['usuario_email'],
            $log['usuario_tipo'],
            $log['acao'],
            $log['modulo'],
            $log['registro_id'] ?? '',
            strip_tags($log['descricao'] ?? ''),
            $log['ip_address'],
            $log['user_agent']
        ], ';');
    }
    
    fclose($output);
    exit;
}

// Exportar para HTML (visualização)
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Exportar Logs - Auditoria</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 12px;
        }
        h1 {
            color: #667eea;
            text-align: center;
            font-size: 18px;
        }
        .info {
            text-align: center;
            margin-bottom: 20px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #999;
        }
        @media print {
            body {
                margin: 0;
                padding: 10px;
            }
            .btn-print {
                display: none;
            }
        }
        .btn-print {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print();">🖨️ Imprimir / Salvar PDF</button>
    
    <h1>Relatório de Logs de Auditoria</h1>
    <div class="info">
        Período: <?php echo date('d/m/Y', strtotime($data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($data_fim)); ?><br>
        Total de registros: <?php echo count($logs); ?><br>
        Gerado em: <?php echo date('d/m/Y H:i:s'); ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Data/Hora</th>
                <th>Usuário</th>
                <th>E-mail</th>
                <th>Ação</th>
                <th>Módulo</th>
                <th>Descrição</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($log['usuario_nome'] ?? 'Sistema'); ?></td>
                <td><?php echo htmlspecialchars($log['usuario_email'] ?? ''); ?></td>
                <td><?php echo $log['acao']; ?></td>
                <td><?php echo ucfirst($log['modulo']); ?></td>
                <td><?php echo htmlspecialchars(substr($log['descricao'] ?? '', 0, 100)); ?></td>
                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <tr>
                <td colspan="7" style="text-align: center;">Nenhum registro encontrado</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Relatório gerado automaticamente pelo sistema PontoFácil</p>
    </div>
    
    <script>
        window.print();
    </script>
</body>
</html>