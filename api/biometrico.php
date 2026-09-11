<?php
// api/biometrico.php - Cadastro facial e registro de ponto por reconhecimento facial
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/facial_recognition.php';

$db = getDB();
$input = json_decode(file_get_contents('php://input'), true);
$acao = $input['acao'] ?? ($_GET['acao'] ?? '');

function currentApiOrSessionUser(bool $allowPublic = false, array $input = []) {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $token = trim(str_replace('Bearer ', '', $auth));

    if ($token !== '') {
        $user = validateAPIToken($token);
        if (!isset($user['error'])) {
            return $user;
        }
        jsonError($user['error'], 'INVALID_TOKEN', (int) ($user['code'] ?? 401));
    }

    // O link assinado define a empresa do quiosque e tem precedência sobre
    // uma eventual sessão aberta no mesmo navegador.
    if ($allowPublic) {
        $empresaId = (int) ($input['empresa_id'] ?? 0);
        $signature = (string) ($input['chave'] ?? '');
        $expected = $empresaId > 0 ? hash_hmac('sha256', (string) $empresaId, DB_PASS . '|ponto-publico') : '';
        if ($empresaId > 0 && $signature !== '' && hash_equals($expected, $signature)) {
            return ['id' => null, 'nome' => 'Ponto público', 'email' => '', 'tipo' => 'publico', 'tipo_usuario' => 'publico', 'empresa_id' => $empresaId, 'funcionario_id' => null];
        }
        jsonError('Link público inválido ou expirado', 'INVALID_PUBLIC_LINK', 401);
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!empty($_SESSION['usuario_id'])) {
        return [
            'id' => $_SESSION['usuario_id'],
            'nome' => $_SESSION['usuario_nome'] ?? '',
            'email' => $_SESSION['usuario_email'] ?? '',
            'tipo' => $_SESSION['usuario_tipo'] ?? 'funcionario',
            'tipo_usuario' => ($_SESSION['usuario_tipo'] ?? '') === 'funcionario' ? 'funcionario' : 'admin',
            'empresa_id' => $_SESSION['empresa_id'] ?? null,
            'funcionario_id' => $_SESSION['funcionario_id'] ?? null,
        ];
    }

    jsonError('Nao autorizado', 'UNAUTHORIZED', 401);
}

function nextPointType(PDO $db, $funcionario_id) {
    $stmt = $db->prepare("SELECT tipo FROM pontos
                          WHERE funcionario_id = :id AND DATE(data_hora) = CURDATE()
                          ORDER BY data_hora DESC LIMIT 1");
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

function ensureBiometricTable(PDO $db) {
    $stmt = $db->query("SHOW TABLES LIKE 'biometricos_faciais'");
    return (bool) $stmt->fetch();
}

function pontoRegras(PDO $db, int $empresaId): array {
    try {
        $stmt = $db->prepare('SELECT ponto_apenas_empresa, intervalo_minimo_batidas FROM config_horarios WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([':empresa_id' => $empresaId]);
        $config = $stmt->fetch() ?: [];
        return [
            'apenas_empresa' => array_key_exists('ponto_apenas_empresa', $config) ? (int) $config['ponto_apenas_empresa'] === 1 : true,
            'intervalo' => max(120, (int) ($config['intervalo_minimo_batidas'] ?? 120)),
        ];
    } catch (Throwable $e) {
        return ['apenas_empresa' => true, 'intervalo' => 120];
    }
}

function validarLocalEIntervalo(PDO $db, array $funcionario, array $input): ?array {
    $regras = pontoRegras($db, (int) $funcionario['empresa_id']);
    $latitude = $input['latitude'] ?? null;
    $longitude = $input['longitude'] ?? null;

    if ($regras['apenas_empresa']) {
        if ($funcionario['filial_latitude'] === null || $funcionario['filial_longitude'] === null) {
            return ['warning' => 'A localização da filial ainda não está cadastrada; este ponto foi permitido sem validação de distância.'];
        }
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return ['message' => 'Permita o acesso à localização para registrar o ponto.', 'code' => 'LOCATION_REQUIRED', 'status' => 422];
        }

        $lat = (float) $latitude;
        $lon = (float) $longitude;
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return ['message' => 'Localização inválida.', 'code' => 'INVALID_LOCATION', 'status' => 422];
        }

        $earthRadius = 6371000;
        $dLat = deg2rad($lat - (float) $funcionario['filial_latitude']);
        $dLon = deg2rad($lon - (float) $funcionario['filial_longitude']);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat)) * cos(deg2rad((float) $funcionario['filial_latitude'])) * sin($dLon / 2) ** 2;
        $distance = $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
        $radius = max(1, (float) ($funcionario['filial_raio'] ?? 100));
        if ($distance > $radius) {
            return ['message' => 'Ponto bloqueado: você está fora do raio permitido da empresa (' . round($distance) . ' m de distância).', 'code' => 'OUTSIDE_COMPANY', 'status' => 403];
        }
    }

    $stmt = $db->prepare('SELECT data_hora FROM pontos WHERE funcionario_id = :id ORDER BY data_hora DESC LIMIT 1');
    $stmt->execute([':id' => $funcionario['funcionario_id']]);
    $last = $stmt->fetchColumn();
    if ($last) {
        $remaining = ($regras['intervalo'] * 60) - (time() - strtotime($last));
        if ($remaining > 0) {
            return ['message' => 'Aguarde mais ' . ceil($remaining / 60) . ' minutos para registrar outra batida.', 'code' => 'MINIMUM_INTERVAL', 'status' => 429];
        }
    }
    return null;
}

