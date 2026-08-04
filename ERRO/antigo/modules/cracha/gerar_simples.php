<?php
// modules/cracha/gerar_simples.php - Versão de teste para diagnóstico (CORRIGIDO)

// Iniciar sessão APENAS se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;

$query = "SELECT f.*, fil.nome_fantasia as filial_nome, c.nome as cargo_nome
          FROM funcionarios f
          LEFT JOIN filiais fil ON f.filial_id = fil.id
          LEFT JOIN cargos c ON f.cargo_id = c.id
          WHERE f.id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    die('Funcionário não encontrado');
}

// Exibir informações para teste
echo "<h1>Teste - Crachá de " . htmlspecialchars($funcionario['nome']) . "</h1>";
echo "<p>Matrícula: " . htmlspecialchars($funcionario['matricula']) . "</p>";
echo "<p>Filial: " . htmlspecialchars($funcionario['filial_nome']) . "</p>";
echo "<p>E-mail: " . htmlspecialchars($funcionario['email']) . "</p>";
echo "<p>Data Admissão: " . date('d/m/Y', strtotime($funcionario['data_admissao'])) . "</p>";

// Verificar foto
if (!empty($funcionario['foto']) && file_exists('../../' . $funcionario['foto'])) {
    echo "<p>Foto: OK - arquivo encontrado</p>";
    echo "<img src='../../" . $funcionario['foto'] . "' width='100' style='border-radius: 50%;'><br>";
} else {
    echo "<p>Foto: Não encontrada ou não cadastrada</p>";
    if (!empty($funcionario['foto'])) {
        echo "<p>Caminho buscado: ../../" . $funcionario['foto'] . "</p>";
        echo "<p>Arquivo existe? " . (file_exists('../../' . $funcionario['foto']) ? 'Sim' : 'Não') . "</p>";
    }
}

// Verificar se a pasta de uploads existe
$uploadDir = '../../uploads/funcionarios/';
echo "<p>Pasta de uploads: " . ($uploadDir) . "</p>";
echo "<p>A pasta existe? " . (is_dir($uploadDir) ? 'Sim' : 'Não') . "</p>";

// Listar arquivos na pasta
if (is_dir($uploadDir)) {
    echo "<p>Arquivos na pasta:</p><ul>";
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li>" . $file . "</li>";
        }
    }
    echo "</ul>";
}

echo "<br><button onclick='window.print()'>Imprimir / Salvar PDF</button>";
echo "<br><br><a href='index.php'>Voltar</a>";
?>