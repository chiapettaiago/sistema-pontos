<?php
/**
 * EXCLUSÃO DE FUNCIONÁRIO (POST apenas + CSRF)
 */
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Força autenticação e admin
forceAuthentication();
requireAdmin();

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.0 405 Method Not Allowed');
    die('Método não permitido. Use POST.');
}

// Verifica CSRF
verifyCSRFToken();

// Obtém ID do funcionário
$id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

if ($id <= 0) {
    $_SESSION['error'] = 'ID de funcionário inválido';
    header('Location: ' . BASE_URL . '/modules/funcionarios/index.php');
    exit;
}

// Confirmação adicional (opcional)
if (!isset($_POST['confirm']) || $_POST['confirm'] !== 'sim') {
    $_SESSION['error'] = 'Confirmação de exclusão necessária';
    header('Location: ' . BASE_URL . '/modules/funcionarios/index.php');
    exit;
}

try {
    global $pdo;
    
    // Verifica se o funcionário existe
    $stmt = $pdo->prepare("SELECT id, nome FROM funcionarios WHERE id = ?");
    $stmt->execute([$id]);
    $funcionario = $stmt->fetch();
    
    if (!$funcionario) {
        $_SESSION['error'] = 'Funcionário não encontrado';
        header('Location: ' . BASE_URL . '/modules/funcionarios/index.php');
        exit;
    }
    
    // Opção 1: Soft delete (recomendado)
    $stmt = $pdo->prepare("UPDATE funcionarios SET status = 'inativo', deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log da exclusão
    logAccess('excluir_funcionario', "Excluiu funcionário: {$funcionario['nome']} (ID: $id)");
    
    $_SESSION['success'] = 'Funcionário desativado com sucesso';
    
} catch (PDOException $e) {
    error_log("Erro ao excluir funcionário: " . $e->getMessage());
    $_SESSION['error'] = 'Erro ao excluir funcionário. Tente novamente.';
}

header('Location: ' . BASE_URL . '/modules/funcionarios/index.php');
exit;
?>
