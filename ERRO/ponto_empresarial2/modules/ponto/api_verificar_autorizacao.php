<?php
// modules/ponto/api_verificar_autorizacao.php - Verificar autorização
session_start();
header('Content-Type: application/json');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$token = $_GET['token'] ?? '';

if (!$token) {
    echo json_encode(['autorizado' => false, 'rejeitado' => false]);
    exit;
}

$stmt = $db->prepare("SELECT status FROM autorizacoes_ponto WHERE token = :token");
$stmt->execute([':token' => $token]);
$solicitacao = $stmt->fetch();

if (!$solicitacao) {
    echo json_encode(['autorizado' => false, 'rejeitado' => false]);
    exit;
}

echo json_encode([
    'autorizado' => $solicitacao['status'] === 'aprovado',
    'rejeitado' => $solicitacao['status'] === 'rejeitado'
]);
?>