<?php
// modules/notificacoes/marcar_todas.php - Marcar todas notificações como lidas
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$usuario_id = $_SESSION['usuario_id'];

try {
    $stmt = $db->prepare("UPDATE notificacoes SET lida = 1, data_leitura = NOW() WHERE usuario_id = :usuario_id AND lida = 0");
    $stmt->execute([':usuario_id' => $usuario_id]);
    
    $_SESSION['mensagem'] = 'Todas as notificações foram marcadas como lidas!';
    $_SESSION['tipo_mensagem'] = 'success';
    
} catch (Exception $e) {
    $_SESSION['mensagem'] = 'Erro ao marcar notificações: ' . $e->getMessage();
    $_SESSION['tipo_mensagem'] = 'error';
}

header('Location: index.php');
exit;
?>