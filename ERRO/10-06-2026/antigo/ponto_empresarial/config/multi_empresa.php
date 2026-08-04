<?php
// config/multi_empresa.php - Configuração Multi-Empresa
// NÃO pode haver nada antes desta linha

// Detectar empresa pelo subdomínio ou sessão
function detectarEmpresa($db) {
    // Se já tem empresa na sessão, usar ela
    if (isset($_SESSION['empresa_id']) && isset($_SESSION['empresa_dominio'])) {
        return [
            'id' => $_SESSION['empresa_id'],
            'nome' => $_SESSION['empresa_nome'],
            'dominio' => $_SESSION['empresa_dominio']
        ];
    }
    
    // Detectar pelo domínio
    $host = $_SERVER['HTTP_HOST'];
    
    // Remover www. e .localhost
    $dominio = str_replace(['www.', '.localhost', '.pontofacil.com'], '', $host);
    
    // Se for domínio principal (admin)
    if ($host == 'localhost' || $host == 'admin.pontofacil.com' || $dominio == 'admin') {
        return null; // Acesso ao sistema principal (Super Admin)
    }
    
    // Buscar empresa pelo domínio
    $query = "SELECT id, nome, dominio, status FROM empresas WHERE dominio = :dominio AND status = 'ativa'";
    $stmt = $db->prepare($query);
    $stmt->execute([':dominio' => $dominio]);
    $empresa = $stmt->fetch();
    
    if (!$empresa) {
        // Tentar buscar pelo subdomínio inteiro
        $subdominio = explode('.', $host)[0];
        $stmt->execute([':dominio' => $subdominio]);
        $empresa = $stmt->fetch();
        
        if (!$empresa) {
            die('Empresa não encontrada ou inativa. Contate o administrador do sistema.');
        }
    }
    
    // Salvar na sessão
    $_SESSION['empresa_id'] = $empresa['id'];
    $_SESSION['empresa_nome'] = $empresa['nome'];
    $_SESSION['empresa_dominio'] = $empresa['dominio'];
    
    return $empresa;
}

// Verificar recursos do plano
function verificarRecurso($db, $empresa_id, $recurso) {
    // Buscar plano ativo da empresa
    $query = "SELECT p.* FROM assinaturas a
              JOIN planos p ON a.plano_id = p.id
              WHERE a.empresa_id = :empresa_id 
              AND a.status = 'ativa'
              AND (a.data_fim IS NULL OR a.data_fim >= CURDATE())
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':empresa_id' => $empresa_id]);
    $plano = $stmt->fetch();
    
    if (!$plano) return false;
    
    // Mapeamento de recursos
    $recursos = [
        'departamentos' => 'recurso_departamentos',
        'horas_extras' => 'recurso_horas_extras',
        'banco_horas' => 'recurso_banco_horas',
        'justificativas' => 'recurso_justificativas',
        'relatorios_avancados' => 'recurso_relatorios_avancados',
        'multi_gestores' => 'recurso_multi_gestores',
        'qrcode' => 'recurso_qrcode',
        'exportacao_excel' => 'recurso_exportacao_excel',
        'api' => 'recurso_api',
        'suporte_prioritario' => 'recurso_suporte_prioritario'
    ];
    
    if (isset($recursos[$recurso])) {
        return $plano[$recursos[$recurso]] == 1;
    }
    
    return false;
}

// Verificar limite de funcionários
function verificarLimiteFuncionarios($db, $empresa_id) {
    $query = "SELECT p.recurso_funcionarios FROM assinaturas a
              JOIN planos p ON a.plano_id = p.id
              WHERE a.empresa_id = :empresa_id 
              AND a.status = 'ativa'
              AND (a.data_fim IS NULL OR a.data_fim >= CURDATE())
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':empresa_id' => $empresa_id]);
    $limite = $stmt->fetchColumn();
    
    if ($limite == 0) return true; // Ilimitado
    
    // Contar funcionários ativos
    $stmt = $db->prepare("SELECT COUNT(*) FROM funcionarios WHERE empresa_id = :empresa_id AND status = 'ativo'");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $total = $stmt->fetchColumn();
    
    return $total < $limite;
}

// Obter plano atual da empresa
function getPlanoAtual($db, $empresa_id) {
    $query = "SELECT p.*, a.data_fim, a.data_inicio, a.status as assinatura_status
              FROM assinaturas a
              JOIN planos p ON a.plano_id = p.id
              WHERE a.empresa_id = :empresa_id 
              AND a.status = 'ativa'
              AND (a.data_fim IS NULL OR a.data_fim >= CURDATE())
              ORDER BY a.id DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':empresa_id' => $empresa_id]);
    return $stmt->fetch();
}

// Adicionar filtro de empresa às queries
function filtroEmpresa($empresa_id, $tabela = null) {
    if ($tabela) {
        return " $tabela.empresa_id = :empresa_id";
    }
    return " empresa_id = :empresa_id";
}
?>