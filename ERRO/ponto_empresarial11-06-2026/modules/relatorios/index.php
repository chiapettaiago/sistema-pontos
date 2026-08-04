<?php
// modules/relatorios/index.php - Dashboard de Relatórios (COMPLETO)
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa', 'gestor'])) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Relatórios';
$activePage = 'relatorios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;

// Estatísticas rápidas
$stmt = $db->prepare("SELECT COUNT(*) as total FROM funcionarios WHERE empresa_id = :empresa_id AND status = 'ativo'");
$stmt->execute([':empresa_id' => $empresa_id]);
$total_funcionarios = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM pontos WHERE empresa_id = :empresa_id AND DATE(data_hora) = CURDATE()");
$stmt->execute([':empresa_id' => $empresa_id]);
$total_pontos_hoje = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM solicitacoes WHERE empresa_id = :empresa_id AND status = 'pendente'");
$stmt->execute([':empresa_id' => $empresa_id]);
$solicitacoes_pendentes = $stmt->fetch()['total'];
?>

<style>
.relatorios-container {
    max-width: 1200px;
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

/* Cards de Estatísticas */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.stat-card {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 20px;
    border: 1px solid var(--border-color);
    transition: all 0.3s;
    text-align: center;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.stat-card .stat-icon {
    font-size: 32px;
    margin-bottom: 12px;
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

/* Grid de Relatórios */
.relatorios-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 24px;
}

.relatorio-card {
    background: var(--bg-primary);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: all 0.3s;
}

.relatorio-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.relatorio-header {
    padding: 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.relatorio-header i {
    font-size: 40px;
    margin-bottom: 12px;
    display: block;
}

.relatorio-header h3 {
    font-size: 18px;
    margin: 0;
}

.relatorio-body {
    padding: 20px;
}

.relatorio-descricao {
    color: var(--text-secondary);
    font-size: 13px;
    margin-bottom: 20px;
    line-height: 1.5;
    min-height: 60px;
}

.relatorio-acoes {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-relatorio {
    flex: 1;
    padding: 10px;
    border-radius: 10px;
    text-align: center;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-visualizar {
    background: #667eea;
    color: white;
}

.btn-visualizar:hover {
    background: #5a67d8;
}

.btn-excel {
    background: #10b981;
    color: white;
}

.btn-excel:hover {
    background: #059669;
}

.btn-pdf {
    background: #ef4444;
    color: white;
}

.btn-pdf:hover {
    background: #dc2626;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .relatorios-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="relatorios-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-chart-bar"></i> Central de Relatórios</h2>
            <p>Gerencie e exporte relatórios do sistema</p>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users" style="color: #667eea;"></i></div>
            <div class="stat-number"><?php echo $total_funcionarios; ?></div>
            <div class="stat-label">Funcionários Ativos</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-fingerprint" style="color: #10b981;"></i></div>
            <div class="stat-number"><?php echo $total_pontos_hoje; ?></div>
            <div class="stat-label">Pontos Hoje</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clipboard-list" style="color: #f59e0b;"></i></div>
            <div class="stat-number"><?php echo $solicitacoes_pendentes; ?></div>
            <div class="stat-label">Solicitações Pendentes</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-alt" style="color: #8b5cf6;"></i></div>
            <div class="stat-number"><?php echo date('m/Y'); ?></div>
            <div class="stat-label">Mês Atual</div>
        </div>
    </div>

    <!-- Relatórios -->
    <div class="relatorios-grid">
        <!-- Relatório 1: Extrato Individual -->
        <div class="relatorio-card">
            <div class="relatorio-header">
                <i class="fas fa-user-clock"></i>
                <h3>Extrato Individual</h3>
            </div>
            <div class="relatorio-body">
                <div class="relatorio-descricao">
                    Relatório detalhado por funcionário com entradas, saídas, horas trabalhadas, 
                    saldo, faltas e atrasos. Suporta períodos diário, semanal, mensal e personalizado.
                </div>
                <div class="relatorio-acoes">
                    <a href="extrato_funcionario.php" class="btn-relatorio btn-visualizar">
                        <i class="fas fa-eye"></i> Visualizar
                    </a>
                    <a href="exportar_excel.php?tipo=extrato_funcionario" class="btn-relatorio btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="exportar_pdf.php?tipo=extrato_funcionario" class="btn-relatorio btn-pdf" target="_blank">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- Relatório 2: Extrato Coletivo -->
        <div class="relatorio-card">
            <div class="relatorio-header">
                <i class="fas fa-users"></i>
                <h3>Extrato Coletivo</h3>
            </div>
            <div class="relatorio-body">
                <div class="relatorio-descricao">
                    Relatório resumido de todos os funcionários com dias trabalhados, 
                    faltas, atrasos, presença percentual e situação geral.
                </div>
                <div class="relatorio-acoes">
                    <a href="extrato_coletivo.php" class="btn-relatorio btn-visualizar">
                        <i class="fas fa-eye"></i> Visualizar
                    </a>
                    <a href="exportar_excel.php?tipo=extrato_coletivo" class="btn-relatorio btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="exportar_pdf.php?tipo=extrato_coletivo" class="btn-relatorio btn-pdf" target="_blank">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- Relatório 3: Pontos por Funcionário -->
        <div class="relatorio-card">
            <div class="relatorio-header">
                <i class="fas fa-fingerprint"></i>
                <h3>Pontos por Funcionário</h3>
            </div>
            <div class="relatorio-body">
                <div class="relatorio-descricao">
                    Listagem detalhada de todos os registros de ponto de um funcionário 
                    em um período específico.
                </div>
                <div class="relatorio-acoes">
                    <a href="pontos_funcionario.php" class="btn-relatorio btn-visualizar">
                        <i class="fas fa-eye"></i> Visualizar
                    </a>
                    <a href="exportar_excel.php?tipo=pontos_funcionario" class="btn-relatorio btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="exportar_pdf.php?tipo=pontos_funcionario" class="btn-relatorio btn-pdf" target="_blank">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- Relatório 4: Atrasos e Faltas -->
        <div class="relatorio-card">
            <div class="relatorio-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Atrasos e Faltas</h3>
            </div>
            <div class="relatorio-body">
                <div class="relatorio-descricao">
                    Relatório de funcionários com atrasos e faltas, incluindo 
                    análise detalhada e resumo por funcionário.
                </div>
                <div class="relatorio-acoes">
                    <a href="atrasos_faltas.php" class="btn-relatorio btn-visualizar">
                        <i class="fas fa-eye"></i> Visualizar
                    </a>
                    <a href="exportar_excel.php?tipo=atrasos_faltas" class="btn-relatorio btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="exportar_pdf.php?tipo=atrasos_faltas" class="btn-relatorio btn-pdf" target="_blank">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- Relatório 5: Horas Trabalhadas -->
        <div class="relatorio-card">
            <div class="relatorio-header">
                <i class="fas fa-chart-line"></i>
                <h3>Horas Trabalhadas</h3>
            </div>
            <div class="relatorio-body">
                <div class="relatorio-descricao">
                    Relatório de horas trabalhadas por funcionário, incluindo 
                    horas normais e horas extras.
                </div>
                <div class="relatorio-acoes">
                    <a href="horas_trabalhadas.php" class="btn-relatorio btn-visualizar">
                        <i class="fas fa-eye"></i> Visualizar
                    </a>
                    <a href="exportar_excel.php?tipo=horas_trabalhadas" class="btn-relatorio btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="exportar_pdf.php?tipo=horas_trabalhadas" class="btn-relatorio btn-pdf" target="_blank">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- Relatório 6: Extrato Geral da Empresa -->
        <div class="relatorio-card">
            <div class="relatorio-header">
                <i class="fas fa-building"></i>
                <h3>Extrato Geral</h3>
            </div>
            <div class="relatorio-body">
                <div class="relatorio-descricao">
                    Visão geral da empresa com estatísticas de funcionários, 
                    dias trabalhados, atrasos e situação geral.
                </div>
                <div class="relatorio-acoes">
                    <a href="extrato_geral.php" class="btn-relatorio btn-visualizar">
                        <i class="fas fa-eye"></i> Visualizar
                    </a>
                    <a href="exportar_excel.php?tipo=extrato_geral" class="btn-relatorio btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="exportar_pdf.php?tipo=extrato_geral" class="btn-relatorio btn-pdf" target="_blank">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>