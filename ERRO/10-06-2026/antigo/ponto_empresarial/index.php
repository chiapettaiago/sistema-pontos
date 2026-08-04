<?php
// index.php - Dashboard Principal (COM PERMISSÕES E PLANOS)
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require_once 'includes/header.php';
require_once 'config/database.php';
require_once 'config/multi_empresa.php';

$database = new Database();
$db = $database->getConnection();

// ============================================
// OBTER INFORMAÇÕES DO USUÁRIO
// ============================================
$empresa_id = getCurrentEmpresaId() ?: 1;
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$usuario_filial_id = $_SESSION['usuario_filial_id'] ?? null;

// Buscar plano da empresa para verificar recursos disponíveis
$query = "SELECT p.* FROM assinaturas a
          JOIN planos p ON a.plano_id = p.id
          WHERE a.empresa_id = :empresa_id AND a.status = 'ativa'
          LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$plano = $stmt->fetch();

// Se não tiver plano, criar um padrão
if (!$plano) {
    $plano = [
        'nome' => 'Básico',
        'recurso_funcionarios' => 10,
        'recurso_horas_extras' => 0,
        'recurso_relatorios_avancados' => 0,
        'recurso_multi_gestores' => 0,
        'recurso_qrcode' => 0,
        'recurso_exportacao_excel' => 0
    ];
}

// ============================================
// ESTATÍSTICAS - FILTRADAS POR EMPRESA E PERMISSÃO
// ============================================

// Total de funcionários ativos (apenas para admin/gestor)
$total_funcionarios = 0;
if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa' || $usuario_tipo === 'gestor') {
    $query = "SELECT COUNT(*) as total FROM funcionarios WHERE status = 'ativo' AND empresa_id = :empresa_id";
    if ($usuario_tipo === 'gestor' && $usuario_filial_id) {
        $query .= " AND filial_id = :filial_id";
    }
    $stmt = $db->prepare($query);
    $params = [':empresa_id' => $empresa_id];
    if ($usuario_tipo === 'gestor' && $usuario_filial_id) {
        $params[':filial_id'] = $usuario_filial_id;
    }
    $stmt->execute($params);
    $total_funcionarios = $stmt->fetch()['total'];
}

// Total de filiais (apenas para admin)
$total_filiais = 0;
if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa') {
    $query = "SELECT COUNT(*) as total FROM filiais WHERE ativo = 1 AND empresa_id = :empresa_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':empresa_id' => $empresa_id]);
    $total_filiais = $stmt->fetch()['total'];
}

// Pontos do usuário logado (todos podem ver seus próprios pontos)
$query = "SELECT COUNT(*) as total FROM pontos 
          WHERE funcionario_id = :funcionario_id 
          AND MONTH(data_hora) = MONTH(CURDATE()) 
          AND YEAR(data_hora) = YEAR(CURDATE())";
$stmt = $db->prepare($query);
$stmt->execute([':funcionario_id' => $usuario_id]);
$meus_pontos_mes = $stmt->fetch()['total'];

// Pontos hoje do usuário
$query = "SELECT COUNT(*) as total FROM pontos 
          WHERE funcionario_id = :funcionario_id AND DATE(data_hora) = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute([':funcionario_id' => $usuario_id]);
$meus_pontos_hoje = $stmt->fetch()['total'];

// Pontos da empresa hoje (apenas para admin/gestor)
$pontos_empresa_hoje = 0;
if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa' || $usuario_tipo === 'gestor') {
    $query = "SELECT COUNT(*) as total FROM pontos 
              WHERE DATE(data_hora) = CURDATE() AND empresa_id = :empresa_id";
    if ($usuario_tipo === 'gestor' && $usuario_filial_id) {
        $query .= " AND filial_id = :filial_id";
    }
    $stmt = $db->prepare($query);
    $params = [':empresa_id' => $empresa_id];
    if ($usuario_tipo === 'gestor' && $usuario_filial_id) {
        $params[':filial_id'] = $usuario_filial_id;
    }
    $stmt->execute($params);
    $pontos_empresa_hoje = $stmt->fetch()['total'];
}

// ============================================
// DADOS DO USUÁRIO (funcionário logado)
// ============================================
$query = "SELECT f.*, fil.nome_fantasia as filial_nome, c.nome as cargo_nome
          FROM funcionarios f
          LEFT JOIN filiais fil ON f.filial_id = fil.id
          LEFT JOIN cargos c ON f.cargo_id = c.id
          WHERE f.id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $usuario_id]);
$usuario = $stmt->fetch();

// Buscar pontos de hoje do usuário
$query = "SELECT tipo, data_hora, DATE_FORMAT(data_hora, '%H:%i') as hora
          FROM pontos 
          WHERE funcionario_id = :id AND DATE(data_hora) = CURDATE()
          ORDER BY data_hora ASC";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $usuario_id]);
$pontos_hoje_usuario = $stmt->fetchAll();

$pontos_hoje_map = [];
foreach ($pontos_hoje_usuario as $ponto) {
    $pontos_hoje_map[$ponto['tipo']] = $ponto['hora'];
}

