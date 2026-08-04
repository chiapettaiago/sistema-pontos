<?php
// includes/auth.php - Permissões por tipo de usuário (ATUALIZADO)
// NÃO pode haver nada antes desta linha

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Conectar ao banco
$database = new Database();
$db = $database->getConnection();

// ============================================
// FUNÇÕES DE SESSÃO E AUTENTICAÇÃO
// ============================================

function isLoggedIn() {
    return isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_tipo']);
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        if (!headers_sent()) {
            header('Location: /ponto_empresarial/login.php');
            exit;
        } else {
            echo '<script>window.location.href="/ponto_empresarial/login.php";</script>';
            exit;
        }
    }
}

function redirectIfNotAdmin() {
    redirectIfNotLoggedIn();
    $tipo = $_SESSION['usuario_tipo'] ?? '';
    if ($tipo !== 'super_admin' && $tipo !== 'admin_empresa') {
        if (!headers_sent()) {
            header('Location: /ponto_empresarial/index.php');
            exit;
        } else {
            echo '<script>window.location.href="/ponto_empresarial/index.php";</script>';
            exit;
        }
    }
}

// ============================================
// PERMISSÕES POR TIPO DE USUÁRIO
// ============================================

function hasPermission($permissao) {
    if (!isLoggedIn()) return false;
    
    $tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';
    
    // Super Admin tem acesso total
    if ($tipo === 'super_admin') {
        return true;
    }
    
    // Admin de Empresa tem acesso total na sua empresa
    if ($tipo === 'admin_empresa') {
        return true;
    }
    
    // Permissões específicas por tipo
    $permissoes = [
        'gestor' => [
            'ver_funcionarios' => true,
            'editar_funcionarios' => true,
            'excluir_funcionarios' => false,
            'cadastrar_funcionarios' => true,
            'ver_usuarios' => false,
            'editar_usuarios' => false,
            'ver_filiais' => false,
            'ver_relatorios' => true,
            'aprovar_solicitacoes' => true,
            'ver_solicitacoes' => true,
            'registrar_ponto' => true,
            'ver_extrato' => true,
            'gerar_cracha' => true,
            'ver_notificacoes' => false,
            'configurar_notificacoes' => false
        ],
        'supervisor' => [
            'ver_funcionarios' => true,
            'editar_funcionarios' => false,
            'excluir_funcionarios' => false,
            'cadastrar_funcionarios' => false,
            'ver_usuarios' => false,
            'ver_filiais' => false,
            'ver_relatorios' => true,
            'aprovar_solicitacoes' => false,
            'ver_solicitacoes' => true,
            'registrar_ponto' => true,
            'ver_extrato' => true,
            'gerar_cracha' => true,
            'ver_notificacoes' => false,
            'configurar_notificacoes' => false
        ],
        'funcionario' => [
            'ver_funcionarios' => false,
            'editar_funcionarios' => false,
            'excluir_funcionarios' => false,
            'cadastrar_funcionarios' => false,
            'ver_usuarios' => false,
            'ver_filiais' => false,
            'ver_relatorios' => false,
            'ver_solicitacoes' => true,
            'criar_solicitacoes' => true,
            'registrar_ponto' => true,
            'ver_extrato' => true,
            'gerar_cracha' => true
        ]
    ];
    
    $userPerms = $permissoes[$tipo] ?? [];
    return $userPerms[$permissao] ?? false;
}

// ============================================
// FUNÇÕES DE VERIFICAÇÃO DE ACESSO
// ============================================

function canEditFuncionario($funcionario_id) {
    if (!isLoggedIn()) return false;
    
    $tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';
    $usuario_filial_id = $_SESSION['usuario_filial_id'] ?? null;
    
    // Super Admin e Admin Empresa podem editar qualquer funcionário
    if ($tipo === 'super_admin' || $tipo === 'admin_empresa') {
        return true;
    }
    
    // Gestor só pode editar funcionários da sua filial
    if ($tipo === 'gestor' && $usuario_filial_id) {
        global $db;
        $stmt = $db->prepare("SELECT filial_id FROM funcionarios WHERE id = :id");
        $stmt->execute([':id' => $funcionario_id]);
        $funcionario = $stmt->fetch();
        return $funcionario && $funcionario['filial_id'] == $usuario_filial_id;
    }
    
    return false;
}

