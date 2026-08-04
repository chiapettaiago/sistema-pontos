<?php
// api/config.php - Configurações da API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Responder requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configurações
define('API_VERSION', '1.0.0');
define('API_NAME', 'PontoFácil API');
define('TOKEN_EXPIRY', 86400); // 24 horas em segundos

// Banco de dados
require_once '../config/database.php';

function getDB() {
    $database = new Database();
    return $database->getConnection();
}

// Função para responder JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para responder erro
function jsonError($message, $code = 400, $status = 400) {
    jsonResponse([
        'success' => false,
        'error' => $message,
        'code' => $code
    ], $status);
}

// Função para responder sucesso
function jsonSuccess($data = null, $message = null) {
    $response = ['success' => true];
    if ($message) $response['message'] = $message;
    if ($data) $response['data'] = $data;
    jsonResponse($response);
}

// Validação de campos obrigatórios
function validateRequired($data, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        jsonError('Campos obrigatórios faltando: ' . implode(', ', $missing));
    }
    return true;
}

// Autenticação via Bearer Token
function authenticate() {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($auth)) {
        jsonError('Token de autenticação não fornecido', 'MISSING_TOKEN', 401);
    }
    
    $token = str_replace('Bearer ', '', $auth);
    
    $db = getDB();
    $stmt = $db->prepare("SELECT u.*, f.id as funcionario_id 
                          FROM usuarios_sistema u
                          LEFT JOIN funcionarios f ON u.email = f.email
                          WHERE u.api_token = :token AND u.status = 'ativo'");
    $stmt->execute([':token' => $token]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        jsonError('Token inválido ou expirado', 'INVALID_TOKEN', 401);
    }
    
    return $usuario;
}

// Gerar token aleatório
function generateToken($length = 60) {
    return bin2hex(random_bytes($length));
}

// Validar CPF
function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11) return false;
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;
    return true;
}
?>