<?php
// api/config.php - configuracao comum da API

header('Content-Type: application/json; charset=UTF-8');

$allowed_origins = [
    'http://localhost',
    'http://127.0.0.1',
    'http://localhost:3000',
    'http://localhost:8081',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:8081',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/config.php';

define('API_VERSION', '1.0.0');
define('API_NAME', 'Ponto Facil API');
define('TOKEN_EXPIRY', 28800);

function getDB() {
    global $pdo;
    return $pdo;
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($message, $code = 'ERROR', $status = 400) {
    jsonResponse([
        'success' => false,
        'error' => $message,
        'message' => $message,
        'code' => $code
    ], $status);
}

function jsonSuccess($data = null, $message = null) {
    $response = ['success' => true];
    if ($message !== null) {
        $response['message'] = $message;
    }
    if ($data !== null) {
        $response['data'] = $data;
        if (is_array($data)) {
            foreach (['token', 'expires_in', 'expires_at', 'usuario', 'user'] as $key) {
                if (array_key_exists($key, $data)) {
                    $response[$key] = $data[$key];
                }
            }
        }
    }
    jsonResponse($response);
}

function validateRequired($data, $fields) {
    if (!is_array($data)) {
        jsonError('JSON invalido', 'INVALID_JSON', 400);
    }

    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $missing[] = $field;
        }
    }

    if ($missing) {
        jsonError('Campos obrigatorios faltando: ' . implode(', ', $missing), 'MISSING_FIELDS', 400);
    }

    return true;
}

function apiColumnExists(PDO $db, $table, $column) {
    static $cache = [];
    $key = "{$table}.{$column}";
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column");
        $stmt->execute([':column' => $column]);
        $cache[$key] = (bool) $stmt->fetch();
    } catch (Exception $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function generateAPIToken($user_id, $user_type, $empresa_id = null) {
    $db = getDB();
    $token = generateToken(32);
    $tokenHash = hash('sha256', $token);
    $expires_at = date('Y-m-d H:i:s', time() + TOKEN_EXPIRY);

    try {
        $table = $user_type === 'admin' ? 'usuarios_sistema' : 'funcionarios';
        if (apiColumnExists($db, $table, 'token_expires_at')) {
            $stmt = $db->prepare("UPDATE {$table} SET api_token = :token, token_expires_at = :expires_at WHERE id = :id");
            $stmt->execute([':token' => $tokenHash, ':expires_at' => $expires_at, ':id' => $user_id]);
        } else {
            $stmt = $db->prepare("UPDATE {$table} SET api_token = :token WHERE id = :id");
            $stmt->execute([':token' => $tokenHash, ':id' => $user_id]);
        }

        return [
            'token' => $token,
            'expires_at' => $expires_at,
            'expires_in' => TOKEN_EXPIRY
        ];
    } catch (Exception $e) {
        error_log('Erro ao gerar token API: ' . $e->getMessage());
        return false;
    }
}

function validateAPIToken($token) {
    $db = getDB();
    $token = trim(str_replace('Bearer ', '', $token));

    if ($token === '') {
        return ['error' => 'Token nao fornecido', 'code' => 401];
    }

    try {
        $token = hash('sha256', $token);
        $stmt = $db->prepare("SELECT * FROM usuarios_sistema WHERE api_token = :token AND status = 'ativo'");
        $stmt->execute([':token' => $token]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            if (!empty($usuario['token_expires_at']) && strtotime($usuario['token_expires_at']) < time()) {
                return ['error' => 'Token expirado', 'code' => 401];
            }

            $tipo = $usuario['tipo'] === 'admin' ? 'admin_empresa' : $usuario['tipo'];
            $administrador = in_array($tipo, ['super_admin', 'admin_empresa'], true);
            $permissions = [
                'gerenciar_filiais' => $administrador,
                'gerenciar_funcionarios' => $administrador,
                'ver_relatorios' => $administrador,
            ];
            $funcionarioId = null;
            $linked = $db->prepare("SELECT id, pode_gerenciar_filiais, pode_gerenciar_funcionarios, pode_ver_relatorios FROM funcionarios WHERE usuario_sistema_id = :id AND status = 'ativo' LIMIT 1");
            $linked->execute([':id' => $usuario['id']]);
            if ($flags = $linked->fetch()) {
                $funcionarioId = (int) $flags['id'];
                $permissions = [
                    'gerenciar_filiais' => $administrador || (int) $flags['pode_gerenciar_filiais'] === 1,
                    'gerenciar_funcionarios' => $administrador || (int) $flags['pode_gerenciar_funcionarios'] === 1,
                    'ver_relatorios' => $administrador || (int) $flags['pode_ver_relatorios'] === 1,
                ];
            }

            return [
                'id' => $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'tipo' => $tipo,
                'tipo_usuario' => 'admin',
                'empresa_id' => $usuario['empresa_id'] ?? null,
                'funcionario_id' => $funcionarioId,
                'permissions' => $permissions,
            ];
        }

        $stmt = $db->prepare("SELECT * FROM funcionarios WHERE api_token = :token AND status = 'ativo'");
        $stmt->execute([':token' => $token]);
        $funcionario = $stmt->fetch();

        if ($funcionario) {
            if (!empty($funcionario['token_expires_at']) && strtotime($funcionario['token_expires_at']) < time()) {
                return ['error' => 'Token expirado', 'code' => 401];
            }

            $tipo = $funcionario['tipo_usuario'] === 'admin' ? 'admin_empresa' : ($funcionario['tipo_usuario'] ?? 'funcionario');
            return [
                'id' => $funcionario['id'],
                'nome' => $funcionario['nome'],
                'email' => $funcionario['email'],
                'tipo' => $tipo,
                'tipo_usuario' => 'funcionario',
                'empresa_id' => $funcionario['empresa_id'] ?? null,
                'filial_id' => $funcionario['filial_id'] ?? null,
                'funcionario_id' => $funcionario['id'],
                'permissions' => [
                    'gerenciar_filiais' => (int) ($funcionario['pode_gerenciar_filiais'] ?? 0) === 1,
                    'gerenciar_funcionarios' => (int) ($funcionario['pode_gerenciar_funcionarios'] ?? 0) === 1,
                    'ver_relatorios' => (int) ($funcionario['pode_ver_relatorios'] ?? 0) === 1,
                ],
            ];
        }

        return ['error' => 'Token invalido', 'code' => 401];
    } catch (Exception $e) {
        error_log('Erro ao validar token API: ' . $e->getMessage());
        return ['error' => 'Erro interno', 'code' => 500];
    }
}

function authenticate() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $token = trim(str_replace('Bearer ', '', $auth));

    $user = validateAPIToken($token);
    if (isset($user['error'])) {
        jsonError($user['error'], 'INVALID_TOKEN', (int) ($user['code'] ?? 401));
    }

    return $user;
}

function sendResponse($data, $status = 200) {
    jsonResponse($data, $status);
}

function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) !== 11) return false;
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;
    return true;
}
?>
