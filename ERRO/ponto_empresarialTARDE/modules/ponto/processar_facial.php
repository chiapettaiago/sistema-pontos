<?php
// modules/ponto/processar_facial.php - Registrar ponto com verificacao facial da sessao
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once '../../config/database.php';

function responderFacial($success, $message, $status = 200, $extra = []) {
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function similaridadeCosseno($vecA, $vecB) {
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

function verificarDescritorFacial(PDO $db, $funcionario_id, $descritor_atual) {
    if (empty($descritor_atual) || !is_array($descritor_atual)) {
        return ['ok' => false, 'message' => 'Nenhum rosto foi detectado para validacao'];
    }

    $stmt = $db->query("SHOW TABLES LIKE 'biometricos_faciais'");
    if (!$stmt->fetch()) {
        return ['ok' => false, 'message' => 'Biometria facial ainda nao foi configurada'];
    }

    $stmt = $db->prepare("SELECT descritores FROM biometricos_faciais WHERE funcionario_id = :id AND ativo = 1 ORDER BY id DESC LIMIT 1");
    $stmt->execute([':id' => $funcionario_id]);
    $registro = $stmt->fetch();

    if (!$registro) {
        return ['ok' => false, 'message' => 'Cadastre sua biometria facial antes de bater o ponto'];
    }

    $descritores_salvos = json_decode($registro['descritores'], true);
    if (!is_array($descritores_salvos) || empty($descritores_salvos)) {
        return ['ok' => false, 'message' => 'Cadastro facial invalido. Refaca o cadastro'];
    }

    $melhorScore = 0;
    foreach ($descritores_salvos as $amostra) {
        $score = similaridadeCosseno($descritor_atual, $amostra);
        if ($score > $melhorScore) {
            $melhorScore = $score;
        }
    }

    return [
        'ok' => $melhorScore >= 0.60,
        'score' => $melhorScore,
        'message' => $melhorScore >= 0.60 ? 'Face validada' : 'Rosto nao reconhecido para este funcionario'
    ];
}

if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['usuario_id'])) {
    responderFacial(false, 'Nao autorizado', 401);
}

$database = new Database();
$db = $database->getConnection();

$funcionario_id = $_SESSION['funcionario_id'] ?? null;
if (!$funcionario_id && isset($_SESSION['usuario_id'], $_SESSION['usuario_email'])) {
    $stmt = $db->prepare("SELECT id FROM funcionarios WHERE email = :email");
    $stmt->execute([':email' => $_SESSION['usuario_email']]);
    $func = $stmt->fetch();
    if ($func) {
        $funcionario_id = $func['id'];
        $_SESSION['funcionario_id'] = $funcionario_id;
    }
}

if (!$funcionario_id) {
    responderFacial(false, 'Funcionario nao identificado na sessao', 401);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    responderFacial(false, 'JSON invalido', 400);
}

$tipo = $input['tipo'] ?? '';
$foto_base64 = $input['foto'] ?? '';
$descritor = $input['descritor'] ?? [];
$latitude = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;

$tipos_validos = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
if (!in_array($tipo, $tipos_validos, true)) {
    responderFacial(false, 'Tipo de ponto invalido', 400);
}

$stmt = $db->prepare("SELECT * FROM funcionarios WHERE id = :id AND status = 'ativo'");
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    responderFacial(false, 'Funcionario nao encontrado ou inativo', 404);
}

$validacao = verificarDescritorFacial($db, $funcionario_id, $descritor);
if (!$validacao['ok']) {
    responderFacial(false, $validacao['message'], 401, ['score' => round($validacao['score'] ?? 0, 4)]);
}

$stmt = $db->prepare("SELECT id FROM pontos
                      WHERE funcionario_id = :id
                      AND tipo = :tipo
                      AND DATE(data_hora) = CURDATE()");
$stmt->execute([':id' => $funcionario_id, ':tipo' => $tipo]);
if ($stmt->fetch()) {
    responderFacial(false, 'Voce ja registrou este ponto hoje', 400);
}

$stmt = $db->prepare("SELECT tipo FROM pontos
                      WHERE funcionario_id = :id AND DATE(data_hora) = CURDATE()
                      ORDER BY data_hora DESC LIMIT 1");
$stmt->execute([':id' => $funcionario_id]);
$ultimo = $stmt->fetch();

$sequencia = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
$indice_atual = array_search($tipo, $sequencia, true);
if (!$ultimo && $tipo !== 'entrada') {
    responderFacial(false, 'Primeiro ponto do dia deve ser Entrada', 400);
}

if ($ultimo) {
    $indice_anterior = array_search($ultimo['tipo'], $sequencia, true);
    if ($indice_atual !== $indice_anterior + 1) {
        $nomes = [
            'entrada' => 'Entrada',
            'saida_almoco' => 'Saida almoco',
            'volta_almoco' => 'Volta almoco',
            'saida' => 'Saida'
        ];
        $proximo = $sequencia[$indice_anterior + 1] ?? null;
        responderFacial(false, $proximo ? 'Proximo ponto deve ser: ' . $nomes[$proximo] : 'Voce ja finalizou o expediente', 400);
    }
}

$foto_path = null;
if ($foto_base64 !== '') {
    $foto_data = preg_replace('#^data:image/[^;]+;base64,#', '', $foto_base64);
    $foto_data = str_replace(' ', '+', $foto_data);
    $decoded = base64_decode($foto_data, true);

    if ($decoded === false || strlen($decoded) < 1024) {
        responderFacial(false, 'Foto facial invalida', 400);
    }

    $uploadDir = '../../uploads/pontos_facial/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $foto_nome = $funcionario_id . '_' . date('Ymd_His') . '.jpg';
    $foto_path = 'uploads/pontos_facial/' . $foto_nome;
    file_put_contents('../../' . $foto_path, $decoded);
}

try {
    $stmt = $db->prepare("INSERT INTO pontos
                          (funcionario_id, filial_id, empresa_id, tipo, data_hora, latitude, longitude, foto_facial, origem)
                          VALUES (:funcionario_id, :filial_id, :empresa_id, :tipo, NOW(), :latitude, :longitude, :foto, 'facial')");
    $stmt->execute([
        ':funcionario_id' => $funcionario_id,
        ':filial_id' => $funcionario['filial_id'],
        ':empresa_id' => $funcionario['empresa_id'] ?? null,
        ':tipo' => $tipo,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':foto' => $foto_path
    ]);

    $nomes = [
        'entrada' => 'Entrada',
        'saida_almoco' => 'Saida almoco',
        'volta_almoco' => 'Volta almoco',
        'saida' => 'Saida'
    ];

    responderFacial(true, ($nomes[$tipo] ?? 'Ponto') . ' registrada com sucesso', 200, [
        'score' => round($validacao['score'] ?? 0, 4),
        'foto' => $foto_path
    ]);
} catch (Exception $e) {
    if ($foto_path && file_exists('../../' . $foto_path)) {
        @unlink('../../' . $foto_path);
    }
    error_log('Erro ponto facial: ' . $e->getMessage());
    responderFacial(false, 'Erro ao registrar ponto facial', 500);
}
?>
