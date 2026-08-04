<?php
// modules/backup/index.php - Painel de Backup (COMPLETO COM TEXTOS CORRETOS)
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

$pageTitle = 'Backup e Restauração';
$activePage = 'backup';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../includes/csrf.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;

$backupDir = __DIR__ . '/backups/';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0777, true);
}

$backups = [];
$files = glob($backupDir . 'backup_*.sql.gz');
foreach ($files as $file) {
    $filename = basename($file);
    $stat = stat($file);
    
    $stmt = $db->prepare("SELECT * FROM backups_registros WHERE nome_arquivo = :nome ORDER BY id DESC LIMIT 1");
    $stmt->execute([':nome' => $filename]);
    $registro = $stmt->fetch();
    
    $backups[] = [
        'nome' => $filename,
        'tamanho' => formatarTamanho(filesize($file)),
        'data' => date('d/m/Y H:i:s', $stat['mtime']),
        'tipo' => $registro['tipo'] ?? 'manual',
        'status' => $registro['status'] ?? 'sucesso'
    ];
}

usort($backups, function($a, $b) {
    return strtotime($b['data']) - strtotime($a['data']);
});

$total_backups = count($backups);
$total_tamanho = 0;
foreach ($files as $file) {
    $total_tamanho += filesize($file);
}

$stmt = $db->prepare("SELECT * FROM backups_config WHERE empresa_id = :empresa_id");
$stmt->execute([':empresa_id' => $empresa_id]);
$config = $stmt->fetch();

if (!$config) {
    $stmt = $db->prepare("INSERT INTO backups_config (empresa_id) VALUES (:empresa_id)");
    $stmt->execute([':empresa_id' => $empresa_id]);
    
    $stmt = $db->prepare("SELECT * FROM backups_config WHERE empresa_id = :empresa_id");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $config = $stmt->fetch();
}

