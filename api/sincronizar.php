<?php
// api/sincronizar.php - Sincronizacao Offline
require_once 'config.php';

$db = getDB();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function currentOfflineSyncUser() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $token = trim(str_replace('Bearer ', '', $auth));

    if ($token !== '') {
        return authenticate();
    }

    if (!empty($_SESSION['funcionario_id'])) {
        return [
            'id' => $_SESSION['funcionario_id'],
            'nome' => $_SESSION['usuario_nome'] ?? '',
            'email' => $_SESSION['usuario_email'] ?? '',
            'tipo' => 'funcionario',
            'tipo_usuario' => 'funcionario',
            'empresa_id' => $_SESSION['empresa_id'] ?? null,
            'filial_id' => $_SESSION['filial_id'] ?? null,
            'funcionario_id' => $_SESSION['funcionario_id'],
        ];
    }

    jsonError('Nao autorizado', 'UNAUTHORIZED', 401);
}

$user = currentOfflineSyncUser();

$funcionario_id = (int) ($user['funcionario_id'] ?? 0);
if ($funcionario_id <= 0 && ($user['tipo_usuario'] ?? '') === 'funcionario') {
    $funcionario_id = (int) ($user['id'] ?? 0);
}

if ($funcionario_id <= 0) {
    jsonError('Token nao pertence a um funcionario', 'FORBIDDEN', 403);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || !isset($data['pontos']) || !is_array($data['pontos'])) {
    jsonError('Lista de pontos invalida', 'MISSING_FIELDS', 400);
}

$stmt = $db->prepare("SELECT id, filial_id, empresa_id FROM funcionarios WHERE id = :id AND status = 'ativo'");
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    jsonError('Funcionario nao encontrado ou inativo', 'FUNCIONARIO_NOT_FOUND', 404);
}

$tipos_validos = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
$processados = 0;
$ignorados = 0;
$erros = [];

$temEmpresa = apiColumnExists($db, 'pontos', 'empresa_id');
$temSincronizado = apiColumnExists($db, 'pontos', 'sincronizado');

foreach ($data['pontos'] as $ponto) {
    $offlineId = $ponto['offline_id'] ?? null;

    try {
        if (empty($ponto['tipo']) || !in_array($ponto['tipo'], $tipos_validos, true)) {
            $erros[] = ['offline_id' => $offlineId, 'error' => 'Tipo invalido'];
            continue;
        }

        $data_hora = $ponto['data_hora'] ?? date('Y-m-d H:i:s');
        if (strtotime($data_hora) === false) {
            $erros[] = ['offline_id' => $offlineId, 'error' => 'Data/hora invalida'];
            continue;
        }

        $data_hora = date('Y-m-d H:i:s', strtotime($data_hora));

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM pontos
            WHERE funcionario_id = :funcionario_id
              AND tipo = :tipo
              AND data_hora = :data_hora
        ");
        $stmt->execute([
            ':funcionario_id' => $funcionario_id,
            ':tipo' => $ponto['tipo'],
            ':data_hora' => $data_hora
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            $ignorados++;
            continue;
        }

        $columns = ['funcionario_id', 'filial_id', 'tipo', 'data_hora', 'latitude', 'longitude', 'origem'];
        $placeholders = [':funcionario_id', ':filial_id', ':tipo', ':data_hora', ':latitude', ':longitude', ':origem'];
        $params = [
            ':funcionario_id' => $funcionario_id,
            ':filial_id' => $funcionario['filial_id'],
            ':tipo' => $ponto['tipo'],
            ':data_hora' => $data_hora,
            ':latitude' => $ponto['latitude'] ?? null,
            ':longitude' => $ponto['longitude'] ?? null,
            ':origem' => 'api'
        ];

        if ($temEmpresa) {
            $columns[] = 'empresa_id';
            $placeholders[] = ':empresa_id';
            $params[':empresa_id'] = $funcionario['empresa_id'];
        }

        if ($temSincronizado) {
            $columns[] = 'sincronizado';
            $placeholders[] = ':sincronizado';
            $params[':sincronizado'] = 1;
        }

        $query = sprintf(
            'INSERT INTO pontos (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $db->prepare($query);
        $stmt->execute($params);

        $processados++;
    } catch (Exception $e) {
        $erros[] = ['offline_id' => $offlineId, 'error' => $e->getMessage()];
    }
}

if (function_exists('logApi')) {
    logApi($funcionario_id, 'sincronizar', 'POST', 'success', $_SERVER['REMOTE_ADDR'] ?? null);
}

jsonResponse([
    'success' => true,
    'processados' => $processados,
    'ignorados' => $ignorados,
    'total' => count($data['pontos']),
    'erros' => $erros
]);
?>
