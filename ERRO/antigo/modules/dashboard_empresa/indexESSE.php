<?php
// modules/dashboard_empresa/index.php - Dashboard do Admin Empresa (COMPLETO + MELHORADO)
$pageTitle = 'Dashboard Empresa';
$activePage = 'dashboard_empresa';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

// Verificar se é admin_empresa, gestor ou superior
if ($_SESSION['usuario_tipo'] !== 'admin_empresa' && $_SESSION['usuario_tipo'] !== 'super_admin' && $_SESSION['usuario_tipo'] !== 'gestor') {
    header('Location: /ponto_empresarial/index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$empresa_id = getCurrentEmpresaId() ?: 1;

// Atualizar última atividade do usuário para controle de online
$stmt = $db->prepare("INSERT INTO sessoes_usuarios (usuario_id, ultima_atividade, ip_address, user_agent) 
                      VALUES (:usuario_id, NOW(), :ip, :agent)
                      ON DUPLICATE KEY UPDATE ultima_atividade = NOW()");
$stmt->execute([
    ':usuario_id' => $_SESSION['usuario_id'],
    ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
]);

// ============================================
// DADOS EXISTENTES
// ============================================

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

// ============================================
// NOVAS FUNCIONALIDADES
// ============================================

// Funcionários online (últimos 5 minutos)
$stmt = $db->prepare("SELECT COUNT(DISTINCT su.usuario_id) as total 
                      FROM sessoes_usuarios su
                      JOIN funcionarios f ON f.usuario_sistema_id = su.usuario_id
                      WHERE f.empresa_id = :empresa_id 
                      AND su.ultima_atividade > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
$stmt->execute([':empresa_id' => $empresa_id]);
$online_agora = $stmt->fetch()['total'];

// Atrasos hoje (entrada após 08:15)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM pontos 
                      WHERE empresa_id = :empresa_id 
                      AND DATE(data_hora) = CURDATE() 
                      AND tipo = 'entrada' 
                      AND TIME(data_hora) > '08:15:00'");
$stmt->execute([':empresa_id' => $empresa_id]);
$atrasos_hoje = $stmt->fetch()['total'];

// Faltas hoje (funcionários sem entrada)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM funcionarios f
                      WHERE f.empresa_id = :empresa_id 
                      AND f.status = 'ativo'
                      AND NOT EXISTS (
                          SELECT 1 FROM pontos p 
                          WHERE p.funcionario_id = f.id 
                          AND DATE(p.data_hora) = CURDATE() 
                          AND p.tipo = 'entrada'
                      )");
$stmt->execute([':empresa_id' => $empresa_id]);
$faltas_hoje = $stmt->fetch()['total'];

// Ranking de atrasos no mês
$stmt = $db->prepare("SELECT 
                        f.id, f.nome, f.matricula, f.foto,
                        COUNT(CASE WHEN p.tipo = 'entrada' AND TIME(p.data_hora) > '08:15:00' THEN 1 END) as total_atrasos
                      FROM funcionarios f
                      LEFT JOIN pontos p ON f.id = p.funcionario_id 
                        AND MONTH(p.data_hora) = MONTH(CURDATE())
                        AND YEAR(p.data_hora) = YEAR(CURDATE())
                      WHERE f.empresa_id = :empresa_id AND f.status = 'ativo'
                      GROUP BY f.id
                      HAVING total_atrasos > 0
                      ORDER BY total_atrasos DESC
                      LIMIT 5");
$stmt->execute([':empresa_id' => $empresa_id]);
$ranking_atrasos = $stmt->fetchAll();

// Últimos pontos registrados
$stmt = $db->prepare("SELECT p.*, f.nome as funcionario_nome, f.matricula
                      FROM pontos p
                      JOIN funcionarios f ON p.funcionario_id = f.id
                      WHERE p.empresa_id = :empresa_id
                      ORDER BY p.data_hora DESC
                      LIMIT 10");
$stmt->execute([':empresa_id' => $empresa_id]);
$ultimos_pontos = $stmt->fetchAll();

// Próximos feriados
$stmt = $db->prepare("SELECT * FROM config_feriados 
                      WHERE empresa_id = :empresa_id 
                      AND data >= CURDATE() 
                      ORDER BY data ASC 
                      LIMIT 5");
$stmt->execute([':empresa_id' => $empresa_id]);
$proximos_feriados = $stmt->fetchAll();

// Percentuais para gráficos
$total_funcionarios = $empresa['total_funcionarios'] ?? 0;
$percentual_presenca = $total_funcionarios > 0 ? round((($total_funcionarios - $faltas_hoje) / $total_funcionarios) * 100, 2) : 0;
$percentual_atrasos = $total_funcionarios > 0 ? round(($atrasos_hoje / $total_funcionarios) * 100, 2) : 0;

// Horas trabalhadas hoje
$stmt = $db->prepare("SELECT SUM(TIMESTAMPDIFF(MINUTE, entrada, saida)) as total_minutos
                      FROM (
                          SELECT 
                              DATE(data_hora) as dia,
                              MIN(CASE WHEN tipo = 'entrada' THEN data_hora END) as entrada,
                              MAX(CASE WHEN tipo = 'saida' THEN data_hora END) as saida
                          FROM pontos
                          WHERE empresa_id = :empresa_id AND DATE(data_hora) = CURDATE()
                          GROUP BY DATE(data_hora)
                      ) as resumo");
$stmt->execute([':empresa_id' => $empresa_id]);
$total_minutos = $stmt->fetch()['total_minutos'] ?? 0;
$horas_hoje = floor($total_minutos / 60);
$minutos_hoje = $total_minutos % 60;
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

/* Stats Grid Principal */
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
    display: flex;
    align-items: center;
    gap: 16px;
    border: 1px solid var(--border-color);
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-icon i {
    font-size: 24px;
    color: white;
}

.stat-info h3 {
    font-size: 24px;
    font-weight: 700;
    margin: 0;
    color: var(--text-primary);
}

.stat-info p {
    margin: 4px 0 0;
    color: var(--text-secondary);
    font-size: 12px;
}

/* Stats Grid Secundário */
.stats-grid-secundario {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.badge-success {
    background: #d1fae5;
    color: #059669;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
}

/* Gráficos */
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
    background: var(--bg-secondary);
}

.chart-header h3 {
    margin: 0;
    font-size: 16px;
}

.chart-body {
    padding: 20px;
    height: 300px;
}

/* Ranking e Listas */
.ranking-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
}

.ranking-position {
    width: 32px;
    font-weight: 700;
    font-size: 16px;
    text-align: center;
}

.ranking-position.top-1 { color: #fbbf24; }
.ranking-position.top-2 { color: #9ca3af; }
.ranking-position.top-3 { color: #b45309; }

.ranking-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
    overflow: hidden;
}

.ranking-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.ranking-info {
    flex: 1;
}

.ranking-name {
    font-weight: 500;
    font-size: 14px;
}

.ranking-meta {
    font-size: 11px;
    color: var(--text-secondary);
}

.ranking-value {
    font-weight: 600;
    color: #f59e0b;
}

.ponto-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
}

.ponto-funcionario {
    font-weight: 500;
    font-size: 14px;
}

.ponto-horario {
    font-size: 12px;
    color: #667eea;
    font-weight: 500;
}

.feriado-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
}

.feriado-data {
    font-weight: 600;
    font-size: 14px;
}

.feriado-nome {
    font-size: 12px;
    color: var(--text-secondary);
}

/* Badges */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

.badge-nacional { background: #d1fae5; color: #059669; }
.badge-estadual { background: #bfdbfe; color: #1e40af; }
.badge-municipal { background: #fed7aa; color: #c2410c; }
.badge-pontofacultativo { background: #fef3c7; color: #d97706; }

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.status-pendente { background: #fef3c7; color: #d97706; }
.status-aprovado { background: #d1fae5; color: #059669; }
.status-rejeitado { background: #fee2e2; color: #dc2626; }

.progress-bar {
    background: var(--bg-secondary);
    border-radius: 10px;
    height: 6px;
    overflow: hidden;
    margin: 8px 0;
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

.table-card {
    background: var(--bg-primary);
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid var(--border-color);
    margin-bottom: 24px;
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

.online-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    margin-right: 6px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
    100% { opacity: 1; transform: scale(1); }
}

@media (max-width: 1024px) {
    .charts-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .empresa-header {
        padding: 20px;
    }
    
    .empresa-nome {
        font-size: 22px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .stats-grid-secundario {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid-secundario {
        grid-template-columns: 1fr;
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

<!-- Cards de Estatísticas Principais -->
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

<!-- Cards de Estatísticas Adicionais (NOVOS) -->
<div class="stats-grid-secundario">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
            <i class="fas fa-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $online_agora; ?> <span style="font-size: 12px; font-weight: normal;">online</span></h3>
            <p><span class="online-indicator"></span> Funcionários Agora</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $atrasos_hoje; ?> / <?php echo $percentual_atrasos; ?>%</h3>
            <p>Atrasos Hoje</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <i class="fas fa-calendar-times"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $faltas_hoje; ?></h3>
            <p>Faltas Hoje</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $horas_hoje; ?>h <?php echo $minutos_hoje; ?>min</h3>
            <p>Horas Trabalhadas Hoje</p>
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

<!-- Gráficos e Ranking (NOVO) -->
<div class="charts-row">
    <!-- Gráfico de Presença (NOVO) -->
    <div class="chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-pie"></i> Presença Hoje</h3>
        </div>
        <div class="chart-body">
            <canvas id="presencaChart"></canvas>
        </div>
    </div>
    
    <!-- Ranking de Atrasos (NOVO) -->
    <div class="chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-trophy"></i> Ranking de Atrasos (Mês)</h3>
        </div>
        <div class="chart-body" style="height: auto; max-height: 300px; overflow-y: auto;">
            <?php if (empty($ranking_atrasos)): ?>
                <div style="text-align: center; padding: 30px; color: var(--text-secondary);">
                    <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                    Nenhum atraso registrado neste mês!
                </div>
            <?php else: ?>
                <?php foreach ($ranking_atrasos as $index => $func): ?>
                    <div class="ranking-item">
                        <div class="ranking-position <?php echo $index == 0 ? 'top-1' : ($index == 1 ? 'top-2' : ($index == 2 ? 'top-3' : '')); ?>">
                            <?php echo $index + 1; ?>º
                        </div>
                        <div class="ranking-avatar">
                            <?php if (!empty($func['foto']) && file_exists('../../' . $func['foto'])): ?>
                                <img src="../../<?php echo $func['foto']; ?>" alt="<?php echo htmlspecialchars($func['nome']); ?>">
                            <?php else: ?>
                                <?php echo strtoupper(substr($func['nome'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="ranking-info">
                            <div class="ranking-name"><?php echo htmlspecialchars($func['nome']); ?></div>
                            <div class="ranking-meta">Matrícula: <?php echo htmlspecialchars($func['matricula']); ?></div>
                        </div>
                        <div class="ranking-value"><?php echo $func['total_atrasos']; ?> atraso(s)</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Segunda linha de gráficos -->
<div class="charts-row">
    <!-- Gráfico de Pontos por Dia (seu original) -->
    <div class="chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-line"></i> Registros por Dia</h3>
        </div>
        <div class="chart-body">
            <canvas id="pontosDiaChart" width="400" height="300"></canvas>
        </div>
    </div>
    
    <!-- Gráfico de Distribuição por Tipo (seu original) -->
    <div class="chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-chart-pie"></i> Distribuição por Tipo</h3>
        </div>
        <div class="chart-body">
            <canvas id="pontosTipoChart" width="400" height="300"></canvas>
        </div>
    </div>
</div>

<!-- Últimos Pontos Registrados (NOVO) -->
<div class="table-card">
    <div class="table-header">
        <h3><i class="fas fa-history"></i> Últimos Pontos Registrados</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Matrícula</th>
                    <th>Tipo</th>
                    <th>Data/Hora</th>
                    <th>Origem</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ultimos_pontos)): ?>
                    <tr class="fade-in">
                        <td colspan="5" style="text-align: center; padding: 30px;">
                            Nenhum ponto registrado hoje
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ultimos_pontos as $ponto): ?>
                        <tr class="fade-in">
                            <td><strong><?php echo htmlspecialchars($ponto['funcionario_nome']); ?></strong></td>
                            <td><?php echo htmlspecialchars($ponto['matricula']); ?></td>
                            <td>
                                <?php 
                                $tipos = [
                                    'entrada' => '✅ Entrada',
                                    'saida_almoco' => '🍽️ Saída Almoço',
                                    'volta_almoco' => '🔄 Volta Almoço',
                                    'saida' => '🏁 Saída'
                                ];
                                echo $tipos[$ponto['tipo']] ?? $ponto['tipo'];
                                ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($ponto['data_hora'])); ?></td>
                            <td><?php echo $ponto['origem'] == 'web' ? '🌐 Web' : '📱 App'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Top Funcionários (seu original) -->
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

<!-- Próximos Feriados (NOVO) e Últimas Solicitações -->
<div class="charts-row">
    <!-- Próximos Feriados (NOVO) -->
    <div class="chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-calendar-alt"></i> Próximos Feriados</h3>
        </div>
        <div class="chart-body" style="height: auto;">
            <?php if (empty($proximos_feriados)): ?>
                <div style="text-align: center; padding: 30px; color: var(--text-secondary);">
                    <i class="fas fa-calendar-check" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                    Nenhum feriado próximo
                </div>
            <?php else: ?>
                <?php foreach ($proximos_feriados as $feriado): ?>
                    <div class="feriado-item">
                        <div>
                            <div class="feriado-data"><?php echo date('d/m/Y', strtotime($feriado['data'])); ?></div>
                            <div class="feriado-nome"><?php echo htmlspecialchars($feriado['nome']); ?></div>
                        </div>
                        <div>
                            <span class="badge badge-<?php echo $feriado['tipo']; ?>">
                                <?php 
                                $tipos_feriado = [
                                    'nacional' => 'Nacional',
                                    'estadual' => 'Estadual',
                                    'municipal' => 'Municipal',
                                    'pontofacultativo' => 'Ponto Facultativo'
                                ];
                                echo $tipos_feriado[$feriado['tipo']] ?? $feriado['tipo'];
                                ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Últimas Solicitações (seu original) -->
    <div class="chart-card">
        <div class="chart-header">
            <h3><i class="fas fa-clipboard-list"></i> Últimas Solicitações</h3>
            <a href="../solicitacoes/admin.php" class="btn btn-sm btn-secondary">Ver todas</a>
        </div>
        <div class="chart-body" style="padding: 0; height: auto;">
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
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Gráfico de Pontos por Dia (seu original)
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
        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
    });
}

// Gráfico de Pontos por Tipo (seu original)
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
        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
    });
}

// Gráfico de Presença (NOVO)
const ctxPresenca = document.getElementById('presencaChart')?.getContext('2d');
if (ctxPresenca) {
    new Chart(ctxPresenca, {
        type: 'doughnut',
        data: {
            labels: ['Presentes', 'Faltas'],
            datasets: [{
                data: [<?php echo $total_funcionarios - $faltas_hoje; ?>, <?php echo $faltas_hoje; ?>],
                backgroundColor: ['#10b981', '#ef4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>