<?php

require_once __DIR__ . '/../vendor/autoload.php';

use lbuchs\WebAuthn\WebAuthn;

function passkeyJson(bool $success, array $data = [], string $message = '', int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function passkeyEnsureTable(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS credenciais_passkey (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        usuario_tipo ENUM('sistema','funcionario') NOT NULL,
        usuario_id INT NOT NULL,
        credencial_id VARBINARY(1024) NOT NULL,
        chave_publica TEXT NOT NULL,
        contador_assinatura BIGINT UNSIGNED NOT NULL DEFAULT 0,
        transportes VARCHAR(255) NULL,
        nome VARCHAR(100) NOT NULL DEFAULT 'Meu dispositivo',
        ultimo_uso DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_passkey_credencial (credencial_id(255)),
        KEY idx_passkey_usuario (usuario_tipo, usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function passkeyRpId(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? parse_url(SITE_URL, PHP_URL_HOST) ?? 'localhost'));
    return preg_replace('/:\d+$/', '', $host);
}

function passkeyServer(): WebAuthn
{
    return new WebAuthn(SITE_NAME, passkeyRpId(), ['none'], true);
}

function passkeyDecode(?string $value): string
{
    $decoded = base64_decode((string) $value, true);
    if ($decoded === false) {
        throw new InvalidArgumentException('Dado WebAuthn inválido.');
    }
    return $decoded;
}

function passkeyUserHandle(string $tipo, int $id): string
{
    return hash_hmac('sha256', $tipo . ':' . $id, DB_PASS, true);
}

function passkeySessionIdentity(): array
{
    if (empty($_SESSION['usuario_id'])) {
        throw new RuntimeException('Faça login antes de cadastrar este dispositivo.');
    }
    $tipoLogin = (string) ($_SESSION['tipo_login'] ?? '');
    $tipo = in_array($tipoLogin, ['funcionario', 'facial'], true) ? 'funcionario' : 'sistema';
    return [
        'tipo' => $tipo,
        'id' => (int) $_SESSION['usuario_id'],
        'nome' => (string) ($_SESSION['usuario_nome'] ?? 'Usuário'),
        'email' => (string) ($_SESSION['usuario_email'] ?? ''),
    ];
}

function passkeyFinishLogin(PDO $db, array $credencial): string
{
    session_regenerate_id(true);
    if ($credencial['usuario_tipo'] === 'funcionario') {
        $stmt = $db->prepare("SELECT f.*, e.nome_empresa empresa_nome FROM funcionarios f LEFT JOIN empresa e ON e.id=f.empresa_id WHERE f.id=:id AND f.status='ativo' LIMIT 1");
        $stmt->execute([':id' => $credencial['usuario_id']]);
        $f = $stmt->fetch();
        if (!$f) throw new RuntimeException('Colaborador inativo ou não encontrado.');
        $_SESSION['usuario_id']=$f['id']; $_SESSION['usuario_nome']=$f['nome']; $_SESSION['usuario_email']=$f['email']; $_SESSION['usuario_tipo']=appNormalizeUserType($f['tipo_usuario'] ?? 'funcionario');
        $_SESSION['empresa_id']=$f['empresa_id']; $_SESSION['empresa_nome']=$f['empresa_nome'] ?? null; $_SESSION['filial_id']=$f['filial_id'] ?? null; $_SESSION['usuario_filial_id']=$f['filial_id'] ?? null;
        $_SESSION['funcionario_id']=$f['id']; $_SESSION['tipo_login']='funcionario'; $_SESSION['funcionario_nome']=$f['nome']; $_SESSION['funcionario_email']=$f['email']; $_SESSION['funcionario_empresa_id']=$f['empresa_id'];
        return appUrl(appDestinationAfterLogin($_SESSION['usuario_tipo']));
    }

    $stmt = $db->prepare("SELECT u.*, e.nome empresa_nome, f.id funcionario_id, f.filial_id funcionario_filial_id FROM usuarios_sistema u LEFT JOIN empresas e ON e.id=u.empresa_id LEFT JOIN funcionarios f ON f.usuario_sistema_id=u.id AND f.status='ativo' WHERE u.id=:id AND u.status='ativo' LIMIT 1");
    $stmt->execute([':id' => $credencial['usuario_id']]);
    $u = $stmt->fetch();
    if (!$u) {
        // Compatibilidade com passkeys cadastradas por colaboradores que
        // entraram via reconhecimento facial antes da correção do tipo.
        $credencial['usuario_tipo'] = 'funcionario';
        return passkeyFinishLogin($db, $credencial);
    }
    foreach (['id'=>'usuario_id','nome'=>'usuario_nome','email'=>'usuario_email','tipo'=>'usuario_tipo','empresa_id'=>'empresa_id'] as $key => $session) $_SESSION[$session] = $u[$key] ?? null;
    $_SESSION['empresa_nome']=$u['empresa_nome'] ?? null; $_SESSION['funcionario_id']=$u['funcionario_id'] ?? null; $_SESSION['filial_id']=$u['funcionario_filial_id'] ?? null; $_SESSION['usuario_filial_id']=$u['funcionario_filial_id'] ?? null; $_SESSION['tipo_login']='sistema';
    $_SESSION['user_id']=$u['id']; $_SESSION['user_nome']=$u['nome']; $_SESSION['user_email']=$u['email']; $_SESSION['user_tipo']=$u['tipo'];
    return appUrl(appDestinationAfterLogin($u['tipo']));
}
