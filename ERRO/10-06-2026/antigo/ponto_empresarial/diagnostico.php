<?php
session_start();

require_once __DIR__ . '/config/database.php';

$resultado = [
    'data_hora' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'https' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'sim' : 'nao',
    'host' => $_SERVER['HTTP_HOST'] ?? '',
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'script' => $_SERVER['SCRIPT_NAME'] ?? '',
    'cookie_path' => session_get_cookie_params()['path'] ?? '',
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_save_path' => ini_get('session.save_path'),
    'session_name' => session_name(),
    'usuario_id' => $_SESSION['usuario_id'] ?? null,
    'usuario_tipo' => $_SESSION['usuario_tipo'] ?? null,
    'empresa_id' => $_SESSION['empresa_id'] ?? null,
];

$testeBanco = null;
$erroBanco = null;

try {
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->query('SELECT 1 AS ok');
    $testeBanco = $stmt->fetch()['ok'] ?? null;
} catch (Exception $e) {
    $erroBanco = $e->getMessage();
}

if (isset($_GET['set_session'])) {
    $_SESSION['usuario_id'] = 999999;
    $_SESSION['usuario_tipo'] = 'diagnostico';
    $_SESSION['empresa_id'] = 1;
    header('Location: diagnostico.php');
    exit;
}

if (isset($_GET['clear_session'])) {
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 3600,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            $params['secure'] ?? false,
            $params['httponly'] ?? true
        );
    }
    session_destroy();
    header('Location: diagnostico.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostico do Sistema</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f6f7fb; color: #111827; padding: 24px; }
        .card { max-width: 900px; margin: 0 auto 16px; background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 6px 24px rgba(0,0,0,.08); }
        h1, h2 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        td:first-child { width: 240px; font-weight: bold; color: #374151; }
        .ok { color: #059669; font-weight: bold; }
        .erro { color: #dc2626; font-weight: bold; }
        .acoes a { display: inline-block; margin-right: 10px; padding: 10px 14px; background: #667eea; color: #fff; text-decoration: none; border-radius: 10px; }
        .acoes a.sec { background: #6b7280; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Diagnostico rapido</h1>
        <p>Abra esta pagina no servidor e veja se a sessao, o banco e o caminho estao corretos.</p>
        <div class="acoes">
            <a href="diagnostico.php?set_session=1">Gravar sessao teste</a>
            <a href="diagnostico.php?clear_session=1" class="sec">Limpar sessao</a>
            <a href="login.php" class="sec">Ir para login</a>
            <a href="logout.php">Testar logout</a>
        </div>
    </div>

    <div class="card">
        <h2>Estado atual</h2>
        <table>
            <tr><td>Data e hora</td><td><?php echo htmlspecialchars($resultado['data_hora']); ?></td></tr>
            <tr><td>PHP</td><td><?php echo htmlspecialchars($resultado['php_version']); ?></td></tr>
            <tr><td>HTTPS</td><td><?php echo htmlspecialchars($resultado['https']); ?></td></tr>
            <tr><td>Host</td><td><?php echo htmlspecialchars($resultado['host']); ?></td></tr>
            <tr><td>URI</td><td><?php echo htmlspecialchars($resultado['uri']); ?></td></tr>
            <tr><td>Script</td><td><?php echo htmlspecialchars($resultado['script']); ?></td></tr>
            <tr><td>Session ID</td><td><code><?php echo htmlspecialchars($resultado['session_id']); ?></code></td></tr>
            <tr><td>Session name</td><td><?php echo htmlspecialchars($resultado['session_name']); ?></td></tr>
            <tr><td>Session save path</td><td><?php echo htmlspecialchars((string) $resultado['session_save_path']); ?></td></tr>
            <tr><td>Cookie path</td><td><?php echo htmlspecialchars($resultado['cookie_path']); ?></td></tr>
            <tr><td>Usuario ID</td><td><?php echo htmlspecialchars((string) $resultado['usuario_id']); ?></td></tr>
            <tr><td>Usuario tipo</td><td><?php echo htmlspecialchars((string) $resultado['usuario_tipo']); ?></td></tr>
            <tr><td>Empresa ID</td><td><?php echo htmlspecialchars((string) $resultado['empresa_id']); ?></td></tr>
        </table>
    </div>

    <div class="card">
        <h2>Banco de dados</h2>
        <?php if ($erroBanco): ?>
            <p class="erro">Falha ao conectar: <?php echo htmlspecialchars($erroBanco); ?></p>
        <?php else: ?>
            <p class="ok">Conexao OK. Resposta do teste: <?php echo htmlspecialchars((string) $testeBanco); ?></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Como interpretar</h2>
        <p>Se ao clicar em <strong>Gravar sessao teste</strong> o <code>Usuario ID</code> continuar vazio, o problema está em cookie/sessão/caminho.</p>
        <p>Se o banco falhar, o problema está nas credenciais de <code>config/database.php</code> ou no acesso ao MySQL.</p>
        <p>Se <code>login.php</code> abrir mas o logout não voltar, o caminho base do servidor provavelmente não é <code>/ponto_empresarial</code>.</p>
    </div>
</body>
</html>
