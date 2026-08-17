<?php
// modules/solicitacoes/admin.php - Gerenciar Solicitações (COM FOTO E DETALHES)
$pageTitle = 'Gerenciar Solicitações';
$activePage = 'solicitacoes_admin';

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

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$funcionario_logado_id = $_SESSION['funcionario_id'] ?? null;

// Processar ação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $solicitacao_id = $_POST['id'] ?? 0;
    $acao = $_POST['acao'] ?? '';
    $resposta = trim($_POST['resposta'] ?? '');
    
    if ($solicitacao_id && in_array($acao, ['aprovar', 'rejeitar'])) {
        $novo_status = $acao === 'aprovar' ? 'aprovado' : 'rejeitado';
        
        try {
            $stmt = $db->prepare("UPDATE solicitacoes 
                                  SET status = :status, 
                                      resposta = :resposta, 
                                      data_resposta = NOW(),
                                      respondido_por = :respondido_por
                                  WHERE id = :id");
            
            $stmt->execute([
                ':status' => $novo_status,
                ':resposta' => $resposta,
                ':respondido_por' => $funcionario_logado_id,
                ':id' => $solicitacao_id
            ]);
            
            $_SESSION['mensagem'] = $acao === 'aprovar' ? 'Solicitação aprovada!' : 'Solicitação rejeitada!';
            $_SESSION['tipo_mensagem'] = 'success';
        } catch (Exception $e) {
            $_SESSION['mensagem'] = 'Erro: ' . $e->getMessage();
            $_SESSION['tipo_mensagem'] = 'error';
        }
        header('Location: admin.php');
        exit;
    }
}

// Buscar parâmetros de filtro
$status_filtro = isset($_GET['status']) ? $_GET['status'] : 'pendente';
$tipo_filtro = isset($_GET['tipo']) ? $_GET['tipo'] : '';

// Buscar solicitações
$query = "SELECT s.*, 
          f.id as funcionario_id,
          f.nome as funcionario_nome,
          f.matricula,
          f.foto,
          f.email as funcionario_email,
          f.cpf,
          f.data_admissao,
          fil.nome_fantasia as filial_nome,
          c.nome as cargo_nome,
          CASE 
              WHEN s.tipo = 'ferias' THEN 'Férias'
              WHEN s.tipo = 'abono' THEN 'Abono'
              WHEN s.tipo = 'licenca' THEN 'Licença Médica'
              WHEN s.tipo = 'justificativa' THEN 'Justificativa'
              WHEN s.tipo = 'atestado' THEN 'Atestado'
              WHEN s.tipo = 'outros' THEN 'Outros'
              ELSE s.tipo
          END as tipo_nome,
          DATE_FORMAT(s.data_inicio, '%d/%m/%Y') as data_inicio_formatada,
          DATE_FORMAT(s.data_fim, '%d/%m/%Y') as data_fim_formatada,
          DATE_FORMAT(f.data_admissao, '%d/%m/%Y') as admissao_formatada
          FROM solicitacoes s
          LEFT JOIN funcionarios f ON s.funcionario_id = f.id
          LEFT JOIN filiais fil ON f.filial_id = fil.id
          LEFT JOIN cargos c ON f.cargo_id = c.id
          WHERE 1=1";

$params = [];

if ($status_filtro && $status_filtro !== 'todos') {
    $query .= " AND s.status = :status";
    $params[':status'] = $status_filtro;
}

$query .= " ORDER BY s.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$solicitacoes = $stmt->fetchAll();

// Estatísticas
$stats_query = "SELECT 
                SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) as aprovadas,
                SUM(CASE WHEN status = 'rejeitado' THEN 1 ELSE 0 END) as rejeitadas
                FROM solicitacoes";
$stmt = $db->query($stats_query);
$stats = $stmt->fetch();

require_once '../../includes/header.php';
?>
<div class="pf-page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-clipboard-check me-2 text-primary"></i>Gerenciar Solicitações</h1>
        <p class="text-muted">Aprove ou reprove solicitações de funcionários</p>
    </div>
</div>

<!-- Filtros -->
<div class="card pf-table-card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-sm-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="pendente"  <?php echo ($_GET['status'] ?? '') === 'pendente'  ? 'selected' : ''; ?>>Pendente</option>
                    <option value="aprovado"  <?php echo ($_GET['status'] ?? '') === 'aprovado'  ? 'selected' : ''; ?>>Aprovado</option>
                    <option value="reprovado" <?php echo ($_GET['status'] ?? '') === 'reprovado' ? 'selected' : ''; ?>>Reprovado</option>
                </select>
            </div>
            <div class="col-sm-4">
                <label class="form-label">Funcionário</label>
                <input type="text" name="funcionario" class="form-control" placeholder="Nome" value="<?php echo htmlspecialchars($_GET['funcionario'] ?? ''); ?>">
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
                        <th>Funcionário</th>
                        <th>Tipo</th>
                        <th class="d-none d-lg-table-cell">Descrição</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitacoes as $sol): ?>
                    <tr>
                        <td class="text-muted small"><?php echo date('d/m/Y', strtotime($sol['created_at'])); ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($sol['funcionario_nome']); ?></td>
                        <td><?php echo ucfirst(str_replace('_',' ',$sol['tipo'])); ?></td>
                        <td class="d-none d-lg-table-cell text-muted small">
                            <?php echo htmlspecialchars(substr($sol['descricao'] ?? '', 0, 60)); ?>
                        </td>
                        <td>
                            <?php $bc = ['aprovado'=>'bg-success','reprovado'=>'bg-danger','pendente'=>'bg-warning text-dark']; ?>
                            <span class="badge <?php echo $bc[$sol['status']] ?? 'bg-secondary'; ?>"><?php echo ucfirst($sol['status']); ?></span>
                        </td>
                        <td class="text-end">
                            <?php if ($sol['status'] === 'pendente'): ?>
                            <div class="btn-group btn-group-sm">
                                <a href="aprovar.php?id=<?php echo $sol['id']; ?>" class="btn btn-success" onclick="return confirm('Aprovar?')">
                                    <i class="fas fa-check"></i>
                                </a>
                                <a href="cancelar.php?id=<?php echo $sol['id']; ?>" class="btn btn-danger" onclick="return confirm('Reprovar?')">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($solicitacoes)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">
                        <i class="fas fa-clipboard fa-2x d-block mb-2 opacity-25"></i>Nenhuma solicitação encontrada
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
