<?php
// modules/ponto/processar_facial.php - Processar ponto com reconhecimento facial
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$tipo = $input['tipo'] ?? '';
$foto = $input['foto'] ?? '';
$funcionario_id = $input['funcionario_id'] ?? $_SESSION['funcionario_id'] ?? null;

// Validar tipo
$tipos_validos = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
if (!in_array($tipo, $tipos_validos)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de ponto inválido']);
    exit;
}

if (!$funcionario_id) {
    echo json_encode(['success' => false, 'message' => 'Funcionário não identificado']);
    exit;
}

// Buscar funcionário
$stmt = $db->prepare("SELECT * FROM funcionarios WHERE id = :id");
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado']);
    exit;
}

// Verificar sequência de pontos
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
        echo json_encode(['success' => false, 'message' => 'Sequência incorreta!']);
        exit;
    }
}

// Salvar foto facial
$foto_path = null;
if ($foto) {
    $uploadDir = '../../uploads/pontos_facial/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $foto_nome = $funcionario_id . '_' . date('Ymd_His') . '.jpg';
    $foto_path = 'uploads/pontos_facial/' . $foto_nome;
    $foto_data = str_replace('data:image/jpeg;base64,', '', $foto);
    $foto_data = str_replace(' ', '+', $foto_data);
    file_put_contents('../../' . $foto_path, base64_decode($foto_data));
}

// Registrar ponto
try {
    $stmt = $db->prepare("INSERT INTO pontos 
                          (funcionario_id, filial_id, tipo, data_hora, foto_facial, origem) 
                          VALUES (:funcionario_id, :filial_id, :tipo, NOW(), :foto, 'facial')");
    $stmt->execute([
        ':funcionario_id' => $funcionario_id,
        ':filial_id' => $funcionario['filial_id'],
        ':tipo' => $tipo,
        ':foto' => $foto_path
    ]);
    
    $nomes = [
        'entrada' => 'Entrada',
        'saida_almoco' => 'Saída Almoço',
        'volta_almoco' => 'Volta Almoço',
        'saida' => 'Saída'
    ];
    
    echo json_encode(['success' => true, 'message' => $nomes[$tipo] . ' registrada com sucesso!']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>