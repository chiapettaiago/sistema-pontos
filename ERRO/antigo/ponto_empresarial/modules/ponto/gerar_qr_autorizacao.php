<?php
// modules/ponto/gerar_qr_autorizacao.php - Gerar QR Code para autorização do gerente
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$funcionario_id = $input['funcionario_id'] ?? $_SESSION['funcionario_id'];
$tipo = $input['tipo'] ?? '';
$latitude = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;

if (!$funcionario_id || !$tipo) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

// Buscar dados do funcionário
$stmt = $db->prepare("SELECT f.*, fi.nome_fantasia as filial_nome 
                      FROM funcionarios f
                      LEFT JOIN filiais fi ON f.filial_id = fi.id
                      WHERE f.id = :id");
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado']);
    exit;
}

// Gerar token único para autorização
$token = bin2hex(random_bytes(32));

// Salvar solicitação de autorização
$stmt = $db->prepare("INSERT INTO autorizacoes_ponto 
    (funcionario_id, tipo, latitude, longitude, token, status, created_at) 
    VALUES 
    (:funcionario_id, :tipo, :latitude, :longitude, :token, 'pendente', NOW())");

$stmt->execute([
    ':funcionario_id' => $funcionario_id,
    ':tipo' => $tipo,
    ':latitude' => $latitude,
    ':longitude' => $longitude,
    ':token' => $token
]);

// Gerar URL para o gerente autorizar
$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$url_autorizacao = $protocolo . '://' . $host . '/ponto_empresarial/modules/ponto/autorizar_gerente.php?token=' . $token;

// Gerar QR Code
$qr_url = "https://quickchart.io/qr?text=" . urlencode($url_autorizacao) . "&size=250&margin=2";

echo json_encode([
    'success' => true,
    'qr_code' => $qr_url,
    'token' => $token
]);
?>