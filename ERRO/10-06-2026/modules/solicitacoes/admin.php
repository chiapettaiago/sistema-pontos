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

<style>
.solicitacoes-container {
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
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-card .stat-number {
    font-size: 32px;
    font-weight: bold;
}

.stat-card .stat-label {
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 5px;
}

.stat-card.pendente .stat-number { color: #f59e0b; }
.stat-card.aprovada .stat-number { color: #10b981; }
.stat-card.rejeitada .stat-number { color: #ef4444; }

/* Filtros */
.filters-bar {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: flex-end;
    border: 1px solid var(--border-color);
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-size: 12px;
    color: var(--text-secondary);
    font-weight: 500;
}

.filter-group select {
    padding: 10px 16px;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: 14px;
    min-width: 150px;
}

.btn-filter {
    padding: 10px 24px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 500;
}

.btn-clear {
    padding: 10px 24px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-weight: 500;
}

/* Cards de Solicitações */
.solicitacao-card {
    background: var(--bg-primary);
    border-radius: 16px;
    margin-bottom: 16px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: all 0.3s;
}

.solicitacao-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.solicitacao-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
    flex-wrap: wrap;
    gap: 10px;
}

.funcionario-info {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
}

.funcionario-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}

.funcionario-avatar i {
    font-size: 24px;
    color: white;
}

.funcionario-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.funcionario-detalhes {
    display: flex;
    flex-direction: column;
}

.funcionario-nome {
    font-weight: 600;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.funcionario-nome i {
    font-size: 12px;
    color: #667eea;
    cursor: pointer;
}

.funcionario-nome i:hover {
    color: #764ba2;
}

.funcionario-matricula {
    font-size: 11px;
    color: var(--text-secondary);
}

.solicitacao-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-pendente {
    background: #fef3c7;
    color: #d97706;
}

.status-aprovado {
    background: #d1fae5;
    color: #059669;
}

.status-rejeitado {
    background: #fee2e2;
    color: #dc2626;
}

.solicitacao-body {
    padding: 20px;
}

.solicitacao-tipo {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    background: #e0e7ff;
    color: #4338ca;
    margin-bottom: 12px;
}

.solicitacao-titulo {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 12px;
    color: var(--text-primary);
}

.solicitacao-descricao {
    background: var(--bg-secondary);
    padding: 12px 16px;
    border-radius: 12px;
    margin: 12px 0;
    font-size: 14px;
    line-height: 1.5;
    color: var(--text-primary);
}

.solicitacao-datas {
    display: flex;
    gap: 24px;
    font-size: 13px;
    color: var(--text-secondary);
    margin: 12px 0;
    flex-wrap: wrap;
}

.solicitacao-datas i {
    margin-right: 6px;
    width: 16px;
}

.solicitacao-actions {
    display: flex;
    gap: 12px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--border-color);
}

.btn-aprovar {
    flex: 1;
    padding: 12px;
    background: #10b981;
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-aprovar:hover {
    background: #059669;
    transform: translateY(-1px);
}

.btn-rejeitar {
    flex: 1;
    padding: 12px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-rejeitar:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

.btn-detalhes {
    padding: 12px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-detalhes:hover {
    background: var(--bg-tertiary);
}

/* Modal de Detalhes do Funcionário */
.modal-funcionario {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-funcionario .modal-content {
    background: var(--bg-primary);
    border-radius: 24px;
    padding: 24px;
    max-width: 450px;
    width: 90%;
    text-align: center;
}

.modal-funcionario .foto-grande {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    margin: 0 auto 16px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.modal-funcionario .foto-grande i {
    font-size: 60px;
    color: white;
}

.modal-funcionario .foto-grande img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.modal-funcionario h3 {
    margin-bottom: 8px;
}

.modal-funcionario .info-linha {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
    text-align: left;
}

.modal-funcionario .info-label {
    font-weight: 600;
    color: var(--text-secondary);
}

.modal-funcionario .info-value {
    color: var(--text-primary);
}

.modal-funcionario .close-modal {
    margin-top: 20px;
    padding: 10px 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
}

/* Modal Resposta */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal .modal-content {
    background: var(--bg-primary);
    border-radius: 24px;
    padding: 24px;
    max-width: 500px;
    width: 90%;
}

.modal textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
    resize: vertical;
    margin: 16px 0;
    font-family: inherit;
}

.modal-buttons {
    display: flex;
    gap: 12px;
}

.modal-buttons button {
    flex: 1;
    padding: 12px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 500;
    border: none;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px;
    background: var(--bg-primary);
    border-radius: 16px;
    border: 1px solid var(--border-color);
}

.empty-state i {
    font-size: 64px;
    color: #ccc;
    margin-bottom: 16px;
}

/* Alerts */
.alert {
    padding: 12px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

@media (max-width: 768px) {
    .stats-grid {
        gap: 10px;
    }
    .stat-number {
        font-size: 24px;
    }
    .solicitacao-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .solicitacao-datas {
        flex-direction: column;
        gap: 8px;
    }
    .solicitacao-actions {
        flex-direction: column;
    }
}
</style>

<div class="solicitacoes-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-user-check"></i> Gerenciar Solicitações</h2>
            <p>Aprove ou rejeite solicitações dos funcionários</p>
        </div>
    </div>

    <?php if (isset($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?php echo $_SESSION['tipo_mensagem'] ?? 'success'; ?>">
            <i class="fas <?php echo ($_SESSION['tipo_mensagem'] ?? 'success') == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php 
                echo $_SESSION['mensagem'];
                unset($_SESSION['mensagem']);
                unset($_SESSION['tipo_mensagem']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Cards de Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card pendente" onclick="window.location.href='?status=pendente'">
            <div class="stat-number"><?php echo $stats['pendentes'] ?? 0; ?></div>
            <div class="stat-label">📋 Pendentes</div>
        </div>
        <div class="stat-card aprovada" onclick="window.location.href='?status=aprovado'">
            <div class="stat-number"><?php echo $stats['aprovadas'] ?? 0; ?></div>
            <div class="stat-label">✅ Aprovadas</div>
        </div>
        <div class="stat-card rejeitada" onclick="window.location.href='?status=rejeitado'">
            <div class="stat-number"><?php echo $stats['rejeitadas'] ?? 0; ?></div>
            <div class="stat-label">❌ Rejeitadas</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-bar">
        <div class="filter-group">
            <label>Status</label>
            <select id="statusFilter">
                <option value="pendente" <?php echo $status_filtro == 'pendente' ? 'selected' : ''; ?>>Pendentes</option>
                <option value="aprovado" <?php echo $status_filtro == 'aprovado' ? 'selected' : ''; ?>>Aprovadas</option>
                <option value="rejeitado" <?php echo $status_filtro == 'rejeitado' ? 'selected' : ''; ?>>Rejeitadas</option>
                <option value="todos" <?php echo $status_filtro == 'todos' ? 'selected' : ''; ?>>Todas</option>
            </select>
        </div>
        <div class="filter-group">
            <label>&nbsp;</label>
            <button class="btn-filter" onclick="aplicarFiltro()">Filtrar</button>
        </div>
        <div class="filter-group">
            <label>&nbsp;</label>
            <a href="admin.php" class="btn-clear">Limpar</a>
        </div>
    </div>

    <!-- Lista de Solicitações -->
    <?php if (empty($solicitacoes)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>Nenhuma solicitação encontrada</p>
        </div>
    <?php else: ?>
        <?php foreach ($solicitacoes as $s): ?>
        <div class="solicitacao-card">
            <div class="solicitacao-header">
                <div class="funcionario-info" onclick="verDetalhesFuncionario(<?php echo $s['funcionario_id']; ?>)">
                    <div class="funcionario-avatar">
                        <?php if (!empty($s['foto']) && file_exists('../../' . $s['foto'])): ?>
                            <img src="../../<?php echo $s['foto']; ?>" alt="Foto">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="funcionario-detalhes">
                        <div class="funcionario-nome">
                            <?php echo htmlspecialchars($s['funcionario_nome'] ?? 'Funcionário'); ?>
                            <i class="fas fa-info-circle" title="Clique para ver detalhes"></i>
                        </div>
                        <div class="funcionario-matricula">Matrícula: <?php echo htmlspecialchars($s['matricula'] ?? 'N/A'); ?></div>
                    </div>
                </div>
                <div class="solicitacao-status status-<?php echo $s['status']; ?>">
                    <?php 
                    $statusLabels = [
                        'pendente' => '⏳ Pendente',
                        'aprovado' => '✅ Aprovado',
                        'rejeitado' => '❌ Rejeitado'
                    ];
                    echo $statusLabels[$s['status']] ?? $s['status'];
                    ?>
                </div>
            </div>
            
            <div class="solicitacao-body">
                <div class="solicitacao-tipo">
                    <i class="fas 
                        <?php echo $s['tipo'] == 'ferias' ? 'fa-umbrella-beach' : 
                                     ($s['tipo'] == 'abono' ? 'fa-gift' : 
                                     ($s['tipo'] == 'licenca' ? 'fa-notes-medical' : 'fa-file-alt')); ?>">
                    </i>
                    <?php echo $s['tipo_nome']; ?>
                </div>
                
                <div class="solicitacao-titulo">
                    <?php echo htmlspecialchars($s['titulo'] ?? 'Solicitação'); ?>
                </div>
                
                <?php if (!empty($s['descricao'])): ?>
                <div class="solicitacao-descricao">
                    <?php echo nl2br(htmlspecialchars($s['descricao'])); ?>
                </div>
                <?php endif; ?>
                
                <div class="solicitacao-datas">
                    <?php if ($s['data_inicio']): ?>
                    <span><i class="fas fa-calendar-alt"></i> Início: <?php echo $s['data_inicio_formatada']; ?></span>
                    <?php endif; ?>
                    <?php if ($s['data_fim_formatada']): ?>
                    <span><i class="fas fa-calendar-check"></i> Fim: <?php echo $s['data_fim_formatada']; ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-clock"></i> Solicitado: <?php echo date('d/m/Y H:i', strtotime($s['created_at'])); ?></span>
                </div>
                
                <?php if ($s['status'] == 'pendente'): ?>
                <div class="solicitacao-actions">
                    <button class="btn-aprovar" onclick="abrirModal(<?php echo $s['id']; ?>, 'aprovar', '<?php echo addslashes($s['funcionario_nome'] ?? 'Funcionário'); ?>')">
                        <i class="fas fa-check"></i> Aprovar
                    </button>
                    <button class="btn-rejeitar" onclick="abrirModal(<?php echo $s['id']; ?>, 'rejeitar', '<?php echo addslashes($s['funcionario_nome'] ?? 'Funcionário'); ?>')">
                        <i class="fas fa-times"></i> Rejeitar
                    </button>
                </div>
                <?php elseif ($s['resposta']): ?>
                <div class="solicitacao-descricao" style="background: rgba(16, 185, 129, 0.1); margin-top: 8px;">
                    <i class="fas fa-reply"></i> <strong>Resposta:</strong><br>
                    <?php echo nl2br(htmlspecialchars($s['resposta'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Resposta -->
<div id="modalResposta" class="modal">
    <div class="modal-content">
        <h3 id="modalTitulo">Aprovar Solicitação</h3>
        <p id="modalSubtitulo" style="color: var(--text-secondary);"></p>
        <form method="POST" action="">
            <input type="hidden" name="id" id="solicitacaoId">
            <input type="hidden" name="acao" id="solicitacaoAcao">
            <textarea name="resposta" id="resposta" rows="4" placeholder="Digite uma resposta/observação (opcional)..."></textarea>
            <div class="modal-buttons">
                <button type="submit" id="btnConfirmar" style="flex:1; background: #10b981; color: white;">Confirmar</button>
                <button type="button" onclick="fecharModal()" style="flex:1; background: var(--bg-secondary); cursor: pointer;">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Detalhes do Funcionário -->
<div id="modalDetalhesFuncionario" class="modal-funcionario">
    <div class="modal-content">
        <div id="detalhesConteudo"></div>
        <button class="close-modal" onclick="fecharModalFuncionario()">Fechar</button>
    </div>
</div>

<script>
function aplicarFiltro() {
    var status = document.getElementById('statusFilter').value;
    window.location.href = 'admin.php?status=' + status;
}

var modal = document.getElementById('modalResposta');
var modalFuncionario = document.getElementById('modalDetalhesFuncionario');

function abrirModal(id, acao, nome) {
    document.getElementById('solicitacaoId').value = id;
    document.getElementById('solicitacaoAcao').value = acao;
    
    if (acao === 'aprovar') {
        document.getElementById('modalTitulo').innerHTML = '✅ Aprovar Solicitação';
        document.getElementById('modalSubtitulo').innerHTML = 'Deseja aprovar a solicitação de <strong>' + nome + '</strong>?';
        document.getElementById('btnConfirmar').style.background = '#10b981';
        document.getElementById('btnConfirmar').innerHTML = '<i class="fas fa-check"></i> Aprovar';
    } else {
        document.getElementById('modalTitulo').innerHTML = '❌ Rejeitar Solicitação';
        document.getElementById('modalSubtitulo').innerHTML = 'Deseja rejeitar a solicitação de <strong>' + nome + '</strong>?';
        document.getElementById('btnConfirmar').style.background = '#ef4444';
        document.getElementById('btnConfirmar').innerHTML = '<i class="fas fa-times"></i> Rejeitar';
    }
    modal.style.display = 'flex';
}

function fecharModal() {
    modal.style.display = 'none';
}

function verDetalhesFuncionario(funcionarioId) {
    // Buscar dados do funcionário via AJAX ou usar dados já carregados
    <?php 
    // Criar array com dados dos funcionários
    $funcionarios_data = [];
    foreach ($solicitacoes as $s) {
        if (!isset($funcionarios_data[$s['funcionario_id']])) {
            $funcionarios_data[$s['funcionario_id']] = [
                'id' => $s['funcionario_id'],
                'nome' => $s['funcionario_nome'],
                'matricula' => $s['matricula'],
                'email' => $s['funcionario_email'],
                'cpf' => $s['cpf'],
                'admissao' => $s['admissao_formatada'],
                'cargo' => $s['cargo_nome'],
                'filial' => $s['filial_nome'],
                'foto' => $s['foto']
            ];
        }
    }
    ?>
    
    var funcionarios = <?php echo json_encode(array_values($funcionarios_data)); ?>;
    var funcionario = funcionarios.find(f => f.id == funcionarioId);
    
    if (funcionario) {
        var fotoHtml = '';
        if (funcionario.foto && funcionario.foto !== '') {
            fotoHtml = '<img src="../../' + funcionario.foto + '" alt="Foto">';
        } else {
            fotoHtml = '<i class="fas fa-user-circle"></i>';
        }
        
        document.getElementById('detalhesConteudo').innerHTML = `
            <div class="foto-grande">
                ${fotoHtml}
            </div>
            <h3>${funcionario.nome || 'Funcionário'}</h3>
            <div class="info-linha">
                <span class="info-label">Matrícula:</span>
                <span class="info-value">${funcionario.matricula || 'N/A'}</span>
            </div>
            <div class="info-linha">
                <span class="info-label">E-mail:</span>
                <span class="info-value">${funcionario.email || 'N/A'}</span>
            </div>
            <div class="info-linha">
                <span class="info-label">CPF:</span>
                <span class="info-value">${funcionario.cpf || 'N/A'}</span>
            </div>
            <div class="info-linha">
                <span class="info-label">Cargo:</span>
                <span class="info-value">${funcionario.cargo || 'Não definido'}</span>
            </div>
            <div class="info-linha">
                <span class="info-label">Filial:</span>
                <span class="info-value">${funcionario.filial || 'Matriz'}</span>
            </div>
            <div class="info-linha">
                <span class="info-label">Data Admissão:</span>
                <span class="info-value">${funcionario.admissao || 'N/A'}</span>
            </div>
        `;
        modalFuncionario.style.display = 'flex';
    }
}

function fecharModalFuncionario() {
    modalFuncionario.style.display = 'none';
}

window.onclick = function(event) {
    if (event.target === modal) {
        fecharModal();
    }
    if (event.target === modalFuncionario) {
        fecharModalFuncionario();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>