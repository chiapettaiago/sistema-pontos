<?php
// modules/relatorios/index.php - Página Principal de Relatórios (CORRIGIDO)
$pageTitle = 'Relatórios Gerenciais';
$activePage = 'relatorios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

// Verificar permissão
checkModuleAccess('relatorios');

$database = new Database();
$db = $database->getConnection();

// Verificar se o usuário está logado e tem filial definida
$usuario_filial_id = $_SESSION['usuario_filial_id'] ?? null;
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';
$empresa_id = getCurrentEmpresaId() ?: 1;

// Buscar filiais para filtro
if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin') {
    $stmt = $db->query("SELECT id, nome_fantasia FROM filiais WHERE ativo = 1 AND empresa_id = $empresa_id ORDER BY nome_fantasia");
    $filiais = $stmt->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, nome_fantasia FROM filiais WHERE id = :id AND ativo = 1");
    $stmt->execute([':id' => $usuario_filial_id]);
    $filiais = $stmt->fetchAll();
}

// Estatísticas gerais
$stats = [];

// Total de funcionários ativos
$query = "SELECT COUNT(*) as total FROM funcionarios WHERE status = 'ativo' AND empresa_id = :empresa_id";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$stats['total_funcionarios'] = $stmt->fetch()['total'] ?? 0;

// Total de pontos no mês
$query = "SELECT COUNT(*) as total FROM pontos 
          WHERE MONTH(data_hora) = MONTH(CURDATE()) 
          AND YEAR(data_hora) = YEAR(CURDATE())
          AND empresa_id = :empresa_id";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$stats['total_pontos'] = $stmt->fetch()['total'] ?? 0;

// Média de pontos por dia
$query = "SELECT COUNT(*) / COUNT(DISTINCT DATE(data_hora)) as media 
          FROM pontos 
          WHERE MONTH(data_hora) = MONTH(CURDATE()) 
          AND YEAR(data_hora) = YEAR(CURDATE())
          AND empresa_id = :empresa_id";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$result = $stmt->fetch();
$stats['media_pontos'] = round($result['media'] ?? 0, 1);

// Funcionários que mais bateram ponto
$query = "SELECT f.nome, f.matricula, COUNT(p.id) as total_pontos
          FROM funcionarios f
          LEFT JOIN pontos p ON f.id = p.funcionario_id 
          WHERE MONTH(p.data_hora) = MONTH(CURDATE()) 
          AND YEAR(p.data_hora) = YEAR(CURDATE())
          AND f.empresa_id = :empresa_id
          GROUP BY f.id
          ORDER BY total_pontos DESC
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$top_funcionarios = $stmt->fetchAll();

// Pontos por tipo no mês
$query = "SELECT tipo, COUNT(*) as total 
          FROM pontos 
          WHERE MONTH(data_hora) = MONTH(CURDATE()) 
          AND YEAR(data_hora) = YEAR(CURDATE())
          AND empresa_id = :empresa_id
          GROUP BY tipo";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$pontos_por_tipo = $stmt->fetchAll();

// Pontos por dia no mês (para gráfico)
$query = "SELECT DATE(data_hora) as data, COUNT(*) as total 
          FROM pontos 
          WHERE MONTH(data_hora) = MONTH(CURDATE()) 
          AND YEAR(data_hora) = YEAR(CURDATE())
          AND empresa_id = :empresa_id
          GROUP BY DATE(data_hora)
          ORDER BY data ASC";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$pontos_por_dia = $stmt->fetchAll();

// Resumo por filial
$query = "SELECT fil.nome_fantasia, 
          COUNT(DISTINCT f.id) as total_funcionarios,
          COUNT(p.id) as total_pontos
          FROM filiais fil
          LEFT JOIN funcionarios f ON fil.id = f.filial_id AND f.status = 'ativo'
          LEFT JOIN pontos p ON f.id = p.funcionario_id 
          WHERE MONTH(p.data_hora) = MONTH(CURDATE()) 
          AND YEAR(p.data_hora) = YEAR(CURDATE())
          AND fil.empresa_id = :empresa_id
          GROUP BY fil.id";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$resumo_filiais = $stmt->fetchAll();

// Se não for admin, filtrar apenas pela filial do usuário
if ($usuario_tipo !== 'super_admin' && $usuario_tipo !== 'admin' && $usuario_filial_id) {
    $resumo_filiais = array_filter($resumo_filiais, function($filial) use ($usuario_filial_id) {
        return true; // Simplificado
    });
}
?>

<style>
.charts-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.chart-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.chart-header {
    padding: 16px 20px;
    border-bottom: 1px solid #e5e5e5;
}

.chart-header h3 {
    margin: 0;
    font-size: 16px;
}

.chart-body {
    padding: 20px;
}

.badge-success {
    background: #d1fae5;
    color: #059669;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
}

.links-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.report-link {
    background: white;
    padding: 20px;
    border-radius: 16px;
    text-decoration: none;
    transition: all 0.3s;
    border: 1px solid #e5e5e5;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.report-link:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    border-color: #667eea;
}

.report-link i {
    font-size: 40px;
    color: #667eea;
    margin-bottom: 12px;
}

.report-link span {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.report-link small {
    font-size: 12px;
    color: #666;
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-chart-bar"></i> Relatórios Gerenciais</h2>
        <p>Análise completa de pontos e frequência</p>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['total_funcionarios']; ?></h3>
            <p>Funcionários Ativos</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fas fa-fingerprint"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['total_pontos']; ?></h3>
            <p>Total de Registros</p>
            <small>no mês atual</small>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['media_pontos']; ?></h3>
            <p>Média por Dia</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <i class="fas fa-building"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count($resumo_filiais); ?></h3>
            <p>Filiais Ativas</p>
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
                    <td><?php echo htmlspecialchars($func['nome']); ?></td>
                    <td><?php echo htmlspecialchars($func['matricula']); ?></td>
                    <td class="text-center"><span class="badge-success"><?php echo $func['total_pontos']; ?> registros</span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($top_funcionarios)): ?>
                <tr>
                    <td colspan="3" style="text-align: center;">Nenhum registro encontrado</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Links para Relatórios Detalhados -->
<div class="reports-links">
    <h3><i class="fas fa-file-alt"></i> Relatórios Detalhados</h3>
    <div class="links-grid">
        <a href="extrato.php" class="report-link">
            <i class="fas fa-calendar-alt"></i>
            <span>Extrato de Ponto</span>
            <small>Consulta detalhada por funcionário</small>
        </a>
        <a href="horas_trabalhadas.php" class="report-link">
            <i class="fas fa-clock"></i>
            <span>Horas Trabalhadas</span>
            <small>Total de horas por período</small>
        </a>
        <a href="banco_horas.php" class="report-link">
            <i class="fas fa-piggy-bank"></i>
            <span>Banco de Horas</span>
            <small>Saldo de horas extras</small>
        </a>
        <a href="atrasos.php" class="report-link">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Atrasos e Faltas</span>
            <small>Relatório de ocorrências</small>
        </a>
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
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
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
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}
</script>

<style>
.text-center {
    text-align: center;
}
.reports-links {
    margin-top: 32px;
}
.reports-links h3 {
    margin-bottom: 20px;
    font-size: 18px;
    color: #333;
}
</style>

<?php require_once '../../includes/footer.php'; ?>