<?php
// modules/solicitacoes/index.php - Minhas Solicitações (CORRIGIDO)
$pageTitle = 'Minhas Solicitações';
$activePage = 'solicitacoes';

// ============================================
// VERIFICAÇÕES ANTES DO HEADER
// ============================================
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Pega o ID do funcionário da sessão
$funcionario_id = $_SESSION['funcionario_id'] ?? null;

if (!$funcionario_id && isset($_SESSION['usuario_id'])) {
    $stmt = $db->prepare("SELECT id FROM funcionarios WHERE email = :email");
    $stmt->execute([':email' => $_SESSION['usuario_email']]);
    $func = $stmt->fetch();
    if ($func) {
        $funcionario_id = $func['id'];
        $_SESSION['funcionario_id'] = $funcionario_id;
    }
}

if (!$funcionario_id) {
    $_SESSION['mensagem'] = 'Perfil de funcionário não encontrado.';
    header('Location: ../../index.php');
    exit;
}

// Buscar parâmetros de filtro
$status = isset($_GET['status']) ? $_GET['status'] : '';
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

// Query base - CORRIGIDA: usando created_at em vez de data_solicitacao
$query = "SELECT s.*, 
          DATE_FORMAT(s.data_inicio, '%d/%m/%Y') as data_inicio_formatada,
          DATE_FORMAT(s.data_fim, '%d/%m/%Y') as data_fim_formatada,
          DATE_FORMAT(s.created_at, '%d/%m/%Y %H:%i') as data_solicitacao_formatada,
          CASE 
              WHEN s.tipo = 'ferias' THEN 'Férias'
              WHEN s.tipo = 'abono' THEN 'Abono'
              WHEN s.tipo = 'licenca' THEN 'Licença'
              WHEN s.tipo = 'justificativa_ausencia' THEN 'Justificativa de Ausência'
              WHEN s.tipo = 'justificativa' THEN 'Justificativa'
              WHEN s.tipo = 'alteracao_ponto' THEN 'Alteração de Ponto'
              WHEN s.tipo = 'atestado' THEN 'Atestado'
              WHEN s.tipo = 'outros' THEN 'Outros'
              ELSE s.tipo
          END as tipo_nome,
          CASE 
              WHEN s.status = 'pendente' THEN '⏳ Pendente'
              WHEN s.status = 'aprovado' THEN '✅ Aprovado'
              WHEN s.status = 'rejeitado' THEN '❌ Rejeitado'
              WHEN s.status = 'cancelado' THEN '🗑️ Cancelado'
              ELSE s.status
          END as status_texto
          FROM solicitacoes s
          WHERE s.funcionario_id = :funcionario_id";

$params = [':funcionario_id' => $funcionario_id];

if ($status) {
    $query .= " AND s.status = :status";
    $params[':status'] = $status;
}

if ($tipo) {
    $query .= " AND s.tipo = :tipo";
    $params[':tipo'] = $tipo;
}

$query .= " ORDER BY s.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$solicitacoes = $stmt->fetchAll();

// Estatísticas
$stats = [];
$query = "SELECT 
          SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
          SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) as aprovadas,
          SUM(CASE WHEN status = 'rejeitado' THEN 1 ELSE 0 END) as rejeitadas,
          SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as canceladas
          FROM solicitacoes 
          WHERE funcionario_id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $funcionario_id]);
$stats = $stmt->fetch();

// Tipos para o filtro
$tipos_filtro = [
    '' => 'Todos os tipos',
    'ferias' => 'Férias',
    'abono' => 'Abono',
    'licenca' => 'Licença',
    'justificativa' => 'Justificativa',
    'atestado' => 'Atestado',
    'alteracao_ponto' => 'Alteração de Ponto',
    'outros' => 'Outros'
];

require_once '../../includes/header.php';
?>
<div class="pf-page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-envelope-open-text me-2 text-primary"></i>Minhas Solicitações</h1>
        <p class="text-muted">Acompanhe suas solicitações de ajuste</p>
    </div>
    <a href="nova.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Nova Solicitação</a>
</div>

<!-- Filtros -->
<div class="card pf-table-card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-sm-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="pendente" <?php echo ($_GET['status'] ?? '') === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                    <option value="aprovado" <?php echo ($_GET['status'] ?? '') === 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                    <option value="reprovado" <?php echo ($_GET['status'] ?? '') === 'reprovado' ? 'selected' : ''; ?>>Reprovado</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <a href="?" class="btn btn-outline-secondary ms-1"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card pf-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th class="d-none d-md-table-cell">Descrição</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitacoes as $sol): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($sol['created_at'])); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $sol['tipo'])); ?></td>
                        <td class="d-none d-md-table-cell text-muted">
                            <?php echo htmlspecialchars(strlen($sol['descricao'] ?? '') > 60 ? substr($sol['descricao'], 0, 57).'...' : ($sol['descricao'] ?? '')); ?>
                        </td>
                        <td>
                            <?php $bc = ['aprovado'=>'bg-success','reprovado'=>'bg-danger','pendente'=>'bg-warning text-dark']; ?>
                            <span class="badge <?php echo $bc[$sol['status']] ?? 'bg-secondary'; ?>"><?php echo ucfirst($sol['status']); ?></span>
                        </td>
                        <td class="text-end">
                            <a href="nova.php?id=<?php echo $sol['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                            <?php if ($sol['status'] === 'pendente'): ?>
                            <a href="cancelar.php?id=<?php echo $sol['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancelar solicitação?')">
                                <i class="fas fa-times"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($solicitacoes)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">
                        <i class="fas fa-envelope-open fa-2x d-block mb-2 opacity-25"></i>Nenhuma solicitação encontrada
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
