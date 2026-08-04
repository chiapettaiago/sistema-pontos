<?php
// modules/solicitacoes/index.php - Minhas Solicitações (CORRIGIDO)
$pageTitle = 'Minhas Solicitações';
$activePage = 'solicitacoes';

// ============================================
// VERIFICAÇÕES ANTES DO HEADER
// ============================================
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Pega o ID do funcionário da sessão
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
    $_SESSION['mensagem'] = 'Perfil de funcionário não encontrado.';
    header('Location: ../../index.php');
    exit;
}

// Buscar parâmetros de filtro
$status = isset($_GET['status']) ? $_GET['status'] : '';
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

// Query base - CORRIGIDA: usando created_at em vez de data_solicitacao
$query = "SELECT s.*, 
          DATE_FORMAT(s.data_inicio, '%d/%m/%Y') as data_inicio_formatada,
          DATE_FORMAT(s.data_fim, '%d/%m/%Y') as data_fim_formatada,
          DATE_FORMAT(s.created_at, '%d/%m/%Y %H:%i') as data_solicitacao_formatada,
          CASE 
              WHEN s.tipo = 'ferias' THEN 'Férias'
              WHEN s.tipo = 'abono' THEN 'Abono'
              WHEN s.tipo = 'licenca' THEN 'Licença'
              WHEN s.tipo = 'justificativa_ausencia' THEN 'Justificativa de Ausência'
              WHEN s.tipo = 'justificativa' THEN 'Justificativa'
              WHEN s.tipo = 'alteracao_ponto' THEN 'Alteração de Ponto'
              WHEN s.tipo = 'atestado' THEN 'Atestado'
              WHEN s.tipo = 'outros' THEN 'Outros'
              ELSE s.tipo
          END as tipo_nome,
          CASE 
              WHEN s.status = 'pendente' THEN '⏳ Pendente'
              WHEN s.status = 'aprovado' THEN '✅ Aprovado'
              WHEN s.status = 'rejeitado' THEN '❌ Rejeitado'
              WHEN s.status = 'cancelado' THEN '🗑️ Cancelado'
              ELSE s.status
          END as status_texto
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

$query .= " ORDER BY s.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$solicitacoes = $stmt->fetchAll();

// Estatísticas
$stats = [];
$query = "SELECT 
          SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
          SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) as aprovadas,
          SUM(CASE WHEN status = 'rejeitado' THEN 1 ELSE 0 END) as rejeitadas,
          SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as canceladas
          FROM solicitacoes 
          WHERE funcionario_id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $funcionario_id]);
$stats = $stmt->fetch();

// Tipos para o filtro
$tipos_filtro = [
    '' => 'Todos os tipos',
    'ferias' => 'Férias',
    'abono' => 'Abono',
    'licenca' => 'Licença',
    'justificativa' => 'Justificativa',
    'atestado' => 'Atestado',
    'alteracao_ponto' => 'Alteração de Ponto',
    'outros' => 'Outros'
];

require_once '../../includes/header.php';
?>

<style>
.stats-mini {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.stat-mini-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 16px;
    text-align: center;
    border: 1px solid var(--border-color);
}

.stat-mini-card .value {
    font-size: 28px;
    font-weight: 700;
}

.stat-mini-card .label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 4px;
}

