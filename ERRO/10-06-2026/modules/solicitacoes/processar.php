<?php
// modules/solicitacoes/processar.php - Processar ações unificadas (APROVAR/REJEITAR/CANCELAR)
session_start();
require_once '../../config/database.php';

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$id = $_POST['id'] ?? 0;
$acao = $_POST['acao'] ?? '';
$resposta = trim($_POST['resposta'] ?? '');
$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';

if (!$id || !$acao) {
    $_SESSION['mensagem'] = 'Dados inválidos';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: ' . ($usuario_tipo == 'funcionario' ? 'index.php' : 'admin.php'));
    exit;
}

// ============================================
// FUNCIONÁRIO CANCELANDO SOLICITAÇÃO
// ============================================
if ($acao == 'cancelar' && $usuario_tipo == 'funcionario') {
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
    $stmt = $db->prepare("SELECT id FROM solicitacoes 
                          WHERE id = :id AND funcionario_id = :funcionario_id AND status = 'pendente'");
    $stmt->execute([':id' => $id, ':funcionario_id' => $funcionario_id]);
    
    if (!$stmt->fetch()) {
        $_SESSION['mensagem'] = 'Solicitação não encontrada ou não pode ser cancelada';
        $_SESSION['tipo_mensagem'] = 'error';
        header('Location: index.php');
        exit;
    }
    
    try {
        $stmt = $db->prepare("UPDATE solicitacoes SET status = 'cancelado', data_resposta = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        $_SESSION['mensagem'] = '✅ Solicitação cancelada com sucesso!';
        $_SESSION['tipo_mensagem'] = 'success';
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['mensagem'] = 'Erro ao cancelar: ' . $e->getMessage();
        $_SESSION['tipo_mensagem'] = 'error';
        header('Location: index.php');
        exit;
    }
}

// ============================================
// GESTOR APROVANDO/REJEITANDO SOLICITAÇÃO
// ============================================
if (in_array($acao, ['aprovar', 'rejeitar']) && in_array($usuario_tipo, ['super_admin', 'admin_empresa', 'gestor'])) {
    $status = $acao == 'aprovar' ? 'aprovado' : 'rejeitado';
    
    // Pegar o ID do funcionário logado (quem está aprovando)
    $respondido_por = $_SESSION['funcionario_id'] ?? null;
    if (!$respondido_por && isset($_SESSION['usuario_id'])) {
        $stmt = $db->prepare("SELECT id FROM funcionarios WHERE email = :email");
        $stmt->execute([':email' => $_SESSION['usuario_email']]);
        $func = $stmt->fetch();
        if ($func) {
            $respondido_por = $func['id'];
        }
    }
    
    try {
        $query = "UPDATE solicitacoes SET 
                  status = :status, 
                  resposta = :resposta, 
                  data_resposta = NOW(),
                  respondido_por = :respondido_por
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':status' => $status,
            ':resposta' => $resposta,
            ':respondido_por' => $respondido_por,
            ':id' => $id
        ]);
        
        $_SESSION['mensagem'] = '✅ Solicitação ' . ($acao == 'aprovar' ? 'aprovada' : 'rejeitada') . ' com sucesso!';
        $_SESSION['tipo_mensagem'] = 'success';
        header('Location: admin.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['mensagem'] = 'Erro ao processar: ' . $e->getMessage();
        $_SESSION['tipo_mensagem'] = 'error';
        header('Location: admin.php');
        exit;
    }
}

// Ação inválida
$_SESSION['mensagem'] = 'Ação inválida ou sem permissão';
$_SESSION['tipo_mensagem'] = 'error';
header('Location: index.php');
exit;
?>