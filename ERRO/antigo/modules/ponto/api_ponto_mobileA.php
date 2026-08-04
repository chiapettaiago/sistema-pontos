<?php
// modules/ponto/api_ponto_mobile.php - API para bater ponto pelo celular
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Receber dados
$input = json_decode(file_get_contents('php://input'), true);
$dados = $input['dados'] ?? null;
$latitude = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;
$token = $input['token'] ?? null;

// Se veio QR Code com dados do funcionário
if ($dados) {
    // Decodificar os dados do QR Code
    $funcionario_data = json_decode(urldecode($dados), true);
    
    if ($funcionario_data && isset($funcionario_data['matricula'])) {
        // Buscar funcionário pela matrícula
        $stmt = $db->prepare("SELECT f.*, fi.nome_fantasia as filial_nome 
                              FROM funcionarios f
                              LEFT JOIN filiais fi ON f.filial_id = fi.id
                              WHERE f.matricula = :matricula AND f.status = 'ativo'");
        $stmt->execute([':matricula' => $funcionario_data['matricula']]);
        $funcionario = $stmt->fetch();
        
        if (!$funcionario) {
            echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado']);
            exit;
        }
        
        $funcionario_id = $funcionario['id'];
    } else {
        echo json_encode(['success' => false, 'message' => 'QR Code inválido']);
        exit;
    }
} elseif ($token) {
    // Token de sessão (caso já esteja logado)
    // Buscar funcionário pelo token
    $stmt = $db->prepare("SELECT f.* FROM funcionarios f WHERE f.api_token = :token");
    $stmt->execute([':token' => $token]);
    $funcionario = $stmt->fetch();
    
    if (!$funcionario) {
        echo json_encode(['success' => false, 'message' => 'Token inválido']);
        exit;
    }
    
    $funcionario_id = $funcionario['id'];
} else {
    echo json_encode(['success' => false, 'message' => 'Dados não fornecidos']);
    exit;
}

// Buscar pontos de hoje
$stmt = $db->prepare("SELECT tipo FROM pontos 
                      WHERE funcionario_id = :id AND DATE(data_hora) = CURDATE() 
                      ORDER BY data_hora ASC");
$stmt->execute([':id' => $funcionario_id]);
$pontosHoje = $stmt->fetchAll();

$tiposRegistrados = [];
foreach ($pontosHoje as $ponto) {
    $tiposRegistrados[] = $ponto['tipo'];
}

// Determinar próximo tipo de ponto
$sequencia = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
$proximo_tipo = 'entrada';

if (in_array('entrada', $tiposRegistrados) && !in_array('saida_almoco', $tiposRegistrados)) {
    $proximo_tipo = 'saida_almoco';
} elseif (in_array('saida_almoco', $tiposRegistrados) && !in_array('volta_almoco', $tiposRegistrados)) {
    $proximo_tipo = 'volta_almoco';
} elseif (in_array('volta_almoco', $tiposRegistrados) && !in_array('saida', $tiposRegistrados)) {
    $proximo_tipo = 'saida';
} elseif (in_array('entrada', $tiposRegistrados) && 
          in_array('saida_almoco', $tiposRegistrados) && 
          in_array('volta_almoco', $tiposRegistrados) && 
          in_array('saida', $tiposRegistrados)) {
    echo json_encode(['success' => false, 'message' => 'Dia finalizado!']);
    exit;
}

// Validar horário (06:00 às 22:00)
$hora_atual = date('H:i:s');
if ($hora_atual < '06:00:00' || $hora_atual > '22:00:00') {
    echo json_encode(['success' => false, 'message' => 'Fora do horário permitido (06:00 às 22:00)']);
    exit;
}

// Validar sequência
$ultimo_tipo = end($tiposRegistrados);
if ($ultimo_tipo == 'entrada' && $proximo_tipo != 'saida_almoco') {
    echo json_encode(['success' => false, 'message' => 'Registre a saída para almoço primeiro']);
    exit;
}
if ($ultimo_tipo == 'saida_almoco' && $proximo_tipo != 'volta_almoco') {
    echo json_encode(['success' => false, 'message' => 'Registre a volta do almoço primeiro']);
    exit;
}

// Registrar ponto
try {
    $stmt = $db->prepare("INSERT INTO pontos 
                          (funcionario_id, filial_id, tipo, data_hora, latitude, longitude, origem) 
                          VALUES 
                          (:funcionario_id, :filial_id, :tipo, NOW(), :latitude, :longitude, 'mobile_qr')");
    
    $stmt->execute([
        ':funcionario_id' => $funcionario_id,
        ':filial_id' => $funcionario['filial_id'],
        ':tipo' => $proximo_tipo,
        ':latitude' => $latitude,
        ':longitude' => $longitude
    ]);
    
    $nomes = [
        'entrada' => 'Entrada',
        'saida_almoco' => 'Saída para Almoço',
        'volta_almoco' => 'Volta do Almoço',
        'saida' => 'Saída'
    ];
    
    echo json_encode([
        'success' => true,
        'message' => $nomes[$proximo_tipo] . ' registrada com sucesso!',
        'tipo' => $proximo_tipo,
        'data_hora' => date('Y-m-d H:i:s'),
        'latitude' => $latitude,
        'longitude' => $longitude
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>