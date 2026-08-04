<?php
// modules/admin/empresas/suspender.php - Suspender Empresa (COMPLETO)
// NÃO PODE HAVER NADA ANTES DESTA LINHA

session_start();

// Verificar se está logado e é super admin
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_tipo'] ?? '') !== 'super_admin') {
    header('Location: /ponto_empresarial/login.php');
    exit;
}

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;

// Buscar nome da empresa para a mensagem
$stmt = $db->prepare("SELECT nome FROM empresas WHERE id = :id");
$stmt->execute([':id' => $id]);
$empresa = $stmt->fetch();

if ($empresa) {
    // Atualizar status para suspensa
    $stmt = $db->prepare("UPDATE empresas SET status = 'suspensa' WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    // Registrar log
    $log = $db->prepare("INSERT INTO logs_sistema (funcionario_id, acao, descricao, ip) 
                         VALUES (:funcionario_id, 'SUSPENDER_EMPRESA', :descricao, :ip)");
    $log->execute([
        ':funcionario_id' => $_SESSION['usuario_id'],
        ':descricao' => "Empresa suspensa: {$empresa['nome']}",
        ':ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    // Mensagem de sucesso na sessão
    $_SESSION['mensagem'] = "Empresa '{$empresa['nome']}' foi suspensa com sucesso!";
    $_SESSION['tipo_mensagem'] = "success";
} else {
    $_SESSION['mensagem'] = "Empresa não encontrada";
    $_SESSION['tipo_mensagem'] = "error";
}

// Redirecionar para a lista de empresas
header('Location: index.php');
exit;
?>