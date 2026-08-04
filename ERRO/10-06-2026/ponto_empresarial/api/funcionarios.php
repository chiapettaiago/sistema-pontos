<?php
// api/funcionarios.php - Dados do funcionário e crachá
require_once 'config.php';

$user = authenticate();
$db = getDB();

// Buscar funcionário
$funcionario_id = $user['funcionario_id'] ?? null;
if (!$funcionario_id) {
    $stmt = $db->prepare("SELECT id FROM funcionarios WHERE email = :email");
    $stmt->execute([':email' => $user['email']]);
    $func = $stmt->fetch();
    $funcionario_id = $func ? $func['id'] : null;
}

if (!$funcionario_id) {
    jsonError('Perfil de funcionário não encontrado', 'FUNCIONARIO_NOT_FOUND', 404);
}

// GET - Dados do funcionário
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $apenas_cracha = isset($_GET['cracha']);
    
    $query = "SELECT f.*, 
              e.nome_empresa as empresa_nome,
              fi.nome_fantasia as filial_nome,
              c.nome as cargo_nome,
              d.nome as departamento_nome
              FROM funcionarios f
              LEFT JOIN empresa e ON f.empresa_id = e.id
              LEFT JOIN filiais fi ON f.filial_id = fi.id
              LEFT JOIN cargos c ON f.cargo_id = c.id
              LEFT JOIN departamentos d ON f.departamento_id = d.id
              WHERE f.id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $funcionario_id]);
    $funcionario = $stmt->fetch();
    
    if (!$funcionario) {
        jsonError('Funcionário não encontrado', 'NOT_FOUND', 404);
    }
    
    // Formatar CPF
    $cpf_formatado = '';
    if (!empty($funcionario['cpf'])) {
        $cpf_limpo = preg_replace('/[^0-9]/', '', $funcionario['cpf']);
        if (strlen($cpf_limpo) == 11) {
            $cpf_formatado = substr($cpf_limpo, 0, 3) . '.' . 
                             substr($cpf_limpo, 3, 3) . '.' . 
                             substr($cpf_limpo, 6, 3) . '-' . 
                             substr($cpf_limpo, 9, 2);
        }
    }
    
    // Calcular tempo de empresa
    $tempo_empresa = null;
    if (!empty($funcionario['data_admissao']) && $funcionario['data_admissao'] != '0000-00-00') {
        $data_admissao = new DateTime($funcionario['data_admissao']);
        $hoje = new DateTime();
        $diferenca = $hoje->diff($data_admissao);
        $tempo_empresa = [
            'anos' => $diferenca->y,
            'meses' => $diferenca->m,
            'dias' => $diferenca->d,
            'texto' => ($diferenca->y > 0 ? $diferenca->y . ' ano(s) ' : '') .
                       ($diferenca->m > 0 ? $diferenca->m . ' mês(es)' : '')
        ];
    }
    
    $response = [
        'id' => $funcionario['id'],
        'nome' => $funcionario['nome'],
        'matricula' => $funcionario['matricula'],
        'cpf' => $cpf_formatado,
        'email' => $funcionario['email'],
        'telefone' => $funcionario['telefone'],
        'foto' => $funcionario['foto'] ? '/ponto_empresarial/' . $funcionario['foto'] : null,
        'cargo' => $funcionario['cargo_nome'],
        'departamento' => $funcionario['departamento_nome'],
        'filial' => $funcionario['filial_nome'],
        'empresa' => $funcionario['empresa_nome'],
        'data_admissao' => date('d/m/Y', strtotime($funcionario['data_admissao'])),
        'tempo_empresa' => $tempo_empresa
    ];
    
    if ($apenas_cracha) {
        // Gerar URL do QR Code para crachá
        $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $base_url = $protocolo . '://' . $host . '/ponto_empresarial';
        
        $dados_qr = [
            'id' => $funcionario['matricula'],
            'nome' => $funcionario['nome'],
            'matricula' => $funcionario['matricula'],
            'cpf' => $cpf_formatado,
            'empresa' => $funcionario['empresa_nome'],
            'filial' => $funcionario['filial_nome'],
            'valido_ate' => date('Y-m-d', strtotime('+1 year'))
        ];
        
        $url_validacao = $base_url . '/validar_cracha.php?data=' . urlencode(json_encode($dados_qr));
        
        $response['cracha'] = [
            'qr_code_url' => "https://quickchart.io/qr?text=" . urlencode($url_validacao) . "&size=200&margin=2",
            'valido_ate' => date('d/m/Y', strtotime('+1 year')),
            'url_validacao' => $url_validacao
        ];
    }
    
    jsonSuccess($response);
}
?>