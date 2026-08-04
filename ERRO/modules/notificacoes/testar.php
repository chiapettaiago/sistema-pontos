<?php
// modules/notificacoes/testar.php - Testar envio de e-mail
$pageTitle = 'Testar Notificações';
$activePage = 'notificacoes';
require_once '../../includes/header.php';
require_once '../../includes/mail.php';
require_once '../../config/database.php';

checkModuleAccess('admin');

$database = new Database();
$db = $database->getConnection();

$resultado = '';
$tipo_resultado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_teste = $_POST['email_teste'] ?? $_SESSION['usuario_email'];
    $tipo_teste = $_POST['tipo_teste'] ?? 'lembrete';
    
    $usuario = $db->query("SELECT nome FROM funcionarios WHERE id = " . $_SESSION['usuario_id'])->fetch();
    
    switch ($tipo_teste) {
        case 'aprovacao':
            $sucesso = enviarAprovacaoSolicitacao($email_teste, $usuario['nome'], 'Férias', 'Teste de aprovação');
            break;
        case 'rejeicao':
            $sucesso = enviarRejeicaoSolicitacao($email_teste, $usuario['nome'], 'Abono', 'Teste de rejeição - motivo exemplo');
            break;
        case 'lembrete':
            $sucesso = enviarLembretePonto($email_teste, $usuario['nome'], '08:00');
            break;
        case 'atraso':
            $sucesso = enviarAlertaAtraso($email_teste, $usuario['nome'], date('d/m/Y'), '08:00', '08:30', 30);
            break;
        default:
            $sucesso = false;
    }
    
    if ($sucesso) {
        $resultado = "E-mail de teste enviado com sucesso para {$email_teste}!";
        $tipo_resultado = 'success';
    } else {
        $resultado = "Erro ao enviar e-mail. Verifique as configurações SMTP.";
        $tipo_resultado = 'error';
    }
}
?>

<style>
.test-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    border: 1px solid var(--border-color);
}

.test-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.test-option {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    border: 2px solid transparent;
}

.test-option:hover {
    transform: translateY(-2px);
    border-color: var(--primary);
}

.test-option.selected {
    border-color: var(--primary);
    background: rgba(102, 126, 234, 0.1);
}

.test-option i {
    font-size: 32px;
    margin-bottom: 8px;
    display: block;
}
</style>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-paper-plane"></i> Testar Envio de E-mail</h3>
        </div>
        
        <?php if ($resultado): ?>
            <div class="alert alert-<?php echo $tipo_resultado; ?>">
                <?php echo $resultado; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="form-main">
            <div class="form-group">
                <label>E-mail de teste</label>
                <input type="email" name="email_teste" value="<?php echo $_SESSION['usuario_email']; ?>" required>
            </div>
            
            <label>Tipo de notificação</label>
            <div class="test-options">
                <div class="test-option" data-tipo="lembrete">
                    <i class="fas fa-bell"></i>
                    <strong>Lembrete</strong>
                    <small>Lembrete de ponto</small>
                </div>
                <div class="test-option" data-tipo="aprovacao">
                    <i class="fas fa-check-circle"></i>
                    <strong>Aprovação</strong>
                    <small>Solicitação aprovada</small>
                </div>
                <div class="test-option" data-tipo="rejeicao">
                    <i class="fas fa-times-circle"></i>
                    <strong>Rejeição</strong>
                    <small>Solicitação rejeitada</small>
                </div>
                <div class="test-option" data-tipo="atraso">
                    <i class="fas fa-clock"></i>
                    <strong>Atraso</strong>
                    <small>Alerta de atraso</small>
                </div>
            </div>
            <input type="hidden" name="tipo_teste" id="tipo_teste" value="lembrete">
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Enviar Teste
                </button>
            </div>
        </form>
        
        <div class="alert alert-info" style="margin-top: 20px;">
            <i class="fas fa-info-circle"></i>
            <strong>Configuração SMTP:</strong> Para que os e-mails funcionem, configure as constantes SMTP no arquivo <code>includes/mail.php</code>.
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.test-option').forEach(opt => {
    opt.addEventListener('click', function() {
        document.querySelectorAll('.test-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');
        document.getElementById('tipo_teste').value = this.dataset.tipo;
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>