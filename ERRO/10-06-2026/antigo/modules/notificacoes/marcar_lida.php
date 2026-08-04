<?php
// modules/notificacoes/marcar_lida.php - Marcar notificação como lida (AJAX)
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$usuario_id = $_SESSION['usuario_id'];
$id = $_POST['id'] ?? 0;
$excluir = isset($_POST['excluir']) ? true : false;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    if ($excluir) {
        $stmt = $db->prepare("DELETE FROM notificacoes WHERE id = :id AND usuario_id = :usuario_id");
        $stmt->execute([':id' => $id, ':usuario_id' => $usuario_id]);
    } else {
        $stmt = $db->prepare("UPDATE notificacoes SET lida = 1, data_leitura = NOW() WHERE id = :id AND usuario_id = :usuario_id");
        $stmt->execute([':id' => $id, ':usuario_id' => $usuario_id]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>