function resolveFuncionarioFotoPath(array $funcionario) {
    if (!empty($funcionario['foto'])) {
        return '/' . ltrim($funcionario['foto'], '/');
    }

    return null;
}

$user = currentApiOrSessionUser(
    in_array($acao, ['registrar_ponto_facial_publico', 'registrar_ponto_publico'], true),
    is_array($input) ? $input : []
);

if ($acao === 'salvar_facial') {
    if (!in_array($user['tipo'], ['super_admin', 'admin_empresa', 'gestor'], true)) {
        jsonError('Sem permissao para cadastrar biometria facial', 'FORBIDDEN', 403);
    }

    $funcionario_id = (int) ($input['funcionario_id'] ?? 0);
    $descritores = $input['descritores'] ?? [];

    if (!$funcionario_id || empty($descritores) || !is_array($descritores)) {
        jsonError('Dados biometricos incompletos', 'INVALID_DATA', 400);
    }

    foreach ($descritores as $descritor) {
        if (facialNormalizeDescriptor($descritor) === null) {
            jsonError('Uma ou mais amostras faciais sao invalidas', 'INVALID_DESCRIPTOR', 400);
        }
    }

    try {
        if (!ensureBiometricTable($db)) {
            jsonError('Tabela biometricos_faciais nao encontrada', 'MISSING_TABLE', 500);
        }

        if ($user['tipo'] !== 'super_admin') {
            $stmt = $db->prepare("SELECT id FROM funcionarios WHERE id = :id AND empresa_id = :empresa_id");
            $stmt->execute([':id' => $funcionario_id, ':empresa_id' => $user['empresa_id']]);
            if (!$stmt->fetch()) {
                jsonError('Funcionario fora da sua empresa', 'FORBIDDEN', 403);
            }
        }

        $descritores_json = json_encode($descritores);
        $check = $db->prepare("SELECT id FROM biometricos_faciais WHERE funcionario_id = :funcionario_id");
        $check->execute([':funcionario_id' => $funcionario_id]);
        $temUpdatedAt = apiColumnExists($db, 'biometricos_faciais', 'updated_at');

        $temAmostras = apiColumnExists($db, 'biometricos_faciais', 'amostras');
        if ($check->fetch()) {
            $query = "UPDATE biometricos_faciais
                      SET descritores = :descritores, ativo = 1"
                      . ($temAmostras ? ", amostras = :amostras" : "")
                      . ($temUpdatedAt ? ", updated_at = NOW()" : "") . "
                      WHERE funcionario_id = :funcionario_id";
        } else {
            $columns = "funcionario_id, descritores, ativo";
            $values = ":funcionario_id, :descritores, 1";
            if ($temAmostras) {
                $columns .= ", amostras";
                $values .= ", :amostras";
            }
            if ($temUpdatedAt) {
                $columns .= ", updated_at";
                $values .= ", NOW()";
            }
            $query = "INSERT INTO biometricos_faciais ($columns)
                      VALUES ($values)";
        }

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':funcionario_id' => $funcionario_id,
            ':descritores' => $descritores_json,
            ':amostras' => count($descritores)
        ]);

        jsonSuccess(null, 'Cadastro facial salvo com sucesso');
    } catch (Exception $e) {
        error_log('Erro ao salvar facial: ' . $e->getMessage());
        jsonError('Erro ao salvar cadastro facial', 'SERVER_ERROR', 500);
    }
}

