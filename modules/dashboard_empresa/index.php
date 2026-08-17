<?php
// modules/dashboard_empresa/index.php - Dashboard do Admin Empresa (COMPLETO)
$pageTitle = 'Dashboard Empresa';
$activePage = 'dashboard_empresa';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

// Verificar se é admin_empresa, gestor ou superior
if ($_SESSION['usuario_tipo'] !== 'admin_empresa' && $_SESSION['usuario_tipo'] !== 'super_admin' && $_SESSION['usuario_tipo'] !== 'gestor') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

require_once '../../includes/header.php';

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

// Buscar dados da empresa
$query = "SELECT e.*, 
          (SELECT COUNT(*) FROM funcionarios WHERE empresa_id = e.id AND status = 'ativo') as total_funcionarios,
          (SELECT COUNT(*) FROM filiais WHERE empresa_id = e.id AND ativo = 1) as total_filiais,
          (SELECT COUNT(*) FROM pontos WHERE empresa_id = e.id AND DATE(data_hora) = CURDATE()) as pontos_hoje,
          (SELECT COUNT(*) FROM solicitacoes WHERE empresa_id = e.id AND status = 'pendente') as solicitacoes_pendentes
          FROM empresas e
          WHERE e.id = :empresa_id";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$empresa = $stmt->fetch();

// Buscar assinatura atual
$query = "SELECT a.*, p.nome as plano_nome, p.recurso_funcionarios, p.recurso_filiais,
          p.recurso_horas_extras, p.recurso_relatorios_avancados, p.recurso_multi_gestores
          FROM assinaturas a
          JOIN planos p ON a.plano_id = p.id
          WHERE a.empresa_id = :empresa_id AND a.status = 'ativa'
          ORDER BY a.id DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$assinatura = $stmt->fetch();

// Estatísticas do mês
$query = "SELECT 
          COUNT(DISTINCT DATE(data_hora)) as dias_trabalhados,
          COUNT(*) as total_pontos,
          COUNT(DISTINCT funcionario_id) as funcionarios_ativos
          FROM pontos 
          WHERE empresa_id = :empresa_id 
          AND MONTH(data_hora) = MONTH(CURDATE()) 
          AND YEAR(data_hora) = YEAR(CURDATE())";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$stats_mes = $stmt->fetch();

// Pontos por tipo no mês (para gráfico)
$query = "SELECT tipo, COUNT(*) as total 
          FROM pontos 
          WHERE empresa_id = :empresa_id 
          AND MONTH(data_hora) = MONTH(CURDATE()) 
          AND YEAR(data_hora) = YEAR(CURDATE())
          GROUP BY tipo";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$pontos_por_tipo = $stmt->fetchAll();

// Pontos por dia no mês (para gráfico)
$query = "SELECT DATE(data_hora) as data, COUNT(*) as total 
          FROM pontos 
          WHERE empresa_id = :empresa_id 
          AND MONTH(data_hora) = MONTH(CURDATE()) 
          AND YEAR(data_hora) = YEAR(CURDATE())
          GROUP BY DATE(data_hora)
          ORDER BY data ASC";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$pontos_por_dia = $stmt->fetchAll();

// Últimas solicitações
$query = "SELECT s.*, f.nome as funcionario_nome
          FROM solicitacoes s
          JOIN funcionarios f ON s.funcionario_id = f.id
          WHERE s.empresa_id = :empresa_id
          ORDER BY s.created_at DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$ultimas_solicitacoes = $stmt->fetchAll();

// Funcionários que mais bateram ponto
$query = "SELECT f.nome, f.matricula, COUNT(p.id) as total_pontos
          FROM funcionarios f
          LEFT JOIN pontos p ON f.id = p.funcionario_id 
          WHERE f.empresa_id = :empresa_id 
          AND MONTH(p.data_hora) = MONTH(CURDATE()) 
          AND YEAR(p.data_hora) = YEAR(CURDATE())
          GROUP BY f.id
          ORDER BY total_pontos DESC
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$top_funcionarios = $stmt->fetchAll();

