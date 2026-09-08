<?php
// modules/auditoria/index.php - Painel de Logs de Auditoria (COM PERMISSÕES E SEGURANÇA)
session_start();

if (!isset($_SESSION['usuario_id'])) {
header('Location: ../../login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
$empresa_id = $_SESSION['empresa_id'] ?? 1;

// ============================================
// VERIFICAR PERMISSÃO - Apenas Super Admin e Admin Empresa
// ============================================
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa'])) {
    // Registrar tentativa de acesso não autorizado
    require_once '../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Registrar log de segurança
    try {
        $stmt = $db->prepare("INSERT INTO logs_auditoria 
            (usuario_id, usuario_nome, usuario_email, usuario_tipo, acao, modulo, descricao, ip_address, user_agent, created_at) 
            VALUES 
            (:usuario_id, :nome, :email, :tipo, 'VIEW', 'auditoria', :descricao, :ip, :agent, NOW())");
        $stmt->execute([
            ':usuario_id' => $_SESSION['usuario_id'] ?? null,
            ':nome' => $_SESSION['usuario_nome'] ?? 'Desconhecido',
            ':email' => $_SESSION['usuario_email'] ?? null,
            ':tipo' => $usuario_tipo,
            ':descricao' => 'TENTATIVA NÃO AUTORIZADA - Acesso negado à auditoria',
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // Ignorar erro de log
    }
    
    // Redirecionar para página inicial
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Auditoria e Logs';
$activePage = 'auditoria';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../includes/funcoes_auditoria.php';

$database = new Database();
$db = $database->getConnection();

// ============================================
// SUPER ADMIN: pode ver todas as empresas
// ADMIN EMPRESA: vê apenas sua empresa
// ============================================
$empresa_filtro = $_GET['empresa_id'] ?? '';

// Se for Super Admin, buscar lista de empresas
$empresas = [];
if ($usuario_tipo === 'super_admin') {
    $stmt = $db->query("SELECT id, nome FROM empresas ORDER BY nome");
    $empresas = $stmt->fetchAll();
}

// Parâmetros de filtro
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$usuario_id = $_GET['usuario_id'] ?? '';
$acao = $_GET['acao'] ?? '';
$modulo = $_GET['modulo'] ?? '';
$pagina = (int)($_GET['pagina'] ?? 1);
$por_pagina = 50;
$offset = ($pagina - 1) * $por_pagina;

// Montar query com base no tipo de usuário
$query = "SELECT l.* FROM logs_auditoria l WHERE 1=1";
$params = [];

// Filtrar por empresa (Super Admin)
if ($usuario_tipo === 'super_admin' && $empresa_filtro) {
    // Para Super Admin, filtrar por empresa (precisa de JOIN com tabela de usuários)
    $query .= " AND l.usuario_id IN (SELECT id FROM usuarios_sistema WHERE empresa_id = :empresa_filtro)";
    $params[':empresa_filtro'] = $empresa_filtro;
} elseif ($usuario_tipo === 'admin_empresa') {
    // Admin Empresa vê apenas usuários da sua empresa
    $query .= " AND l.usuario_id IN (SELECT id FROM usuarios_sistema WHERE empresa_id = :empresa_id)";
    $params[':empresa_id'] = $empresa_id;
}

// Filtros de data
$query .= " AND l.created_at BETWEEN :data_inicio AND DATE_ADD(:data_fim, INTERVAL 1 DAY)";
$params[':data_inicio'] = $data_inicio . ' 00:00:00';
$params[':data_fim'] = $data_fim . ' 23:59:59';

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
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_registros = $stmt->fetch()['total'];
$total_paginas = ceil($total_registros / $por_pagina);

// Buscar logs
$query .= " ORDER BY l.created_at DESC LIMIT :offset, :limit";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

// Buscar usuários para filtro (apenas da empresa atual)
$usuarios = [];
if ($usuario_tipo === 'super_admin' && $empresa_filtro) {
    $stmt = $db->prepare("SELECT id, nome, email FROM usuarios_sistema WHERE empresa_id = :empresa_id ORDER BY nome");
    $stmt->execute([':empresa_id' => $empresa_filtro]);
    $usuarios = $stmt->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, nome, email FROM usuarios_sistema WHERE empresa_id = :empresa_id ORDER BY nome");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $usuarios = $stmt->fetchAll();
}

// Estatísticas
$statsQuery = "SELECT 
                  COUNT(*) as total,
                  COUNT(DISTINCT usuario_id) as usuarios_ativos
                FROM logs_auditoria l
                WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
if ($usuario_tipo === 'admin_empresa') {
    $statsQuery .= " AND l.usuario_id IN (SELECT id FROM usuarios_sistema WHERE empresa_id = :empresa_id)";
}
$stmt = $db->prepare($statsQuery);
if ($usuario_tipo === 'admin_empresa') {
    $stmt->bindValue(':empresa_id', $empresa_id);
}
$stmt->execute();
$stats = $stmt->fetch();

// Ações disponíveis
$acoes = ['INSERT', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'VIEW', 'EXPORT', 'BACKUP', 'RESTORE'];
$modulos_lista = ['funcionarios', 'pontos', 'solicitacoes', 'relatorios', 'configuracoes', 'backup', 'usuarios', 'login', 'auditoria'];
?>

<style>
.auditoria-container {
    max-width: 1400px;
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

/* Filters */
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
    font-weight: 500;
}

.filter-group input,
.filter-group select {
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--bg-primary);
    color: var(--text-primary);
    min-width: 150px;
}

.btn-filter {
    padding: 10px 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 500;
}

.btn-clear {
    padding: 10px 20px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    cursor: pointer;
    text-decoration: none;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    border: 1px solid var(--border-color);
}

.stat-card .stat-number {
    font-size: 32px;
    font-weight: 700;
    color: var(--text-primary);
}

.stat-card .stat-label {
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 5px;
}

/* Table */
.table-card {
    background: var(--bg-primary);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.table-header {
    padding: 16px 20px;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.table-header h3 {
    margin: 0;
    font-size: 16px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table th {
    background: var(--bg-secondary);
    font-weight: 600;
    font-size: 12px;
    color: var(--text-secondary);
}

.data-table tr:hover {
    background: var(--bg-secondary);
}

.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

.badge-INSERT { background: #d1fae5; color: #059669; }
.badge-UPDATE { background: #bfdbfe; color: #1e40af; }
.badge-DELETE { background: #fee2e2; color: #dc2626; }
.badge-LOGIN { background: #d1fae5; color: #059669; }
.badge-LOGOUT { background: #fef3c7; color: #d97706; }
.badge-VIEW { background: #e0e7ff; color: #4338ca; }
.badge-EXPORT { background: #fed7aa; color: #c2410c; }
.badge-BACKUP { background: #d1fae5; color: #059669; }
.badge-RESTORE { background: #fee2e2; color: #dc2626; }

.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    padding: 20px;
    flex-wrap: wrap;
}

.pagination a, .pagination span {
    padding: 8px 12px;
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-primary);
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
}

.pagination .active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-color: transparent;
}

.pagination a:hover {
    background: var(--bg-tertiary);
}

.empty-state {
    text-align: center;
    padding: 60px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    display: block;
}

.btn-export {
    background: #10b981;
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
}

.btn-clear-logs {
    background: #ef4444;
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
}

@media (max-width: 768px) {
    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .data-table {
        font-size: 12px;
    }
    
    .data-table th,
    .data-table td {
        padding: 8px;
    }
}
</style>

<div class="auditoria-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-history"></i> Auditoria e Logs</h2>
            <p>Registro de todas as ações dos usuários no sistema</p>
        </div>
        <div class="module-actions">
            <a href="exportar.php" class="btn-export">
                <i class="fas fa-file-excel"></i> Exportar Logs
            </a>
            <a href="limpar.php" class="btn-clear-logs" onclick="return confirm('Tem certeza que deseja limpar logs antigos? Esta ação não pode ser desfeita.')">
                <i class="fas fa-trash-alt"></i> Limpar Logs Antigos
            </a>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
            <div class="stat-label">Total de Ações (30 dias)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['usuarios_ativos'] ?? 0; ?></div>
            <div class="stat-label">Usuários Ativos</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo date('d/m/Y', strtotime($data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($data_fim)); ?></div>
            <div class="stat-label">Período</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-form">
            <div class="filter-group">
                <label>Data Início</label>
                <input type="date" name="data_inicio" value="<?php echo $data_inicio; ?>">
            </div>
            <div class="filter-group">
                <label>Data Fim</label>
                <input type="date" name="data_fim" value="<?php echo $data_fim; ?>">
            </div>
            
            <!-- Filtro por Empresa (apenas Super Admin) -->
            <?php if ($usuario_tipo === 'super_admin' && !empty($empresas)): ?>
            <div class="filter-group">
                <label>Empresa</label>
                <select name="empresa_id">
                    <option value="">Todas as empresas</option>
                    <?php foreach ($empresas as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php echo $empresa_filtro == $emp['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($emp['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="filter-group">
                <label>Usuário</label>
                <select name="usuario_id">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $usuario_id == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Ação</label>
                <select name="acao">
                    <option value="">Todas</option>
                    <?php foreach ($acoes as $a): ?>
                        <option value="<?php echo $a; ?>" <?php echo $acao == $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Módulo</label>
                <select name="modulo">
                    <option value="">Todos</option>
                    <?php foreach ($modulos_lista as $m): ?>
                        <option value="<?php echo $m; ?>" <?php echo $modulo == $m ? 'selected' : ''; ?>><?php echo ucfirst($m); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn-filter">Filtrar</button>
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <a href="index.php" class="btn-clear">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Aviso de Segurança (apenas para Super Admin) -->
    <?php if ($usuario_tipo === 'super_admin'): ?>
    <div style="margin-bottom: 20px; padding: 12px; background: #fef3c7; border-radius: 12px; border-left: 4px solid #f59e0b;">
        <i class="fas fa-shield-alt"></i>
        <strong>Modo Super Admin:</strong> Você está visualizando logs de <?php echo $empresa_filtro ? 'uma empresa específica' : 'TODAS as empresas'; ?>.
    </div>
    <?php endif; ?>

    <!-- Tabela de Logs -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Registros de Auditoria</h3>
            <span>Total: <?php echo number_format($total_registros); ?> registros</span>
        </div>
        <div class="table-responsive">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>Nenhum registro encontrado no período selecionado</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Usuário</th>
                            <th>Ação</th>
                            <th>Módulo</th>
                            <th>Descrição</th>
                            <th>IP</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($log['usuario_nome'] ?? 'Sistema'); ?></strong>
                                    <small style="display: block; color: var(--text-secondary);"><?php echo htmlspecialchars($log['usuario_email'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $log['acao']; ?>">
                                        <?php echo $log['acao']; ?>
                                    </span>
                                </td>
                                <td><?php echo ucfirst($log['modulo']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars(substr($log['descricao'] ?? '', 0, 100)); ?>
                                    <?php if (strlen($log['descricao'] ?? '') > 100): ?>...<?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                <td>
                                    <a href="detalhes.php?id=<?php echo $log['id']; ?>" class="btn-icon" title="Ver detalhes">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Paginação -->
        <?php if ($total_paginas > 1): ?>
            <div class="pagination">
                <?php if ($pagina > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>">&laquo; Anterior</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <?php if ($i == $pagina): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($pagina < $total_paginas): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>">Próximo &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
