<?php
// modules/funcionarios/alterar_senha.php - Alterar Senha do Funcionário
$pageTitle = 'Alterar Senha';
$activePage = 'funcionarios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

checkModuleAccess('funcionarios');

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;

// Buscar dados do funcionário
$stmt = $db->prepare("SELECT nome FROM funcionarios WHERE id = :id");
$stmt->execute([':id' => $id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    if (empty($nova_senha)) {
        $error = 'Nova senha é obrigatória';
    } elseif (strlen($nova_senha) < 6) {
        $error = 'A senha deve ter no mínimo 6 caracteres';
    } elseif ($nova_senha !== $confirmar_senha) {
        $error = 'As senhas não conferem';
    } else {
        try {
            $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("UPDATE funcionarios SET senha = :senha WHERE id = :id");
            $stmt->execute([':senha' => $senha_hash, ':id' => $id]);
            
            logAcao($db, 'UPDATE', 'funcionarios', $id, "Alterou senha do funcionário: {$funcionario['nome']}");
            
            $success = 'Senha alterada com sucesso!';
            
        } catch (Exception $e) {
            $error = 'Erro ao alterar senha: ' . $e->getMessage();
        }
    }
}
?>

<div class="form-container">
    <div class="form-card" style="max-width: 500px; margin: 0 auto;">
        <div class="form-header">
            <h3><i class="fas fa-key"></i> Alterar Senha</h3>
            <p>Funcionário: <strong><?php echo htmlspecialchars($funcionario['nome']); ?></strong></p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="form-main">
            <div class="form-group">
                <label>Nova Senha *</label>
                <input type="password" name="nova_senha" required minlength="6">
                <small>Mínimo 6 caracteres</small>
            </div>
            
            <div class="form-group">
                <label>Confirmar Nova Senha *</label>
                <input type="password" name="confirmar_senha" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Nova Senha
                </button>
                <a href="visualizar.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>