<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/router.php';
require_once __DIR__ . '/../includes/facial_recognition.php';

function jsonOut($success, $message, $status = 200, $extra = []) {
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonOut(false, 'JSON invalido', 400);
}

$descritor = $input['descritor'] ?? [];
if (facialNormalizeDescriptor($descritor) === null) {
    jsonOut(false, 'Descritor facial invalido', 400);
}

$db = getDB();

try {
    $stmt = $db->query("SHOW TABLES LIKE 'biometricos_faciais'");
    if (!$stmt->fetch()) {
        jsonOut(false, 'Biometria facial nao cadastrada', 400);
    }

    $stmt = $db->prepare("SELECT bf.funcionario_id, bf.descritores, f.nome, f.email, f.tipo_usuario, f.empresa_id, f.filial_id, f.status
                          FROM biometricos_faciais bf
                          JOIN funcionarios f ON bf.funcionario_id = f.id
                          WHERE bf.ativo = 1 AND f.status = 'ativo'");
    $stmt->execute();
    $faces = $stmt->fetchAll();

    $match = facialBestMatch($descritor, $faces);
    if (!$match['matched']) {
        $message = ($match['reason'] ?? '') === 'ambiguous'
            ? 'Reconhecimento inconclusivo. Tente novamente em melhor iluminacao'
            : 'Rosto nao reconhecido';
        jsonOut(false, $message, 401, [
            'distance' => isset($match['distance']) ? round($match['distance'], 4) : null,
        ]);
    }
    $melhor = $match['face'];

    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $melhor['funcionario_id'];
    $_SESSION['usuario_nome'] = $melhor['nome'];
    $_SESSION['usuario_email'] = $melhor['email'];
    $_SESSION['usuario_tipo'] = appNormalizeUserType($melhor['tipo_usuario'] ?: 'funcionario');
    $_SESSION['funcionario_id'] = $melhor['funcionario_id'];
    $_SESSION['empresa_id'] = $melhor['empresa_id'] ?? null;
    $_SESSION['filial_id'] = $melhor['filial_id'] ?? null;
    $_SESSION['usuario_filial_id'] = $melhor['filial_id'] ?? null;
    $_SESSION['tipo_login'] = 'facial';

    jsonOut(true, 'Login facial realizado com sucesso', 200, [
        'score' => round(max(0, 1 - $match['distance']), 4),
        'distance' => round($match['distance'], 4),
        'usuario' => [
            'id' => (int) $melhor['funcionario_id'],
            'nome' => $melhor['nome'],
            'email' => $melhor['email'],
            'tipo' => $_SESSION['usuario_tipo']
        ],
        'redirect' => appUrl(appDestinationAfterLogin($_SESSION['usuario_tipo']))
    ]);
} catch (Exception $e) {
    error_log('Erro login facial: ' . $e->getMessage());
    jsonOut(false, 'Erro interno ao validar login facial', 500);
}
