<?php
// modules/solicitacoes/exportar.php - Exportar solicitações para Excel
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa', 'gestor'])) {
    header('Location: index.php');
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$formato = $_GET['formato'] ?? 'excel';
$status_filtro = $_GET['status'] ?? 'todos';
$tipo_filtro = $_GET['tipo'] ?? '';

// Buscar solicitações
$query = "SELECT s.*, 
          f.nome as funcionario_nome,
          f.matricula,
          f.email as funcionario_email,
          fil.nome_fantasia as filial_nome,
          c.nome as cargo_nome,
          CASE 
              WHEN s.tipo = 'ferias' THEN 'Férias'
              WHEN s.tipo = 'abono' THEN 'Abono'
              WHEN s.tipo = 'licenca' THEN 'Licença Médica'
              WHEN s.tipo = 'justificativa' THEN 'Justificativa'
              WHEN s.tipo = 'atestado' THEN 'Atestado'
              ELSE s.tipo
          END as tipo_nome,
          DATE_FORMAT(s.data_inicio, '%d/%m/%Y') as data_inicio_formatada,
          DATE_FORMAT(s.data_fim, '%d/%m/%Y') as data_fim_formatada,
          DATE_FORMAT(s.created_at, '%d/%m/%Y %H:%i') as data_solicitacao,
          DATE_FORMAT(s.data_resposta, '%d/%m/%Y %H:%i') as data_resposta_formatada
          FROM solicitacoes s
          LEFT JOIN funcionarios f ON s.funcionario_id = f.id
          LEFT JOIN filiais fil ON f.filial_id = fil.id
          LEFT JOIN cargos c ON f.cargo_id = c.id
          WHERE 1=1";

$params = [];

if ($status_filtro !== 'todos') {
    $query .= " AND s.status = :status";
    $params[':status'] = $status_filtro;
}
if ($tipo_filtro) {
    $query .= " AND s.tipo = :tipo";
    $params[':tipo'] = $tipo_filtro;
}

$query .= " ORDER BY s.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$solicitacoes = $stmt->fetchAll();

// Nome do arquivo
$filename = 'solicitacoes_' . date('Y-m-d_H-i-s');

if ($formato === 'excel') {
    // Exportar para Excel (CSV)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Cabeçalhos
    fputcsv($output, [
        'ID', 'Funcionário', 'Matrícula', 'Filial', 'Cargo', 'Tipo', 
        'Título', 'Data Início', 'Data Fim', 'Data Solicitação', 
        'Status', 'Resposta', 'Data Resposta'
    ], ';');
    
    // Dados
    foreach ($solicitacoes as $s) {
        fputcsv($output, [
            $s['id'],
            $s['funcionario_nome'],
            $s['matricula'],
            $s['filial_nome'],
            $s['cargo_nome'],
            $s['tipo_nome'],
            $s['titulo'],
            $s['data_inicio_formatada'] ?? '',
            $s['data_fim_formatada'] ?? '',
            $s['data_solicitacao'],
            $s['status'],
            strip_tags($s['resposta'] ?? ''),
            $s['data_resposta_formatada'] ?? ''
        ], ';');
    }
    
    fclose($output);
    exit;
}

if ($formato === 'pdf') {
    // Para PDF, use uma biblioteca como Dompdf ou TCPDF
    // Exemplo com HTML+CSS para impressão
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Relatório de Solicitações</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
            }
            h1 {
                color: #333;
                text-align: center;
            }
            .info {
                margin-bottom: 20px;
                text-align: center;
                color: #666;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 10px;
                text-align: left;
                font-size: 12px;
            }
            th {
                background: #667eea;
                color: white;
            }
            .status-pendente { color: #f59e0b; }
            .status-aprovado { color: #10b981; }
            .status-rejeitado { color: #ef4444; }
            .status-cancelado { color: #6b7280; }
            .footer {
                margin-top: 30px;
                text-align: center;
                font-size: 10px;
                color: #999;
            }
        </style>
    </head>
    <body>
        <h1>Relatório de Solicitações</h1>
        <div class="info">
            Gerado em: <?php echo date('d/m/Y H:i:s'); ?><br>
            Status: <?php echo $status_filtro == 'todos' ? 'Todos' : ucfirst($status_filtro); ?>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Funcionário</th>
                    <th>Matrícula</th>
                    <th>Tipo</th>
                    <th>Título</th>
                    <th>Período</th>
                    <th>Status</th>
                    <th>Data Solicitação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitacoes as $s): ?>
                <tr>
                    <td><?php echo $s['id']; ?></td>
                    <td><?php echo $s['funcionario_nome']; ?></td>
                    <td><?php echo $s['matricula']; ?></td>
                    <td><?php echo $s['tipo_nome']; ?></td>
                    <td><?php echo htmlspecialchars($s['titulo'] ?? ''); ?></td>
                    <td>
                        <?php echo $s['data_inicio_formatada'] ?? ''; ?>
                        <?php echo $s['data_fim_formatada'] ? ' até ' . $s['data_fim_formatada'] : ''; ?>
                    </td>
                    <td class="status-<?php echo $s['status']; ?>"><?php echo ucfirst($s['status']); ?></td>
                    <td><?php echo $s['data_solicitacao']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Sistema PontoFácil - Relatório gerado automaticamente</p>
        </div>
        
        <script>
            window.print();
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>