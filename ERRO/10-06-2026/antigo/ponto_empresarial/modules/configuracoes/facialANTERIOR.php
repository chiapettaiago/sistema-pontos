<?php
// modules/configuracoes/facial.php - Configurações de Reconhecimento Facial
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

$pageTitle = 'Configurações de Reconhecimento Facial';
$activePage = 'configuracoes';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$success = '';
$error = '';

// Buscar configurações atuais
$stmt = $db->prepare("SELECT * FROM config_facial WHERE empresa_id = :empresa_id");
$stmt->execute([':empresa_id' => $empresa_id]);
$config = $stmt->fetch();

if (!$config) {
    // Criar configuração padrão
    $stmt = $db->prepare("INSERT INTO config_facial (empresa_id) VALUES (:empresa_id)");
    $stmt->execute([':empresa_id' => $empresa_id]);
    
    $stmt = $db->prepare("SELECT * FROM config_facial WHERE empresa_id = :empresa_id");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $config = $stmt->fetch();
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $facial_obrigatorio = isset($_POST['facial_obrigatorio']) ? 1 : 0;
    $confianca_minima = (int)($_POST['confianca_minima'] ?? 70);
    $tentativas_maximas = (int)($_POST['tentativas_maximas'] ?? 3);
    $permitir_fallback = isset($_POST['permitir_fallback']) ? 1 : 0;
    $qualidade_imagem = isset($_POST['qualidade_imagem']) ? 1 : 0;
    $detectar_expressao = isset($_POST['detectar_expressao']) ? 1 : 0;
    $validar_vivo = isset($_POST['validar_vivo']) ? 1 : 0;
    $tempo_maximo_captura = (int)($_POST['tempo_maximo_captura'] ?? 30);
    $zoom_automatico = isset($_POST['zoom_automatico']) ? 1 : 0;
    
    // Validar valores
    if ($confianca_minima < 50) $confianca_minima = 50;
    if ($confianca_minima > 99) $confianca_minima = 99;
    if ($tentativas_maximas < 1) $tentativas_maximas = 1;
    if ($tentativas_maximas > 10) $tentativas_maximas = 10;
    if ($tempo_maximo_captura < 10) $tempo_maximo_captura = 10;
    if ($tempo_maximo_captura > 60) $tempo_maximo_captura = 60;
    
    try {
        $stmt = $db->prepare("UPDATE config_facial SET 
            facial_obrigatorio = :facial_obrigatorio,
            confianca_minima = :confianca_minima,
            tentativas_maximas = :tentativas_maximas,
            permitir_fallback = :permitir_fallback,
            qualidade_imagem = :qualidade_imagem,
            detectar_expressao = :detectar_expressao,
            validar_vivo = :validar_vivo,
            tempo_maximo_captura = :tempo_maximo_captura,
            zoom_automatico = :zoom_automatico
            WHERE empresa_id = :empresa_id");
        
        $stmt->execute([
            ':facial_obrigatorio' => $facial_obrigatorio,
            ':confianca_minima' => $confianca_minima,
            ':tentativas_maximas' => $tentativas_maximas,
            ':permitir_fallback' => $permitir_fallback,
            ':qualidade_imagem' => $qualidade_imagem,
            ':detectar_expressao' => $detectar_expressao,
            ':validar_vivo' => $validar_vivo,
            ':tempo_maximo_captura' => $tempo_maximo_captura,
            ':zoom_automatico' => $zoom_automatico,
            ':empresa_id' => $empresa_id
        ]);
        
        $success = 'Configurações de reconhecimento facial salvas com sucesso!';
        
        // Recarregar configurações
        $stmt = $db->prepare("SELECT * FROM config_facial WHERE empresa_id = :empresa_id");
        $stmt->execute([':empresa_id' => $empresa_id]);
        $config = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = 'Erro ao salvar: ' . $e->getMessage();
    }
}

// Calcular nível de segurança
$nivel_seguranca = 'Baixo';
$nivel_cor = '#f59e0b';
if ($config['facial_obrigatorio'] && $config['validar_vivo'] && $config['qualidade_imagem'] && $config['confianca_minima'] >= 80) {
    $nivel_seguranca = 'Alto';
    $nivel_cor = '#10b981';
} elseif ($config['facial_obrigatorio'] && $config['confianca_minima'] >= 70) {
    $nivel_seguranca = 'Médio';
    $nivel_cor = '#667eea';
}
?>

<style>
.config-container {
    max-width: 900px;
    margin: 0 auto;
}

.module-header {
    margin-bottom: 24px;
}

.module-title h2 {
    margin: 0;
    font-size: 24px;
}

.module-title p {
    margin: 8px 0 0 0;
    color: var(--text-secondary);
    font-size: 14px;
}

.config-card {
    background: var(--bg-primary);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    margin-bottom: 24px;
    overflow: hidden;
}

.config-header {
    padding: 16px 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.config-header i {
    margin-right: 8px;
}

.config-header h3 {
    margin: 0;
    font-size: 18px;
}

.config-body {
    padding: 24px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    font-size: 14px;
}

.form-group label i {
    margin-right: 6px;
    color: #667eea;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: 14px;
    transition: all 0.3s;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-group small {
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 4px;
    display: block;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-row .form-group {
    margin-bottom: 0;
}

.switch-group {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
}

.switch-group:last-child {
    border-bottom: none;
}

.switch-label {
    font-weight: 500;
}

.switch-label small {
    font-size: 11px;
    color: var(--text-secondary);
    display: block;
    font-weight: normal;
    margin-top: 4px;
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
    background-color: #667eea;
}

input:checked + .slider:before {
    transform: translateX(24px);
}

.range-value {
    display: inline-block;
    margin-left: 10px;
    padding: 4px 8px;
    background: var(--bg-secondary);
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    color: #667eea;
}

.btn {
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.alert {
    padding: 12px 16px;
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

.alert-info {
    background: #bfdbfe;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}

.form-actions {
    display: flex;
    gap: 16px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}

.security-level {
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 20px;
    text-align: center;
}

.security-level .level {
    font-size: 24px;
    font-weight: 700;
    margin-top: 8px;
}

.security-level .level-baixo { color: #f59e0b; }
.security-level .level-medio { color: #667eea; }
.security-level .level-alto { color: #10b981; }

.info-box {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.info-box p {
    margin: 0;
    font-size: 13px;
    color: #92400e;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    
    .switch-group {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        justify-content: center;
    }
}
</style>

<div class="config-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-camera"></i> Configurações de Reconhecimento Facial</h2>
            <p>Configure as regras e parâmetros para o reconhecimento facial</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Nível de Segurança -->
    <div class="config-card">
        <div class="config-header">
            <i class="fas fa-shield-alt"></i>
            <h3>Nível de Segurança</h3>
        </div>
        <div class="config-body">
            <div class="security-level">
                <span>🔒 Nível de Segurança Atual</span>
                <div class="level level-<?php echo strtolower($nivel_seguranca); ?>"><?php echo $nivel_seguranca; ?></div>
                <small>Baseado nas configurações atuais</small>
            </div>
        </div>
    </div>

    <form method="POST" action="">
        <!-- Configurações Gerais -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-sliders-h"></i>
                <h3>Configurações Gerais</h3>
            </div>
            <div class="config-body">
                <div class="switch-group">
                    <div class="switch-label">
                        Obrigar Reconhecimento Facial
                        <small>Exigir reconhecimento facial para todos os registros de ponto</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="facial_obrigatorio" value="1" <?php echo $config['facial_obrigatorio'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-percent"></i> Confiança Mínima (%)
                        <span class="range-value" id="confiancaValue"><?php echo $config['confianca_minima']; ?>%</span>
                    </label>
                    <input type="range" name="confianca_minima" min="50" max="99" step="1" value="<?php echo $config['confianca_minima']; ?>" onchange="document.getElementById('confiancaValue').innerHTML = this.value + '%'">
                    <small>Quanto maior o valor, mais rigorosa será a validação facial (Recomendado: 70-80%)</small>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-redo-alt"></i> Tentativas Máximas</label>
                    <input type="number" name="tentativas_maximas" value="<?php echo $config['tentativas_maximas']; ?>" min="1" max="10">
                    <small>Número de tentativas permitidas antes de bloquear o registro</small>
                </div>

                <div class="switch-group">
                    <div class="switch-label">
                        Permitir Fallback Manual
                        <small>Permitir registro manual quando o facial falhar</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="permitir_fallback" value="1" <?php echo $config['permitir_fallback'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Qualidade da Imagem -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-image"></i>
                <h3>Qualidade da Imagem</h3>
            </div>
            <div class="config-body">
                <div class="switch-group">
                    <div class="switch-label">
                        Validar Qualidade da Imagem
                        <small>Verificar iluminação, nitidez e enquadramento</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="qualidade_imagem" value="1" <?php echo $config['qualidade_imagem'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="switch-group">
                    <div class="switch-label">
                        Detectar Expressão Facial
                        <small>Verificar se a pessoa está com expressão neutra</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="detectar_expressao" value="1" <?php echo $config['detectar_expressao'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="switch-group">
                    <div class="switch-label">
                        Validar Pessoa Viva (Liveness)
                        <small>Detectar se é uma pessoa real (não foto ou vídeo)</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="validar_vivo" value="1" <?php echo $config['validar_vivo'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-stopwatch"></i> Tempo Máximo de Captura (segundos)</label>
                    <input type="number" name="tempo_maximo_captura" value="<?php echo $config['tempo_maximo_captura']; ?>" min="10" max="60">
                    <small>Tempo máximo permitido para capturar a foto</small>
                </div>

                <div class="switch-group">
                    <div class="switch-label">
                        Zoom Automático no Rosto
                        <small>Centralizar e dar zoom automático no rosto</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="zoom_automatico" value="1" <?php echo $config['zoom_automatico'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Recomendações -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-lightbulb"></i>
                <h3>Recomendações de Segurança</h3>
            </div>
            <div class="config-body">
                <ul style="margin: 0; padding-left: 20px; color: var(--text-secondary);">
                    <li><strong>Confiança Mínima:</strong> Para maior segurança, utilize 80% ou mais</li>
                    <li><strong>Validação de Pessoa Viva:</strong> Essencial para evitar fraudes com fotos</li>
                    <li><strong>Tentativas Máximas:</strong> 3 tentativas é o ideal para equilibrar segurança e usabilidade</li>
                    <li><strong>Qualidade da Imagem:</strong> Ativar para garantir fotos nítidas e bem iluminadas</li>
                    <li><strong>Permitir Fallback:</strong> Útil para casos de problemas técnicos na câmera</li>
                </ul>
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

<script>
// Range value display
document.querySelector('input[name="confianca_minima"]').addEventListener('input', function() {
    document.getElementById('confiancaValue').innerHTML = this.value + '%';
});
</script>

<?php require_once '../../includes/footer.php'; ?>