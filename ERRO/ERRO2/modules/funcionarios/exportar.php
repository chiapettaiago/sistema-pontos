<?php
// modules/funcionarios/exportar.php - Exportar Funcionários com Filtros
require_once '../../config/database.php';
require_once '../../includes/auth.php';

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Parâmetros de filtro (mesmos do index.php)
$search = $_GET['search'] ?? '';
$filial_id = $_GET['filial_id'] ?? '';
$status = $_GET['status'] ?? 'todos';
$tipo_usuario = $_GET['tipo_usuario'] ?? '';
$formato = $_GET['formato'] ?? 'csv';
$empresa_id = getCurrentEmpresaId();
if ($empresa_id === null || $empresa_id === '') {
    $empresa_id = $_SESSION['empresa_id'] ?? null;
}
if ($empresa_id === null || $empresa_id === '') {
    header('Location: ../../index.php');
    exit;
}

// Query base com filtros
$query = "SELECT f.*, fil.nome_fantasia as filial_nome, c.nome as cargo_nome, d.nome as departamento_nome
          FROM funcionarios f
          LEFT JOIN filiais fil ON f.filial_id = fil.id
          LEFT JOIN cargos c ON f.cargo_id = c.id
          LEFT JOIN departamentos d ON f.departamento_id = d.id
          WHERE f.empresa_id = :empresa_id";

$params = [':empresa_id' => $empresa_id];

// Aplicar filtros
if ($search) {
    $query .= " AND (f.nome LIKE :search OR f.email LIKE :search OR f.matricula LIKE :search OR f.cpf LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($_SESSION['usuario_tipo'] === 'super_admin' || $_SESSION['usuario_tipo'] === 'admin') {
    if ($filial_id) {
        $query .= " AND f.filial_id = :filial_id";
        $params[':filial_id'] = $filial_id;
    }
} else {
    $query .= " AND f.filial_id = :filial_id";
    $params[':filial_id'] = $_SESSION['usuario_filial_id'];
}

if ($status !== 'todos') {
    $query .= " AND f.status = :status";
    $params[':status'] = $status;
}

if ($tipo_usuario) {
    $query .= " AND f.tipo_usuario = :tipo_usuario";
    $params[':tipo_usuario'] = $tipo_usuario;
}

$query .= " ORDER BY f.nome ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$funcionarios = $stmt->fetchAll();

// Nome do arquivo
$filename = 'funcionarios_' . date('Y-m-d_H-i-s');

// Função para converter status para texto
function getStatusText($status) {
    $statuses = [
        'ativo' => 'Ativo',
        'ferias' => 'Férias',
        'licenca' => 'Licença',
        'desligado' => 'Desligado',
        'afastado' => 'Afastado'
    ];
    return $statuses[$status] ?? $status;
}

// Função para converter tipo usuário para texto
function getTipoUsuarioText($tipo) {
    $tipos = [
        'super_admin' => 'Super Administrador',
        'admin' => 'Administrador',
        'gestor' => 'Gestor',
        'supervisor' => 'Supervisor',
        'funcionario' => 'Funcionário'
    ];
    return $tipos[$tipo] ?? $tipo;
}

// Exportar CSV
if ($formato == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM para compatibilidade com Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeçalhos
    $cabecalho = [
        'Matrícula',
        'Nome',
        'E-mail',
        'CPF',
        'Telefone',
        'Celular',
        'Filial',
        'Cargo',
        'Departamento',
        'Data Admissão',
        'Status',
        'Tipo Usuário',
        'Data Cadastro'
    ];
    fputcsv($output, $cabecalho, ';');
    
    // Dados
    foreach ($funcionarios as $func) {
        $linha = [
            $func['matricula'],
            $func['nome'],
            $func['email'],
            $func['cpf'] ?? '',
            $func['telefone'] ?? '',
            $func['celular'] ?? '',
            $func['filial_nome'],
            $func['cargo_nome'] ?? '',
            $func['departamento_nome'] ?? '',
            date('d/m/Y', strtotime($func['data_admissao'])),
            getStatusText($func['status']),
            getTipoUsuarioText($func['tipo_usuario']),
            date('d/m/Y H:i', strtotime($func['created_at']))
        ];
        fputcsv($output, $linha, ';');
    }
    
    fclose($output);
    exit;
}

