<?php
// modules/filiais/excluir.php - Desativar/Reativar Filial
require_once '../../config/database.php';

redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;
$reativar = isset($_GET['reativar']) ? true : false;

// Buscar filial
$stmt = $db->prepare("SELECT nome_fantasia, ativo FROM filiais WHERE id = :id");
$stmt->execute([':id' => $id]);
$filial = $stmt->fetch();

if (!$filial) {
    header('Location: index.php');
    exit;
}

if ($reativar) {
    // Reativar filial
    $stmt = $db->prepare("UPDATE filiais SET ativo = 1 WHERE id = :id");
    $stmt->execute([':id' => $id]);
    logAcao($db, 'UPDATE', 'filiais', $id, "Reativou filial: {$filial['nome_fantasia']}");
    $_SESSION['message'] = "Filial reativada com sucesso!";
} else {
    // Desativar filial
    $stmt = $db->prepare("UPDATE filiais SET ativo = 0 WHERE id = :id");
    $stmt->execute([':id' => $id]);
    logAcao($db, 'UPDATE', 'filiais', $id, "Desativou filial: {$filial['nome_fantasia']}");
    $_SESSION['message'] = "Filial desativada com sucesso!";
}

header('Location: index.php');
exit;
?>