function canViewFuncionario($funcionario_id) {
    if (!isLoggedIn()) return false;
    
    $tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';
    $usuario_filial_id = $_SESSION['usuario_filial_id'] ?? null;
    $usuario_id = $_SESSION['usuario_id'] ?? null;
    
    // Super Admin e Admin Empresa podem ver qualquer funcionário
    if ($tipo === 'super_admin' || $tipo === 'admin_empresa') {
        return true;
    }
    
    // Gestor e Supervisor só podem ver funcionários da sua filial
    if (($tipo === 'gestor' || $tipo === 'supervisor') && $usuario_filial_id) {
        global $db;
        $stmt = $db->prepare("SELECT filial_id FROM funcionarios WHERE id = :id");
        $stmt->execute([':id' => $funcionario_id]);
        $funcionario = $stmt->fetch();
        return $funcionario && $funcionario['filial_id'] == $usuario_filial_id;
    }
    
    // Funcionário só pode ver a si mesmo
    if ($tipo === 'funcionario') {
        return $funcionario_id == $usuario_id;
    }
    
    return false;
}

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

function getCurrentUserId() {
    return $_SESSION['usuario_id'] ?? null;
}

function getCurrentUserTipo() {
    return $_SESSION['usuario_tipo'] ?? null;
}

function getCurrentEmpresaId() {
    return $_SESSION['empresa_id'] ?? null;
}

function getCurrentEmpresaNome() {
    return $_SESSION['empresa_nome'] ?? null;
}

function getCurrentFuncionarioId() {
    return $_SESSION['funcionario_id'] ?? null;
}

function getCurrentUserFilial() {
    return $_SESSION['usuario_filial_id'] ?? null;
}

// ============================================
// VERIFICAÇÃO DE ACESSO A MÓDULOS
// ============================================

function checkModuleAccess($module) {
    redirectIfNotLoggedIn();
    
    // Mapeamento de módulos para tipos de usuário
    $modulePermissions = [
        'dashboard' => ['super_admin', 'admin_empresa', 'gestor', 'supervisor', 'funcionario'],
        'usuarios' => ['super_admin', 'admin_empresa'],
        'funcionarios' => ['super_admin', 'admin_empresa', 'gestor', 'supervisor'],
        'filiais' => ['super_admin', 'admin_empresa'],
        'relatorios' => ['super_admin', 'admin_empresa', 'gestor', 'supervisor'],
        'ponto' => ['super_admin', 'admin_empresa', 'gestor', 'supervisor', 'funcionario'],
        'cracha' => ['super_admin', 'admin_empresa', 'gestor', 'supervisor', 'funcionario'],
        'solicitacoes' => ['super_admin', 'admin_empresa', 'gestor', 'supervisor', 'funcionario'],
        'solicitacoes_admin' => ['super_admin', 'admin_empresa', 'gestor'],
        'planos' => ['super_admin'],
        'empresas' => ['super_admin'],
        'assinaturas' => ['super_admin'],
        'notificacoes' => ['super_admin', 'admin_empresa'],
        'biometrico' => ['super_admin', 'admin_empresa', 'gestor']
    ];
    
    $userType = $_SESSION['usuario_tipo'] ?? 'funcionario';
    
    if (!in_array($userType, $modulePermissions[$module] ?? [])) {
        if (!headers_sent()) {
            header('Location: /ponto_empresarial/index.php');
            exit;
        } else {
            echo '<script>window.location.href="/ponto_empresarial/index.php";</script>';
            exit;
        }
    }
}

// ============================================
// LOG DE AÇÕES
// ============================================

function logAcao($db, $acao, $tabela = null, $registro_id = null, $descricao = null) {
    if (!isLoggedIn()) return;
    
    try {
        $query = "INSERT INTO logs_sistema (funcionario_id, acao, tabela_afetada, registro_id, descricao, ip, user_agent) 
                  VALUES (:funcionario_id, :acao, :tabela, :registro_id, :descricao, :ip, :user_agent)";
        
        $stmt = $db->prepare($query);
        
        $funcionario_id = getCurrentFuncionarioId() ?: getCurrentUserId();
        
        $stmt->execute([
            ':funcionario_id' => $funcionario_id,
            ':acao' => $acao,
            ':tabela' => $tabela,
            ':registro_id' => $registro_id,
            ':descricao' => $descricao,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Erro ao logar ação: " . $e->getMessage());
    }
}

// ============================================
// FUNÇÕES DE CONFIGURAÇÃO
// ============================================

function getConfig($db, $chave, $empresa_id = null) {
    if ($empresa_id === null) {
        $empresa_id = getCurrentEmpresaId();
    }
    
    try {
        $query = "SELECT valor FROM configuracoes WHERE chave = :chave AND (empresa_id = :empresa_id OR empresa_id IS NULL)";
        $stmt = $db->prepare($query);
        $stmt->execute([':chave' => $chave, ':empresa_id' => $empresa_id]);
        $result = $stmt->fetch();
        return $result ? $result['valor'] : null;
    } catch (Exception $e) {
        return null;
    }
}
?>