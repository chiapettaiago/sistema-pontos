<?php
// includes/auth.php - Permissões por tipo de usuário (ATUALIZADO)
// NÃO pode haver nada antes desta linha

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/router.php';

// Conectar ao banco
$database = new Database();
$db = $database->getConnection();

// Completa sessoes de usuarios administrativos que tambem possuem vinculo
// funcional. Isso permite usar ponto e solicitacoes sem um segundo login.
if (isset($_SESSION['usuario_id']) && empty($_SESSION['funcionario_id'])) {
    try {
        $stmtVinculo = $db->prepare(
            "SELECT id, filial_id, empresa_id FROM funcionarios
             WHERE usuario_sistema_id = :usuario_id AND status = 'ativo'
             LIMIT 1"
        );
        $stmtVinculo->execute([':usuario_id' => $_SESSION['usuario_id']]);
        $vinculo = $stmtVinculo->fetch();
        if ($vinculo) {
            $_SESSION['funcionario_id'] = (int) $vinculo['id'];
            $_SESSION['filial_id'] = (int) $vinculo['filial_id'];
            $_SESSION['usuario_filial_id'] = (int) $vinculo['filial_id'];
            $_SESSION['empresa_id'] = (int) $vinculo['empresa_id'];
        }
    } catch (Throwable $e) {
        error_log('Falha ao carregar vinculo funcional da sessao: ' . $e->getMessage());
    }
}

