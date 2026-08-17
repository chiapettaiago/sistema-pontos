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
<div class="pf-page-header">
    <h1><i class="fas fa-chart-bar me-2 text-primary"></i>Relatórios</h1>
    <p class="text-muted">Acesse todos os relatórios do sistema</p>
</div>

<div class="row g-4">
    <div class="col-md-6 col-xl-4">
        <a href="horas_trabalhadas.php" class="card pf-stat-card p-4 text-decoration-none text-dark h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="pf-stat-icon" style="background:var(--pf-gradient);"><i class="fas fa-hourglass-half text-white"></i></div>
                <h5 class="mb-0 fw-semibold">Horas Trabalhadas</h5>
            </div>
            <p class="text-muted small mb-0">Relatório detalhado das horas trabalhadas por funcionário</p>
        </a>
    </div>
    <div class="col-md-6 col-xl-4">
        <a href="banco_horas.php" class="card pf-stat-card p-4 text-decoration-none text-dark h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="pf-stat-icon" style="background:linear-gradient(135deg,#10b981,#059669);"><i class="fas fa-piggy-bank text-white"></i></div>
                <h5 class="mb-0 fw-semibold">Banco de Horas</h5>
            </div>
            <p class="text-muted small mb-0">Saldo de horas extras e compensadas</p>
        </a>
    </div>
    <div class="col-md-6 col-xl-4">
        <a href="atrasos_faltas.php" class="card pf-stat-card p-4 text-decoration-none text-dark h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="pf-stat-icon" style="background:linear-gradient(135deg,#ef4444,#dc2626);"><i class="fas fa-exclamation-triangle text-white"></i></div>
                <h5 class="mb-0 fw-semibold">Atrasos e Faltas</h5>
            </div>
            <p class="text-muted small mb-0">Controle de atrasos, faltas e ausências</p>
        </a>
    </div>
    <div class="col-md-6 col-xl-4">
        <a href="horas_extras.php" class="card pf-stat-card p-4 text-decoration-none text-dark h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="pf-stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);"><i class="fas fa-plus-square text-white"></i></div>
                <h5 class="mb-0 fw-semibold">Horas Extras</h5>
            </div>
            <p class="text-muted small mb-0">Relatório de horas extras realizadas</p>
        </a>
    </div>
    <div class="col-md-6 col-xl-4">
        <a href="extrato_funcionario.php" class="card pf-stat-card p-4 text-decoration-none text-dark h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="pf-stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);"><i class="fas fa-user-clock text-white"></i></div>
                <h5 class="mb-0 fw-semibold">Extrato por Funcionário</h5>
            </div>
            <p class="text-muted small mb-0">Todos os registros de ponto de um funcionário</p>
        </a>
    </div>
    <div class="col-md-6 col-xl-4">
        <a href="extrato_coletivo.php" class="card pf-stat-card p-4 text-decoration-none text-dark h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="pf-stat-icon" style="background:linear-gradient(135deg,#0891b2,#0e7490);"><i class="fas fa-users text-white"></i></div>
                <h5 class="mb-0 fw-semibold">Extrato Coletivo</h5>
            </div>
            <p class="text-muted small mb-0">Registros de todos os funcionários por período</p>
        </a>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
