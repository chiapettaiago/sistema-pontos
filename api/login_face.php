<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/router.php';

function jsonOut($success, $message, $status = 200, $extra = []) {
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function cosineSimilarity($vecA, $vecB) {
    if (!is_array($vecA) || !is_array($vecB) || count($vecA) !== count($vecB)) {
        return 0;
    }

    $dot = 0;
    $normA = 0;
    $normB = 0;
    for ($i = 0; $i < count($vecA); $i++) {
        $a = (float) $vecA[$i];
        $b = (float) $vecB[$i];
        $dot += $a * $b;
        $normA += $a * $a;
        $normB += $b * $b;
    }

    if ($normA <= 0 || $normB <= 0) {
        return 0;
    }

    return $dot / (sqrt($normA) * sqrt($normB));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonOut(false, 'JSON invalido', 400);
}

$descritor = $input['descritor'] ?? [];
if (empty($descritor) || !is_array($descritor)) {
    jsonOut(false, 'Nenhum descritor facial recebido', 400);
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

    $melhor = null;
    $melhorScore = 0;
    $limiar = 0.60;

    foreach ($faces as $face) {
        $descritoresSalvos = json_decode($face['descritores'], true);
        if (!is_array($descritoresSalvos)) {
            continue;
        }

        foreach ($descritoresSalvos as $amostra) {
            $score = cosineSimilarity($descritor, $amostra);
            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhor = $face;
            }
        }
    }

    if (!$melhor || $melhorScore < $limiar) {
        jsonOut(false, 'Rosto nao reconhecido', 401, ['score' => round($melhorScore, 4)]);
    }

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
        'score' => round($melhorScore, 4),
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
