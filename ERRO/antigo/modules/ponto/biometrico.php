<?php
// modules/ponto/biometrico.php - Registro de Ponto por Biometria (CORRIGIDO)
$pageTitle = 'Registro Biométrico';
$activePage = 'ponto';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/config.php';

$database = new Database();
$db = $database->getConnection();

// Buscar configurações
$query = "SELECT valor FROM configuracoes WHERE chave = 'biometrico_tipo'";
$stmt = $db->query($query);
$biometrico_tipo = $stmt->fetch()['valor'] ?? 'ambos';

$mensagem = '';
$tipo_mensagem = '';
?>

<style>
.biometrico-container {
    max-width: 500px;
    margin: 0 auto;
}

.biometrico-card {
    background: var(--bg-primary);
    border-radius: 24px;
    padding: 32px;
    text-align: center;
    border: 1px solid var(--border-color);
}

.biometrico-icon {
    font-size: 80px;
    margin-bottom: 20px;
}

.biometrico-title {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 16px;
}

.biometrico-subtitle {
    color: var(--text-secondary);
    margin-bottom: 32px;
}

.btn-biometrico {
    display: block;
    width: 100%;
    padding: 16px;
    border: none;
    border-radius: 16px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    margin-bottom: 16px;
    transition: all 0.3s;
}

.btn-digital {
    background: #667eea;
    color: white;
}

.btn-facial {
    background: #10b981;
    color: white;
}

.btn-biometrico:hover {
    transform: translateY(-2px);
}

.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid white;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.video-container {
    position: relative;
    width: 100%;
    max-width: 400px;
    margin: 20px auto;
    border-radius: 16px;
    overflow: hidden;
}

video {
    width: 100%;
    border-radius: 16px;
    background: #1f2937;
}

.overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.7));
    padding: 20px;
    text-align: center;
    color: white;
}

.face-guide {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 200px;
    height: 200px;
    border: 2px solid rgba(255,255,255,0.5);
    border-radius: 50%;
    pointer-events: none;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 24px;
    padding: 24px;
    max-width: 500px;
    width: 90%;
    text-align: center;
}

