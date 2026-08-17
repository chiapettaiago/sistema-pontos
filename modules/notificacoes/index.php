<?php
// modules/notificacoes/index.php - Lista de Notificações
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Notificações';
$activePage = 'notificacoes';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$usuario_id = $_SESSION['usuario_id'];
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';

// Parâmetros de filtro
$status = isset($_GET['status']) ? $_GET['status'] : 'todas';
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

// Buscar notificações
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

if ($status == 'nao_lidas') {
    $query .= " AND n.lida = 0";
} elseif ($status == 'lidas') {
    $query .= " AND n.lida = 1";
}

if ($tipo) {
    $query .= " AND n.tipo = :tipo";
    $params[':tipo'] = $tipo;
}

$query .= " ORDER BY n.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$notificacoes = $stmt->fetchAll();

// Contadores
$stmt = $db->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0");
$stmt->execute([':usuario_id' => $usuario_id]);
$total_nao_lidas = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id");
$stmt->execute([':usuario_id' => $usuario_id]);
$total_notificacoes = $stmt->fetch()['total'];

// Tipos para filtro
$tipos = [
    '' => 'Todos os tipos',
    'sistema' => 'Sistema',
    'ponto' => 'Ponto',
    'solicitacao' => 'Solicitação',
    'alerta' => 'Alerta',
    'lembrete' => 'Lembrete'
];

$icones = [
    'sistema' => 'fa-cog',
    'ponto' => 'fa-fingerprint',
    'solicitacao' => 'fa-clipboard-list',
    'alerta' => 'fa-exclamation-triangle',
    'lembrete' => 'fa-bell'
];

$cores = [
    'sistema' => '#667eea',
    'ponto' => '#10b981',
    'solicitacao' => '#f59e0b',
    'alerta' => '#ef4444',
    'lembrete' => '#8b5cf6'
];
?>
<div class="pf-page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-bell me-2 text-primary"></i>Notificações</h1>
        <p class="text-muted">Central de notificações do sistema</p>
    </div>
    <?php if (!empty($notificacoes_nao_lidas)): ?>
    <a href="marcar_todas.php" class="btn btn-outline-primary"><i class="fas fa-check-double me-1"></i>Marcar todas como lidas</a>
    <?php endif; ?>
</div>

<div class="card pf-table-card">
    <div class="card-body p-0">
        <?php if (!empty($notificacoes)): ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($notificacoes as $n): ?>
            <li class="list-group-item <?php echo !$n['lida'] ? 'list-group-item-light' : ''; ?>">
                <div class="d-flex align-items-start gap-3">
                    <div class="mt-1">
                        <i class="fas fa-bell <?php echo !$n['lida'] ? 'text-primary' : 'text-muted'; ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold <?php echo !$n['lida'] ? '' : 'text-muted'; ?>">
                            <?php echo htmlspecialchars($n['titulo'] ?? $n['mensagem'] ?? ''); ?>
                        </div>
                        <?php if (!empty($n['mensagem']) && !empty($n['titulo'])): ?>
                        <div class="text-muted small"><?php echo htmlspecialchars($n['mensagem']); ?></div>
                        <?php endif; ?>
                        <div class="text-muted small mt-1">
                            <i class="fas fa-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($n['created_at'])); ?>
                        </div>
                    </div>
                    <?php if (!$n['lida']): ?>
                    <a href="marcar_lida.php?id=<?php echo $n['id']; ?>" class="btn btn-sm btn-outline-primary flex-shrink-0">
                        <i class="fas fa-check"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-bell-slash fa-3x mb-3 opacity-25"></i>
            <p>Nenhuma notificação encontrada</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
