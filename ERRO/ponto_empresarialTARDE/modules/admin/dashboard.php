<?php
// modules/admin/dashboard.php - Dashboard do Super Admin
$pageTitle = 'Admin Dashboard';
$activePage = 'admin_dashboard';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

// Verificar se é super admin
if ($_SESSION['usuario_tipo'] !== 'super_admin') {
    header('Location: /index.php');
    exit;
}

require_once '../../includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Estatísticas
$stats = [];

// Total de empresas
$stmt = $db->query("SELECT COUNT(*) as total FROM empresas");
$stats['total_empresas'] = $stmt->fetch()['total'];

// Empresas ativas
$stmt = $db->query("SELECT COUNT(*) as total FROM empresas WHERE status = 'ativa'");
$stats['empresas_ativas'] = $stmt->fetch()['total'];

// Total de funcionários (todas empresas)
$stmt = $db->query("SELECT COUNT(*) as total FROM funcionarios");
$stats['total_funcionarios'] = $stmt->fetch()['total'];

// Faturamento mensal
$stmt = $db->query("SELECT SUM(valor) as total FROM assinaturas WHERE status = 'ativa' AND ciclo = 'mensal'");
$stats['faturamento_mensal'] = $stmt->fetch()['total'] ?? 0;

// Planos ativos
$stmt = $db->query("SELECT COUNT(*) as total FROM planos WHERE ativo = 1");
$stats['total_planos'] = $stmt->fetch()['total'];

// Gráfico: Empresas por plano
$query = "SELECT p.nome, COUNT(a.empresa_id) as total 
          FROM planos p
          LEFT JOIN assinaturas a ON p.id = a.plano_id AND a.status = 'ativa'
          GROUP BY p.id";
$stmt = $db->query($query);
$empresas_por_plano = $stmt->fetchAll();

// Últimas empresas cadastradas
$stmt = $db->query("SELECT * FROM empresas ORDER BY created_at DESC LIMIT 5");
$ultimas_empresas = $stmt->fetchAll();

// Assinaturas a vencer (próximos 30 dias)
$stmt = $db->query("SELECT a.*, e.nome as empresa_nome, p.nome as plano_nome
                    FROM assinaturas a
                    JOIN empresas e ON a.empresa_id = e.id
                    JOIN planos p ON a.plano_id = p.id
                    WHERE a.data_fim IS NOT NULL 
                    AND a.data_fim BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                    AND a.status = 'ativa'
                    ORDER BY a.data_fim ASC");
$assinaturas_vencer = $stmt->fetchAll();
?>

<style>
.admin-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.admin-stat-card {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 24px;
    border: 1px solid var(--border-color);
    transition: var(--transition);
}

.admin-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.admin-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
}

.admin-stat-icon i {
    font-size: 24px;
    color: white;
}

.admin-stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
}

.admin-stat-label {
    font-size: 14px;
    color: var(--text-secondary);
    margin-top: 4px;
}

.admin-section {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
}

.admin-section-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
}

.empresa-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
}

.empresa-item:last-child {
    border-bottom: none;
}

