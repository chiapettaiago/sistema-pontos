<?php
// modules/biometrico/index.php - Dashboard Biométrico
$pageTitle = 'Biometria';
$activePage = 'biometrico';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

checkModuleAccess('funcionarios');

$database = new Database();
$db = $database->getConnection();

$empresa_id = getCurrentEmpresaId();
if ($empresa_id === null || $empresa_id === '') {
    $empresa_id = $_SESSION['empresa_id'] ?? null;
}
if ($empresa_id === null || $empresa_id === '') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Estatísticas
$query = "SELECT 
          COUNT(*) as total_funcionarios,
          SUM(CASE WHEN bf.id IS NOT NULL THEN 1 ELSE 0 END) as total_facial,
          SUM(CASE WHEN bd.id IS NOT NULL THEN 1 ELSE 0 END) as total_digital
          FROM funcionarios f
          LEFT JOIN biometricos_faciais bf ON f.id = bf.funcionario_id AND bf.ativo = 1
          LEFT JOIN biometricos_digitais bd ON f.id = bd.funcionario_id AND bd.ativo = 1
          WHERE f.empresa_id = :empresa_id AND f.status = 'ativo'";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$stats = $stmt->fetch();

// Últimos logs somente da empresa atual
$query = "SELECT l.*, f.nome as funcionario_nome
          FROM logs_biometricos l
          LEFT JOIN funcionarios f ON l.funcionario_id = f.id
          WHERE f.empresa_id = :empresa_id
          ORDER BY l.created_at DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$logs = $stmt->fetchAll();
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    border: 1px solid var(--border-color);
}

.stat-value {
    font-size: 36px;
    font-weight: 700;
    color: var(--primary);
}

.stat-label {
    font-size: 14px;
    color: var(--text-secondary);
    margin-top: 8px;
}

.biometrico-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    margin-bottom: 30px;
}

.action-card {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 24px;
    text-align: center;
    border: 1px solid var(--border-color);
    transition: all 0.3s;
    text-decoration: none;
    color: inherit;
    display: block;
}

.action-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
}

.action-icon {
    font-size: 48px;
    margin-bottom: 16px;
}

.action-icon i {
    font-size: 48px;
}

.action-facial i { color: #10b981; }
.action-digital i { color: #667eea; }

.action-title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 8px;
}

.action-desc {
    color: var(--text-secondary);
    font-size: 14px;
}

.log-table {
    width: 100%;
    border-collapse: collapse;
}

.log-table th,
.log-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.log-table th {
    background: var(--bg-secondary);
    font-weight: 600;
}

.status-success { color: #10b981; }
.status-error { color: #ef4444; }

.biometria-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin: 24px 0;
}

.summary-card {
    background: rgba(255,255,255,0.96);
    border-radius: 18px;
    padding: 18px;
    border: 1px solid rgba(255,255,255,0.35);
    box-shadow: 0 14px 40px rgba(15, 23, 42, 0.10);
}

.summary-title {
    font-size: 14px;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.summary-value {
    font-size: 32px;
    font-weight: 800;
    color: #111827;
}

.summary-link {
    display: inline-flex;
    margin-top: 10px;
    color: #1d4ed8;
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-fingerprint"></i> Biometria</h2>
        <p>Gerencie o cadastro biométrico dos funcionários</p>
    </div>
    <div class="module-actions">
        <a href="cadastrar.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Cadastrar Biometria
        </a>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['total_funcionarios'] ?? 0; ?></div>
        <div class="stat-label">Funcionários Ativos</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['total_facial'] ?? 0; ?></div>
        <div class="stat-label">Com Facial Cadastrado</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['total_digital'] ?? 0; ?></div>
        <div class="stat-label">Com Digital Cadastrada</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo ($stats['total_funcionarios'] ?? 0) - ($stats['total_facial'] ?? 0); ?></div>
        <div class="stat-label">Pendentes</div>
    </div>
</div>

<div class="biometria-summary">
    <div class="summary-card">
        <div class="summary-title">Foto cadastrada</div>
        <div class="summary-value"><?php echo $stats['total_funcionarios'] ?? 0; ?></div>
        <a href="../funcionarios/index.php" class="summary-link"><i class="fas fa-user-edit"></i> Revisar fotos dos funcionários</a>
    </div>
    <div class="summary-card">
        <div class="summary-title">Biometria facial</div>
        <div class="summary-value"><?php echo $stats['total_facial'] ?? 0; ?></div>
        <a href="cadastrar.php" class="summary-link"><i class="fas fa-camera"></i> Cadastrar/atualizar rostos</a>
    </div>
    <div class="summary-card">
        <div class="summary-title">Biometria digital</div>
        <div class="summary-value"><?php echo $stats['total_digital'] ?? 0; ?></div>
        <a href="../biometrico/digital.php" class="summary-link"><i class="fas fa-fingerprint"></i> Revisar digital</a>
    </div>
</div>

<!-- Ações Principais -->
<div class="biometrico-actions">
    <a href="cadastrar.php" class="action-card">
        <div class="action-icon action-facial">
            <i class="fas fa-camera"></i>
        </div>
        <div class="action-title">Cadastrar Biometria</div>
        <div class="action-desc">Registre a face dos funcionários</div>
    </a>
    
    <a href="../ponto/biometrico.php" class="action-card">
        <div class="action-icon action-digital">
            <i class="fas fa-fingerprint"></i>
        </div>
        <div class="action-title">Ponto por Biometria</div>
        <div class="action-desc">Registre o ponto usando reconhecimento facial</div>
    </a>
</div>

<!-- Últimos Registros -->
<div class="table-card">
    <div class="table-header">
        <h3><i class="fas fa-history"></i> Últimas Tentativas Biométricas</h3>
    </div>
    <div class="table-responsive">
        <table class="log-table">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Funcionário</th>
                    <th>Tipo</th>
                    <th>Status</th>
                 </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($log['funcionario_nome'] ?? 'Desconhecido'); ?></td>
                    <td><?php echo $log['tipo'] == 'facial' ? '👤 Facial' : '🖐️ Digital'; ?></td>
                    <td class="<?php echo $log['sucesso'] ? 'status-success' : 'status-error'; ?>">
                        <?php echo $log['sucesso'] ? '✅ Sucesso' : '❌ Falha'; ?>
                      </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="4" style="text-align: center;">Nenhum registro encontrado</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>