function formatarTamanho($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

$mensagem = $_SESSION['mensagem'] ?? '';
$tipo_mensagem = $_SESSION['tipo_mensagem'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['tipo_mensagem']);
?>

<style>
.backup-container { max-width: 1200px; margin: 0 auto; }
.module-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
.module-title h2 { margin: 0; font-size: 24px; }
.module-title p { margin: 8px 0 0 0; color: var(--text-secondary); font-size: 14px; }
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.3s; }
.btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
.btn-secondary { background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color); }
.btn-success { background: #10b981; color: white; }
.btn-warning { background: #f59e0b; color: white; }
.btn-danger { background: #ef4444; color: white; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px; }
.stat-card { background: var(--bg-primary); border-radius: 16px; padding: 20px; border: 1px solid var(--border-color); text-align: center; }
.stat-card .stat-number { font-size: 32px; font-weight: 700; color: var(--text-primary); }
.stat-card .stat-label { font-size: 13px; color: var(--text-secondary); margin-top: 5px; }
.actions-card { background: var(--bg-primary); border-radius: 16px; border: 1px solid var(--border-color); padding: 20px; margin-bottom: 24px; }
.actions-grid { display: flex; gap: 16px; flex-wrap: wrap; justify-content: center; }
.table-card { background: var(--bg-primary); border-radius: 16px; border: 1px solid var(--border-color); overflow: hidden; }
.table-header { padding: 16px 20px; background: var(--bg-secondary); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
.table-header h3 { margin: 0; font-size: 16px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-color); }
.data-table th { background: var(--bg-secondary); font-weight: 600; font-size: 12px; color: var(--text-secondary); }
.data-table tr:hover { background: var(--bg-secondary); }
.badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; }
.badge-manual { background: #d1fae5; color: #059669; }
.badge-automatico { background: #bfdbfe; color: #1e40af; }
.badge-agendado { background: #fed7aa; color: #c2410c; }
.badge-sucesso { background: #d1fae5; color: #059669; }
.badge-falha { background: #fee2e2; color: #dc2626; }
.empty-state { text-align: center; padding: 60px; color: var(--text-secondary); }
.empty-state i { font-size: 48px; margin-bottom: 16px; display: block; }
.alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; }
.alert-warning { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
.alert-info { background: #bfdbfe; color: #1e40af; border: 1px solid #bfdbfe; }
.btn-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.inline-form { display: inline-flex; margin: 0; }
.inline-form .btn { white-space: nowrap; }
@media (max-width: 768px) { .data-table { font-size: 12px; } .data-table th, .data-table td { padding: 8px; } .btn-actions { flex-direction: column; } }
</style>

<div class="backup-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-database"></i> Backup e Restauração</h2>
            <p>Gerencie backups do banco de dados</p>
        </div>
        <div class="module-actions">
            <a href="criar.php" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Backup</a>
            <a href="config.php" class="btn btn-secondary"><i class="fas fa-cog"></i> Configurações</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-number"><?php echo $total_backups; ?></div><div class="stat-label">Total de Backups</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo formatarTamanho($total_tamanho); ?></div><div class="stat-label">Espaço Utilizado</div></div>
        <div class="stat-card"><div class="stat-number"><?php echo $config['dias_retencao']; ?></div><div class="stat-label">Dias de Retenção</div></div>
    </div>

    <div class="actions-card">
        <div class="actions-grid">
            <a href="criar.php" class="btn btn-success"><i class="fas fa-database"></i> Backup Manual</a>
            <a href="restaurar.php" class="btn btn-warning"><i class="fas fa-undo-alt"></i> Restaurar Backup</a>
            <a href="config.php" class="btn btn-secondary"><i class="fas fa-clock"></i> Configurar Agendamento</a>
        </div>
    </div>

    <?php if (!$config['backup_automatico']): ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> O backup automático está desativado. Ative nas configurações para backups regulares.</div>
    <?php else: ?>
    <div class="alert alert-info"><i class="fas fa-info-circle"></i> Backup automático: <?php echo ucfirst($config['frequencia']); ?> às <?php echo substr($config['hora_backup'], 0, 5); ?>. Retenção de <?php echo $config['dias_retencao']; ?> dias.</div>
    <?php endif; ?>

    <?php if ($mensagem): ?>
    <div class="alert alert-<?php echo $tipo_mensagem === 'success' ? 'info' : 'warning'; ?>">
        <i class="fas <?php echo $tipo_mensagem === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
        <?php echo htmlspecialchars($mensagem); ?>
    </div>
    <?php endif; ?>

    <!-- Tabela de Backups -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Backups Disponíveis</h3>
            <?php if ($total_backups > 0): ?>
            <form method="POST" action="excluir.php" class="inline-form" onsubmit="return confirm('Tem certeza que deseja excluir TODOS os backups?')">
                <?php echo csrfField(); ?>
                <input type="hidden" name="todos" value="1">
                <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">
                    <i class="fas fa-trash-alt"></i> Excluir Todos
                </button>
            </form>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <?php if (empty($backups)): ?>
                <div class="empty-state"><i class="fas fa-database"></i><p>Nenhum backup encontrado</p><a href="criar.php" class="btn btn-primary">Criar primeiro backup</a></div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Arquivo</th><th>Data</th><th>Tamanho</th><th>Tipo</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td><i class="fas fa-archive"></i> <?php echo htmlspecialchars($backup['nome']); ?></td>
                            <td><?php echo $backup['data']; ?></td>
                            <td><?php echo $backup['tamanho']; ?></td>
                            <td><span class="badge badge-<?php echo $backup['tipo']; ?>"><?php echo ucfirst($backup['tipo']); ?></span></td>
                            <td><span class="badge badge-<?php echo $backup['status']; ?>"><?php echo ucfirst($backup['status']); ?></span></td>
                            <td class="btn-actions">
                                <a href="download.php?file=<?php echo urlencode($backup['nome']); ?>" class="btn btn-success" style="padding: 6px 10px; font-size: 12px; border-radius: 6px;"><i class="fas fa-download"></i> Baixar</a>
                                <a href="restaurar.php?file=<?php echo urlencode($backup['nome']); ?>" class="btn btn-warning" style="padding: 6px 10px; font-size: 12px; border-radius: 6px;" onclick="return confirm('ATENÇÃO: Restaurar um backup irá SUBSTITUIR todos os dados atuais! Tem certeza?')"><i class="fas fa-undo-alt"></i> Restaurar</a>
                                <form method="POST" action="excluir.php" class="inline-form" onsubmit="return confirm('Tem certeza que deseja excluir este backup?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="file" value="<?php echo htmlspecialchars($backup['nome']); ?>">
                                    <button type="submit" class="btn btn-danger" style="padding: 6px 10px; font-size: 12px; border-radius: 6px;"><i class="fas fa-trash-alt"></i> Excluir</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="actions-card" style="margin-top: 24px;">
        <div style="font-size: 13px; color: var(--text-secondary);">
            <strong><i class="fas fa-info-circle"></i> Informações importantes:</strong><br>
            • Os backups são salvos na pasta <code>modules/backup/backups/</code><br>
            • Recomenda-se manter backups regulares para prevenir perda de dados<br>
            • Backups automáticos são configurados via CRON<br>
            • Ao restaurar um backup, todos os dados atuais serão SUBSTITUÍDOS
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
