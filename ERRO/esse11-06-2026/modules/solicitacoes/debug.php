<?php
$host = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($host, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Acesso restrito');
}

// debug.php - Diagnóstico de Solicitações (Compatível PHP 7.0)
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    die('Faça login primeiro');
}

$database = new Database();
$db = $database->getConnection();

$empresa_id = isset($_SESSION['empresa_id']) ? $_SESSION['empresa_id'] : 1;
$funcionario_id = isset($_SESSION['funcionario_id']) ? $_SESSION['funcionario_id'] : null;

echo "<h1>Diagnóstico de Solicitações</h1>";
echo "<hr>";

echo "<h3>Configuração da Sessão:</h3>";
echo "<pre>";
echo "empresa_id: " . ($empresa_id ? $empresa_id : 'NÃO DEFINIDO') . "\n";
echo "usuario_tipo: " . (isset($_SESSION['usuario_tipo']) ? $_SESSION['usuario_tipo'] : 'NÃO DEFINIDO') . "\n";
echo "funcionario_id: " . ($funcionario_id ? $funcionario_id : 'NÃO DEFINIDO') . "\n";
echo "usuario_nome: " . (isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'NÃO DEFINIDO') . "\n";
echo "</pre>";

echo "<h3>Tabela solicitacoes - Estrutura:</h3>";
try {
    $stmt = $db->query("DESCRIBE solicitacoes");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Padrão</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Erro ao verificar estrutura: " . $e->getMessage();
}

echo "<h3>Todas as solicitações no banco:</h3>";
try {
    $stmt = $db->query("SELECT * FROM solicitacoes ORDER BY created_at DESC");
    $solicitacoes = $stmt->fetchAll();
    
    if (empty($solicitacoes)) {
        echo "<p style='color:red'>NENHUMA solicitação encontrada no banco de dados!</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr>";
        echo "<th>ID</th><th>Funcionário ID</th><th>Empresa ID</th><th>Filial ID</th><th>Tipo</th><th>Título</th><th>Status</th><th>Data</th>";
        echo "</tr>";
        foreach ($solicitacoes as $s) {
            echo "<tr>";
            echo "<td>" . $s['id'] . "</td>";
            echo "<td>" . ($s['funcionario_id'] ? $s['funcionario_id'] : 'NULL') . "</td>";
            echo "<td>" . (isset($s['empresa_id']) ? $s['empresa_id'] : 'NULL') . "</td>";
            echo "<td>" . (isset($s['filial_id']) ? $s['filial_id'] : 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($s['tipo']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($s['titulo'], 0, 50)) . "</td>";
            echo "<td>" . $s['status'] . "</td>";
            echo "<td>" . $s['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Erro ao buscar solicitações: " . $e->getMessage();
}

echo "<h3>Funcionários da empresa ID {$empresa_id}:</h3>";
try {
    $stmt = $db->prepare("SELECT id, nome, matricula, empresa_id FROM funcionarios WHERE empresa_id = :empresa_id LIMIT 10");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $funcionarios = $stmt->fetchAll();
    
    if (empty($funcionarios)) {
        echo "<p style='color:orange'>Nenhum funcionário encontrado para empresa_id = {$empresa_id}</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Matrícula</th><th>Empresa ID</th></tr>";
        foreach ($funcionarios as $f) {
            echo "<tr>";
            echo "<td>" . $f['id'] . "</td>";
            echo "<td>" . htmlspecialchars($f['nome']) . "</td>";
            echo "<td>" . htmlspecialchars($f['matricula']) . "</td>";
            echo "<td>" . $f['empresa_id'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}

echo "<h3>Solicitações com nomes dos funcionários:</h3>";
try {
    $query = "SELECT s.*, f.nome as funcionario_nome 
              FROM solicitacoes s
              LEFT JOIN funcionarios f ON s.funcionario_id = f.id
              ORDER BY s.created_at DESC";
    $stmt = $db->query($query);
    $solicitacoes = $stmt->fetchAll();
    
    if (empty($solicitacoes)) {
        echo "<p>Nenhuma solicitação encontrada</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Funcionário</th><th>Funcionário ID</th><th>Tipo</th><th>Status</th><th>Data</th></tr>";
        foreach ($solicitacoes as $s) {
            echo "<tr>";
            echo "<td>" . $s['id'] . "</td>";
            echo "<td>" . (isset($s['funcionario_nome']) ? htmlspecialchars($s['funcionario_nome']) : 'N/A') . "</td>";
            echo "<td>" . $s['funcionario_id'] . "</td>";
            echo "<td>" . $s['tipo'] . "</td>";
            echo "<td>" . $s['status'] . "</td>";
            echo "<td>" . $s['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}

echo "<br><br>";
echo "<a href='admin.php'>Voltar para Admin</a>";
?>
