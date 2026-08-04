<?php
// modules/biometrico/config.php - Configurações da Biometria
$pageTitle = 'Configurações Biométricas';
$activePage = 'biometrico';
require_once '../../includes/header.php';
require_once '../../config/database.php';

checkModuleAccess('configurar_notificacoes');

$database = new Database();
$db = $database->getConnection();

// Buscar configurações
$configs = [];
$query = "SELECT chave, valor FROM configuracoes WHERE grupo = 'biometrico'";
$stmt = $db->query($query);
while ($row = $stmt->fetch()) {
    $configs[$row['chave']] = $row['valor'];
}

// Salvar configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $biometrico_ativado = isset($_POST['biometrico_ativado']) ? 1 : 0;
    $biometrico_tipo = $_POST['biometrico_tipo'] ?? 'ambos';
    $biometrico_limiar_facial = $_POST['biometrico_limiar_facial'] ?? 0.6;
    $biometrico_amostras_faciais = $_POST['biometrico_amostras_faciais'] ?? 5;
    
    $querys = [
        "INSERT INTO configuracoes (chave, valor, grupo) VALUES ('biometrico_ativado', '$biometrico_ativado', 'biometrico') ON DUPLICATE KEY UPDATE valor = '$biometrico_ativado'",
        "INSERT INTO configuracoes (chave, valor, grupo) VALUES ('biometrico_tipo', '$biometrico_tipo', 'biometrico') ON DUPLICATE KEY UPDATE valor = '$biometrico_tipo'",
        "INSERT INTO configuracoes (chave, valor, grupo) VALUES ('biometrico_limiar_facial', '$biometrico_limiar_facial', 'biometrico') ON DUPLICATE KEY UPDATE valor = '$biometrico_limiar_facial'",
        "INSERT INTO configuracoes (chave, valor, grupo) VALUES ('biometrico_amostras_faciais', '$biometrico_amostras_faciais', 'biometrico') ON DUPLICATE KEY UPDATE valor = '$biometrico_amostras_faciais'"
    ];
    
    foreach ($querys as $q) {
        $db->exec($q);
    }
    
    $success = "Configurações salvas com sucesso!";
}
?>

<style>
.config-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}

.config-card {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.tipo-option {
    display: inline-block;
    margin-right: 20px;
    padding: 10px 20px;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
}

.tipo-option.selected {
    border-color: var(--primary);
    background: rgba(102, 126, 234, 0.1);
}

.switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--primary);
}

input:checked + .slider:before {
    transform: translateX(24px);
}
</style>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-fingerprint"></i> Configurações Biométricas</h3>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Informação:</strong> O reconhecimento digital requer um leitor biométrico (hardware) conectado ao computador. 
            O reconhecimento facial utiliza a webcam do dispositivo.
        </div>
        
        <form method="POST" action="" class="form-main">
            <div class="config-section">
                <h4><i class="fas fa-toggle-on"></i> Ativar Biometria</h4>
                <div class="config-card">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Ativar sistema de biometria</span>
                        <label class="switch">
                            <input type="checkbox" name="biometrico_ativado" value="1" <?php echo ($configs['biometrico_ativado'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="config-section">
                <h4><i class="fas fa-chart-pie"></i> Tipo de Biometria</h4>
                <div class="config-card">
                    <div class="tipo-group">
                        <label class="tipo-option <?php echo ($configs['biometrico_tipo'] ?? 'ambos') == 'digital' ? 'selected' : ''; ?>">
                            <input type="radio" name="biometrico_tipo" value="digital" style="display: none;" <?php echo ($configs['biometrico_tipo'] ?? 'ambos') == 'digital' ? 'checked' : ''; ?>>
                            <i class="fas fa-fingerprint"></i> Apenas Digital
                        </label>
                        <label class="tipo-option <?php echo ($configs['biometrico_tipo'] ?? 'ambos') == 'facial' ? 'selected' : ''; ?>">
                            <input type="radio" name="biometrico_tipo" value="facial" style="display: none;" <?php echo ($configs['biometrico_tipo'] ?? 'ambos') == 'facial' ? 'checked' : ''; ?>>
                            <i class="fas fa-face-smile"></i> Apenas Facial
                        </label>
                        <label class="tipo-option <?php echo ($configs['biometrico_tipo'] ?? 'ambos') == 'ambos' ? 'selected' : ''; ?>">
                            <input type="radio" name="biometrico_tipo" value="ambos" style="display: none;" <?php echo ($configs['biometrico_tipo'] ?? 'ambos') == 'ambos' ? 'checked' : ''; ?>>
                            <i class="fas fa-shield"></i> Ambos
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="config-section" id="facialConfig">
                <h4><i class="fas fa-face-smile"></i> Configurações Faciais</h4>
                <div class="config-card">
                    <div class="form-group">
                        <label>Limiar de Similaridade (0-1)</label>
                        <input type="range" name="biometrico_limiar_facial" min="0" max="1" step="0.05" 
                               value="<?php echo $configs['biometrico_limiar_facial'] ?? 0.6; ?>" 
                               oninput="this.nextElementSibling.value = this.value">
                        <output><?php echo $configs['biometrico_limiar_facial'] ?? 0.6; ?></output>
                        <small>Quanto menor o valor, mais rigoroso o reconhecimento (recomendado: 0.6)</small>
                    </div>
                    <div class="form-group">
                        <label>Número de Amostras para Cadastro</label>
                        <select name="biometrico_amostras_faciais">
                            <option value="3" <?php echo ($configs['biometrico_amostras_faciais'] ?? 5) == 3 ? 'selected' : ''; ?>>3 amostras</option>
                            <option value="5" <?php echo ($configs['biometrico_amostras_faciais'] ?? 5) == 5 ? 'selected' : ''; ?>>5 amostras</option>
                            <option value="7" <?php echo ($configs['biometrico_amostras_faciais'] ?? 5) == 7 ? 'selected' : ''; ?>>7 amostras</option>
                            <option value="10" <?php echo ($configs['biometrico_amostras_faciais'] ?? 5) == 10 ? 'selected' : ''; ?>>10 amostras</option>
                        </select>
                        <small>Mais amostras = maior precisão, mas leva mais tempo para cadastrar</small>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Configurações
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Seleção de tipo
document.querySelectorAll('.tipo-option').forEach(opt => {
    opt.addEventListener('click', function() {
        const radio = this.querySelector('input[type="radio"]');
        radio.checked = true;
        
        document.querySelectorAll('.tipo-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');
    });
});

// Mostrar/esconder configurações faciais baseado no tipo
function toggleFacialConfig() {
    const tipo = document.querySelector('input[name="biometrico_tipo"]:checked').value;
    const facialConfig = document.getElementById('facialConfig');
    if (tipo === 'digital') {
        facialConfig.style.display = 'none';
    } else {
        facialConfig.style.display = 'block';
    }
}

document.querySelectorAll('input[name="biometrico_tipo"]').forEach(radio => {
    radio.addEventListener('change', toggleFacialConfig);
});
toggleFacialConfig();
</script>

<?php require_once '../../includes/footer.php'; ?>