// ============================================
// GRÁFICOS - APENAS PARA QUEM TEM PERMISSÃO
// ============================================
$dadosGrafico = [];
$top_funcionarios = [];

if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa' || $usuario_tipo === 'gestor') {
    // Gráfico da semana
    $query = "SELECT DATE(data_hora) as data, COUNT(*) as total 
              FROM pontos 
              WHERE data_hora >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              AND empresa_id = :empresa_id";
    if ($usuario_tipo === 'gestor' && $usuario_filial_id) {
        $query .= " AND filial_id = :filial_id";
    }
    $query .= " GROUP BY DATE(data_hora) ORDER BY data ASC";
    
    $stmt = $db->prepare($query);
    $params = [':empresa_id' => $empresa_id];
    if ($usuario_tipo === 'gestor' && $usuario_filial_id) {
        $params[':filial_id'] = $usuario_filial_id;
    }
    $stmt->execute($params);
    $dadosGrafico = $stmt->fetchAll();
    
    // Top funcionários (apenas se o plano permitir relatórios avançados)
    if ($plano['recurso_relatorios_avancados'] == 1 || $usuario_tipo === 'super_admin') {
        $query = "SELECT f.nome, f.matricula, COUNT(p.id) as total_pontos
                  FROM funcionarios f
                  LEFT JOIN pontos p ON f.id = p.funcionario_id 
                  WHERE MONTH(p.data_hora) = MONTH(CURDATE()) 
                  AND YEAR(p.data_hora) = YEAR(CURDATE())
                  AND f.empresa_id = :empresa_id
                  GROUP BY f.id
                  ORDER BY total_pontos DESC
                  LIMIT 5";
        if ($usuario_tipo === 'gestor' && $usuario_filial_id) {
            $query .= " AND f.filial_id = :filial_id";
        }
        $stmt = $db->prepare($query);
        $params = [':empresa_id' => $empresa_id];
        if ($usuario_tipo === 'gestor' && $usuario_filial_id) {
            $params[':filial_id'] = $usuario_filial_id;
        }
        $stmt->execute($params);
        $top_funcionarios = $stmt->fetchAll();
    }
}

// Solicitações pendentes (apenas para admin/gestor)
$solicitacoes_pendentes = [];
if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa' || $usuario_tipo === 'gestor') {
    $query = "SELECT s.*, f.nome as funcionario_nome
              FROM solicitacoes s
              JOIN funcionarios f ON s.funcionario_id = f.id
              WHERE s.empresa_id = :empresa_id AND s.status = 'pendente'
              ORDER BY s.created_at DESC
              LIMIT 5";
    if ($usuario_tipo === 'gestor' && $usuario_filial_id) {
        $query .= " AND f.filial_id = :filial_id";
    }
    $stmt = $db->prepare($query);
    $params = [':empresa_id' => $empresa_id];
    if ($usuario_tipo === 'gestor' && $usuario_filial_id) {
        $params[':filial_id'] = $usuario_filial_id;
    }
    $stmt->execute($params);
    $solicitacoes_pendentes = $stmt->fetchAll();
}

// Buscar nome da empresa
$query = "SELECT nome FROM empresas WHERE id = :empresa_id";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$empresa_nome = $stmt->fetch()['nome'] ?? 'Minha Empresa';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.dashboard-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    border-radius: 20px;
    padding: 20px 30px;
    margin-bottom: 24px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.dashboard-header h1 {
    font-size: 24px;
    margin: 0;
}

.dashboard-header .empresa-nome {
    font-size: 14px;
    opacity: 0.9;
    margin-top: 4px;
}

.welcome-card {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.welcome-avatar {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 32px;
    font-weight: bold;
}

.welcome-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.welcome-text h2 {
    font-size: 24px;
    margin-bottom: 4px;
}

.welcome-text p {
    color: var(--text-secondary);
}

.ponto-status-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-top: 16px;
}

.ponto-status-item {
    text-align: center;
    padding: 16px;
    background: var(--bg-secondary);
    border-radius: 16px;
}

.ponto-status-item.completed {
    background: #d1fae5;
    color: #059669;
}

.ponto-status-item.pending {
    background: #fee2e2;
    color: #dc2626;
}

.ponto-status-item i {
    font-size: 24px;
    margin-bottom: 8px;
    display: block;
}

.ponto-status-item span {
    font-size: 12px;
    display: block;
}