// Dias restantes da assinatura
$dias_restantes = 0;
$assinatura_status = 'ativa';
$data_fim = null;

if ($assinatura && $assinatura['data_fim']) {
    $data_fim = new DateTime($assinatura['data_fim']);
    $hoje = new DateTime();
    $dias_restantes = $hoje->diff($data_fim)->days;
    if ($data_fim < $hoje) {
        $assinatura_status = 'expirada';
    } elseif ($dias_restantes <= 30) {
        $assinatura_status = 'vencer';
    }
}
?>
<!-- PAGE HEADER -->
<div class="pf-page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-chart-line me-2 text-primary"></i>Dashboard da Empresa</h1>
        <p class="text-muted">Visão geral das atividades de <?php echo htmlspecialchars($empresa['nome'] ?? ''); ?></p>
    </div>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card pf-stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="pf-stat-icon" style="background:var(--pf-gradient);">
                    <i class="fas fa-users text-white"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold"><?php echo $empresa['total_funcionarios']; ?></div>
                    <div class="text-muted small">Funcionários</div>
                    <?php if (!empty($assinatura)): ?>
                    <div class="small text-primary">
                        Limite: <?php echo $assinatura['recurso_funcionarios'] == 0 ? 'Ilimitado' : $assinatura['recurso_funcionarios']; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card pf-stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="pf-stat-icon" style="background:linear-gradient(135deg,#10b981,#059669);">
                    <i class="fas fa-store text-white"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold"><?php echo $empresa['total_filiais']; ?></div>
                    <div class="text-muted small">Filiais</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card pf-stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="pf-stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                    <i class="fas fa-fingerprint text-white"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold"><?php echo $stats_mes['total_pontos'] ?? 0; ?></div>
                    <div class="text-muted small">Registros no Mês</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card pf-stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="pf-stat-icon" style="background:linear-gradient(135deg,#ef4444,#dc2626);">
                    <i class="fas fa-clipboard-list text-white"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold"><?php echo $empresa['solicitacoes_pendentes']; ?></div>
                    <div class="text-muted small">Solicitações Pendentes</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Gráfico por dia -->
    <div class="col-lg-7">
        <div class="card pf-table-card h-100">
            <div class="card-header bg-transparent border-bottom fw-semibold">
                <i class="fas fa-chart-line me-2 text-primary"></i>Registros por Dia (Mês)
            </div>
            <div class="card-body" style="height:260px;">
                <canvas id="pontosDiaChart"></canvas>
            </div>
        </div>
    </div>
    <!-- Gráfico por tipo -->
    <div class="col-lg-5">
        <div class="card pf-table-card h-100">
            <div class="card-header bg-transparent border-bottom fw-semibold">
                <i class="fas fa-chart-pie me-2 text-primary"></i>Distribuição por Tipo
            </div>
            <div class="card-body d-flex align-items-center justify-content-center" style="height:260px;">
                <canvas id="pontosTipoChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Recursos do plano -->
    <?php if (!empty($assinatura)): ?>
    <div class="col-lg-4">
        <div class="card pf-table-card h-100">
            <div class="card-header bg-transparent border-bottom fw-semibold">
                <i class="fas fa-crown me-2 text-warning"></i>Plano: <?php echo htmlspecialchars($assinatura['plano_nome'] ?? 'Básico'); ?>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span><i class="fas fa-users me-2 text-muted"></i>Funcionários</span>
                        <strong><?php echo $assinatura['recurso_funcionarios'] == 0 ? 'Ilimitado' : $assinatura['recurso_funcionarios']; ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span><i class="fas fa-clock me-2 text-muted"></i>Horas Extras</span>
                        <span class="badge <?php echo $assinatura['recurso_horas_extras'] ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo $assinatura['recurso_horas_extras'] ? 'Sim' : 'Não'; ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span><i class="fas fa-chart-bar me-2 text-muted"></i>Relatórios Avançados</span>
                        <span class="badge <?php echo $assinatura['recurso_relatorios_avancados'] ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo $assinatura['recurso_relatorios_avancados'] ? 'Sim' : 'Não'; ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span><i class="fas fa-users-cog me-2 text-muted"></i>Multi Gestores</span>
                        <span class="badge <?php echo $assinatura['recurso_multi_gestores'] ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo $assinatura['recurso_multi_gestores'] ? 'Sim' : 'Não'; ?>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top Funcionários -->
    <div class="col-lg-<?php echo !empty($assinatura) ? '8' : '12'; ?>">
        <div class="card pf-table-card h-100">
            <div class="card-header bg-transparent border-bottom fw-semibold">
                <i class="fas fa-trophy me-2 text-warning"></i>Top Funcionários (Mês)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Funcionário</th><th>Matrícula</th><th>Registros</th></tr></thead>
                        <tbody>
                            <?php foreach ($top_funcionarios as $func): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($func['nome']); ?></td>
                                <td class="text-muted"><?php echo htmlspecialchars($func['matricula']); ?></td>
                                <td><span class="badge bg-success"><?php echo $func['total_pontos']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($top_funcionarios)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Nenhum registro encontrado</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Últimas Solicitações -->
