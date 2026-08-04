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

<style>
.notificacoes-container {
    max-width: 900px;
    margin: 0 auto;
}

.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.module-title h2 {
    margin: 0;
    font-size: 24px;
}

.module-title p {
    margin: 8px 0 0 0;
    color: var(--text-secondary);
    font-size: 14px;
}

.stats-badge {
    background: #ef4444;
    color: white;
    border-radius: 20px;
    padding: 2px 8px;
    font-size: 12px;
    margin-left: 8px;
}

/* Filtros */
.filters-bar {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: center;
    border: 1px solid var(--border-color);
}

.filter-group {
    display: flex;
    gap: 8px;
    align-items: center;
}

.filter-group select {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--bg-primary);
    color: var(--text-primary);
}

.btn-filter {
    padding: 8px 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
}

.btn-marcar-todas {
    padding: 8px 16px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    cursor: pointer;
    text-decoration: none;
    font-size: 13px;
}

/* Lista de Notificações */
.notificacoes-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.notificacao-item {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 16px 20px;
    border: 1px solid var(--border-color);
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
    display: flex;
    gap: 16px;
    align-items: flex-start;
}

.notificacao-item:hover {
    transform: translateX(4px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.notificacao-item.nao-lida {
    background: rgba(102, 126, 234, 0.05);
    border-left: 3px solid #667eea;
}

.notificacao-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.notificacao-icon i {
    font-size: 24px;
    color: white;
}

.notificacao-conteudo {
    flex: 1;
}

.notificacao-titulo {
    font-weight: 600;
    margin-bottom: 4px;
    font-size: 15px;
    color: var(--text-primary);
}

.notificacao-mensagem {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 8px;
    line-height: 1.4;
}

.notificacao-data {
    font-size: 11px;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 12px;
}

.notificacao-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.btn-marcar-lida {
    background: none;
    border: none;
    color: #10b981;
    cursor: pointer;
    font-size: 14px;
    padding: 4px;
}

.btn-marcar-lida:hover {
    color: #059669;
}

.btn-excluir {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-size: 14px;
    padding: 4px;
}

.btn-excluir:hover {
    color: #dc2626;
}

.notificacao-link {
    text-decoration: none;
    color: inherit;
    display: flex;
    flex: 1;
    gap: 16px;
}

.empty-state {
    text-align: center;
    padding: 60px;
    background: var(--bg-primary);
    border-radius: 16px;
    border: 1px solid var(--border-color);
}

.empty-state i {
    font-size: 64px;
    color: #ccc;
    margin-bottom: 16px;
}

/* Configurações */
.config-card {
    background: var(--bg-primary);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    margin-bottom: 24px;
    overflow: hidden;
}

.config-header {
    padding: 16px 20px;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
    font-weight: 600;
}

.config-body {
    padding: 20px;
}

.config-group {
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
}

.config-group:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.config-label {
    font-weight: 500;
}

.config-label small {
    font-size: 11px;
    color: var(--text-secondary);
    display: block;
}

.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #667eea;
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.time-input {
    padding: 6px 10px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--bg-primary);
    color: var(--text-primary);
}

.btn-salvar {
    padding: 10px 24px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    margin-top: 16px;
}

@media (max-width: 768px) {
    .notificacao-item {
        flex-direction: column;
    }
    
    .notificacao-actions {
        align-self: flex-end;
    }
    
    .filters-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        justify-content: space-between;
    }
}
</style>

<div class="notificacoes-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-bell"></i> Notificações</h2>
            <p>Central de notificações do sistema</p>
        </div>
        <div class="module-actions">
            <a href="config.php" class="btn-marcar-todas" style="background: #667eea; color: white; border: none;">
                <i class="fas fa-cog"></i> Configurações
            </a>
            <?php if ($total_nao_lidas > 0): ?>
                <a href="marcar_todas.php" class="btn-marcar-todas">
                    <i class="fas fa-check-double"></i> Marcar todas como lidas
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-bar">
        <div class="filter-group">
            <label>Status:</label>
            <select id="statusFilter">
                <option value="todas" <?php echo $status == 'todas' ? 'selected' : ''; ?>>Todas</option>
                <option value="nao_lidas" <?php echo $status == 'nao_lidas' ? 'selected' : ''; ?>>Não lidas</option>
                <option value="lidas" <?php echo $status == 'lidas' ? 'selected' : ''; ?>>Lidas</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Tipo:</label>
            <select id="tipoFilter">
                <?php foreach ($tipos as $key => $nome): ?>
                    <option value="<?php echo $key; ?>" <?php echo $tipo == $key ? 'selected' : ''; ?>><?php echo $nome; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <button class="btn-filter" onclick="aplicarFiltro()">Filtrar</button>
            <button class="btn-filter" onclick="limparFiltro()" style="background: #6b7280;">Limpar</button>
        </div>
    </div>

    <!-- Lista de Notificações -->
    <?php if (empty($notificacoes)): ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <p>Nenhuma notificação encontrada</p>
            <small style="color: var(--text-secondary);">As notificações aparecerão aqui quando você receber alertas do sistema</small>
        </div>
    <?php else: ?>
        <div class="notificacoes-list">
            <?php foreach ($notificacoes as $notif): ?>
                <div class="notificacao-item <?php echo $notif['lida'] ? '' : 'nao-lida'; ?>" id="notif-<?php echo $notif['id']; ?>">
                    <div class="notificacao-icon" style="background: <?php echo $cores[$notif['tipo']]; ?>20;">
                        <i class="fas <?php echo $icones[$notif['tipo']]; ?>" style="color: <?php echo $cores[$notif['tipo']]; ?>;"></i>
                    </div>
                    <div class="notificacao-conteudo">
                        <div class="notificacao-titulo">
                            <?php echo htmlspecialchars($notif['titulo']); ?>
                        </div>
                        <div class="notificacao-mensagem">
                            <?php echo nl2br(htmlspecialchars($notif['mensagem'])); ?>
                        </div>
                        <div class="notificacao-data">
                            <span><i class="fas fa-calendar-alt"></i> <?php echo $notif['data_exibicao']; ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo $notif['hora_formatada']; ?></span>
                        </div>
                    </div>
                    <div class="notificacao-actions">
                        <?php if (!$notif['lida']): ?>
                            <button class="btn-marcar-lida" onclick="marcarLida(<?php echo $notif['id']; ?>)" title="Marcar como lida">
                                <i class="fas fa-check-circle"></i>
                            </button>
                        <?php endif; ?>
                        <button class="btn-excluir" onclick="excluirNotificacao(<?php echo $notif['id']; ?>)" title="Excluir">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function aplicarFiltro() {
    var status = document.getElementById('statusFilter').value;
    var tipo = document.getElementById('tipoFilter').value;
    window.location.href = 'index.php?status=' + status + '&tipo=' + tipo;
}

function limparFiltro() {
    window.location.href = 'index.php';
}

function marcarLida(id) {
    fetch('marcar_lida.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('notif-' + id).classList.remove('nao-lida');
            location.reload();
        }
    });
}

function excluirNotificacao(id) {
    if (confirm('Tem certeza que deseja excluir esta notificação?')) {
        fetch('marcar_lida.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id + '&excluir=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('notif-' + id).remove();
            }
        });
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>