<?php
// modules/admin/empresas/recuperar.php - Recuperar Empresas Excluídas (CORRIGIDO - HEADERS)
// NÃO PODE HAVER NADA ANTES DESTA LINHA

session_start();

// Verificar se está logado e é super admin
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_tipo'] ?? '') !== 'super_admin') {
    header('Location: /login.php');
    exit;
}

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// ============================================
// PROCESSAR RECUPERAÇÃO (ANTES DO HEADER)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'recuperar') {
    $id = $_POST['id'];
    
    try {
        // Buscar dados da empresa excluída
        $stmt = $db->prepare("SELECT * FROM empresas_excluidas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $excluida = $stmt->fetch();
        
        if ($excluida) {
            // Verificar se já existe empresa com o mesmo nome
            $stmt = $db->prepare("SELECT id FROM empresas WHERE nome = :nome");
            $stmt->execute([':nome' => $excluida['nome']]);
            $existe = $stmt->fetch();
            
            if ($existe) {
                $_SESSION['mensagem'] = "Já existe uma empresa com o nome '{$excluida['nome']}'. Não é possível recuperar.";
                $_SESSION['tipo_mensagem'] = "error";
            } else {
                // Recriar empresa
                $stmt = $db->prepare("INSERT INTO empresas (nome, cnpj, email, status, data_ativacao) 
                                      VALUES (:nome, :cnpj, :email, 'ativa', CURDATE())");
                $stmt->execute([
                    ':nome' => $excluida['nome'],
                    ':cnpj' => $excluida['cnpj'],
                    ':email' => $excluida['email']
                ]);
                
                $nova_empresa_id = $db->lastInsertId();
                
                // Registrar log
                $log = $db->prepare("INSERT INTO logs_sistema (funcionario_id, acao, descricao, ip) 
                                     VALUES (:funcionario_id, 'RECUPERAR_EMPRESA', :descricao, :ip)");
                $log->execute([
                    ':funcionario_id' => $_SESSION['usuario_id'],
                    ':descricao' => "Empresa recuperada: {$excluida['nome']} (ID original: {$excluida['empresa_original_id']})",
                    ':ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                // Remover do histórico
                $stmt = $db->prepare("DELETE FROM empresas_excluidas WHERE id = :id");
                $stmt->execute([':id' => $id]);
                
                $_SESSION['mensagem'] = "Empresa '{$excluida['nome']}' foi recuperada com sucesso!";
                $_SESSION['tipo_mensagem'] = "success";
            }
        } else {
            $_SESSION['mensagem'] = "Registro não encontrado na lixeira";
            $_SESSION['tipo_mensagem'] = "error";
        }
    } catch (Exception $e) {
        $_SESSION['mensagem'] = "Erro ao recuperar empresa: " . $e->getMessage();
        $_SESSION['tipo_mensagem'] = "error";
    }
    
    header('Location: recuperar.php');
    exit;
}

// ============================================
// AGORA SIM, INCLUIR O HEADER
// ============================================
$pageTitle = 'Empresas Excluídas';
$activePage = 'admin_empresas';
require_once '../../../includes/header.php';
require_once '../../../config/database.php';

// Conectar novamente (já está conectado, mas vamos garantir)
$database = new Database();
$db = $database->getConnection();

// Exibir mensagem se existir
if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    $tipo_mensagem = $_SESSION['tipo_mensagem'] ?? 'info';
    echo "<div class='alert alert-{$tipo_mensagem}' style='margin-bottom: 20px;'>
            <i class='fas " . ($tipo_mensagem == 'success' ? 'fa-check-circle' : 'fa-info-circle') . "'></i> 
            {$mensagem}
          </div>";
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);
}

// Buscar empresas excluídas
$query = "SELECT * FROM empresas_excluidas ORDER BY data_exclusao DESC";
$stmt = $db->query($query);
$empresas_excluidas = $stmt->fetchAll();
?>

<style>
.empresa-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 16px;
    border: 1px solid var(--border-color);
    transition: var(--transition);
}

.empresa-card:hover {
    box-shadow: var(--shadow-md);
}

.empresa-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
}

.empresa-nome {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 4px;
    color: #dc2626;
}

.empresa-detalhes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    padding: 12px;
    background: var(--bg-secondary);
    border-radius: 12px;
    margin: 12px 0;
}

.empresa-detalhe {
    display: flex;
    flex-direction: column;
}

.empresa-detalhe-label {
    font-size: 11px;
    color: var(--text-secondary);
}

.empresa-detalhe-valor {
    font-size: 14px;
    font-weight: 500;
}

.btn-recuperar {
    background: #10b981;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    transition: var(--transition);
    font-size: 14px;
    font-weight: 500;
}

.btn-recuperar:hover {
    background: #059669;
    transform: translateY(-2px);
}

.empty-state {
    text-align: center;
    padding: 60px;
}

.empty-state i {
    font-size: 48px;
    color: #ccc;
    margin-bottom: 16px;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-inativa {
    background: #fee2e2;
    color: #dc2626;
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-trash-restore"></i> Lixeira - Empresas Excluídas</h2>
        <p>Recupere empresas que foram movidas para a lixeira</p>
    </div>
    <div class="module-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para Empresas
        </a>
    </div>
</div>

<?php if (empty($empresas_excluidas)): ?>
<div class="empty-state">
    <i class="fas fa-trash-restore"></i>
    <p style="margin-top: 16px;">Não há empresas na lixeira</p>
    <a href="index.php" class="btn btn-primary" style="margin-top: 16px;">Voltar para Empresas</a>
</div>
<?php else: ?>
    <?php foreach ($empresas_excluidas as $empresa): ?>
    <div class="empresa-card">
        <div class="empresa-header">
            <div>
                <div class="empresa-nome"><?php echo htmlspecialchars($empresa['nome']); ?></div>
                <div style="font-size: 13px; color: var(--text-secondary);">
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($empresa['email'] ?: 'Não informado'); ?>
                </div>
            </div>
            <div>
                <span class="status-badge status-inativa">
                    <i class="fas fa-trash"></i> Na lixeira
                </span>
            </div>
        </div>
        
        <div class="empresa-detalhes">
            <div class="empresa-detalhe">
                <span class="empresa-detalhe-label">CNPJ</span>
                <span class="empresa-detalhe-valor"><?php echo htmlspecialchars($empresa['cnpj'] ?: '--'); ?></span>
            </div>
            <div class="empresa-detalhe">
                <span class="empresa-detalhe-label">Data de Exclusão</span>
                <span class="empresa-detalhe-valor"><?php echo date('d/m/Y H:i', strtotime($empresa['data_exclusao'])); ?></span>
            </div>
            <div class="empresa-detalhe">
                <span class="empresa-detalhe-label">ID Original</span>
                <span class="empresa-detalhe-valor">#<?php echo $empresa['empresa_original_id']; ?></span>
            </div>
        </div>
        
        <div style="margin-top: 16px;">
            <form method="POST" action="" style="display: inline;">
                <input type="hidden" name="acao" value="recuperar">
                <input type="hidden" name="id" value="<?php echo $empresa['id']; ?>">
                <button type="submit" class="btn-recuperar" onclick="return confirm('Tem certeza que deseja recuperar esta empresa? Ela será restaurada como ativa.')">
                    <i class="fas fa-trash-restore"></i> Recuperar Empresa
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once '../../../includes/footer.php'; ?>
