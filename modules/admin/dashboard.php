<?php
// modules/admin/dashboard.php - Dashboard do Super Admin
$pageTitle = 'Admin Dashboard';
$activePage = 'admin_dashboard';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

// Verificar se é super admin
if ($_SESSION['usuario_tipo'] !== 'super_admin') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

require_once '../../includes/header.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = getCurrentEmpresaId() ?? null;

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
<!-- PAGE HEADER -->
<div class="pf-page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-crown me-2 text-warning"></i>Painel Administrativo</h1>
        <p class="text-muted">Gerencie todas as empresas, planos e assinaturas da plataforma</p>
    </div>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card pf-stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="pf-stat-icon" style="background:var(--pf-gradient);">
                    <i class="fas fa-building text-white"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold"><?php echo $stats['total_empresas']; ?></div>
                    <div class="text-muted small">Total de Empresas</div>
                    <div class="text-success small"><?php echo $stats['empresas_ativas']; ?> ativas</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card pf-stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="pf-stat-icon" style="background:linear-gradient(135deg,#10b981,#059669);">
                    <i class="fas fa-users text-white"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold"><?php echo $stats['total_funcionarios']; ?></div>
                    <div class="text-muted small">Funcionários Cadastrados</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card pf-stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="pf-stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                    <i class="fas fa-chart-line text-white"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold">R$ <?php echo number_format($stats['faturamento_mensal'], 2, ',', '.'); ?></div>
                    <div class="text-muted small">Faturamento Mensal</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card pf-stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="pf-stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);">
                    <i class="fas fa-crown text-white"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold"><?php echo $stats['total_planos']; ?></div>
                    <div class="text-muted small">Planos Disponíveis</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Gráfico -->
    <div class="col-lg-5">
        <div class="card pf-table-card h-100">
            <div class="card-header bg-transparent border-bottom fw-semibold">
                <i class="fas fa-chart-pie me-2 text-primary"></i>Distribuição por Plano
            </div>
            <div class="card-body d-flex align-items-center justify-content-center" style="height:280px;">
                <canvas id="planosChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Últimas Empresas -->
    <div class="col-lg-7">
        <div class="card pf-table-card h-100">
            <div class="card-header bg-transparent border-bottom fw-semibold">
                <i class="fas fa-clock me-2 text-primary"></i>Últimas Empresas Cadastradas
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table pf-table-card mb-0">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>E-mail</th>
                                <th>Status</th>
                                <th class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimas_empresas as $empresa): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($empresa['nome']); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($empresa['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo $empresa['status'] === 'ativa' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst($empresa['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="empresas/visualizar.php?id=<?php echo $empresa['id']; ?>"
                                       class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assinaturas a vencer -->
<?php if (!empty($assinaturas_vencer)): ?>
<div class="card pf-table-card mb-4">
    <div class="card-header bg-transparent border-bottom fw-semibold text-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>Assinaturas a Vencer (próximos 30 dias)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Empresa</th><th>Plano</th><th>Vencimento</th><th class="text-end">Ação</th></tr></thead>
                <tbody>
                    <?php foreach ($assinaturas_vencer as $a): ?>
                    <tr>
                        <td class="fw-semibold"><?php echo htmlspecialchars($a['empresa_nome']); ?></td>
                        <td><?php echo htmlspecialchars($a['plano_nome']); ?></td>
                        <td>
                            <span class="badge bg-warning text-dark">
                                <i class="fas fa-calendar me-1"></i><?php echo date('d/m/Y', strtotime($a['data_fim'])); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="assinaturas/renovar.php?id=<?php echo $a['id']; ?>" class="btn btn-sm btn-warning">
                                <i class="fas fa-sync-alt"></i> Renovar
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Links Rápidos -->
<div class="card pf-table-card mb-4">
    <div class="card-header bg-transparent border-bottom fw-semibold">
        <i class="fas fa-link me-2 text-primary"></i>Links Rápidos
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-6 col-md-3">
                <a href="empresas/index.php" class="btn btn-outline-primary w-100">
                    <i class="fas fa-building d-block mb-1 fs-5"></i>Empresas
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="planos/index.php" class="btn btn-outline-warning w-100">
                    <i class="fas fa-crown d-block mb-1 fs-5"></i>Planos
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="assinaturas/index.php" class="btn btn-outline-success w-100">
                    <i class="fas fa-receipt d-block mb-1 fs-5"></i>Assinaturas
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="../relatorios/geral.php" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-chart-bar d-block mb-1 fs-5"></i>Relatórios
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('planosChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: [<?php
            $labels = []; $values = [];
            foreach ($empresas_por_plano as $p) {
                $labels[] = "'" . addslashes($p['nome']) . "'";
                $values[] = $p['total'];
            }
            echo implode(',', $labels);
        ?>],
        datasets: [{
            data: [<?php echo implode(',', $values); ?>],
            backgroundColor: ['#667eea','#10b981','#f59e0b','#ef4444','#8b5cf6'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>

