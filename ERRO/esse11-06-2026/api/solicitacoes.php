<?php
// api/solicitacoes.php - CRUD de solicitações
require_once 'config.php';

$user = authenticate();
$db = getDB();

// Buscar funcionário_id
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

// GET - Listar solicitações
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = $_GET['status'] ?? '';
    $tipo = $_GET['tipo'] ?? '';
    $pagina = (int)($_GET['pagina'] ?? 1);
    $por_pagina = 20;
    $offset = ($pagina - 1) * $por_pagina;
    
    $query = "SELECT s.*, 
              DATE_FORMAT(s.created_at, '%d/%m/%Y %H:%i') as data_solicitacao,
              CASE 
                  WHEN s.tipo = 'ferias' THEN 'Férias'
                  WHEN s.tipo = 'abono' THEN 'Abono'
                  WHEN s.tipo = 'licenca' THEN 'Licença'
                  WHEN s.tipo = 'justificativa' THEN 'Justificativa'
                  WHEN s.tipo = 'atestado' THEN 'Atestado'
                  ELSE s.tipo
              END as tipo_nome
              FROM solicitacoes s
              WHERE s.funcionario_id = :funcionario_id";
    
    $params = [':funcionario_id' => $funcionario_id];
    
    if ($status) {
        $query .= " AND s.status = :status";
        $params[':status'] = $status;
    }
    
    if ($tipo) {
        $query .= " AND s.tipo = :tipo";
        $params[':tipo'] = $tipo;
    }
    
    $query .= " ORDER BY s.created_at DESC LIMIT :offset, :limit";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':funcionario_id', $funcionario_id);
    if ($status) $stmt->bindValue(':status', $status);
    if ($tipo) $stmt->bindValue(':tipo', $tipo);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->execute();
    $solicitacoes = $stmt->fetchAll();
    
    // Contar total
    $countQuery = "SELECT COUNT(*) as total FROM solicitacoes WHERE funcionario_id = :funcionario_id";
    $countParams = [':funcionario_id' => $funcionario_id];
    if ($status) $countQuery .= " AND status = :status";
    if ($tipo) $countQuery .= " AND tipo = :tipo";
    
    $stmt = $db->prepare($countQuery);
    $stmt->execute($countParams);
    $total = $stmt->fetch()['total'];
    
    jsonSuccess([
        'dados' => $solicitacoes,
        'paginacao' => [
            'pagina_atual' => $pagina,
            'total_paginas' => ceil($total / $por_pagina),
            'total_registros' => (int)$total,
            'registros_por_pagina' => $por_pagina
        ]
    ]);
}

// POST - Criar solicitação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    validateRequired($input, ['tipo', 'titulo', 'descricao']);
    
    $tipo = $input['tipo'];
    $titulo = trim($input['titulo']);
    $descricao = trim($input['descricao']);
    $data_inicio = $input['data_inicio'] ?? null;
    $data_fim = $input['data_fim'] ?? null;
    
    $tipos_validos = ['ferias', 'abono', 'licenca', 'justificativa', 'atestado', 'outros'];
    if (!in_array($tipo, $tipos_validos)) {
        jsonError('Tipo de solicitação inválido', 'INVALID_TYPE', 400);
    }
    
    // Para férias, datas são obrigatórias
    if ($tipo == 'ferias' && (!$data_inicio || !$data_fim)) {
        jsonError('Datas de início e fim são obrigatórias para férias', 'MISSING_DATES', 400);
    }
    
    // Buscar empresa e filial do funcionário
    $stmt = $db->prepare("SELECT empresa_id, filial_id FROM funcionarios WHERE id = :id");
    $stmt->execute([':id' => $funcionario_id]);
    $func = $stmt->fetch();
    
    $stmt = $db->prepare("INSERT INTO solicitacoes 
                          (funcionario_id, empresa_id, filial_id, tipo, titulo, descricao, 
                           data_inicio, data_fim, status, created_at) 
                          VALUES 
                          (:funcionario_id, :empresa_id, :filial_id, :tipo, :titulo, :descricao,
                           :data_inicio, :data_fim, 'pendente', NOW())");
    
    $stmt->execute([
        ':funcionario_id' => $funcionario_id,
        ':empresa_id' => $func['empresa_id'],
        ':filial_id' => $func['filial_id'],
        ':tipo' => $tipo,
        ':titulo' => $titulo,
        ':descricao' => $descricao,
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim
    ]);
    
    jsonSuccess([
        'id' => $db->lastInsertId(),
        'tipo' => $tipo,
        'status' => 'pendente'
    ], 'Solicitação criada com sucesso!');
}

// PUT - Cancelar solicitação
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? ($_GET['id'] ?? 0);
    $acao = $input['acao'] ?? 'cancelar';
    
    if (!$id) {
        jsonError('ID da solicitação não informado', 'MISSING_ID', 400);
    }
    
    // Verificar se a solicitação pertence ao funcionário e está pendente
    $stmt = $db->prepare("SELECT status FROM solicitacoes 
                          WHERE id = :id AND funcionario_id = :funcionario_id");
    $stmt->execute([':id' => $id, ':funcionario_id' => $funcionario_id]);
    $solicitacao = $stmt->fetch();
    
    if (!$solicitacao) {
        jsonError('Solicitação não encontrada', 'NOT_FOUND', 404);
    }
    
    if ($solicitacao['status'] !== 'pendente') {
        jsonError('Apenas solicitações pendentes podem ser canceladas', 'INVALID_STATUS', 400);
    }
    
    $stmt = $db->prepare("UPDATE solicitacoes SET status = 'cancelado', data_resposta = NOW() 
                          WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    jsonSuccess(null, 'Solicitação cancelada com sucesso!');
}
?>