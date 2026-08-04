<?php
// modules/ponto/registrar_ponto_celular.php - Registrar ponto via celular
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['funcionario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$funcionario_id = $_SESSION['funcionario_id'];
$tipo = $input['tipo'] ?? '';
$latitude = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;
$token_biometrico = $input['token_biometrico'] ?? null;
$manual = $input['manual'] ?? false;

// Validar tipo
$tipos_validos = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
if (!in_array($tipo, $tipos_validos)) {
    echo json_encode(['success' => false, 'message' => 'Tipo inválido']);
    exit;
}

// Verificar sequência
$stmt = $db->prepare("SELECT tipo FROM pontos 
                      WHERE funcionario_id = :id AND DATE(data_hora) = CURDATE() 
                      ORDER BY data_hora DESC LIMIT 1");
$stmt->execute([':id' => $funcionario_id]);
$ultimo = $stmt->fetch();

$sequencia = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
$indice_atual = array_search($tipo, $sequencia);

if ($ultimo) {
    $indice_anterior = array_search($ultimo['tipo'], $sequencia);
    if ($indice_atual != $indice_anterior + 1) {
        echo json_encode(['success' => false, 'message' => 'Sequência incorreta', 'solicitar_autorizacao' => true]);
        exit;
    }
}

// Verificar horário
$hora_atual = date('H:i:s');
if ($hora_atual < '06:00:00' || $hora_atual > '22:00:00') {
    echo json_encode(['success' => false, 'message' => 'Fora do horário permitido', 'solicitar_autorizacao' => true]);
    exit;
}

// Buscar filial
$stmt = $db->prepare("SELECT filial_id FROM funcionarios WHERE id = :id");
$stmt->execute([':id' => $funcionario_id]);
$filial_id = $stmt->fetchColumn();

// Registrar ponto
try {
    $stmt = $db->prepare("INSERT INTO pontos 
                          (funcionario_id, filial_id, tipo, data_hora, latitude, longitude, origem) 
                          VALUES 
                          (:funcionario_id, :filial_id, :tipo, NOW(), :latitude, :longitude, 'celular')");
    
    $stmt->execute([
        ':funcionario_id' => $funcionario_id,
        ':filial_id' => $filial_id,
        ':tipo' => $tipo,
        ':latitude' => $latitude,
        ':longitude' => $longitude
    ]);
    
    $nomes = [
        'entrada' => 'Entrada',
        'saida_almoco' => 'Saída para Almoço',
        'volta_almoco' => 'Volta do Almoço',
        'saida' => 'Saída'
    ];
    
    echo json_encode(['success' => true, 'message' => $nomes[$tipo] . ' registrada com sucesso!']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>