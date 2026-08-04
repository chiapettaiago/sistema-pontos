<?php
// modules/auditoria/detalhes.php - Detalhes de uma ação de auditoria
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa'])) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Detalhes do Log';
$activePage = 'auditoria';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;

// Buscar detalhes do log
$stmt = $db->prepare("SELECT * FROM logs_auditoria WHERE id = :id");
$stmt->execute([':id' => $id]);
$log = $stmt->fetch();

if (!$log) {
    header('Location: index.php');
    exit;
}

// Formatar dados anteriores e novos
$dados_anteriores = $log['dados_anteriores'] ? json_decode($log['dados_anteriores'], true) : null;
$dados_novos = $log['dados_novos'] ? json_decode($log['dados_novos'], true) : null;

// Cores por ação
$cores = [
    'INSERT' => ['bg' => '#d1fae5', 'text' => '#059669', 'icon' => 'fa-plus-circle'],
    'UPDATE' => ['bg' => '#bfdbfe', 'text' => '#1e40af', 'icon' => 'fa-edit'],
    'DELETE' => ['bg' => '#fee2e2', 'text' => '#dc2626', 'icon' => 'fa-trash-alt'],
    'LOGIN' => ['bg' => '#d1fae5', 'text' => '#059669', 'icon' => 'fa-sign-in-alt'],
    'LOGOUT' => ['bg' => '#fef3c7', 'text' => '#d97706', 'icon' => 'fa-sign-out-alt'],
    'VIEW' => ['bg' => '#e0e7ff', 'text' => '#4338ca', 'icon' => 'fa-eye'],
    'EXPORT' => ['bg' => '#fed7aa', 'text' => '#c2410c', 'icon' => 'fa-file-excel'],
    'BACKUP' => ['bg' => '#d1fae5', 'text' => '#059669', 'icon' => 'fa-database'],
    'RESTORE' => ['bg' => '#fee2e2', 'text' => '#dc2626', 'icon' => 'fa-undo-alt']
];

$cor = $cores[$log['acao']] ?? ['bg' => '#e5e7eb', 'text' => '#374151', 'icon' => 'fa-info-circle'];
?>

<style>
.detalhes-container {
    max-width: 1000px;
    margin: 0 auto;
}

.detalhes-card {
    background: var(--bg-primary);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    margin-bottom: 24px;
}

.detalhes-header {
    padding: 24px;
    background: <?php echo $cor['bg']; ?>;
    color: <?php echo $cor['text']; ?>;
    display: flex;
    align-items: center;
    gap: 16px;
}

.detalhes-header i {
    font-size: 48px;
}

.detalhes-header h3 {
    margin: 0;
    font-size: 24px;
}

.detalhes-header p {
    margin: 8px 0 0;
    opacity: 0.8;
}

.detalhes-body {
    padding: 24px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.info-card {
    background: var(--bg-secondary);
    border-radius: 16px;
    padding: 16px;
}

.info-card h4 {
    margin: 0 0 16px 0;
    font-size: 14px;
    color: var(--text-secondary);
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 8px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid var(--border-color);
    font-size: 14px;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: var(--text-secondary);
}

.info-value {
    color: var(--text-primary);
    word-break: break-word;
    text-align: right;
}

.diff-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
}

.diff-table th,
.diff-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.diff-table th {
    background: var(--bg-secondary);
    font-weight: 600;
    font-size: 12px;
}

.diff-old {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
}

.diff-new {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
}

.diff-unchanged {
    color: var(--text-secondary);
}

.campo-nome {
    font-weight: 600;
}