.modal-buttons {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

.modal-buttons button {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
}

.btn-cancelar {
    background: #e5e7eb;
    color: #374151;
}

.btn-confirmar {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.facial-status {
    margin-top: 16px;
    padding: 12px;
    border-radius: 8px;
    font-size: 14px;
    text-align: center;
}

.facial-status.success {
    background: #d1fae5;
    color: #059669;
}

.facial-status.error {
    background: #fee2e2;
    color: #dc2626;
}

.facial-status.info {
    background: #bfdbfe;
    color: #1e40af;
}
</style>

<div class="biometrico-container">
    <div class="biometrico-card">
        <div class="biometrico-icon">
            <i class="fas fa-fingerprint"></i>
        </div>
        <div class="biometrico-title">Registro Biométrico</div>
        <div class="biometrico-subtitle">
            Escolha a forma de identificação para registrar o ponto
        </div>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>"><?php echo $mensagem; ?></div>
        <?php endif; ?>
        
        <?php if ($biometrico_tipo == 'digital' || $biometrico_tipo == 'ambos'): ?>
        <button type="button" class="btn-biometrico btn-digital" id="btnDigital">
            <i class="fas fa-fingerprint"></i> Registrar com Digital
        </button>
        <?php endif; ?>
        
        <?php if ($biometrico_tipo == 'facial' || $biometrico_tipo == 'ambos'): ?>
        <button type="button" class="btn-biometrico btn-facial" id="btnFacial">
            <i class="fas fa-face-smile"></i> Registrar com Reconhecimento Facial
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Facial -->
<div id="facialModal" class="modal">
    <div class="modal-content">
        <h3 style="margin-bottom: 16px;">
            <i class="fas fa-face-smile"></i> Reconhecimento Facial
        </h3>
        
        <div class="video-container">
            <video id="video" autoplay muted playsinline></video>
            <div class="face-guide"></div>
            <div class="overlay">
                <span id="facialStatusMsg">Posicione o rosto no círculo</span>
            </div>
        </div>
        
        <div id="facialStatus" class="facial-status info">
            <i class="fas fa-info-circle"></i> Aguardando detecção...
        </div>
        
        <div class="modal-buttons">
            <button id="capturarFace" class="btn-confirmar">
                <i class="fas fa-camera"></i> Capturar e Registrar
            </button>
            <button id="fecharFacial" class="btn-cancelar">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
// ============================================
// VARIÁVEIS
// ============================================
let video = null;
let stream = null;
let modelsLoaded = false;
let detectionInterval = null;
let currentDetection = null;

// Elementos DOM
const facialModal = document.getElementById('facialModal');
const btnFacial = document.getElementById('btnFacial');
const btnDigital = document.getElementById('btnDigital');
const fecharFacial = document.getElementById('fecharFacial');
const capturarFace = document.getElementById('capturarFace');
const facialStatus = document.getElementById('facialStatus');
const facialStatusMsg = document.getElementById('facialStatusMsg');

// ============================================
// FUNÇÕES DE UI
// ============================================
function updateFacialStatus(message, type = 'info') {
    facialStatus.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i> ${message}`;
    facialStatus.className = `facial-status ${type}`;
}

// ============================================
// CARREGAR MODELOS DO FACE-API
// ============================================
async function loadFaceModels() {
    // Tentar carregar da CDN primeiro (mais confiável)
    const MODEL_URL = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/weights/';
    
    updateFacialStatus('Carregando modelos de reconhecimento facial...', 'info');
    
    try {
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
        
        modelsLoaded = true;
        updateFacialStatus('✅ Modelos carregados! Posicione o rosto no círculo.', 'success');
        return true;
    } catch (err) {
        console.error('Erro ao carregar modelos:', err);
        updateFacialStatus('❌ Erro ao carregar modelos: ' + err.message, 'error');
        return false;
    }
}

// ============================================
// INICIAR WEBCAM
// ============================================
async function startWebcam() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: {
                width: { ideal: 640 },
                height: { ideal: 480 },
                facingMode: 'user'
            }
        });
        
        video = document.getElementById('video');
        video.srcObject = stream;
        
        await new Promise((resolve) => {
            video.onloadedmetadata = () => {
                video.play();
                resolve();
            };
        });
        
        updateFacialStatus('✅ Câmera ativada! Aguardando detecção facial...', 'success');
        startDetection();
        
    } catch (err) {
        let errorMsg = '';
        if (err.name === 'NotAllowedError') {
            errorMsg = 'Permissão negada. Clique no cadeado/ícone de câmera e permita o acesso.';
        } else if (err.name === 'NotFoundError') {
            errorMsg = 'Nenhuma câmera encontrada. Verifique se sua webcam está conectada.';
        } else {
            errorMsg = err.message;
        }
        updateFacialStatus('❌ ' + errorMsg, 'error');
        console.error('Erro ao acessar câmera:', err);
    }
}

// ============================================
// DETECTAR FACE CONTINUAMENTE
// ============================================
function startDetection() {
    if (detectionInterval) clearInterval(detectionInterval);
    
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    
    detectionInterval = setInterval(async () => {
        if (!video || video.paused || video.ended || !modelsLoaded) return;
        
        try {
            const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptor();
            
            if (detection) {
                currentDetection = detection;
                facialStatusMsg.innerHTML = '✅ Rosto detectado! Clique em "Capturar e Registrar"';
                updateFacialStatus('✅ Rosto detectado! Pronto para registrar.', 'success');
            } else {
                currentDetection = null;
                facialStatusMsg.innerHTML = '⚠️ Nenhum rosto detectado. Posicione o rosto no círculo.';
                if (modelsLoaded) {
                    updateFacialStatus('⚠️ Nenhum rosto detectado. Posicione o rosto no círculo.', 'warning');
                }
            }
        } catch (err) {
            console.error('Erro na detecção:', err);
        }
    }, 500);
}

// ============================================
// PARAR WEBCAM
// ============================================
function stopWebcam() {
    if (detectionInterval) {
        clearInterval(detectionInterval);
        detectionInterval = null;
    }
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    if (video) {
        video.srcObject = null;
    }
    currentDetection = null;
}

// ============================================
// REGISTRAR PONTO POR FACE
// ============================================
async function registrarPontoPorFace() {
    if (!currentDetection) {
        updateFacialStatus('⚠️ Nenhum rosto detectado. Posicione o rosto no círculo e tente novamente.', 'warning');
        alert('Nenhum rosto detectado. Posicione o rosto no círculo e tente novamente.');
        return;
    }
    
    const descriptor = Array.from(currentDetection.descriptor);
    
    updateFacialStatus('📤 Enviando dados...', 'info');
    capturarFace.disabled = true;
    capturarFace.innerHTML = '<span class="loading"></span> Processando...';
    
    try {
        const response = await fetch('/ponto_empresarial/api/biometrico.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'registrar_ponto_facial',
                descritor: descriptor
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            updateFacialStatus('✅ ' + result.message, 'success');
            
            // Mostrar mensagem de sucesso
            alert('✅ Ponto registrado com sucesso!\n\nFuncionário: ' + (result.funcionario || 'Desconhecido'));
            
            // Fechar modal após sucesso
            setTimeout(() => {
                closeFacialModal();
                window.location.reload();
            }, 1500);
        } else {
            updateFacialStatus('❌ ' + (result.error || 'Erro ao registrar ponto'), 'error');
            capturarFace.disabled = false;
            capturarFace.innerHTML = '<i class="fas fa-camera"></i> Capturar e Registrar';
        }
    } catch (error) {
        console.error('Erro:', error);
        updateFacialStatus('❌ Erro na comunicação com o servidor', 'error');
        capturarFace.disabled = false;
        capturarFace.innerHTML = '<i class="fas fa-camera"></i> Capturar e Registrar';
    }
}

// ============================================
// REGISTRAR PONTO POR DIGITAL (SIMULADO)
// ============================================
async function registrarPontoPorDigital() {
    if (!confirm('Prepare o leitor biométrico e posicione o dedo para registrar o ponto. Clique OK para continuar.')) {
        return;
    }
    
    const btn = document.getElementById('btnDigital');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="loading"></span> Aguardando digital...';
    btn.disabled = true;
    
    try {
        const response = await fetch('/ponto_empresarial/api/biometrico.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'registrar_ponto_digital' })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ Ponto registrado com sucesso!\n\nFuncionário: ' + (result.funcionario || 'Desconhecido'));
            window.location.reload();
        } else {
            alert('❌ ' + (result.error || 'Erro ao registrar ponto'));
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    } catch (error) {
        alert('❌ Erro na comunicação com o servidor');
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

// ============================================
// FUNÇÕES DO MODAL FACIAL
// ============================================
async function openFacialModal() {
    facialModal.style.display = 'flex';
    await loadFaceModels();
    await startWebcam();
}

function closeFacialModal() {
    stopWebcam();
    facialModal.style.display = 'none';
    currentDetection = null;
    updateFacialStatus('Aguardando detecção...', 'info');
    capturarFace.disabled = false;
    capturarFace.innerHTML = '<i class="fas fa-camera"></i> Capturar e Registrar';
}

// ============================================
// EVENTOS DOS BOTÕES
// ============================================
if (btnFacial) {
    btnFacial.addEventListener('click', openFacialModal);
}

if (btnDigital) {
    btnDigital.addEventListener('click', registrarPontoPorDigital);
}

if (fecharFacial) {
    fecharFacial.addEventListener('click', closeFacialModal);
}

if (capturarFace) {
    capturarFace.addEventListener('click', registrarPontoPorFace);
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    if (event.target === facialModal) {
        closeFacialModal();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>