.ponto-status-item strong {
    font-size: 18px;
    margin-top: 4px;
    display: block;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.stat-card {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    border: 1px solid var(--border-color);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-icon i {
    font-size: 28px;
    color: white;
}

.stat-info h3 {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
}

.stat-info p {
    color: var(--text-secondary);
    font-size: 14px;
}

.charts-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
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

.table-card {
    background: var(--bg-primary);
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid var(--border-color);
    margin-bottom: 24px;
}

.table-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.table-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.table-responsive {
    overflow-x: auto;
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
    font-weight: 600;
    font-size: 13px;
    color: var(--text-secondary);
    background: var(--bg-secondary);
}

.badge-success {
    background: #d1fae5;
    color: #059669;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
}

.aviso-plano {
    background: #fef3c7;
    color: #d97706;
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

@media (max-width: 768px) {
    .charts-row {
        grid-template-columns: 1fr;
    }
    
    .ponto-status-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .welcome-card {
        text-align: center;
        justify-content: center;
    }
}
</style>

<div class="dashboard-header">
    <div>
        <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
        <div class="empresa-nome">
            <i class="fas fa-building"></i> <?php echo htmlspecialchars($empresa_nome); ?>
        </div>
    </div>
    <div class="dashboard-date">
        <i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y'); ?>
    </div>
</div>

<!-- Cards de Estatísticas - baseados na permissão -->
<div class="stats-grid">
    <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa' || $usuario_tipo === 'gestor'): ?>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_funcionarios; ?></h3>
            <p>Funcionários</p>
            <small><?php echo $plano['recurso_funcionarios'] == 0 ? 'Ilimitado' : 'Limite: ' . $plano['recurso_funcionarios']; ?></small>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fas fa-store"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_filiais; ?></h3>
            <p>Filiais</p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class="fas fa-fingerprint"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $meus_pontos_mes; ?></h3>
            <p>Meus Registros no Mês</p>
        </div>
    </div>
    
    <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa' || $usuario_tipo === 'gestor'): ?>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <i class="fas fa-fingerprint"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $pontos_empresa_hoje; ?></h3>
            <p>Registros Hoje (Empresa)</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Status do Ponto do Funcionário (visível para todos) -->
<div class="table-card">
    <div class="table-header">
        <h3><i class="fas fa-clock"></i> Meu Ponto de Hoje</h3>
        <a href="modules/ponto/registrar.php" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">
            <i class="fas fa-fingerprint"></i> Registrar Ponto
        </a>
    </div>
    <div class="ponto-status-grid" style="padding: 20px;">
        <div class="ponto-status-item <?php echo isset($pontos_hoje_map['entrada']) ? 'completed' : 'pending'; ?>">
            <i class="fas fa-sign-in-alt"></i>
            <span>Entrada</span>
            <strong><?php echo $pontos_hoje_map['entrada'] ?? '--:--'; ?></strong>
        </div>
        <div class="ponto-status-item <?php echo isset($pontos_hoje_map['saida_almoco']) ? 'completed' : 'pending'; ?>">
            <i class="fas fa-utensils"></i>
            <span>Saída Almoço</span>
            <strong><?php echo $pontos_hoje_map['saida_almoco'] ?? '--:--'; ?></strong>
        </div>
        <div class="ponto-status-item <?php echo isset($pontos_hoje_map['volta_almoco']) ? 'completed' : 'pending'; ?>">
            <i class="fas fa-undo-alt"></i>
            <span>Volta Almoço</span>
            <strong><?php echo $pontos_hoje_map['volta_almoco'] ?? '--:--'; ?></strong>
        </div>
        <div class="ponto-status-item <?php echo isset($pontos_hoje_map['saida']) ? 'completed' : 'pending'; ?>">
            <i class="fas fa-sign-out-alt"></i>
            <span>Saída</span>
            <strong><?php echo $pontos_hoje_map['saida'] ?? '--:--'; ?></strong>
        </div>
    </div>
</div>

<!-- Gráficos - apenas se o plano permitir ou se for admin -->
<?php if (!empty($dadosGrafico) && ($plano['recurso_relatorios_avancados'] == 1 || $usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa' || $usuario_tipo === 'gestor')): ?>
<div class="charts-row">
    <div class="chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-line"></i> Registros por Dia (Últimos 7 dias)</h3>
        </div>
        <div class="chart-body">
            <canvas id="pontosDiaChart" width="400" height="300"></canvas>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Top Funcionários - apenas se o plano permitir relatórios avançados -->
<?php if (!empty($top_funcionarios) && ($plano['recurso_relatorios_avancados'] == 1 || $usuario_tipo === 'super_admin')): ?>
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
                    <td class="text-center"><span class="badge-success"><?php echo $func['total_pontos']; ?> registros</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Solicitações Pendentes - apenas para admin/gestor -->
<?php if (!empty($solicitacoes_pendentes) && ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa' || $usuario_tipo === 'gestor')): ?>
<div class="table-card">
    <div class="table-header">
        <h3><i class="fas fa-clipboard-list"></i> Solicitações Pendentes</h3>
        <a href="modules/solicitacoes/admin.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">Ver todas</a>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Funcionário</th>
                    <th>Tipo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitacoes_pendentes as $sol): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($sol['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($sol['funcionario_nome']); ?></td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $sol['tipo'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
// Gráfico de pontos por dia
<?php if (!empty($dadosGrafico) && ($plano['recurso_relatorios_avancados'] == 1 || $usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa' || $usuario_tipo === 'gestor')): ?>
const ctxDia = document.getElementById('pontosDiaChart')?.getContext('2d');
if (ctxDia) {
    new Chart(ctxDia, {
        type: 'line',
        data: {
            labels: [<?php 
                $labels = [];
                $values = [];
                foreach ($dadosGrafico as $d) {
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
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>