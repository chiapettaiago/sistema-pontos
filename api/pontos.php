<?php
// api/pontos.php - Registro e consulta de pontos
require_once 'config.php';

$user = authenticate();
$db = getDB();

// Buscar funcionário_id
$funcionario_id = $user['funcionario_id'] ?? null;
if (!$funcionario_id) {
    $stmt = $db->prepare("SELECT id FROM funcionarios WHERE email = :email");
    $stmt->execute([':email' => $user['email']]);
    $func = $stmt->fetch();
    $funcionario_id = $func ? $func['id'] : null;
}

if (!$funcionario_id) {
    jsonError('Perfil de funcionário não encontrado', 'FUNCIONARIO_NOT_FOUND', 404);
}

// POST - Registrar ponto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    validateRequired($input, ['tipo']);
    
    $tipo = $input['tipo'];
    $latitude = $input['latitude'] ?? null;
    $longitude = $input['longitude'] ?? null;
    $foto_base64 = $input['foto_base64'] ?? null;
    
    $tipos_validos = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
    if (!in_array($tipo, $tipos_validos)) {
        jsonError('Tipo de ponto inválido', 'INVALID_TYPE', 400);
    }
    
    // Verificar se já existe ponto do mesmo tipo hoje
    $stmt = $db->prepare("SELECT id FROM pontos 
                          WHERE funcionario_id = :id 
                          AND tipo = :tipo 
                          AND DATE(data_hora) = CURDATE()");
    $stmt->execute([':id' => $funcionario_id, ':tipo' => $tipo]);
    
    if ($stmt->fetch()) {
        jsonError('Você já registrou este ponto hoje', 'DUPLICATE_POINT', 400);
    }
    
    // Verificar sequência
    $stmt = $db->prepare("SELECT tipo FROM pontos 
                          WHERE funcionario_id = :id 
                          AND DATE(data_hora) = CURDATE() 
                          ORDER BY data_hora DESC LIMIT 1");
    $stmt->execute([':id' => $funcionario_id]);
    $ultimo = $stmt->fetch();
    
    $sequencia = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
    $indice_atual = array_search($tipo, $sequencia);
    
    if ($ultimo) {
        $indice_anterior = array_search($ultimo['tipo'], $sequencia);
        if ($indice_atual != $indice_anterior + 1) {
            $proximo = $sequencia[$indice_anterior + 1] ?? null;
            $msg = $proximo ? "Próximo ponto deve ser: " . $proximo : "Sequência inválida";
            jsonError($msg, 'INVALID_SEQUENCE', 400);
        }
    }
    
    // Salvar foto se fornecida
    $foto_path = null;
    if ($foto_base64) {
        $uploadDir = '../uploads/pontos_facial/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $foto_nome = $funcionario_id . '_' . date('Ymd_His') . '.jpg';
        $foto_path = 'uploads/pontos_facial/' . $foto_nome;
        $foto_data = str_replace('data:image/jpeg;base64,', '', $foto_base64);
        $foto_data = str_replace(' ', '+', $foto_data);
        file_put_contents('../' . $foto_path, base64_decode($foto_data));
    }
    
    // Buscar filial do funcionário
    $stmt = $db->prepare("SELECT filial_id FROM funcionarios WHERE id = :id");
    $stmt->execute([':id' => $funcionario_id]);
    $filial_id = $stmt->fetchColumn();
    
    // Registrar ponto
    $stmt = $db->prepare("INSERT INTO pontos 
                          (funcionario_id, filial_id, tipo, data_hora, latitude, longitude, foto_facial, origem) 
                          VALUES 
                          (:funcionario_id, :filial_id, :tipo, NOW(), :latitude, :longitude, :foto, 'api')");
    
    $stmt->execute([
        ':funcionario_id' => $funcionario_id,
        ':filial_id' => $filial_id,
        ':tipo' => $tipo,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':foto' => $foto_path
    ]);
    
    $nomes = [
        'entrada' => 'Entrada',
        'saida_almoco' => 'Saída Almoço',
        'volta_almoco' => 'Volta Almoço',
        'saida' => 'Saída'
    ];
    
    jsonSuccess([
        'id' => $db->lastInsertId(),
        'tipo' => $tipo,
        'tipo_nome' => $nomes[$tipo],
        'data_hora' => date('Y-m-d H:i:s'),
        'foto' => $foto_path
    ], $nomes[$tipo] . ' registrada com sucesso!');
}

// GET - Consultar pontos do dia
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = $_GET['data'] ?? date('Y-m-d');
    $tipo = $_GET['tipo'] ?? '';
    
    $query = "SELECT p.*, 
              DATE_FORMAT(p.data_hora, '%H:%i:%s') as hora
              FROM pontos p
              WHERE p.funcionario_id = :id AND DATE(p.data_hora) = :data";
    
    $params = [':id' => $funcionario_id, ':data' => $data];
    
    if ($tipo) {
        $query .= " AND p.tipo = :tipo";
        $params[':tipo'] = $tipo;
    }
    
    $query .= " ORDER BY p.data_hora ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $pontos = $stmt->fetchAll();
    
    $tipos = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
    $pontos_map = [];
    foreach ($pontos as $p) {
        $pontos_map[$p['tipo']] = $p;
    }
    
    jsonSuccess([
        'data' => $data,
        'pontos' => $pontos,
        'status' => [
            'entrada' => isset($pontos_map['entrada']),
            'saida_almoco' => isset($pontos_map['saida_almoco']),
            'volta_almoco' => isset($pontos_map['volta_almoco']),
            'saida' => isset($pontos_map['saida'])
        ]
    ]);
}
?>