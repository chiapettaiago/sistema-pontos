<?php
// modules/ponto/biometrico.php - Registro de Ponto por Biometria (CORRIGIDO)
$pageTitle = 'Registro Biométrico';
$activePage = 'ponto';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/config.php';

$database = new Database();
$db = $database->getConnection();

$mensagem = '';
$tipo_mensagem = '';
?>

<style>
.biometrico-container {
    max-width: 500px;
    margin: 0 auto;
    color: var(--text-primary);
}

.biometrico-card {
    background: color-mix(in srgb, var(--bg-primary) 94%, transparent);
    border-radius: 28px;
    padding: 32px;
    text-align: center;
    border: 1px solid var(--border-color);
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.18);
    color: var(--text-primary);
    backdrop-filter: blur(18px);
}

.biometrico-icon {
    font-size: 80px;
    margin-bottom: 20px;
    color: var(--pf-primary);
    filter: drop-shadow(0 10px 22px rgba(99, 102, 241, .22));
}

.biometrico-title {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 16px;
    color: var(--text-primary);
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
    background: linear-gradient(135deg, #0f172a, #667eea 55%, #764ba2);
    color: white;
}

.btn-facial {
    background: linear-gradient(135deg, #0f172a, #10b981 55%, #059669);
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
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid var(--border-color);
    background: #030712;
}

video {
    width: 100%;
    border-radius: 20px;
    background: #111827;
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
    background: rgba(2, 6, 23, 0.82);
    backdrop-filter: blur(8px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--bg-primary);
    border-radius: 28px;
    padding: 24px;
    max-width: 500px;
    width: 90%;
    text-align: center;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
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
    background: var(--bg-tertiary);
    color: var(--text-primary);
    border: 1px solid var(--border-color) !important;
}

.btn-confirmar {
    background: linear-gradient(135deg, #0f172a, #667eea 55%, #764ba2);
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
    background: rgba(16, 185, 129, .14);
    color: #047857;
    border: 1px solid rgba(16, 185, 129, .25);
}

.facial-status.error {
    background: rgba(239, 68, 68, .13);
    color: #b91c1c;
    border: 1px solid rgba(239, 68, 68, .24);
}

.facial-status.info {
    background: rgba(59, 130, 246, .13);
    color: #1d4ed8;
    border: 1px solid rgba(59, 130, 246, .24);
}

.biometrico-note {
    margin-top: 16px;
    padding: 12px 14px;
    border-radius: 14px;
    background: color-mix(in srgb, var(--pf-primary) 9%, var(--bg-secondary));
    color: var(--text-secondary);
    font-size: 13px;
    line-height: 1.5;
    text-align: left;
    border: 1px solid color-mix(in srgb, var(--pf-primary) 25%, var(--border-color));
}

.biometrico-note strong {
    color: var(--text-primary);
}

.biometrico-note code {
    color: var(--pf-primary);
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 1px 5px;
}

.back-action {
    display: block;
    width: 100%;
    margin-top: 14px;
    padding: 13px 14px;
    border-radius: 16px;
    background: var(--bg-tertiary);
    color: var(--text-primary);
    text-decoration: none;
    font-weight: 800;
    border: 1px solid var(--border-color);
    transition: background-color .2s, border-color .2s, transform .2s;
}

.back-action:hover {
    color: var(--text-primary);
    background: color-mix(in srgb, var(--pf-primary) 10%, var(--bg-tertiary));
    border-color: var(--pf-primary);
    transform: translateY(-1px);
}

[data-bs-theme="dark"] .biometrico-card,
[data-bs-theme="dark"] .modal-content {
    background: rgba(17, 24, 39, .96);
    border-color: #334155;
    box-shadow: 0 28px 80px rgba(0, 0, 0, .48);
}

[data-bs-theme="dark"] .facial-status.success {
    color: #6ee7b7;
}

[data-bs-theme="dark"] .facial-status.error {
    color: #fca5a5;
}

[data-bs-theme="dark"] .facial-status.info {
    color: #93c5fd;
}

[data-bs-theme="dark"] .btn-cancelar:hover {
    background: #243147;
}

@media (max-width: 520px) {
    .biometrico-card {
        padding: 22px 18px;
        border-radius: 22px;
    }

    .video-container {
        max-width: 100%;
    }

    .face-guide {
        width: 170px;
        height: 170px;
    }

    video {
        min-height: 240px;
    }
}
</style>

<div class="biometrico-container">
    <div class="biometrico-card">
        <div class="biometrico-icon">
            <i class="fas fa-fingerprint"></i>
        </div>
        <div class="biometrico-title">Registro Biométrico</div>
        <div class="biometrico-subtitle">
            Confirme sua identidade pelo reconhecimento facial para registrar o ponto
        </div>

        <div class="biometrico-note">
            <strong>Registro protegido:</strong> não é permitido registrar o ponto manualmente. A marcação só será gravada após a validação da face cadastrada.
        </div>
        <div class="biometrico-note">
            <strong>Uso no celular:</strong> se a câmera não abrir, use HTTPS ou abra a tela em <code>localhost</code>. Quando o rosto ficar estável, o sistema tenta registrar sozinho.
        </div>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>"><?php echo $mensagem; ?></div>
        <?php endif; ?>
        
        <button type="button" class="btn-biometrico btn-facial" id="btnFacial">
            <i class="fas fa-face-smile"></i> Registrar com Reconhecimento Facial
        </button>
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
        <a href="<?php echo htmlspecialchars(BASE_URL . '/modules/ponto/ponto'); ?>" class="back-action"><i class="fas fa-arrow-left"></i> Voltar</a>
        
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

<script src="<?php echo htmlspecialchars(BASE_URL . '/assets/js/face-api.min.js'); ?>"></script>
<script>
// ============================================
// VARIÁVEIS
// ============================================
let video = null;
let stream = null;
let modelsLoaded = false;
let detectionInterval = null;
let currentDetection = null;
let stableDetections = 0;
let autoRegistering = false;
const appBaseUrl = <?php echo json_encode(BASE_URL, JSON_UNESCAPED_SLASHES); ?>;

// Elementos DOM
const facialModal = document.getElementById('facialModal');
const btnFacial = document.getElementById('btnFacial');
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

function getFaceModelSources() {
    const sources = [];
    const localCandidates = [
        appBaseUrl + '/assets/models'
    ];

    localCandidates.forEach((source) => {
        if (!sources.includes(source)) {
            sources.push(source);
        }
    });

    return sources;
}

// ============================================
// CARREGAR MODELOS DO FACE-API
// ============================================
async function loadFaceModels() {
    updateFacialStatus('Carregando modelos de reconhecimento facial...', 'info');

    const sources = getFaceModelSources();
    let lastError = null;

    for (const source of sources) {
        try {
            await faceapi.nets.tinyFaceDetector.loadFromUri(source);
            await faceapi.nets.faceLandmark68Net.loadFromUri(source);
            await faceapi.nets.faceRecognitionNet.loadFromUri(source);

            modelsLoaded = true;
            updateFacialStatus('✅ Reconhecimento facial pronto. Posicione o rosto no círculo.', 'success');
            return true;
        } catch (err) {
            lastError = err;
            console.warn('Falha ao carregar modelos em ' + source, err);
        }
    }

    updateFacialStatus(
        '⚠️ Não foi possível carregar o reconhecimento facial agora. Tente novamente em instantes.',
        'error'
    );
    console.error('Erro ao carregar modelos:', lastError);
    return false;
}

// ============================================
// INICIAR WEBCAM
// ============================================
async function startWebcam() {
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Seu navegador não permite acesso à câmera.');
        }

        if (!window.isSecureContext && !['localhost', '127.0.0.1', '::1'].includes(window.location.hostname)) {
            throw new Error('No celular, a câmera precisa de HTTPS ou acesso via localhost.');
        }

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
        } else if (err.name === 'NotReadableError') {
            errorMsg = 'A câmera está ocupada por outro aplicativo. Feche outros programas e tente novamente.';
        } else {
            errorMsg = err.message;
        }
        updateFacialStatus(
            '❌ ' + errorMsg,
            'error'
        );
        console.error('Erro ao acessar câmera:', err);
    }
}