// Exportar Excel (HTML)
if ($formato == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<title>Relatório de Funcionários</title>';
    echo '<style>';
    echo 'th { background-color: #667eea; color: white; padding: 8px; }';
    echo 'td { padding: 6px; border-bottom: 1px solid #ccc; }';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<h2>Relatório de Funcionários</h2>';
    echo '<p>Gerado em: ' . date('d/m/Y H:i:s') . '</p>';
    echo '<p>Total de registros: ' . count($funcionarios) . '</p>';
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Matrícula</th>';
    echo '<th>Nome</th>';
    echo '<th>E-mail</th>';
    echo '<th>CPF</th>';
    echo '<th>Telefone</th>';
    echo '<th>Celular</th>';
    echo '<th>Filial</th>';
    echo '<th>Cargo</th>';
    echo '<th>Departamento</th>';
    echo '<th>Data Admissão</th>';
    echo '<th>Status</th>';
    echo '<th>Tipo Usuário</th>';
    echo '<th>Data Cadastro</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($funcionarios as $func) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($func['matricula']) . '</td>';
        echo '<td>' . htmlspecialchars($func['nome']) . '</td>';
        echo '<td>' . htmlspecialchars($func['email']) . '</td>';
        echo '<td>' . htmlspecialchars($func['cpf'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($func['telefone'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($func['celular'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($func['filial_nome']) . '</td>';
        echo '<td>' . htmlspecialchars($func['cargo_nome'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($func['departamento_nome'] ?? '') . '</td>';
        echo '<td>' . date('d/m/Y', strtotime($func['data_admissao'])) . '</td>';
        echo '<td>' . getStatusText($func['status']) . '</td>';
        echo '<td>' . getTipoUsuarioText($func['tipo_usuario']) . '</td>';
        echo '<td>' . date('d/m/Y H:i', strtotime($func['created_at'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '<p style="margin-top: 20px; font-size: 10px; color: #666;">Relatório gerado pelo sistema Ponto Fácil</p>';
    echo '</body>';
    echo '</html>';
    exit;
}

// Exportar PDF (HTML para impressão)
if ($formato == 'pdf') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Relatório de Funcionários</title>
        <style>
            @media print {
                body { margin: 0; padding: 20px; }
                .no-print { display: none; }
            }
            body { font-family: Arial, sans-serif; margin: 20px; }
            h2 { color: #667eea; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #667eea; color: white; padding: 10px; text-align: left; }
            td { padding: 8px; border-bottom: 1px solid #ddd; }
            .header { margin-bottom: 20px; }
            .footer { margin-top: 20px; font-size: 10px; color: #666; text-align: center; }
            button { padding: 10px 20px; margin: 10px; cursor: pointer; background: #667eea; color: white; border: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="no-print" style="text-align: center; margin-bottom: 20px;">
            <button onclick="window.print()">🖨️ Imprimir / Salvar como PDF</button>
            <button onclick="window.close()">❌ Fechar</button>
        </div>
        <div class="header">
            <h2>Relatório de Funcionários</h2>
            <p>Gerado em: <?php echo date('d/m/Y H:i:s'); ?></p>
            <p>Total de registros: <?php echo count($funcionarios); ?></p>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Matrícula</th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Filial</th>
                    <th>Cargo</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($funcionarios as $func): ?>
                <tr>
                    <td><?php echo htmlspecialchars($func['matricula']); ?></td>
                    <td><?php echo htmlspecialchars($func['nome']); ?></td>
                    <td><?php echo htmlspecialchars($func['email']); ?></td>
                    <td><?php echo htmlspecialchars($func['filial_nome']); ?></td>
                    <td><?php echo htmlspecialchars($func['cargo_nome'] ?? ''); ?></td>
                    <td><?php echo getStatusText($func['status']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="footer">
            <p>Relatório gerado pelo sistema Ponto Fácil</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>


