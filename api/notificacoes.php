<?php
// api/notificacoes.php - Notificações push
require_once 'config.php';

$user = authenticate();
$db = getDB();

$usuario_id = $user['id'];

// GET - Listar notificações
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $apenas_nao_lidas = isset($_GET['nao_lidas']) ? (int)$_GET['nao_lidas'] : 0;
    $pagina = (int)($_GET['pagina'] ?? 1);
    $por_pagina = 30;
    $offset = ($pagina - 1) * $por_pagina;
    
    $query = "SELECT n.*, 
              DATE_FORMAT(n.created_at, '%d/%m/%Y') as data_formatada,
              DATE_FORMAT(n.created_at, '%H:%i') as hora_formatada,
              CASE 
                  WHEN DATEDIFF(NOW(), n.created_at) = 0 THEN 'Hoje'
                  WHEN DATEDIFF(NOW(), n.created_at) = 1 THEN 'Ontem'
                  ELSE DATE_FORMAT(n.created_at, '%d/%m/%Y')
              END as data_exibicao
              FROM notificacoes n
              WHERE n.usuario_id = :usuario_id";
    
    $params = [':usuario_id' => $usuario_id];
    
    if ($apenas_nao_lidas) {
        $query .= " AND n.lida = 0";
    }
    
    $query .= " ORDER BY n.created_at DESC LIMIT :offset, :limit";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':usuario_id', $usuario_id);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->execute();
    $notificacoes = $stmt->fetchAll();
    
    // Contar não lidas
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notificacoes 
                          WHERE usuario_id = :usuario_id AND lida = 0");
    $stmt->execute([':usuario_id' => $usuario_id]);
    $nao_lidas = $stmt->fetch()['total'];
    
    jsonSuccess([
        'dados' => $notificacoes,
        'nao_lidas' => (int)$nao_lidas,
        'paginacao' => [
            'pagina_atual' => $pagina,
            'registros_por_pagina' => $por_pagina
        ]
    ]);
}

// PUT - Marcar notificação como lida
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? ($_GET['id'] ?? 0);
    $marcar_todas = isset($input['marcar_todas']) ? (int)$input['marcar_todas'] : 0;
    
    if ($marcar_todas) {
        $stmt = $db->prepare("UPDATE notificacoes SET lida = 1, data_leitura = NOW() 
                              WHERE usuario_id = :usuario_id AND lida = 0");
        $stmt->execute([':usuario_id' => $usuario_id]);
        $total = $stmt->rowCount();
        jsonSuccess(['total_atualizadas' => $total], "$total notificações marcadas como lidas");
    } elseif ($id) {
        $stmt = $db->prepare("UPDATE notificacoes SET lida = 1, data_leitura = NOW() 
                              WHERE id = :id AND usuario_id = :usuario_id");
        $stmt->execute([':id' => $id, ':usuario_id' => $usuario_id]);
        jsonSuccess(null, 'Notificação marcada como lida');
    } else {
        jsonError('ID da notificação não informado', 'MISSING_ID', 400);
    }
}

// DELETE - Excluir notificação
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        jsonError('ID da notificação não informado', 'MISSING_ID', 400);
    }
    
    $stmt = $db->prepare("DELETE FROM notificacoes WHERE id = :id AND usuario_id = :usuario_id");
    $stmt->execute([':id' => $id, ':usuario_id' => $usuario_id]);
    
    jsonSuccess(null, 'Notificação excluída com sucesso');
}
?>