.btn-voltar {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-voltar:hover {
    background: var(--bg-tertiary);
    transform: translateY(-2px);
}

.user-agent {
    font-family: monospace;
    font-size: 11px;
    word-break: break-all;
    background: var(--bg-primary);
    padding: 8px;
    border-radius: 8px;
    margin-top: 8px;
}

.empty-value {
    color: var(--text-secondary);
    font-style: italic;
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .info-row {
        flex-direction: column;
        gap: 5px;
    }
    
    .info-value {
        text-align: left;
    }
    
    .diff-table {
        font-size: 12px;
    }
    
    .diff-table th,
    .diff-table td {
        padding: 6px 8px;
    }
}
</style>

<div class="detalhes-container">
    <div class="detalhes-card">
        <div class="detalhes-header">
            <i class="fas <?php echo $cor['icon']; ?>"></i>
            <div>
                <h3><?php echo $log['acao']; ?> - <?php echo ucfirst($log['modulo']); ?></h3>
                <p>ID do registro: <?php echo $log['registro_id'] ?: 'N/A'; ?></p>
            </div>
        </div>
        
        <div class="detalhes-body">
            <!-- Informações Gerais -->
            <div class="info-grid">
                <div class="info-card">
                    <h4><i class="fas fa-info-circle"></i> Informações da Ação</h4>
                    <div class="info-row">
                        <span class="info-label">Data/Hora:</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Ação:</span>
                        <span class="info-value"><?php echo $log['acao']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Módulo:</span>
                        <span class="info-value"><?php echo ucfirst($log['modulo']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">ID do Registro:</span>
                        <span class="info-value"><?php echo $log['registro_id'] ?: '<span class="empty-value">Não aplicável</span>'; ?></span>
                    </div>
                </div>
                
                <div class="info-card">
                    <h4><i class="fas fa-user"></i> Informações do Usuário</h4>
                    <div class="info-row">
                        <span class="info-label">Usuário:</span>
                        <span class="info-value"><?php echo htmlspecialchars($log['usuario_nome'] ?? 'Sistema'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">E-mail:</span>
                        <span class="info-value"><?php echo htmlspecialchars($log['usuario_email'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Tipo:</span>
                        <span class="info-value"><?php echo ucfirst($log['usuario_tipo'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">IP Address:</span>
                        <span class="info-value"><?php echo htmlspecialchars($log['ip_address'] ?: 'Não registrado'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Descrição -->
            <?php if ($log['descricao']): ?>
            <div class="info-card" style="margin-bottom: 24px;">
                <h4><i class="fas fa-comment"></i> Descrição</h4>
                <div style="padding: 12px; background: var(--bg-secondary); border-radius: 12px;">
                    <?php echo nl2br(htmlspecialchars($log['descricao'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Comparação de Dados (para UPDATE) -->
            <?php if ($log['acao'] == 'UPDATE' && ($dados_anteriores || $dados_novos)): ?>
            <div class="info-card" style="margin-bottom: 24px;">
                <h4><i class="fas fa-code-branch"></i> Comparação de Alterações</h4>
                <table class="diff-table">
                    <thead>
                        <tr>
                            <th>Campo</th>
                            <th>Valor Anterior</th>
                            <th>Novo Valor</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $todos_campos = array_unique(array_merge(
                            array_keys($dados_anteriores ?? []),
                            array_keys($dados_novos ?? [])
                        ));
                        
                        foreach ($todos_campos as $campo):
                            $old = $dados_anteriores[$campo] ?? null;
                            $new = $dados_novos[$campo] ?? null;
                            $alterado = ($old != $new);
                        ?>
                        <tr class="<?php echo $alterado ? ($old !== null ? 'diff-old' : ($new !== null ? 'diff-new' : 'diff-unchanged')) : 'diff-unchanged'; ?>">
                            <td class="campo-nome"><?php echo ucfirst(str_replace('_', ' ', $campo)); ?></td>
                            <td>
                                <?php if ($old !== null): ?>
                                    <?php echo htmlspecialchars(is_array($old) ? json_encode($old) : $old); ?>
                                <?php else: ?>
                                    <span class="empty-value">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($new !== null): ?>
                                    <?php echo htmlspecialchars(is_array($new) ? json_encode($new) : $new); ?>
                                <?php else: ?>
                                    <span class="empty-value">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($alterado): ?>
                                    <?php if ($old !== null && $new !== null): ?>
                                        <span style="color: #f59e0b;">✏️ Modificado</span>
                                    <?php elseif ($old !== null && $new === null): ?>
                                        <span style="color: #dc2626;">🗑️ Removido</span>
                                    <?php elseif ($old === null && $new !== null): ?>
                                        <span style="color: #059669;">➕ Adicionado</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #9ca3af;">➖ Inalterado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Dados (para INSERT) -->
            <?php if ($log['acao'] == 'INSERT' && $dados_novos): ?>
            <div class="info-card" style="margin-bottom: 24px;">
                <h4><i class="fas fa-database"></i> Dados Inseridos</h4>
                <table class="diff-table">
                    <thead>
                        <tr>
                            <th>Campo</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dados_novos as $campo => $valor): ?>
                        <tr>
                            <td class="campo-nome"><?php echo ucfirst(str_replace('_', ' ', $campo)); ?></td>
                            <td><?php echo htmlspecialchars(is_array($valor) ? json_encode($valor) : ($valor ?? '<span class="empty-value">NULL</span>')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Dados (para DELETE) -->
            <?php if ($log['acao'] == 'DELETE' && $dados_anteriores): ?>
            <div class="info-card" style="margin-bottom: 24px;">
                <h4><i class="fas fa-trash-alt"></i> Dados Removidos</h4>
                <table class="diff-table">
                    <thead>
                        <tr>
                            <th>Campo</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dados_anteriores as $campo => $valor): ?>
                        <tr>
                            <td class="campo-nome"><?php echo ucfirst(str_replace('_', ' ', $campo)); ?></td>
                            <td><?php echo htmlspecialchars(is_array($valor) ? json_encode($valor) : ($valor ?? '<span class="empty-value">NULL</span>')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- User Agent -->
            <?php if ($log['user_agent']): ?>
            <div class="info-card">
                <h4><i class="fas fa-laptop"></i> Informações do Navegador</h4>
                <div class="user-agent">
                    <?php echo htmlspecialchars($log['user_agent']); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Botão Voltar -->
            <div style="margin-top: 24px; text-align: center;">
                <a href="index.php" class="btn-voltar">
                    <i class="fas fa-arrow-left"></i> Voltar para Auditoria
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
