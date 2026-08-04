<?php
// modules/biometrico/digital.php - Cadastro Digital (Simulado)
$pageTitle = 'Cadastro Digital';
$activePage = 'biometrico';
require_once '../../includes/header.php';
require_once '../../config/database.php';

checkModuleAccess('gerenciar_funcionarios');

$database = new Database();
$db = $database->getConnection();

$funcionario_id = $_GET['id'] ?? 0;

// Buscar dados do funcionário
$query = "SELECT nome, matricula FROM funcionarios WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    header('Location: cadastrar.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $digital_template = $_POST['digital_template'] ?? '';
    $acao = $_POST['acao'] ?? '';
    
    if ($acao == 'salvar' && !empty($digital_template)) {
        try {
            $query = "INSERT INTO biometricos_digitais (funcionario_id, digital_template) 
                      VALUES (:funcionario_id, :digital_template)
                      ON DUPLICATE KEY UPDATE digital_template = :digital_template, updated_at = NOW()";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':funcionario_id' => $funcionario_id,
                ':digital_template' => $digital_template
            ]);
            
            $mensagem = "Digital cadastrada com sucesso!";
            $tipo_mensagem = "success";
            
            // Redirecionar após 2 segundos
            echo '<script>setTimeout(function(){ window.location.href = "cadastrar.php"; }, 2000);</script>';
        } catch (Exception $e) {
            $mensagem = "Erro ao cadastrar digital: " . $e->getMessage();
            $tipo_mensagem = "error";
        }
    }
}
?>

<style>
.digital-container {
    max-width: 500px;
    margin: 0 auto;
}

.digital-card {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 32px;
    text-align: center;
    border: 1px solid var(--border-color);
}

.digital-icon {
    font-size: 80px;
    color: #667eea;
    margin-bottom: 20px;
}

.digital-icon i {
    font-size: 80px;
}

.leitor-simulator {
    background: var(--bg-secondary);
    border-radius: 16px;
    padding: 40px;
    margin: 20px 0;
    cursor: pointer;
    transition: all 0.3s;
    border: 2px dashed var(--border-color);
}

.leitor-simulator:hover {
    background: rgba(102, 126, 234, 0.1);
    border-color: #667eea;
}

.leitor-simulator i {
    font-size: 64px;
    color: #667eea;
    margin-bottom: 16px;
}

.leitor-simulator p {
    margin: 8px 0;
}

.reading-status {
    margin-top: 20px;
    padding: 12px;
    border-radius: 8px;
    display: none;
}

.reading-status.show {
    display: block;
}

.reading-status .loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid #667eea;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
    margin-right: 8px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<div class="digital-container">
    <div class="digital-card">
        <div class="digital-icon">
            <i class="fas fa-fingerprint"></i>
        </div>
        
        <h3>Cadastro Digital</h3>
        <p><strong>Funcionário:</strong> <?php echo htmlspecialchars($funcionario['nome']); ?> (<?php echo htmlspecialchars($funcionario['matricula']); ?>)</p>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>"><?php echo $mensagem; ?></div>
        <?php endif; ?>
        
        <div class="leitor-simulator" id="leitorSimulator">
            <i class="fas fa-fingerprint"></i>
            <p><strong>Clique para simular a leitura da digital</strong></p>
            <p style="font-size: 12px; color: var(--text-secondary);">
                Em produção, isso seria substituído pela leitura real do leitor biométrico
            </p>
        </div>
        
        <div id="readingStatus" class="reading-status">
            <span class="loading"></span> Lendo digital... Aguarde
        </div>
        
        <form method="POST" action="" id="digitalForm">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="digital_template" id="digitalTemplate">
        </form>
        
        <div style="display: flex; gap: 12px; margin-top: 20px;">
            <button id="btnSalvar" class="btn btn-primary" style="flex: 1;" disabled>
                <i class="fas fa-save"></i> Salvar Digital
            </button>
            <a href="cadastrar.php" class="btn btn-secondary" style="flex: 1;">
                <i class="fas fa-times"></i> Cancelar
            </a>
        </div>
        
        <div class="alert alert-info" style="margin-top: 20px;">
            <i class="fas fa-info-circle"></i>
            <strong>Nota:</strong> Em um ambiente de produção, esta tela se integraria com um leitor biométrico real (ex: Nitgen, DigitalPersona, SecuGen), que capturaria a digital do funcionário e geraria um template criptografado.
        </div>
    </div>
</div>

<script>
// Simular leitura de digital
let digitalCaptured = false;
let digitalData = null;

document.getElementById('leitorSimulator').addEventListener('click', function() {
    if (digitalCaptured) return;
    
    const statusDiv = document.getElementById('readingStatus');
    statusDiv.classList.add('show');
    
    // Simular tempo de leitura
    setTimeout(() => {
        // Gerar template simulado
        digitalData = 'digital_template_' + Date.now() + '_' + Math.random().toString(36).substring(7);
        document.getElementById('digitalTemplate').value = digitalData;
        
        statusDiv.innerHTML = '<span style="color: #10b981;">✅ Digital capturada com sucesso!</span>';
        document.getElementById('btnSalvar').disabled = false;
        digitalCaptured = true;
        
        // Mudar estilo do simulador
        document.getElementById('leitorSimulator').style.background = 'rgba(16, 185, 129, 0.1)';
        document.getElementById('leitorSimulator').style.borderColor = '#10b981';
    }, 2000);
});

document.getElementById('btnSalvar').addEventListener('click', function() {
    if (!digitalCaptured) {
        alert('Capture a digital primeiro clicando no leitor');
        return;
    }
    document.getElementById('digitalForm').submit();
});
</script>

<?php require_once '../../includes/footer.php'; ?>