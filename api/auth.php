<?php
// api/auth.php - autenticacao para app/mobile
require_once __DIR__ . '/config.php';

$db = getDB();
$action = $_GET['action'] ?? 'login';

function senhaConfereApi(string $senhaDigitada, string $senhaSalva): bool {
    if (password_verify($senhaDigitada, $senhaSalva)) {
        return true;
    }

    $senhaSalvaLimpa = trim($senhaSalva);

    if (strlen($senhaSalvaLimpa) === 32 && ctype_xdigit($senhaSalvaLimpa)) {
        return hash_equals(strtolower($senhaSalvaLimpa), md5($senhaDigitada));
    }

    return hash_equals($senhaSalvaLimpa, $senhaDigitada);
}

if ($action === 'logout') {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $token = trim(str_replace('Bearer ', '', $headers['Authorization'] ?? $headers['authorization'] ?? ''));

    if ($token !== '') {
        $stmt = $db->prepare("UPDATE usuarios_sistema SET api_token = NULL WHERE api_token = :token");
        $stmt->execute([':token' => $token]);
        $stmt = $db->prepare("UPDATE funcionarios SET api_token = NULL WHERE api_token = :token");
        $stmt->execute([':token' => $token]);
    }

    jsonSuccess(null, 'Logout realizado');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = authenticate();
    jsonSuccess([
        'valid' => true,
        'usuario' => [
            'id' => $user['id'],
            'nome' => $user['nome'],
            'email' => $user['email'],
            'tipo' => $user['tipo'],
            'empresa_id' => $user['empresa_id'] ?? null,
            'funcionario_id' => $user['funcionario_id'] ?? null,
        ]
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Metodo nao permitido', 'METHOD_NOT_ALLOWED', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
validateRequired($input, ['senha']);

$email = trim($input['email'] ?? $input['login'] ?? '');
$senha = $input['senha'] ?? '';
$tipo = $input['tipo'] ?? null;

if ($email === '') {
    jsonError('Email obrigatorio', 'MISSING_EMAIL', 400);
}

try {
    if ($tipo !== 'funcionario') {
        $stmt = $db->prepare("SELECT u.*, e.nome as empresa_nome
                              FROM usuarios_sistema u
                              LEFT JOIN empresas e ON u.empresa_id = e.id
                              WHERE u.email = :email AND u.status = 'ativo'");
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

        if ($usuario && senhaConfereApi($senha, $usuario['senha'])) {
            $tokenData = generateAPIToken($usuario['id'], 'admin', $usuario['empresa_id'] ?? null);
            if (!$tokenData) {
                jsonError('Erro ao gerar token', 'TOKEN_ERROR', 500);
            }

            $update = $db->prepare("UPDATE usuarios_sistema SET ultimo_acesso = NOW() WHERE id = :id");
            $update->execute([':id' => $usuario['id']]);

            jsonSuccess([
                'token' => $tokenData['token'],
                'expires_at' => $tokenData['expires_at'],
                'expires_in' => $tokenData['expires_in'],
                'usuario' => [
                    'id' => $usuario['id'],
                    'nome' => $usuario['nome'],
                    'email' => $usuario['email'],
                    'tipo' => $usuario['tipo'],
                    'empresa_id' => $usuario['empresa_id'] ?? null,
                    'funcionario_id' => $usuario['funcionario_id'] ?? null,
                ],
                'user' => [
                    'id' => $usuario['id'],
                    'nome' => $usuario['nome'],
                    'email' => $usuario['email'],
                    'tipo' => $usuario['tipo'],
                    'empresa_id' => $usuario['empresa_id'] ?? null,
                ]
            ]);
        }
    }

    $stmt = $db->prepare("SELECT f.*
                          FROM funcionarios f
                          WHERE f.email = :email AND f.status = 'ativo'");
    $stmt->execute([':email' => $email]);
    $funcionario = $stmt->fetch();

    if ($funcionario && senhaConfereApi($senha, $funcionario['senha'])) {
        $tokenData = generateAPIToken($funcionario['id'], 'funcionario', $funcionario['empresa_id'] ?? null);
        if (!$tokenData) {
            jsonError('Erro ao gerar token', 'TOKEN_ERROR', 500);
        }

        jsonSuccess([
            'token' => $tokenData['token'],
            'expires_at' => $tokenData['expires_at'],
            'expires_in' => $tokenData['expires_in'],
            'usuario' => [
                'id' => $funcionario['id'],
                'nome' => $funcionario['nome'],
                'email' => $funcionario['email'],
                'tipo' => 'funcionario',
                'matricula' => $funcionario['matricula'] ?? null,
                'empresa_id' => $funcionario['empresa_id'] ?? null,
                'empresa' => $funcionario['empresa_nome'] ?? null,
                'foto' => !empty($funcionario['foto']) ? '/' . $funcionario['foto'] : null,
            ],
            'user' => [
                'id' => $funcionario['id'],
                'nome' => $funcionario['nome'],
                'email' => $funcionario['email'],
                'tipo' => 'funcionario',
                'empresa_id' => $funcionario['empresa_id'] ?? null,
            ]
        ]);
    }

    jsonError('Email ou senha invalidos', 'INVALID_CREDENTIALS', 401);
} catch (Exception $e) {
    error_log('Erro auth API: ' . $e->getMessage());
    jsonError('Erro interno do servidor', 'SERVER_ERROR', 500);
}
?>

