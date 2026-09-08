<?php

session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once '../../config/database.php';

if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['usuario_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nao autorizado']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$funcionarioId = $_SESSION['funcionario_id'] ?? null;

if (!$funcionarioId) {
    $stmt = $db->prepare('SELECT id FROM funcionarios WHERE email = :email AND status = :status LIMIT 1');
    $stmt->execute([':email' => $_SESSION['usuario_email'], ':status' => 'ativo']);
    $funcionarioId = $stmt->fetchColumn() ?: null;
}

if (!$funcionarioId) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Perfil de funcionario nao encontrado']);
    exit;
}

$stmt = $db->prepare('SELECT f.id, f.nome, f.matricula, fi.nome_fantasia AS filial
                      FROM funcionarios f
                      LEFT JOIN filiais fi ON fi.id = f.filial_id
                      WHERE f.id = :id AND f.status = :status');
$stmt->execute([':id' => $funcionarioId, ':status' => 'ativo']);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Funcionario nao encontrado']);
    exit;
}

$stmt = $db->prepare('SELECT tipo, TIME(data_hora) AS hora FROM pontos
                      WHERE funcionario_id = :id AND DATE(data_hora) = CURDATE()
                      ORDER BY data_hora');
$stmt->execute([':id' => $funcionarioId]);
$horarios = ['entrada' => '--:--', 'saida_almoco' => '--:--', 'volta_almoco' => '--:--', 'saida' => '--:--'];
foreach ($stmt->fetchAll() as $ponto) {
    if (array_key_exists($ponto['tipo'], $horarios)) {
        $horarios[$ponto['tipo']] = substr($ponto['hora'], 0, 5);
    }
}

$sequencia = array_keys($horarios);
$proximoTipo = 'finalizado';
foreach ($sequencia as $tipo) {
    if ($horarios[$tipo] === '--:--') {
        $proximoTipo = $tipo;
        break;
    }
}

echo json_encode([
    'success' => true,
    'funcionario_id' => (int) $funcionario['id'],
    'nome' => $funcionario['nome'],
    'matricula' => $funcionario['matricula'],
    'filial' => $funcionario['filial'] ?: 'Nao informada',
    'horarios' => $horarios,
    'proximo_tipo' => $proximoTipo,
], JSON_UNESCAPED_UNICODE);
