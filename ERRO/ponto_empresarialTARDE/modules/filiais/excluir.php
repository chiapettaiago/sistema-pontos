<?php
/**
 * EXCLUSÃO DE FILIAL (POST apenas + CSRF)
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

// Obtém ID da filial
$id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

if ($id <= 0) {
    $_SESSION['error'] = 'ID de filial inválido';
    header('Location: /modules/filiais/index.php');
    exit;
}

// Confirmação
if (!isset($_POST['confirm']) || $_POST['confirm'] !== 'sim') {
    $_SESSION['error'] = 'Confirmação de exclusão necessária';
    header('Location: /modules/filiais/index.php');
    exit;
}

try {
    global $pdo;
    
    // Verifica se existem funcionários vinculados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM funcionarios WHERE filial_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $_SESSION['error'] = "Não é possível excluir a filial pois existem $count funcionários vinculados. Reassigne-os primeiro.";
        header('Location: /modules/filiais/index.php');
        exit;
    }
    
    // Soft delete
    $stmt = $pdo->prepare("UPDATE filiais SET status = 'inativo', deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    
    logAccess('excluir_filial', "Desativou filial ID: $id");
    $_SESSION['success'] = 'Filial desativada com sucesso';
    
} catch (PDOException $e) {
    error_log("Erro ao excluir filial: " . $e->getMessage());
    $_SESSION['error'] = 'Erro ao excluir filial.';
}

header('Location: /modules/filiais/index.php');
exit;
?>