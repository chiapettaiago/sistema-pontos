<?php
// modules/auditoria/index.php - Painel de Logs de Auditoria
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa'])) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Auditoria e Logs';
$activePage = 'auditoria';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;

// Parâmetros de filtro
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$usuario_id = $_GET['usuario_id'] ?? '';
$acao = $_GET['acao'] ?? '';
$modulo = $_GET['modulo'] ?? '';
$pagina = (int)($_GET['pagina'] ?? 1);
$por_pagina = 50;
$offset = ($pagina - 1) * $por_pagina;

// Buscar usuários para filtro
$stmt = $db->prepare("SELECT id, nome, email FROM usuarios_sistema WHERE empresa_id = :empresa_id ORDER BY nome");
$stmt->execute([':empresa_id' => $empresa_id]);
$usuarios = $stmt->fetchAll();

// Montar query de logs
$query = "SELECT l.* 
          FROM logs_auditoria l
          WHERE l.created_at BETWEEN :data_inicio AND DATE_ADD(:data_fim, INTERVAL 1 DAY)";

$params = [
    ':data_inicio' => $data_inicio . ' 00:00:00',
    ':data_fim' => $data_fim . ' 23:59:59'
];

if ($usuario_id) {
    $query .= " AND l.usuario_id = :usuario_id";
    $params[':usuario_id'] = $usuario_id;
}

if ($acao) {
    $query .= " AND l.acao = :acao";
    $params[':acao'] = $acao;
}

if ($modulo) {
    $query .= " AND l.modulo = :modulo";
    $params[':modulo'] = $modulo;
}

// Contar total
$countQuery = str_replace("l.*", "COUNT(*) as total", $query);
$stmt = $db->prepare($countQuery);
$stmt->execute($params);
$total_registros = $stmt->fetch()['total'];
$total_paginas = ceil($total_registros / $por_pagina);

// Buscar logs
$query .= " ORDER BY l.created_at DESC LIMIT :offset, :limit";
$stmt = $db->prepare($query);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$logs = $stmt->fetchAll();

// Estatísticas
$stmt = $db->prepare("SELECT COUNT(*) as total, 
                      COUNT(DISTINCT usuario_id) as usuarios_ativos
                      FROM logs_auditoria 
                      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute();
$stats = $stmt->fetch();

// Ações disponíveis
$acoes = ['INSERT', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'VIEW', 'EXPORT', 'BACKUP', 'RESTORE'];
$modulos_lista = ['funcionarios', 'pontos', 'solicitacoes', 'relatorios', 'configuracoes', 'backup', 'usuarios', 'login'];
?>
<div class="pf-page-header">
    <h1><i class="fas fa-history me-2 text-primary"></i>Auditoria</h1>
    <p class="text-muted">Log de ações realizadas no sistema</p>
</div>

<!-- Filtros -->
<div class="card pf-table-card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-sm-4">
                <label class="form-label">Usuário</label>
                <input type="text" name="usuario" class="form-control" placeholder="Nome ou e-mail" value="<?php echo htmlspecialchars($_GET['usuario'] ?? ''); ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label">Ação</label>
                <input type="text" name="acao" class="form-control" placeholder="Tipo de ação" value="<?php echo htmlspecialchars($_GET['acao'] ?? ''); ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label">Data</label>
                <input type="date" name="data" class="form-control" value="<?php echo htmlspecialchars($_GET['data'] ?? ''); ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <a href="?" class="btn btn-outline-secondary ms-1"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card pf-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th class="d-none d-lg-table-cell">Detalhes</th>
                        <th class="d-none d-md-table-cell">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-muted small"><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($log['usuario_nome'] ?? $log['user_email'] ?? '-'); ?></td>
                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($log['acao'] ?? $log['action'] ?? '-'); ?></span></td>
                        <td class="d-none d-lg-table-cell text-muted small">
                            <?php echo htmlspecialchars(substr($log['detalhes'] ?? $log['details'] ?? '-', 0, 80)); ?>
                        </td>
                        <td class="d-none d-md-table-cell text-muted small"><?php echo htmlspecialchars($log['ip'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">
                        <i class="fas fa-history fa-2x d-block mb-2 opacity-25"></i>Nenhum registro de auditoria
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