/** Atualiza tipo, escopo e permissões a partir do banco em toda requisição. */
function refreshDatabasePermissions(PDO $db): void
{
    if (empty($_SESSION['usuario_id'])) return;

    $tipoLogin = $_SESSION['tipo_login'] ?? 'sistema';
    $funcionarioId = (int) ($_SESSION['funcionario_id'] ?? 0);
    $tipo = appNormalizeUserType((string) ($_SESSION['usuario_tipo'] ?? 'funcionario'));
    $flags = null;

    if ($tipoLogin === 'sistema') {
        $stmt = $db->prepare("SELECT id, tipo, empresa_id, status FROM usuarios_sistema WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int) $_SESSION['usuario_id']]);
        $usuarioDb = $stmt->fetch();
        if (!$usuarioDb || $usuarioDb['status'] !== 'ativo') {
            $_SESSION = [];
            return;
        }
        $tipo = appNormalizeUserType((string) $usuarioDb['tipo']);
        $_SESSION['usuario_tipo'] = $tipo;
        $_SESSION['empresa_id'] = $usuarioDb['empresa_id'] !== null ? (int) $usuarioDb['empresa_id'] : null;

        $stmt = $db->prepare("SELECT id, empresa_id, filial_id, pode_gerenciar_filiais, pode_gerenciar_funcionarios, pode_ver_relatorios FROM funcionarios WHERE usuario_sistema_id = :id AND status = 'ativo' LIMIT 1");
        $stmt->execute([':id' => (int) $_SESSION['usuario_id']]);
        $flags = $stmt->fetch() ?: null;
        if ($flags) {
            $funcionarioId = (int) $flags['id'];
            $_SESSION['funcionario_id'] = $funcionarioId;
            $_SESSION['usuario_filial_id'] = (int) $flags['filial_id'];
        }
    } elseif ($funcionarioId > 0) {
        $stmt = $db->prepare("SELECT id, empresa_id, filial_id, tipo_usuario, status, pode_gerenciar_filiais, pode_gerenciar_funcionarios, pode_ver_relatorios FROM funcionarios WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $funcionarioId]);
        $flags = $stmt->fetch() ?: null;
        if (!$flags || $flags['status'] !== 'ativo') {
            $_SESSION = [];
            return;
        }
        $tipo = appNormalizeUserType((string) ($flags['tipo_usuario'] ?? 'funcionario'));
        $_SESSION['usuario_tipo'] = $tipo;
        $_SESSION['empresa_id'] = (int) $flags['empresa_id'];
        $_SESSION['usuario_filial_id'] = (int) $flags['filial_id'];
    }

    $administrador = in_array($tipo, ['super_admin', 'admin_empresa'], true);
    $temFlags = is_array($flags);
    $_SESSION['db_permissions'] = [
        'gerenciar_filiais' => $administrador || ($temFlags && (int) $flags['pode_gerenciar_filiais'] === 1),
        'gerenciar_funcionarios' => $administrador || ($temFlags
            ? (int) $flags['pode_gerenciar_funcionarios'] === 1
            : $tipo === 'gestor'),
        'ver_relatorios' => $administrador || ($temFlags
            ? (int) $flags['pode_ver_relatorios'] === 1
            : in_array($tipo, ['gestor', 'supervisor'], true)),
    ];
}

refreshDatabasePermissions($db);

// ============================================
// FUNÇÕES DE SESSÃO E AUTENTICAÇÃO
// ============================================

function isLoggedIn() {
    return isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_tipo']);
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        appRememberIntendedRoute();
        if (!headers_sent()) {
    header('Location: ' . BASE_URL . '/login');
            exit;
        } else {
    echo '<script>window.location.href="' . BASE_URL . '/login";</script>';
            exit;
        }
    }
}

function redirectIfNotAdmin() {
    redirectIfNotLoggedIn();
    $tipo = $_SESSION['usuario_tipo'] ?? '';
    if ($tipo !== 'super_admin' && $tipo !== 'admin_empresa') {
        if (!headers_sent()) {
    header('Location: ' . BASE_URL . '/index');
            exit;
        } else {
    echo '<script>window.location.href="' . BASE_URL . '/index";</script>';
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
    
    // Super Admin tem acesso total.
    if ($tipo === 'super_admin') {
        return true;
    }
    
    // Admin de Empresa tem acesso total somente na sua empresa.
    if ($tipo === 'admin_empresa') {
        return true;
    }
    
    $dbPermissions = $_SESSION['db_permissions'] ?? [];
    $databaseMap = [
        'ver_filiais' => 'gerenciar_filiais',
        'editar_filiais' => 'gerenciar_filiais',
        'gerenciar_filiais' => 'gerenciar_filiais',
        'ver_funcionarios' => 'gerenciar_funcionarios',
        'editar_funcionarios' => 'gerenciar_funcionarios',
        'cadastrar_funcionarios' => 'gerenciar_funcionarios',
        'excluir_funcionarios' => 'gerenciar_funcionarios',
        'gerenciar_funcionarios' => 'gerenciar_funcionarios',
        'ver_relatorios' => 'ver_relatorios',
    ];
    if (isset($databaseMap[$permissao]) && array_key_exists($databaseMap[$permissao], $dbPermissions)) {
        return (bool) $dbPermissions[$databaseMap[$permissao]];
    }

    // Permissões básicas inerentes ao perfil.
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
    
    if ($tipo === 'super_admin') {
        return true;
    }

    if ($tipo === 'admin_empresa') {
        global $db;
        $stmt = $db->prepare("SELECT 1 FROM funcionarios WHERE id = :id AND empresa_id = :empresa_id");
        $stmt->execute([':id' => $funcionario_id, ':empresa_id' => (int) ($_SESSION['empresa_id'] ?? 0)]);
        return (bool) $stmt->fetchColumn();
    }

    if (!hasPermission('editar_funcionarios')) return false;
    
    // Usuários delegados gerenciam apenas funcionários da própria empresa/filial.
    if ($usuario_filial_id) {
        global $db;
        $stmt = $db->prepare("SELECT 1 FROM funcionarios WHERE id = :id AND empresa_id = :empresa_id AND filial_id = :filial_id");
        $stmt->execute([
            ':id' => $funcionario_id,
            ':empresa_id' => (int) ($_SESSION['empresa_id'] ?? 0),
            ':filial_id' => (int) $usuario_filial_id,
        ]);
        return (bool) $stmt->fetchColumn();
    }
    
    return false;
}

function canViewFuncionario($funcionario_id) {
    if (!isLoggedIn()) return false;
    
    $tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';
    $usuario_filial_id = $_SESSION['usuario_filial_id'] ?? null;
    $usuario_id = $_SESSION['usuario_id'] ?? null;
    
    if ($tipo === 'super_admin') {
        return true;
    }

    if ($tipo === 'admin_empresa') {
        global $db;
        $stmt = $db->prepare("SELECT 1 FROM funcionarios WHERE id = :id AND empresa_id = :empresa_id");
        $stmt->execute([':id' => $funcionario_id, ':empresa_id' => (int) ($_SESSION['empresa_id'] ?? 0)]);
        return (bool) $stmt->fetchColumn();
    }
    
    // Gestores, supervisores e usuários delegados veem apenas a própria filial.
    if (($tipo === 'gestor' || $tipo === 'supervisor' || hasPermission('gerenciar_funcionarios')) && $usuario_filial_id) {
        global $db;
        $stmt = $db->prepare("SELECT 1 FROM funcionarios WHERE id = :id AND empresa_id = :empresa_id AND filial_id = :filial_id");
        $stmt->execute([
            ':id' => $funcionario_id,
            ':empresa_id' => (int) ($_SESSION['empresa_id'] ?? 0),
            ':filial_id' => (int) $usuario_filial_id,
        ]);
        return (bool) $stmt->fetchColumn();
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
    
    $modulePermission = [
        'filiais' => 'ver_filiais',
        'relatorios' => 'ver_relatorios',
        'funcionarios' => 'ver_funcionarios',
        'solicitacoes_admin' => 'aprovar_solicitacoes',
    ];
    if (isset($modulePermission[$module])) {
        if (!hasPermission($modulePermission[$module])) {
            http_response_code(403);
            $destination = appUrl(appHomeRouteFor($_SESSION['usuario_tipo'] ?? 'funcionario'));
            if (!headers_sent()) {
                header('Location: ' . $destination);
            } else {
                echo '<script>window.location.href=' . json_encode($destination) . ';</script>';
            }
            exit;
        }
        return;
    }

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
    header('Location: ' . BASE_URL . '/index');
            exit;
        } else {
    echo '<script>window.location.href="/index";</script>';
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