.stat-mini-card.pendente .value { color: #f59e0b; }
.stat-mini-card.aprovada .value { color: #10b981; }
.stat-mini-card.rejeitada .value { color: #ef4444; }
.stat-mini-card.cancelada .value { color: #6b7280; }

.solicitacao-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 16px;
    border: 1px solid var(--border-color);
    transition: all 0.3s;
}

.solicitacao-card:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.solicitacao-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 8px;
}

.solicitacao-tipo {
    font-weight: 600;
    font-size: 16px;
}

.solicitacao-tipo i {
    margin-right: 8px;
}

.solicitacao-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-pendente {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.status-aprovado {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-rejeitado {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.status-cancelado {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
}

.solicitacao-datas {
    display: flex;
    gap: 24px;
    margin-bottom: 12px;
    font-size: 14px;
    color: var(--text-secondary);
}

.solicitacao-datas i {
    margin-right: 4px;
}

.solicitacao-descricao {
    background: var(--bg-secondary);
    padding: 12px;
    border-radius: 12px;
    margin: 12px 0;
    font-size: 14px;
}

.solicitacao-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
    font-size: 12px;
    color: var(--text-secondary);
}

.btn-cancelar {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-size: 12px;
}

.btn-cancelar:hover {
    text-decoration: underline;
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

.filters-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
}

.filters-form {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-size: 12px;
    color: var(--text-secondary);
}

.filter-group select {
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
    min-width: 150px;
}

.btn {
    padding: 10px 20px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
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
    margin: 0 0 5px 0;
    font-size: 24px;
}

.module-title p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 14px;
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-clipboard-list"></i> Minhas Solicitações</h2>
        <p>Acompanhe suas solicitações de férias, abono e licenças</p>
    </div>
    <div class="module-actions">
        <a href="nova.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nova Solicitação
        </a>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="stats-mini">
    <div class="stat-mini-card pendente">
        <div class="value"><?php echo $stats['pendentes'] ?? 0; ?></div>
        <div class="label">Pendentes</div>
    </div>
    <div class="stat-mini-card aprovada">
        <div class="value"><?php echo $stats['aprovadas'] ?? 0; ?></div>
        <div class="label">Aprovadas</div>
    </div>
    <div class="stat-mini-card rejeitada">
        <div class="value"><?php echo $stats['rejeitadas'] ?? 0; ?></div>
        <div class="label">Rejeitadas</div>
    </div>
    <div class="stat-mini-card cancelada">
        <div class="value"><?php echo $stats['canceladas'] ?? 0; ?></div>
        <div class="label">Canceladas</div>
    </div>
</div>

<!-- Filtros -->
<div class="filters-card">
    <form method="GET" action="" class="filters-form">
        <div class="filter-group">
            <label><i class="fas fa-filter"></i> Status</label>
            <select name="status">
                <option value="">Todos</option>
                <option value="pendente" <?php echo $status == 'pendente' ? 'selected' : ''; ?>>Pendentes</option>
                <option value="aprovado" <?php echo $status == 'aprovado' ? 'selected' : ''; ?>>Aprovadas</option>
                <option value="rejeitado" <?php echo $status == 'rejeitado' ? 'selected' : ''; ?>>Rejeitadas</option>
                <option value="cancelado" <?php echo $status == 'cancelado' ? 'selected' : ''; ?>>Canceladas</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label><i class="fas fa-tag"></i> Tipo</label>
            <select name="tipo">
                <?php foreach ($tipos_filtro as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $tipo == $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filtrar
            </button>
        </div>
        
        <div class="filter-group">
            <label>&nbsp;</label>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Limpar
            </a>
        </div>
    </form>
</div>

<!-- Lista de Solicitações -->
<?php if (empty($solicitacoes)): ?>
<div class="empty-state">
    <i class="fas fa-clipboard-list"></i>
    <p>Nenhuma solicitação encontrada</p>
    <a href="nova.php" class="btn btn-primary" style="margin-top: 16px;">
        <i class="fas fa-plus"></i> Fazer primeira solicitação
    </a>
</div>
<?php else: ?>
    <?php foreach ($solicitacoes as $s): ?>
    <div class="solicitacao-card">
        <div class="solicitacao-header">
            <div class="solicitacao-tipo">
                <i class="fas 
                    <?php echo $s['tipo'] == 'ferias' ? 'fa-umbrella-beach' : 
                                 ($s['tipo'] == 'abono' ? 'fa-gift' : 
                                 ($s['tipo'] == 'licenca' ? 'fa-notes-medical' : 'fa-comment')); ?>">
                </i>
                <?php echo $s['tipo_nome']; ?>
            </div>
            <div class="solicitacao-status status-<?php echo $s['status']; ?>">
                <?php echo $s['status_texto']; ?>
            </div>
        </div>
        
        <div class="solicitacao-titulo" style="font-size: 16px; font-weight: 600; margin-bottom: 8px;">
            <?php echo htmlspecialchars($s['titulo'] ?? 'Solicitação'); ?>
        </div>
        
        <div class="solicitacao-datas">
            <?php if ($s['data_inicio_formatada']): ?>
            <span><i class="fas fa-calendar-alt"></i> Início: <?php echo $s['data_inicio_formatada']; ?></span>
            <?php endif; ?>
            <?php if ($s['data_fim_formatada']): ?>
            <span><i class="fas fa-calendar-check"></i> Fim: <?php echo $s['data_fim_formatada']; ?></span>
            <?php endif; ?>
        </div>
        
        <?php if ($s['descricao']): ?>
        <div class="solicitacao-descricao">
            <i class="fas fa-comment"></i> <?php echo nl2br(htmlspecialchars($s['descricao'])); ?>
        </div>
        <?php endif; ?>
        
        <div class="solicitacao-footer">
            <span>
                <i class="fas fa-clock"></i> Solicitado em: <?php echo $s['data_solicitacao_formatada']; ?>
            </span>
            <?php if ($s['status'] == 'pendente'): ?>
            <button onclick="cancelarSolicitacao(<?php echo $s['id']; ?>)" class="btn-cancelar">
                <i class="fas fa-times"></i> Cancelar solicitação
            </button>
            <?php endif; ?>
        </div>
        
        <?php if ($s['status'] != 'pendente' && $s['resposta']): ?>
        <div class="solicitacao-descricao" style="background: rgba(59, 130, 246, 0.1); margin-top: 12px;">
            <i class="fas fa-user-check"></i> 
            <strong>Resposta do gestor:</strong> <?php echo nl2br(htmlspecialchars($s['resposta'])); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
function cancelarSolicitacao(id) {
    if (confirm('Tem certeza que deseja cancelar esta solicitação?')) {
        window.location.href = 'cancelar.php?id=' + id;
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>