<?php
// modules/solicitacoes/aprovar.php - Processar Aprovação/Rejeição (CORRIGIDO)
require_once '../../config/database.php';

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Verificar permissão (admin, gestor, super_admin)
$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa', 'gestor'])) {
    $_SESSION['mensagem'] = 'Você não tem permissão para esta ação';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$id = $_POST['id'] ?? 0;
$acao = $_POST['acao'] ?? '';
$observacao = trim($_POST['observacao'] ?? '');

if (!$id || !$acao) {
    $_SESSION['mensagem'] = 'Dados inválidos';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: admin.php');
    exit;
}

// Verificar se a ação é válida
if (!in_array($acao, ['aprovar', 'rejeitar'])) {
    $_SESSION['mensagem'] = 'Ação inválida';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: admin.php');
    exit;
}

$status = ($acao == 'aprovar') ? 'aprovado' : 'rejeitado';
$empresa_id = $_SESSION['empresa_id'] ?? 1;

// Pegar o ID do funcionário logado (quem está aprovando)
$funcionario_aprovador = $_SESSION['funcionario_id'] ?? null;
if (!$funcionario_aprovador) {
    // Buscar o funcionário pelo email
    $stmt = $db->prepare("SELECT id FROM funcionarios WHERE email = :email");
    $stmt->execute([':email' => $_SESSION['usuario_email']]);
    $func = $stmt->fetch();
    if ($func) {
        $funcionario_aprovador = $func['id'];
    }
}

try {
    // Usar as colunas corretas (resposta ao invés de observacao_aprovador)
    $query = "UPDATE solicitacoes SET 
              status = :status, 
              respondido_por = :respondido_por, 
              data_resposta = NOW(),
              resposta = :resposta
              WHERE id = :id AND empresa_id = :empresa_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':status' => $status,
        ':respondido_por' => $funcionario_aprovador,
        ':resposta' => $observacao,
        ':id' => $id,
        ':empresa_id' => $empresa_id
    ]);
    
    $_SESSION['mensagem'] = "Solicitação " . ($acao == 'aprovar' ? 'aprovada' : 'rejeitada') . " com sucesso!";
    $_SESSION['tipo_mensagem'] = 'success';
    
} catch (Exception $e) {
    $_SESSION['mensagem'] = "Erro ao processar solicitação: " . $e->getMessage();
    $_SESSION['tipo_mensagem'] = 'error';
}

header('Location: admin.php');
exit;
?>