if (in_array($acao, ['registrar_ponto_facial', 'registrar_ponto', 'registrar_ponto_facial_publico', 'registrar_ponto_publico'], true)) {
    $descritor_atual = $input['descritor'] ?? [];

    if (facialNormalizeDescriptor($descritor_atual) === null) {
        jsonError('Descritor facial invalido', 'INVALID_DESCRIPTOR', 400);
    }

    try {
        if (!ensureBiometricTable($db)) {
            jsonError('Cadastre a biometria facial antes de bater ponto', 'MISSING_TABLE', 400);
        }

        $params = [];
        $empresaWhere = '';
        if ($user['tipo'] !== 'super_admin' && !empty($user['empresa_id'])) {
            $empresaWhere = ' AND f.empresa_id = :empresa_id';
            $params[':empresa_id'] = $user['empresa_id'];
        }

        $stmt = $db->prepare("SELECT bf.funcionario_id, bf.descritores, f.nome, f.matricula, f.filial_id, f.empresa_id, f.foto,
                                     fi.latitude AS filial_latitude, fi.longitude AS filial_longitude, fi.raio_permitido AS filial_raio
                              FROM biometricos_faciais bf
                              JOIN funcionarios f ON bf.funcionario_id = f.id
                              LEFT JOIN filiais fi ON fi.id = f.filial_id
                              WHERE bf.ativo = 1 AND f.status = 'ativo' {$empresaWhere}");
        $stmt->execute($params);
        $faces = $stmt->fetchAll();

        $match = facialBestMatch($descritor_atual, $faces);
        if (!$match['matched']) {
            $message = ($match['reason'] ?? '') === 'ambiguous'
                ? 'Reconhecimento inconclusivo. Tente novamente em melhor iluminacao'
                : 'Rosto nao reconhecido';
            jsonError($message, 'FACE_NOT_RECOGNIZED', 401);
        }
        $melhor = $match['face'];

        $latitude = $input['latitude'] ?? null;
        $longitude = $input['longitude'] ?? null;
        $precisao = $input['precisao'] ?? null;
        $isQuiosque = in_array($acao, ['registrar_ponto_facial_publico', 'registrar_ponto_publico'], true);
        if ($isQuiosque) {
            if (!is_numeric($latitude) || !is_numeric($longitude)) {
                jsonError('Permita o acesso à localização para registrar o ponto.', 'LOCATION_REQUIRED', 422);
            }
            $latitude = (float) $latitude;
            $longitude = (float) $longitude;
            if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                jsonError('Localização inválida.', 'INVALID_LOCATION', 422);
            }
            // Alguns navegadores antigos não enviam a margem de precisão, embora
            // forneçam coordenadas válidas. A precisão é informativa e não deve
            // impedir o reconhecimento facial nem a batida.
            $precisao = is_numeric($precisao) ? round((float) $precisao, 2) : null;
        }

        if ($user['tipo_usuario'] === 'funcionario' && !empty($user['funcionario_id']) && (int) $user['funcionario_id'] !== (int) $melhor['funcionario_id']) {
            jsonError('Este rosto nao pertence ao funcionario logado', 'FACE_MISMATCH', 403);
        }

        $regraResultado = validarLocalEIntervalo($db, [
            'funcionario_id' => $melhor['funcionario_id'],
            'empresa_id' => $melhor['empresa_id'],
            'filial_latitude' => $melhor['filial_latitude'],
            'filial_longitude' => $melhor['filial_longitude'],
            'filial_raio' => $melhor['filial_raio'],
        ], is_array($input) ? $input : []);
        if (!empty($regraResultado['message'])) {
            jsonError($regraResultado['message'], $regraResultado['code'], $regraResultado['status']);
        }
        $avisoLocalizacao = $regraResultado['warning'] ?? null;

        $tipo = nextPointType($db, $melhor['funcionario_id']);
        if (!$tipo) {
            jsonError('Dia ja finalizado', 'DAY_DONE', 400);
        }

        $origem = $isQuiosque ? 'totem' : 'web';
        $stmt = $db->prepare("INSERT INTO pontos (funcionario_id, filial_id, empresa_id, tipo, data_hora, latitude, longitude, origem)
                              VALUES (:funcionario_id, :filial_id, :empresa_id, :tipo, NOW(), :latitude, :longitude, :origem)");
        $stmt->execute([
            ':funcionario_id' => $melhor['funcionario_id'],
            ':filial_id' => $melhor['filial_id'],
            ':empresa_id' => $melhor['empresa_id'],
            ':tipo' => $tipo,
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':origem' => $origem
        ]);

        $nomes = [
            'entrada' => 'Entrada',
            'saida_almoco' => 'Saida almoco',
            'volta_almoco' => 'Volta almoco',
            'saida' => 'Saida'
        ];

        $mensagemSucesso = ($nomes[$tipo] ?? 'Ponto') . ' registrada com sucesso';
        if ($avisoLocalizacao) $mensagemSucesso .= '. Aviso: ' . $avisoLocalizacao;
        jsonSuccess([
            'funcionario' => $melhor['nome'],
            'funcionario_id' => (int) $melhor['funcionario_id'],
            'tipo' => $tipo,
            'tipo_nome' => $nomes[$tipo] ?? $tipo,
            'score' => round(max(0, 1 - $match['distance']), 4),
            'distance' => round($match['distance'], 4),
            'foto' => resolveFuncionarioFotoPath($melhor),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'precisao' => $precisao,
            'aviso_localizacao' => $avisoLocalizacao
        ], $mensagemSucesso);
    } catch (Exception $e) {
        error_log('Erro no ponto facial: ' . $e->getMessage());
        jsonError('Erro ao registrar ponto facial', 'SERVER_ERROR', 500);
    }
}

if ($acao === 'registrar_ponto_digital') {
    try {
        if ($user['tipo_usuario'] !== 'funcionario' || empty($user['funcionario_id'])) {
            jsonError('Registro digital disponivel apenas para funcionario logado', 'FORBIDDEN', 403);
        }

        if (!ensureBiometricTable($db)) {
            jsonError('Cadastre a biometria digital antes de bater ponto', 'MISSING_TABLE', 400);
        }

        $stmt = $db->prepare("SELECT bd.id, bd.funcionario_id, f.nome, f.matricula, f.filial_id, f.empresa_id
                              FROM biometricos_digitais bd
                              JOIN funcionarios f ON bd.funcionario_id = f.id
                              WHERE bd.ativo = 1 AND f.id = :funcionario_id AND f.status = 'ativo'
                              ORDER BY bd.id DESC LIMIT 1");
        $stmt->execute([':funcionario_id' => $user['funcionario_id']]);
        $digital = $stmt->fetch();

        if (!$digital) {
            jsonError('Biometria digital nao cadastrada para este funcionario', 'DIGITAL_NOT_FOUND', 404);
        }

        $tipo = nextPointType($db, $digital['funcionario_id']);
        if (!$tipo) {
            jsonError('Dia ja finalizado', 'DAY_DONE', 400);
        }

        $stmt = $db->prepare("INSERT INTO pontos (funcionario_id, filial_id, empresa_id, tipo, data_hora, origem)
                              VALUES (:funcionario_id, :filial_id, :empresa_id, :tipo, NOW(), 'biometrico_digital')");
        $stmt->execute([
            ':funcionario_id' => $digital['funcionario_id'],
            ':filial_id' => $digital['filial_id'],
            ':empresa_id' => $digital['empresa_id'],
            ':tipo' => $tipo
        ]);

        $nomes = [
            'entrada' => 'Entrada',
            'saida_almoco' => 'Saida almoco',
            'volta_almoco' => 'Volta almoco',
            'saida' => 'Saida'
        ];

        jsonSuccess([
            'funcionario' => $digital['nome'],
            'funcionario_id' => (int) $digital['funcionario_id'],
            'tipo' => $tipo,
            'tipo_nome' => $nomes[$tipo] ?? $tipo
        ], ($nomes[$tipo] ?? 'Ponto') . ' registrada com sucesso');
    } catch (Exception $e) {
        error_log('Erro no ponto digital: ' . $e->getMessage());
        jsonError('Erro ao registrar ponto digital', 'SERVER_ERROR', 500);
    }
}

jsonError('Acao nao reconhecida', 'UNKNOWN_ACTION', 400);
?>
