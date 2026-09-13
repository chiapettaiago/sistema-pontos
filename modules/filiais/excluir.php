<?php
/**
 * EXCLUSÃO DE FILIAL (POST apenas + CSRF)
 */
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

forceAuthentication();
if (!hasPermission('gerenciar_filiais')) {
    http_response_code(403);
    exit('Acesso negado: seu usuário não possui permissão para gerenciar filiais.');
}

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
    header('Location: ' . BASE_URL . '/modules/filiais/index');
    exit;
}

// Confirmação
if (!isset($_POST['confirm']) || $_POST['confirm'] !== 'sim') {
    $_SESSION['error'] = 'Confirmação de exclusão necessária';
    header('Location: ' . BASE_URL . '/modules/filiais/index');
    exit;
}

try {
    global $pdo;
    $empresaId = (int) ($_SESSION['empresa_id'] ?? 0);
    
    // Verifica se existem funcionários vinculados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM funcionarios WHERE filial_id = :id AND empresa_id = :empresa_id");
    $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $_SESSION['error'] = "Não é possível excluir a filial pois existem $count funcionários vinculados. Reassigne-os primeiro.";
        header('Location: ' . BASE_URL . '/modules/filiais/index');
        exit;
    }
    
    // Soft delete
    $stmt = $pdo->prepare("UPDATE filiais SET status = 'inativo', deleted_at = NOW() WHERE id = :id AND empresa_id = :empresa_id");
    $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
    
    logAccess('excluir_filial', "Desativou filial ID: $id");
    $_SESSION['success'] = 'Filial desativada com sucesso';
    
} catch (PDOException $e) {
    error_log("Erro ao excluir filial: " . $e->getMessage());
    $_SESSION['error'] = 'Erro ao excluir filial.';
}

header('Location: ' . BASE_URL . '/modules/filiais/index');
exit;
?>
