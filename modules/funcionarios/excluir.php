<?php
/**
 * EXCLUSÃO DE FUNCIONÁRIO (POST apenas + CSRF)
 */
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

forceAuthentication();

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
    header('Location: ' . BASE_URL . '/modules/funcionarios/index');
    exit;
}

if ((int) ($_SESSION['funcionario_id'] ?? 0) === $id || !hasPermission('excluir_funcionarios') || !canEditFuncionario($id)) {
    http_response_code(403);
    exit('Acesso negado: seu usuário não pode excluir este funcionário.');
}

// Confirmação adicional (opcional)
if (!isset($_POST['confirm']) || $_POST['confirm'] !== 'sim') {
    $_SESSION['error'] = 'Confirmação de exclusão necessária';
    header('Location: ' . BASE_URL . '/modules/funcionarios/index');
    exit;
}

try {
    global $pdo;
    $empresaId = (int) ($_SESSION['empresa_id'] ?? 0);
    $isSuperAdmin = ($_SESSION['usuario_tipo'] ?? '') === 'super_admin';
    
    // Verifica se o funcionário existe
    $stmt = $pdo->prepare("SELECT id, nome FROM funcionarios WHERE id = :id" . ($isSuperAdmin ? '' : ' AND empresa_id = :empresa_id'));
    $params = [':id' => $id];
    if (!$isSuperAdmin) $params[':empresa_id'] = $empresaId;
    $stmt->execute($params);
    $funcionario = $stmt->fetch();
    
    if (!$funcionario) {
        $_SESSION['error'] = 'Funcionário não encontrado';
        header('Location: ' . BASE_URL . '/modules/funcionarios/index');
        exit;
    }
    
    // Opção 1: Soft delete (recomendado)
    $stmt = $pdo->prepare("UPDATE funcionarios SET status = 'inativo', deleted_at = NOW() WHERE id = :id" . ($isSuperAdmin ? '' : ' AND empresa_id = :empresa_id'));
    $stmt->execute($params);
    
    // Log da exclusão
    logAccess('excluir_funcionario', "Excluiu funcionário: {$funcionario['nome']} (ID: $id)");
    
    $_SESSION['success'] = 'Funcionário desativado com sucesso';
    
} catch (PDOException $e) {
    error_log("Erro ao excluir funcionário: " . $e->getMessage());
    $_SESSION['error'] = 'Erro ao excluir funcionário. Tente novamente.';
}

header('Location: ' . BASE_URL . '/modules/funcionarios/index');
exit;
?>