<div class="card pf-table-card mb-4">
    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="fas fa-clipboard-list me-2 text-primary"></i>Últimas Solicitações</span>
        <a href="../solicitacoes/admin.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Data</th><th>Funcionário</th><th>Tipo</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($ultimas_solicitacoes as $sol): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($sol['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($sol['funcionario_nome']); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $sol['tipo'])); ?></td>
                        <td>
                            <?php
                            $badgeClass = match($sol['status']) {
                                'aprovado'  => 'bg-success',
                                'reprovado' => 'bg-danger',
                                default     => 'bg-warning text-dark'
                            };
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($sol['status']); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($ultimas_solicitacoes)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">Nenhuma solicitação encontrada</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctxDia = document.getElementById('pontosDiaChart')?.getContext('2d');
if (ctxDia) {
    new Chart(ctxDia, {
        type: 'line',
        data: {
            labels: [<?php
                $labels = []; $values = [];
                foreach ($pontos_por_dia as $d) {
                    $labels[] = "'" . date('d/m', strtotime($d['data'])) . "'";
                    $values[] = $d['total'];
                }
                echo implode(',', $labels);
            ?>],
            datasets: [{
                label: 'Registros',
                data: [<?php echo implode(',', $values); ?>],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102,126,234,0.1)',
                tension: 0.4, fill: true, pointRadius: 4
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
}

const ctxTipo = document.getElementById('pontosTipoChart')?.getContext('2d');
if (ctxTipo) {
    <?php
    $entrada = 0; $saidaAlmoco = 0; $voltaAlmoco = 0; $saida = 0;
    foreach ($pontos_por_tipo as $p) {
        if ($p['tipo'] == 'entrada') $entrada = $p['total'];
        elseif ($p['tipo'] == 'saida_almoco') $saidaAlmoco = $p['total'];
        elseif ($p['tipo'] == 'volta_almoco') $voltaAlmoco = $p['total'];
        elseif ($p['tipo'] == 'saida') $saida = $p['total'];
    }
    ?>
    new Chart(ctxTipo, {
        type: 'doughnut',
        data: {
            labels: ['Entrada', 'Saída Almoço', 'Volta Almoço', 'Saída'],
            datasets: [{
                data: [<?php echo "$entrada,$saidaAlmoco,$voltaAlmoco,$saida"; ?>],
                backgroundColor: ['#667eea','#48bb78','#ed8936','#e53e3e'],
                borderWidth: 2, borderColor: '#fff'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>

