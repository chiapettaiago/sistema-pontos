<?php
// modules/dashboard_empresa/index.php - Dashboard do Admin Empresa
$pageTitle = 'Dashboard Empresa';
$activePage = 'dashboard_empresa';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

// Verificar se é admin_empresa ou superior
if ($_SESSION['usuario_tipo'] !== 'admin_empresa' && $_SESSION['usuario_tipo'] !== 'super_admin') {
    header('Location: /ponto_empresarial/index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$empresa_id = getCurrentEmpresaId() ?: 1;

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

<style>
.empresa-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 24px;
    color: white;
}

.empresa-nome {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
}

.empresa-info {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
    margin-top: 16px;
    font-size: 14px;
    opacity: 0.9;
}

.assinatura-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.assinatura-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-ativa { background: #d1fae5; color: #059669; }
.status-vencer { background: #fef3c7; color: #d97706; }
.status-expirada { background: #fee2e2; color: #dc2626; }

.charts-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 24px;
    margin-bottom: 24px;
}

.chart-card {
    background: var(--bg-primary);
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.chart-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
}

.chart-header h3 {
    margin: 0;
    font-size: 16px;
}

.chart-body {
    padding: 20px;
}

.progress-bar {
    background: var(--bg-secondary);
    border-radius: 10px;
    height: 8px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-fill {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    height: 100%;
    border-radius: 10px;
    transition: width 0.3s;
}

.recurso-limit {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 13px;
}

@media (max-width: 768px) {
    .charts-row {
        grid-template-columns: 1fr;
    }
    
    .empresa-header {
        padding: 20px;
    }
    
    .empresa-nome {
        font-size: 22px;
    }
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-chart-pie"></i> Dashboard da Empresa</h2>
        <p>Visão geral da sua organização</p>
    </div>
</div>

<div class="empresa-header">
    <div class="empresa-nome">
        <i class="fas fa-building"></i> <?php echo htmlspecialchars($empresa['nome']); ?>
    </div>
    <div class="empresa-info">
        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($empresa['email']); ?></div>
        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($empresa['telefone'] ?: 'Não informado'); ?></div>
        <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($empresa['cidade'] ?: 'Não informado'); ?></div>
    </div>
</div>

<!-- Card de Assinatura -->
<div class="assinatura-card">
    <div>
        <strong><i class="fas fa-crown"></i> Plano <?php echo htmlspecialchars($assinatura['plano_nome'] ?? 'Nenhum'); ?></strong>
        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
            <?php if ($assinatura && $data_fim): ?>
                <?php if ($assinatura_status == 'vencer'): ?>
                    ⚠️ Vence em <?php echo $dias_restantes; ?> dias
                <?php elseif ($assinatura_status == 'expirada'): ?>
                    ❌ Assinatura expirada
                <?php else: ?>
                    ✅ Até <?php echo $data_fim->format('d/m/Y'); ?>
                <?php endif; ?>
            <?php else: ?>
                Ativo
            <?php endif; ?>
        </div>
    </div>
    <div>
        <span class="assinatura-status status-<?php echo $assinatura_status; ?>">
            <?php echo ucfirst($assinatura_status); ?>
        </span>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $empresa['total_funcionarios']; ?> / <?php echo $assinatura['recurso_funcionarios'] == 0 ? '∞' : $assinatura['recurso_funcionarios']; ?></h3>
            <p>Funcionários Ativos</p>
            <?php if ($assinatura['recurso_funcionarios'] > 0): ?>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo min(100, ($empresa['total_funcionarios'] / $assinatura['recurso_funcionarios']) * 100); ?>%"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fas fa-store"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $empresa['total_filiais']; ?></h3>
            <p>Filiais</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class="fas fa-fingerprint"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats_mes['total_pontos'] ?? 0; ?></h3>
            <p>Registros no Mês</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $empresa['solicitacoes_pendentes']; ?></h3>
            <p>Solicitações Pendentes</p>
        </div>
    </div>
</div>

<!-- Recursos do Plano -->
<div class="table-card" style="margin-bottom: 24px;">
    <div class="table-header">
        <h3><i class="fas fa-crown"></i> Recursos do Plano <?php echo htmlspecialchars($assinatura['plano_nome'] ?? 'Básico'); ?></h3>
    </div>
    <div class="info-content" style="padding: 20px;">
        <div class="recurso-limit">
            <span><i class="fas fa-users"></i> Limite de Funcionários</span>
            <span><strong><?php echo $assinatura['recurso_funcionarios'] == 0 ? 'Ilimitado' : $assinatura['recurso_funcionarios']; ?></strong></span>
        </div>
        <div class="recurso-limit">
            <span><i class="fas fa-clock"></i> Controle de Horas Extras</span>
            <span><strong><?php echo $assinatura['recurso_horas_extras'] ? '✅ Sim' : '❌ Não'; ?></strong></span>
        </div>
        <div class="recurso-limit">
            <span><i class="fas fa-chart-line"></i> Relatórios Avançados</span>
            <span><strong><?php echo $assinatura['recurso_relatorios_avancados'] ? '✅ Sim' : '❌ Não'; ?></strong></span>
        </div>
        <div class="recurso-limit">
            <span><i class="fas fa-users-cog"></i> Múltiplos Gestores</span>
            <span><strong><?php echo $assinatura['recurso_multi_gestores'] ? '✅ Sim' : '❌ Não'; ?></strong></span>
        </div>
    </div>
</div>

<!-- Gráficos -->
<div class="charts-row">
    <div class="chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-line"></i> Registros por Dia</h3>
        </div>
        <div class="chart-body">
            <canvas id="pontosDiaChart" width="400" height="300"></canvas>
        </div>
    </div>
    
    <div class="chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-pie"></i> Distribuição por Tipo</h3>
        </div>
        <div class="chart-body">
            <canvas id="pontosTipoChart" width="400" height="300"></canvas>
        </div>
    </div>
</div>

<!-- Top Funcionários -->
<div class="table-card">
    <div class="table-header">
        <h3><i class="fas fa-trophy"></i> Funcionários com Mais Registros (Mês)</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Matrícula</th>
                    <th>Total de Registros</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_funcionarios as $func): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($func['nome']); ?></strong></td>
                    <td><?php echo htmlspecialchars($func['matricula']); ?></td>
                    <td><span class="badge badge-success"><?php echo $func['total_pontos']; ?> registros</span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($top_funcionarios)): ?>
                <tr><td colspan="3" style="text-align: center;">Nenhum registro encontrado</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Últimas Solicitações -->
<div class="table-card">
    <div class="table-header">
        <h3><i class="fas fa-clipboard-list"></i> Últimas Solicitações</h3>
        <a href="../solicitacoes/admin.php" class="btn btn-sm btn-secondary">Ver todas</a>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Funcionário</th>
                    <th>Tipo</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ultimas_solicitacoes as $sol): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($sol['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($sol['funcionario_nome']); ?></td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $sol['tipo'])); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $sol['status']; ?>">
                            <?php echo ucfirst($sol['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($ultimas_solicitacoes)): ?>
                <tr><td colspan="4" style="text-align: center;">Nenhuma solicitação encontrada</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Gráfico de pontos por dia
const ctxDia = document.getElementById('pontosDiaChart')?.getContext('2d');
if (ctxDia) {
    new Chart(ctxDia, {
        type: 'line',
        data: {
            labels: [<?php 
                $labels = [];
                $values = [];
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
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
}

// Gráfico de pontos por tipo
const ctxTipo = document.getElementById('pontosTipoChart')?.getContext('2d');
if (ctxTipo) {
    new Chart(ctxTipo, {
        type: 'doughnut',
        data: {
            labels: ['Entrada', 'Saída Almoço', 'Volta Almoço', 'Saída'],
            datasets: [{
                data: [
                    <?php 
                    $entrada = 0; $saidaAlmoco = 0; $voltaAlmoco = 0; $saida = 0;
                    foreach ($pontos_por_tipo as $p) {
                        if ($p['tipo'] == 'entrada') $entrada = $p['total'];
                        elseif ($p['tipo'] == 'saida_almoco') $saidaAlmoco = $p['total'];
                        elseif ($p['tipo'] == 'volta_almoco') $voltaAlmoco = $p['total'];
                        elseif ($p['tipo'] == 'saida') $saida = $p['total'];
                    }
                    echo "$entrada, $saidaAlmoco, $voltaAlmoco, $saida";
                    ?>
                ],
                backgroundColor: ['#667eea', '#48bb78', '#ed8936', '#e53e3e'],
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>