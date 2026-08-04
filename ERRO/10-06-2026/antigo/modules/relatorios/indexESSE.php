<?php
// modules/relatorios/index.php - Dashboard de Relatórios
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

// Buscar estatísticas rápidas
// Total de funcionários
$stmt = $db->prepare("SELECT COUNT(*) as total FROM funcionarios WHERE empresa_id = :empresa_id AND status = 'ativo'");
$stmt->execute([':empresa_id' => $empresa_id]);
$total_funcionarios = $stmt->fetch()['total'];

// Total de pontos hoje
$stmt = $db->prepare("SELECT COUNT(*) as total FROM pontos WHERE empresa_id = :empresa_id AND DATE(data_hora) = CURDATE()");
$stmt->execute([':empresa_id' => $empresa_id]);
$total_pontos_hoje = $stmt->fetch()['total'];

// Total de atrasos hoje (pontos depois das 08:00)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM pontos 
                      WHERE empresa_id = :empresa_id 
                      AND DATE(data_hora) = CURDATE() 
                      AND tipo = 'entrada' 
                      AND TIME(data_hora) > '08:00:00'");
$stmt->execute([':empresa_id' => $empresa_id]);
$total_atrasos = $stmt->fetch()['total'];

// Funcionários que não bateram ponto hoje
$stmt = $db->prepare("SELECT COUNT(*) as total FROM funcionarios f
                      WHERE f.empresa_id = :empresa_id 
                      AND f.status = 'ativo'
                      AND NOT EXISTS (
                          SELECT 1 FROM pontos p 
                          WHERE p.funcionario_id = f.id 
                          AND DATE(p.data_hora) = CURDATE()
                      )");
$stmt->execute([':empresa_id' => $empresa_id]);
$sem_ponto = $stmt->fetch()['total'];

// Buscar solicitações pendentes
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
    font-weight: 600;
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

/* Cards de Relatórios */
.relatorios-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
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
    font-size: 14px;
    margin-bottom: 20px;
    line-height: 1.5;
}

