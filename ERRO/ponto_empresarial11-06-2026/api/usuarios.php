<?php
// api/usuarios.php - Buscar dados do usuário
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
    $stmt = $db->prepare("SELECT id, nome, email, tipo, empresa_id, status FROM usuarios_sistema WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $usuario = $stmt->fetch();
    echo json_encode($usuario);
} else {
    echo json_encode(['error' => 'ID não informado']);
}
?>