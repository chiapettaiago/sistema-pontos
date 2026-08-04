<?php
// api/planos.php - API para buscar dados do plano
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'super_admin') {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM planos WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $plano = $stmt->fetch();
    echo json_encode($plano);
} else {
    $stmt = $db->query("SELECT * FROM planos ORDER BY preco_mensal ASC");
    $planos = $stmt->fetchAll();
    echo json_encode($planos);
}
?>