.vencer-badge {
    background: #fef3c7;
    color: #d97706;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-crown"></i> Painel Administrativo</h2>
        <p>Gerencie todas as empresas, planos e assinaturas da plataforma</p>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="admin-stats">
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
            <i class="fas fa-building"></i>
        </div>
        <div class="admin-stat-value"><?php echo $stats['total_empresas']; ?></div>
        <div class="admin-stat-label">Total de Empresas</div>
        <div style="font-size: 12px; margin-top: 8px;">
            <span style="color: #10b981;"><?php echo $stats['empresas_ativas']; ?> ativas</span>
        </div>
    </div>
    
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fas fa-users"></i>
        </div>
        <div class="admin-stat-value"><?php echo $stats['total_funcionarios']; ?></div>
        <div class="admin-stat-label">Funcionários Ativos</div>
    </div>
    
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="admin-stat-value">R$ <?php echo number_format($stats['faturamento_mensal'], 2, ',', '.'); ?></div>
        <div class="admin-stat-label">Faturamento Mensal</div>
    </div>
    
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <i class="fas fa-crown"></i>
        </div>
        <div class="admin-stat-value"><?php echo $stats['total_planos']; ?></div>
        <div class="admin-stat-label">Planos Disponíveis</div>
    </div>
</div>

<!-- Gráficos -->
<div class="admin-section">
    <div class="admin-section-title">
        <i class="fas fa-chart-pie"></i> Distribuição de Empresas por Plano
    </div>
    <div style="height: 300px;">
        <canvas id="planosChart"></canvas>
    </div>
</div>

<!-- Últimas Empresas -->
<div class="admin-section">
    <div class="admin-section-title">
        <i class="fas fa-clock"></i> Últimas Empresas Cadastradas
    </div>
    <div>
        <?php foreach ($ultimas_empresas as $empresa): ?>
        <div class="empresa-item">
            <div>
                <strong><?php echo htmlspecialchars($empresa['nome']); ?></strong>
                <div style="font-size: 12px; color: var(--text-secondary);">
                    <?php echo htmlspecialchars($empresa['email']); ?>
                </div>
            </div>
            <div>
                <span class="status-badge status-<?php echo $empresa['status']; ?>">
                    <?php echo ucfirst($empresa['status']); ?>
                </span>
                <a href="empresas/visualizar.php?id=<?php echo $empresa['id']; ?>" class="btn-icon">
                    <i class="fas fa-eye"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Assinaturas a Vencer -->
<?php if (!empty($assinaturas_vencer)): ?>
<div class="admin-section">
    <div class="admin-section-title">
        <i class="fas fa-exclamation-triangle"></i> Assinaturas a Vencer (Próximos 30 dias)
    </div>
    <div>
        <?php foreach ($assinaturas_vencer as $assinatura): ?>
        <div class="empresa-item">
            <div>
                <strong><?php echo htmlspecialchars($assinatura['empresa_nome']); ?></strong>
                <div style="font-size: 12px; color: var(--text-secondary);">
                    Plano: <?php echo htmlspecialchars($assinatura['plano_nome']); ?>
                </div>
            </div>
            <div>
                <span class="vencer-badge">
                    <i class="fas fa-calendar"></i> 
                    Vence em <?php echo date('d/m/Y', strtotime($assinatura['data_fim'])); ?>
                </span>
                <a href="assinaturas/renovar.php?id=<?php echo $assinatura['id']; ?>" class="btn-icon">
                    <i class="fas fa-sync-alt"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Links Rápidos -->
<div class="admin-section">
    <div class="admin-section-title">
        <i class="fas fa-link"></i> Links Rápidos
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
        <a href="empresas/index.php" class="btn btn-secondary">
            <i class="fas fa-building"></i> Gerenciar Empresas
        </a>
        <a href="planos/index.php" class="btn btn-secondary">
            <i class="fas fa-crown"></i> Gerenciar Planos
        </a>
        <a href="assinaturas/index.php" class="btn btn-secondary">
            <i class="fas fa-receipt"></i> Gerenciar Assinaturas
        </a>
        <a href="../relatorios/geral.php" class="btn btn-secondary">
            <i class="fas fa-chart-bar"></i> Relatórios Globais
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Gráfico de distribuição por plano
const ctx = document.getElementById('planosChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: [<?php 
            $labels = [];
            $values = [];
            foreach ($empresas_por_plano as $p) {
                $labels[] = "'" . addslashes($p['nome']) . "'";
                $values[] = $p['total'];
            }
            echo implode(',', $labels);
        ?>],
        datasets: [{
            data: [<?php echo implode(',', $values); ?>],
            backgroundColor: ['#667eea', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>

