<?php
// modules/notificacoes/buscar.php - Buscar contador de notificações não lidas (AJAX)
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'total' => 0]);
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$usuario_id = $_SESSION['usuario_id'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0");
$stmt->execute([':usuario_id' => $usuario_id]);
$total = $stmt->fetch()['total'];

echo json_encode(['success' => true, 'total' => $total]);
?>