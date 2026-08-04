<?php
// api/biometrico.php - Cadastro facial e registro de ponto por reconhecimento facial
require_once __DIR__ . '/config.php';

$db = getDB();
$input = json_decode(file_get_contents('php://input'), true);
$acao = $input['acao'] ?? ($_GET['acao'] ?? '');

function currentApiOrSessionUser() {
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

function resolveFuncionarioFotoPath(array $funcionario) {
    if (!empty($funcionario['foto'])) {
        return '/' . ltrim($funcionario['foto'], '/');
    }

    return null;
}

$user = currentApiOrSessionUser();

if ($acao === 'salvar_facial') {
    if (!in_array($user['tipo'], ['super_admin', 'admin_empresa', 'gestor'], true)) {
        jsonError('Sem permissao para cadastrar biometria facial', 'FORBIDDEN', 403);
    }

    $funcionario_id = (int) ($input['funcionario_id'] ?? 0);
    $descritores = $input['descritores'] ?? [];

    if (!$funcionario_id || empty($descritores) || !is_array($descritores)) {
        jsonError('Dados biometricos incompletos', 'INVALID_DATA', 400);
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

        if ($check->fetch()) {
            $query = "UPDATE biometricos_faciais
                      SET descritores = :descritores, amostras = :amostras, ativo = 1, updated_at = NOW()
                      WHERE funcionario_id = :funcionario_id";
        } else {
            $query = "INSERT INTO biometricos_faciais (funcionario_id, descritores, amostras, ativo)
                      VALUES (:funcionario_id, :descritores, :amostras, 1)";
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

if ($acao === 'registrar_ponto_facial' || $acao === 'registrar_ponto') {
    $descritor_atual = $input['descritor'] ?? [];

    if (empty($descritor_atual) || !is_array($descritor_atual)) {
        jsonError('Nenhum descritor facial recebido', 'MISSING_DESCRIPTOR', 400);
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

        $stmt = $db->prepare("SELECT bf.funcionario_id, bf.descritores, f.nome, f.matricula, f.filial_id, f.empresa_id, f.foto
                              FROM biometricos_faciais bf
                              JOIN funcionarios f ON bf.funcionario_id = f.id
                              WHERE bf.ativo = 1 AND f.status = 'ativo' {$empresaWhere}");
        $stmt->execute($params);
        $faces = $stmt->fetchAll();

        $melhor = null;
        $melhorScore = 0;
        $limiar = 0.60;

        foreach ($faces as $face) {
            $descritores_salvos = json_decode($face['descritores'], true);
            if (!is_array($descritores_salvos)) {
                continue;
            }

            foreach ($descritores_salvos as $amostra) {
                $score = cosineSimilarity($descritor_atual, $amostra);
                if ($score > $melhorScore) {
                    $melhorScore = $score;
                    $melhor = $face;
                }
            }
        }

        if (!$melhor || $melhorScore < $limiar) {
            jsonError('Rosto nao reconhecido', 'FACE_NOT_RECOGNIZED', 401);
        }

        if ($user['tipo_usuario'] === 'funcionario' && !empty($user['funcionario_id']) && (int) $user['funcionario_id'] !== (int) $melhor['funcionario_id']) {
            jsonError('Este rosto nao pertence ao funcionario logado', 'FACE_MISMATCH', 403);
        }

        $tipo = nextPointType($db, $melhor['funcionario_id']);
        if (!$tipo) {
            jsonError('Dia ja finalizado', 'DAY_DONE', 400);
        }

        $stmt = $db->prepare("INSERT INTO pontos (funcionario_id, filial_id, empresa_id, tipo, data_hora, origem)
                              VALUES (:funcionario_id, :filial_id, :empresa_id, :tipo, NOW(), 'biometrico_facial')");
        $stmt->execute([
            ':funcionario_id' => $melhor['funcionario_id'],
            ':filial_id' => $melhor['filial_id'],
            ':empresa_id' => $melhor['empresa_id'],
            ':tipo' => $tipo
        ]);

        $nomes = [
            'entrada' => 'Entrada',
            'saida_almoco' => 'Saida almoco',
            'volta_almoco' => 'Volta almoco',
            'saida' => 'Saida'
        ];

        jsonSuccess([
            'funcionario' => $melhor['nome'],
            'funcionario_id' => (int) $melhor['funcionario_id'],
            'tipo' => $tipo,
            'tipo_nome' => $nomes[$tipo] ?? $tipo,
            'score' => round($melhorScore, 4),
            'foto' => resolveFuncionarioFotoPath($melhor)
        ], ($nomes[$tipo] ?? 'Ponto') . ' registrada com sucesso');
    } catch (Exception $e) {
        error_log('Erro no ponto facial: ' . $e->getMessage());
        jsonError('Erro ao registrar ponto facial', 'SERVER_ERROR', 500);
    }
}

jsonError('Acao nao reconhecida', 'UNKNOWN_ACTION', 400);
?>
