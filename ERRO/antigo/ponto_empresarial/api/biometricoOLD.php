<?php
// api/biometrico.php - API para Biometria
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';
require_once '../includes/auth.php';

session_start();

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$acao = $input['acao'] ?? $_GET['acao'] ?? '';

// ============================================
// SALVAR DESCRITORES FACIAIS
// ============================================
if ($acao === 'salvar_facial') {
    if (!isset($_SESSION['usuario_id'])) {
        sendResponse(['error' => 'Não autorizado'], 401);
    }
    
    $funcionario_id = $input['funcionario_id'] ?? 0;
    $descritores = $input['descritores'] ?? [];
    
    if (!$funcionario_id || empty($descritores)) {
        sendResponse(['error' => 'Dados incompletos'], 400);
    }
    
    $descritores_json = json_encode($descritores);
    
    try {
        $check = $db->prepare("SELECT id FROM biometricos_faciais WHERE funcionario_id = :funcionario_id");
        $check->execute([':funcionario_id' => $funcionario_id]);
        
        if ($check->fetch()) {
            $query = "UPDATE biometricos_faciais SET descritores = :descritores, amostras = :amostras, updated_at = NOW() 
                      WHERE funcionario_id = :funcionario_id";
        } else {
            $query = "INSERT INTO biometricos_faciais (funcionario_id, descritores, amostras) 
                      VALUES (:funcionario_id, :descritores, :amostras)";
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':funcionario_id' => $funcionario_id,
            ':descritores' => $descritores_json,
            ':amostras' => count($descritores)
        ]);
        
        sendResponse(['success' => true, 'message' => 'Descritores salvos com sucesso']);
    } catch (Exception $e) {
        sendResponse(['error' => $e->getMessage()], 500);
    }
}

// ============================================
// REGISTRAR PONTO POR FACE
// ============================================
if ($acao === 'registrar_ponto_facial') {
    $descritor_atual = $input['descritor'] ?? [];
    
    if (empty($descritor_atual)) {
        sendResponse(['error' => 'Nenhum descritor recebido'], 400);
    }
    
    $query = "SELECT bf.funcionario_id, bf.descritores, f.nome, f.matricula, f.filial_id
              FROM biometricos_faciais bf
              JOIN funcionarios f ON bf.funcionario_id = f.id
              WHERE bf.ativo = 1 AND f.status = 'ativo'";
    $stmt = $db->query($query);
    $faces_cadastradas = $stmt->fetchAll();
    
    $melhor_match = null;
    $melhor_score = 0;
    $limiar = 0.6;
    
    foreach ($faces_cadastradas as $face) {
        $descritores_salvos = json_decode($face['descritores'], true);
        if (empty($descritores_salvos)) continue;
        
        $melhor_score_amostra = 0;
        foreach ($descritores_salvos as $amostra) {
            $score = calcularSimilaridadeCosseno($descritor_atual, $amostra);
            if ($score > $melhor_score_amostra) {
                $melhor_score_amostra = $score;
            }
        }
        
        if ($melhor_score_amostra > $melhor_score && $melhor_score_amostra > $limiar) {
            $melhor_score = $melhor_score_amostra;
            $melhor_match = $face;
        }
    }
    
    if ($melhor_match) {
        $funcionario_id = $melhor_match['funcionario_id'];
        $filial_id = $melhor_match['filial_id'];
        $tipo = determinarTipoPonto($db, $funcionario_id);
        
        if ($tipo) {
            $query = "INSERT INTO pontos (funcionario_id, filial_id, tipo, data_hora, origem) 
                      VALUES (:funcionario_id, :filial_id, :tipo, NOW(), 'biometrico_facial')";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':funcionario_id' => $funcionario_id,
                ':filial_id' => $filial_id,
                ':tipo' => $tipo
            ]);
            
            sendResponse([
                'success' => true,
                'message' => 'Ponto registrado com sucesso',
                'funcionario' => $melhor_match['nome'],
                'tipo' => $tipo,
                'score' => $melhor_score
            ]);
        } else {
            sendResponse(['error' => 'Dia já finalizado'], 400);
        }
    } else {
        sendResponse(['error' => 'Rosto não reconhecido'], 401);
    }
}

// ============================================
// FUNÇÕES AUXILIARES
// ============================================
function calcularSimilaridadeCosseno($vecA, $vecB) {
    if (empty($vecA) || empty($vecB) || count($vecA) !== count($vecB)) {
        return 0;
    }
    
    $dot = 0;
    $normA = 0;
    $normB = 0;
    
    for ($i = 0; $i < count($vecA); $i++) {
        $dot += $vecA[$i] * $vecB[$i];
        $normA += $vecA[$i] * $vecA[$i];
        $normB += $vecB[$i] * $vecB[$i];
    }
    
    if ($normA == 0 || $normB == 0) return 0;
    
    return $dot / (sqrt($normA) * sqrt($normB));
}

function determinarTipoPonto($db, $funcionario_id) {
    $query = "SELECT tipo FROM pontos 
              WHERE funcionario_id = :id AND DATE(data_hora) = CURDATE()
              ORDER BY data_hora DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $funcionario_id]);
    $ultimo = $stmt->fetch();
    
    if (!$ultimo) {
        return 'entrada';
    }
    
    $sequencia = [
        'entrada' => 'saida_almoco',
        'saida_almoco' => 'volta_almoco',
        'volta_almoco' => 'saida'
    ];
    
    return $sequencia[$ultimo['tipo']] ?? null;
}

sendResponse(['error' => 'Ação não reconhecida'], 400);
?>