.relatorio-acoes {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.btn-relatorio {
    flex: 1;
    padding: 10px;
    border-radius: 10px;
    text-align: center;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s;
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

.filters-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
}

.filters-title {
    font-weight: 600;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
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
}

.filter-group select,
.filter-group input {
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
    min-width: 160px;
}

.btn-filter {
    padding: 10px 24px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 500;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .relatorios-grid {
        grid-template-columns: 1fr;
    }
    
    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<div class="relatorios-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-chart-bar"></i> Relatórios e Estatísticas</h2>
            <p>Visualize estatísticas e gere relatórios da empresa</p>
        </div>
    </div>

    <!-- Cards de Estatísticas Rápidas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users" style="color: #667eea;"></i>
            </div>
            <div class="stat-number"><?php echo $total_funcionarios; ?></div>
            <div class="stat-label">Funcionários Ativos</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-fingerprint" style="color: #10b981;"></i>
            </div>
            <div class="stat-number"><?php echo $total_pontos_hoje; ?></div>
            <div class="stat-label">Pontos Hoje</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock" style="color: #f59e0b;"></i>
            </div>
            <div class="stat-number"><?php echo $total_atrasos; ?></div>
            <div class="stat-label">Atrasos Hoje</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-times" style="color: #ef4444;"></i>
            </div>
            <div class="stat-number"><?php echo $sem_ponto; ?></div>
            <div class="stat-label">Sem Ponto Hoje</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clipboard-list" style="color: #8b5cf6;"></i>
            </div>
            <div class="stat-number"><?php echo $solicitacoes_pendentes; ?></div>
            <div class="stat-label">Solicitações Pendentes</div>
        </div>
    </div>

    <!-- Filtro Rápido para Relatórios -->
    <div class="filters-card">
        <div class="filters-title">
            <i class="fas fa-filter"></i> Filtros Rápidos
        </div>
        <form method="GET" action="pontos_funcionario.php" class="filters-form">
            <div class="filter-group">
                <label>Funcionário</label>
                <select name="funcionario_id">
                    <option value="">Todos os funcionários</option>
                    <?php
                    $stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios WHERE empresa_id = :empresa_id AND status = 'ativo' ORDER BY nome");
                    $stmt->execute([':empresa_id' => $empresa_id]);
                    $funcionarios = $stmt->fetchAll();
                    foreach ($funcionarios as $func): ?>
                        <option value="<?php echo $func['id']; ?>">
                            <?php echo htmlspecialchars($func['nome'] . ' (' . $func['matricula'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Mês/Ano</label>
                <input type="month" name="mes" value="<?php echo date('Y-m'); ?>">
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn-filter">
                    <i class="fas fa-chart-line"></i> Gerar Relatório
                </button>
            </div>
        </form>
    </div>

    <!-- Cards de Relatórios Disponíveis -->
    <div class="relatorios-grid">
        <!-- Relatório de Pontos por Funcionário -->
        <div class="relatorio-card">
            <div class="relatorio-header">
                <i class="fas fa-user-clock"></i>
                <h3>Pontos por Funcionário</h3>
            </div>
            <div class="relatorio-body">
                <div class="relatorio-descricao">
                    Visualize todos os registros de ponto de um funcionário específico em um período determinado.
                    Inclui entrada, saída almoço, volta almoço e saída.
                </div>
                <div class="relatorio-acoes">
                    <a href="pontos_funcionario.php" class="btn-relatorio btn-visualizar">
                        <i class="fas fa-eye"></i> Visualizar
                    </a>
                    <a href="exportar_excel.php?tipo=pontos_funcionario" class="btn-relatorio btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="exportar_pdf.php?tipo=pontos_funcionario" class="btn-relatorio btn-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- Extrato Geral da Empresa -->
        <div class="relatorio-card">
            <div class="relatorio-header">
                <i class="fas fa-building"></i>
                <h3>Extrato Geral da Empresa</h3>
            </div>
            <div class="relatorio-body">
                <div class="relatorio-descricao">
                    Relatório consolidado de todos os funcionários da empresa.
                    Mostra horas trabalhadas, atrasos, faltas e muito mais.
                </div>
                <div class="relatorio-acoes">
                    <a href="extrato_geral.php" class="btn-relatorio btn-visualizar">
                        <i class="fas fa-eye"></i> Visualizar
                    </a>
                    <a href="exportar_excel.php?tipo=extrato_geral" class="btn-relatorio btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="exportar_pdf.php?tipo=extrato_geral" class="btn-relatorio btn-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- Relatório de Atrasos e Faltas -->
        <div class="relatorio-card">
            <div class="relatorio-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Atrasos e Faltas</h3>
            </div>
            <div class="relatorio-body">
                <div class="relatorio-descricao">
                    Relatório detalhado de atrasos (entrada após 08:00) e faltas (dias sem registro de ponto).
                </div>
                <div class="relatorio-acoes">
                    <a href="atrasos_faltas.php" class="btn-relatorio btn-visualizar">
                        <i class="fas fa-eye"></i> Visualizar
                    </a>
                    <a href="exportar_excel.php?tipo=atrasos_faltas" class="btn-relatorio btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="exportar_pdf.php?tipo=atrasos_faltas" class="btn-relatorio btn-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- Relatório de Horas Trabalhadas -->
        <div class="relatorio-card">
            <div class="relatorio-header">
                <i class="fas fa-chart-line"></i>
                <h3>Horas Trabalhadas</h3>
            </div>
            <div class="relatorio-body">
                <div class="relatorio-descricao">
                    Relatório de horas trabalhadas por funcionário, incluindo horas normais e horas extras.
                </div>
                <div class="relatorio-acoes">
                    <a href="horas_trabalhadas.php" class="btn-relatorio btn-visualizar">
                        <i class="fas fa-eye"></i> Visualizar
                    </a>
                    <a href="exportar_excel.php?tipo=horas_trabalhadas" class="btn-relatorio btn-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="exportar_pdf.php?tipo=horas_trabalhadas" class="btn-relatorio btn-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>