<?php
// api/auth.php - Autenticação e geração de token
require_once 'config.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    validateRequired($input, ['email', 'senha']);
    
    $email = trim($input['email']);
    $senha = $input['senha'];
    
    // Buscar usuário
    $stmt = $db->prepare("SELECT u.*, f.id as funcionario_id, f.nome as funcionario_nome, f.foto
                          FROM usuarios_sistema u
                          LEFT JOIN funcionarios f ON u.email = f.email
                          WHERE u.email = :email AND u.status = 'ativo'");
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        // Tentar como funcionário
        $stmt = $db->prepare("SELECT f.*, e.nome_empresa as empresa_nome
                              FROM funcionarios f
                              LEFT JOIN empresa e ON f.empresa_id = e.id
                              WHERE f.email = :email AND f.status = 'ativo'");
        $stmt->execute([':email' => $email]);
        $funcionario = $stmt->fetch();
        
        if ($funcionario && password_verify($senha, $funcionario['senha'])) {
            // Criar token para funcionário
            $token = generateToken();
            
            // Salvar token
            $stmt = $db->prepare("UPDATE funcionarios SET api_token = :token WHERE id = :id");
            $stmt->execute([':token' => $token, ':id' => $funcionario['id']]);
            
            jsonSuccess([
                'token' => $token,
                'expires_in' => TOKEN_EXPIRY,
                'usuario' => [
                    'id' => $funcionario['id'],
                    'nome' => $funcionario['nome'],
                    'email' => $funcionario['email'],
                    'tipo' => 'funcionario',
                    'matricula' => $funcionario['matricula'],
                    'empresa' => $funcionario['empresa_nome'],
                    'foto' => $funcionario['foto'] ? '/ponto_empresarial/' . $funcionario['foto'] : null
                ]
            ]);
        } else {
            jsonError('E-mail ou senha inválidos', 'INVALID_CREDENTIALS', 401);
        }
        exit;
    }
    
    // Verificar senha do usuário do sistema
    if (!password_verify($senha, $usuario['senha'])) {
        jsonError('E-mail ou senha inválidos', 'INVALID_CREDENTIALS', 401);
    }
    
    // Gerar token
    $token = generateToken();
    
    // Salvar token
    $stmt = $db->prepare("UPDATE usuarios_sistema SET api_token = :token, ultimo_acesso = NOW() WHERE id = :id");
    $stmt->execute([':token' => $token, ':id' => $usuario['id']]);
    
    jsonSuccess([
        'token' => $token,
        'expires_in' => TOKEN_EXPIRY,
        'usuario' => [
            'id' => $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'tipo' => $usuario['tipo'],
            'funcionario_id' => $usuario['funcionario_id'],
            'empresa_id' => $usuario['empresa_id']
        ]
    ]);
}

// GET - validar token (para verificar se o token ainda é válido)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = authenticate();
    jsonSuccess([
        'valid' => true,
        'usuario' => [
            'id' => $user['id'],
            'nome' => $user['nome'],
            'email' => $user['email'],
            'tipo' => $user['tipo']
        ]
    ]);
}
?>