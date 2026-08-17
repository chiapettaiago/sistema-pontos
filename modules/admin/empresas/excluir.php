<?php
// modules/admin/empresas/excluir.php - Excluir Empresa (APENAS SUPER ADMIN)
// NÃO PODE HAVER NADA ANTES DESTA LINHA

require_once '../../../includes/config.php';

// Verificar se está logado e é super admin
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_tipo'] ?? '') !== 'super_admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;
$recuperar = isset($_GET['recuperar']) ? true : false;

// Buscar dados da empresa
$stmt = $db->prepare("SELECT nome, status FROM empresas WHERE id = :id");
$stmt->execute([':id' => $id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    $_SESSION['mensagem'] = "Empresa não encontrada";
    $_SESSION['tipo_mensagem'] = "error";
    header('Location: index.php');
    exit;
}

if ($recuperar) {
    // ============================================
    // RECUPERAR EMPRESA (Reativar)
    // ============================================
    $stmt = $db->prepare("UPDATE empresas SET status = 'ativa', data_ativacao = CURDATE() WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    // Registrar log
    $log = $db->prepare("INSERT INTO logs_sistema (funcionario_id, acao, descricao, ip) 
                         VALUES (:funcionario_id, 'RECUPERAR_EMPRESA', :descricao, :ip)");
    $log->execute([
        ':funcionario_id' => $_SESSION['usuario_id'],
        ':descricao' => "Empresa recuperada: {$empresa['nome']}",
        ':ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['mensagem'] = "Empresa '{$empresa['nome']}' foi recuperada com sucesso!";
    $_SESSION['tipo_mensagem'] = "success";
    header('Location: index.php');
    exit;
}

// ============================================
// EXCLUIR EMPRESA (Mover para lixeira)
// ============================================

// Verificar se a empresa tem funcionários
$stmt = $db->prepare("SELECT COUNT(*) as total FROM funcionarios WHERE empresa_id = :id AND status != 'desligado'");
$stmt->execute([':id' => $id]);
$total_funcionarios = $stmt->fetch()['total'];

if ($total_funcionarios > 0) {
    $_SESSION['mensagem'] = "Não é possível excluir a empresa '{$empresa['nome']}' pois ela possui $total_funcionarios funcionários ativos. Primeiro desligue os funcionários.";
    $_SESSION['tipo_mensagem'] = "error";
    header('Location: index.php');
    exit;
}

// Verificar se a empresa tem assinaturas ativas
$stmt = $db->prepare("SELECT COUNT(*) as total FROM assinaturas WHERE empresa_id = :id AND status = 'ativa'");
$stmt->execute([':id' => $id]);
$total_assinaturas = $stmt->fetch()['total'];

if ($total_assinaturas > 0) {
    // Cancelar assinaturas ativas
    $stmt = $db->prepare("UPDATE assinaturas SET status = 'cancelada', cancelado_em = CURDATE() WHERE empresa_id = :id AND status = 'ativa'");
    $stmt->execute([':id' => $id]);
}

// Criar tabela de empresas_excluidas se não existir (para histórico)
$db->exec("CREATE TABLE IF NOT EXISTS empresas_excluidas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    empresa_original_id INT,
    nome VARCHAR(100),
    cnpj VARCHAR(18),
    email VARCHAR(100),
    motivo TEXT,
    excluido_por INT,
    data_exclusao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dados_json TEXT,
    FOREIGN KEY (excluido_por) REFERENCES usuarios_sistema(id)
)");

// Salvar dados da empresa no histórico antes de excluir
$dados_json = json_encode([
    'nome' => $empresa['nome'],
    'status' => $empresa['status'],
    'data_exclusao' => date('Y-m-d H:i:s'),
    'excluido_por' => $_SESSION['usuario_nome']
]);

$stmt = $db->prepare("INSERT INTO empresas_excluidas (empresa_original_id, nome, cnpj, email, dados_json, excluido_por) 
                      VALUES (:id, :nome, :cnpj, :email, :dados_json, :excluido_por)");
$stmt->execute([
    ':id' => $id,
    ':nome' => $empresa['nome'],
    ':cnpj' => $empresa['cnpj'] ?? '',
    ':email' => $empresa['email'] ?? '',
    ':dados_json' => $dados_json,
    ':excluido_por' => $_SESSION['usuario_id']
]);

// Excluir empresa
$stmt = $db->prepare("DELETE FROM empresas WHERE id = :id");
$stmt->execute([':id' => $id]);

// Registrar log
$log = $db->prepare("INSERT INTO logs_sistema (funcionario_id, acao, descricao, ip) 
                     VALUES (:funcionario_id, 'EXCLUIR_EMPRESA', :descricao, :ip)");
$log->execute([
    ':funcionario_id' => $_SESSION['usuario_id'],
    ':descricao' => "Empresa excluída: {$empresa['nome']}",
    ':ip' => $_SERVER['REMOTE_ADDR']
]);

$_SESSION['mensagem'] = "Empresa '{$empresa['nome']}' foi excluída com sucesso!";
$_SESSION['tipo_mensagem'] = "success";

header('Location: index.php');
exit;
?>