// ============================================
// DETECTAR FACE CONTINUAMENTE
// ============================================
function startDetection() {
    if (detectionInterval) clearInterval(detectionInterval);
    stableDetections = 0;
    
    detectionInterval = setInterval(async () => {
        if (!video || video.paused || video.ended || !modelsLoaded || autoRegistering) return;
        
        try {
            const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 }))
                .withFaceLandmarks()
                .withFaceDescriptor();
            
            if (detection) {
                currentDetection = detection;
                stableDetections++;
                facialStatusMsg.innerHTML = '✅ Rosto detectado! Mantenha o rosto no centro.';
                updateFacialStatus('✅ Rosto detectado! Mantendo leitura...', 'success');
                if (stableDetections >= 3) {
                    registrarPontoPorFace(true);
                }
            } else {
                currentDetection = null;
                stableDetections = 0;
                facialStatusMsg.innerHTML = '⚠️ Nenhum rosto detectado. Posicione o rosto no círculo.';
                if (modelsLoaded) {
                    updateFacialStatus('⚠️ Nenhum rosto detectado. Posicione o rosto no círculo.', 'info');
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
async function registrarPontoPorFace(auto = false) {
    if (autoRegistering) return;
    autoRegistering = true;

    if (!currentDetection) {
        updateFacialStatus('⚠️ Nenhum rosto detectado. Posicione o rosto no círculo e tente novamente.', 'warning');
        alert('Nenhum rosto detectado. Posicione o rosto no círculo e tente novamente.');
        autoRegistering = false;
        return;
    }
    
    const descriptor = Array.from(currentDetection.descriptor);
    
    updateFacialStatus(auto ? '📤 Rosto confirmado. Registrando ponto...' : '📤 Enviando dados...', 'info');
    capturarFace.disabled = true;
    capturarFace.innerHTML = '<span class="loading"></span> Processando...';
    
    try {
        const response = await fetch(appBaseUrl + '/api/biometrico.php', {
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
            autoRegistering = false;
        }
    } catch (error) {
        console.error('Erro:', error);
        updateFacialStatus('❌ Não foi possível validar sua face. Verifique a conexão e tente novamente.', 'error');
        capturarFace.disabled = false;
        capturarFace.innerHTML = '<i class="fas fa-camera"></i> Capturar e Registrar';
        autoRegistering = false;
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
    stableDetections = 0;
    autoRegistering = false;
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

if (fecharFacial) {
    fecharFacial.addEventListener('click', closeFacialModal);
}

if (capturarFace) {
    capturarFace.addEventListener('click', registrarPontoPorFace);
}

if (facialStatusMsg) {
    facialStatusMsg.innerHTML = 'Posicione o rosto no círculo';
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    if (event.target === facialModal) {
        closeFacialModal();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
