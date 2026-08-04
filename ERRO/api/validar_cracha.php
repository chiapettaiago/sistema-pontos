<?php
// api/validar_cracha.php - API para validação do QR Code
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Receber dados
$input = json_decode(file_get_contents('php://input'), true);
$qr_data = $_GET['data'] ?? $input['data'] ?? '';

if (empty($qr_data)) {
    echo json_encode([
        'success' => false,
        'message' => 'Dados do QR Code não fornecidos'
    ]);
    exit;
}

// Decodificar os dados do QR Code
$dados = json_decode(urldecode($qr_data), true);

if (!$dados || !isset($dados['matricula'])) {
    echo json_encode([
        'success' => false,
        'message' => 'QR Code inválido'
    ]);
    exit;
}

// Buscar funcionário pela matrícula
$stmt = $db->prepare("SELECT f.*, 
                      fi.nome_fantasia as filial_nome,
                      c.nome as cargo_nome,
                      e.nome_empresa as empresa_nome
                      FROM funcionarios f
                      LEFT JOIN filiais fi ON f.filial_id = fi.id
                      LEFT JOIN cargos c ON f.cargo_id = c.id
                      LEFT JOIN empresa e ON f.empresa_id = e.id
                      WHERE f.matricula = :matricula AND f.status = 'ativo'");
$stmt->execute([':matricula' => $dados['matricula']]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    echo json_encode([
        'success' => false,
        'message' => 'Funcionário não encontrado ou inativo'
    ]);
    exit;
}

// Verificar validade (1 ano a partir da admissão ou data atual)
$valido_ate = date('Y-m-d', strtotime('+1 year', strtotime($funcionario['data_admissao'])));
$hoje = date('Y-m-d');

if ($hoje > $valido_ate) {
    echo json_encode([
        'success' => false,
        'message' => 'Crachá expirado',
        'valido_ate' => date('d/m/Y', strtotime($valido_ate))
    ]);
    exit;
}

// Registrar tentativa de validação (opcional - para auditoria)
$stmt = $db->prepare("INSERT INTO validacoes_cracha (funcionario_id, data_validacao, ip_address, user_agent) 
                      VALUES (:funcionario_id, NOW(), :ip, :agent)");
$stmt->execute([
    ':funcionario_id' => $funcionario['id'],
    ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
]);

// Retornar dados do funcionário
echo json_encode([
    'success' => true,
    'message' => 'Crachá válido',
    'dados' => [
        'nome' => $funcionario['nome'],
        'matricula' => $funcionario['matricula'],
        'cargo' => $funcionario['cargo_nome'],
        'filial' => $funcionario['filial_nome'],
        'empresa' => $funcionario['empresa_nome'],
        'foto' => $funcionario['foto'] ? 'https://' . $_SERVER['HTTP_HOST'] . '/' . $funcionario['foto'] : null,
        'valido_ate' => date('d/m/Y', strtotime($valido_ate))
    ]
]);
?>
