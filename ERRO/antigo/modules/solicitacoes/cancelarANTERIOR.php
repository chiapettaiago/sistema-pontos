<?php
// modules/solicitacoes/cancelar.php - Cancelar Solicitação (CORRIGIDO)
require_once '../../config/database.php';

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;

if (!$id) {
    $_SESSION['mensagem'] = 'Solicitação não identificada';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: index.php');
    exit;
}

// CORRIGIDO: Pega o ID do funcionário da sessão
$funcionario_id = $_SESSION['funcionario_id'] ?? null;

if (!$funcionario_id && isset($_SESSION['usuario_id'])) {
    $stmt = $db->prepare("SELECT id FROM funcionarios WHERE email = :email");
    $stmt->execute([':email' => $_SESSION['usuario_email']]);
    $func = $stmt->fetch();
    if ($func) {
        $funcionario_id = $func['id'];
        $_SESSION['funcionario_id'] = $funcionario_id;
    }
}

if (!$funcionario_id) {
    $_SESSION['mensagem'] = 'Perfil de funcionário não encontrado';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: index.php');
    exit;
}

// Verificar se a solicitação pertence ao funcionário e está pendente
$query = "SELECT id, tipo, status FROM solicitacoes 
          WHERE id = :id AND funcionario_id = :funcionario_id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $id, ':funcionario_id' => $funcionario_id]);
$solicitacao = $stmt->fetch();

if (!$solicitacao) {
    $_SESSION['mensagem'] = 'Solicitação não encontrada';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: index.php');
    exit;
}

// Verificar se está pendente (apenas pendentes podem ser canceladas)
if ($solicitacao['status'] !== 'pendente') {
    $_SESSION['mensagem'] = 'Apenas solicitações pendentes podem ser canceladas';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: index.php');
    exit;
}

try {
    $query = "UPDATE solicitacoes SET status = 'cancelado', data_resposta = NOW() WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $id]);
    
    $_SESSION['mensagem'] = "Solicitação cancelada com sucesso!";
    $_SESSION['tipo_mensagem'] = 'success';
    
} catch (Exception $e) {
    $_SESSION['mensagem'] = "Erro ao cancelar solicitação: " . $e->getMessage();
    $_SESSION['tipo_mensagem'] = 'error';
}

header('Location: index.php');
exit;
?>