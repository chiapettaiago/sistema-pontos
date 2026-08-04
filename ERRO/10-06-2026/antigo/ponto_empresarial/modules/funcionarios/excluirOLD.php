<?php
// modules/funcionarios/excluir.php - Desligar/Reativar Funcionário
require_once '../../config/database.php';

checkModuleAccess('funcionarios');

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;
$reativar = isset($_GET['reativar']) ? true : false;

// Buscar funcionário
$stmt = $db->prepare("SELECT nome, status, filial_id FROM funcionarios WHERE id = :id");
$stmt->execute([':id' => $id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    header('Location: index.php');
    exit;
}

// Verificar permissão (gestor só pode desligar funcionários da sua filial)
if ($_SESSION['usuario_tipo'] !== 'admin' && $funcionario['filial_id'] != $_SESSION['usuario_filial_id']) {
    header('Location: index.php');
    exit;
}

if ($reativar) {
    // Reativar funcionário
    $stmt = $db->prepare("UPDATE funcionarios SET status = 'ativo', data_demissao = NULL WHERE id = :id");
    $stmt->execute([':id' => $id]);
    logAcao($db, 'UPDATE', 'funcionarios', $id, "Reativou funcionário: {$funcionario['nome']}");
    $_SESSION['message'] = "Funcionário reativado com sucesso!";
} else {
    // Desligar funcionário
    $data_demissao = date('Y-m-d');
    $stmt = $db->prepare("UPDATE funcionarios SET status = 'desligado', data_demissao = :data_demissao WHERE id = :id");
    $stmt->execute([':data_demissao' => $data_demissao, ':id' => $id]);
    logAcao($db, 'UPDATE', 'funcionarios', $id, "Desligou funcionário: {$funcionario['nome']}");
    $_SESSION['message'] = "Funcionário desligado com sucesso!";
}

header('Location: index.php');
exit;
?>