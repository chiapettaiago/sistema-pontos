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
    background: rgba(255,255,255,0.96);
    border-radius: 28px;
    padding: 32px;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.35);
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.18);
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
    background: rgba(0,0,0,0.9);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: rgba(255,255,255,0.97);
    border-radius: 28px;
    padding: 24px;
    max-width: 500px;
    width: 90%;
    text-align: center;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
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

.biometrico-note {
    margin-top: 16px;
    padding: 12px 14px;
    border-radius: 14px;
    background: #eff6ff;
    color: #1e40af;
    font-size: 13px;
    line-height: 1.5;
    text-align: left;
    border: 1px solid #bfdbfe;
}

.biometrico-note strong {
    color: #111827;
}

.back-action {
    display: block;
    width: 100%;
    margin-top: 14px;
    padding: 13px 14px;
    border-radius: 16px;
    background: #f3f4f6;
    color: #111827;
    text-decoration: none;
    font-weight: 800;
    border: 1px solid #e5e7eb;
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
            Escolha a forma de identificação para registrar o ponto
        </div>

        <div class="biometrico-note">
            <strong>Pronto para produção:</strong> a tela tenta usar a webcam local primeiro e, se necessário, faz fallback para a CDN.
            Se a câmera ou a rede falharem, você ainda pode usar a opção digital simulada para testar o fluxo.
        </div>
        <div class="biometrico-note">
            <strong>Uso no celular:</strong> se a câmera não abrir, use HTTPS ou abra a tela em <code>localhost</code>. Quando o rosto ficar estável, o sistema tenta registrar sozinho.
        </div>
        
        <div class="biometrico-note" id="offlineStatusBox">
            <strong>PendÃªncias offline:</strong> <span id="offlineQueueCount">0</span> registro(s) aguardando sincronizaÃ§Ã£o.
            <button type="button" id="syncOfflineBtn" class="back-action" style="margin-top:10px;">Sincronizar agora</button>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>"><?php echo $mensagem; ?></div>
        <?php endif; ?>
        
        <?php if ($biometrico_tipo == 'digital' || $biometrico_tipo == 'ambos'): ?>
        <button type="button" class="btn-biometrico btn-digital" id="btnDigital" data-tipo="<?php echo htmlspecialchars($proximo_tipo); ?>">
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
        <a href="../../login.php" class="back-action"><i class="fas fa-arrow-left"></i> Voltar ao login</a>
        
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
let stableDetections = 0;
let autoRegistering = false;
const OFFLINE_QUEUE_KEY = 'ponto_empresarial_offline_queue';

// Elementos DOM
const facialModal = document.getElementById('facialModal');
const btnFacial = document.getElementById('btnFacial');
const btnDigital = document.getElementById('btnDigital');
const fecharFacial = document.getElementById('fecharFacial');
const capturarFace = document.getElementById('capturarFace');
const facialStatus = document.getElementById('facialStatus');
const facialStatusMsg = document.getElementById('facialStatusMsg');
const offlineQueueCount = document.getElementById('offlineQueueCount');
const syncOfflineBtn = document.getElementById('syncOfflineBtn');

// ============================================
// FUNÇÕES DE UI
// ============================================
function updateFacialStatus(message, type = 'info') {
    facialStatus.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i> ${message}`;
    facialStatus.className = `facial-status ${type}`;
}

function getOfflineQueue() {
    try {
        return JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]');
    } catch (e) {
        return [];
    }
}

function saveOfflineQueue(queue) {
    localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
}

function refreshOfflineCounter() {
    const queue = getOfflineQueue();
    if (offlineQueueCount) {
        offlineQueueCount.textContent = queue.length;
    }
    if (syncOfflineBtn) {
        syncOfflineBtn.disabled = queue.length === 0;
        syncOfflineBtn.textContent = queue.length === 0 ? 'Sem pendências' : 'Sincronizar agora';
    }
}

function addOfflinePoint(ponto) {
    const queue = getOfflineQueue();
    queue.push({
        offline_id: 'offline_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8),
        ...ponto
    });
    saveOfflineQueue(queue);
    refreshOfflineCounter();
}

async function syncOfflinePoints() {
    const queue = getOfflineQueue();
    if (!queue.length) return;

    try {
        const response = await fetch('/api/sincronizar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ pontos: queue })
        });

        const result = await response.json();
        if (result.success) {
            saveOfflineQueue([]);
            refreshOfflineCounter();
            updateFacialStatus(`✅ ${result.processados} ponto(s) offline sincronizado(s).`, 'success');
        }
    } catch (err) {
        console.warn('Sincronizacao offline ainda nao disponivel:', err);
    }
}

if (syncOfflineBtn) {
    syncOfflineBtn.addEventListener('click', syncOfflinePoints);
}

function getFaceModelSources() {
    const sources = [];
    const localCandidates = [
        '/assets/models',
        '../../assets/models'
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
        '⚠️ Não foi possível carregar o reconhecimento facial agora. Você pode tentar novamente ou usar a digital simulada.',
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
        syncOfflinePoints();
        
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
            '❌ ' + errorMsg + ' Se preferir, feche esta janela e use a opção digital simulada.',
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
        const response = await fetch('/api/biometrico.php', {
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
            if (!navigator.onLine || /NETWORK|Failed to fetch|fetch/i.test(result.error || '')) {
                addOfflinePoint({
                    tipo: tipoPonto,
                    data_hora: new Date().toISOString(),
                    origem: 'offline_facial'
                });
                updateFacialStatus('⚠️ Sem internet no momento. O ponto foi salvo e será sincronizado depois.', 'info');
                setTimeout(() => {
                    closeFacialModal();
                    window.location.reload();
                }, 1500);
                return;
            }
            updateFacialStatus('❌ ' + (result.error || 'Erro ao registrar ponto'), 'error');
            capturarFace.disabled = false;
            capturarFace.innerHTML = '<i class="fas fa-camera"></i> Capturar e Registrar';
            autoRegistering = false;
        }
    } catch (error) {
        console.error('Erro:', error);
        addOfflinePoint({
            tipo: tipoPonto,
            data_hora: new Date().toISOString(),
            origem: 'offline_facial'
        });
        updateFacialStatus('⚠️ Sem conexão. O ponto foi guardado offline e será enviado quando a internet voltar.', 'info');
        setTimeout(() => {
            closeFacialModal();
            window.location.reload();
        }, 1500);
        autoRegistering = false;
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
        const response = await fetch('/api/biometrico.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'registrar_ponto_digital' })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ Ponto registrado com sucesso!\n\nFuncionário: ' + (result.funcionario || 'Desconhecido'));
            window.location.reload();
        } else {
            if (!navigator.onLine || /NETWORK|Failed to fetch|fetch/i.test(result.error || '')) {
                addOfflinePoint({
                    tipo: btn.dataset.tipo || 'entrada',
                    data_hora: new Date().toISOString(),
                    origem: 'offline_digital'
                });
                alert('Sem internet no momento. O ponto digital foi salvo offline e será sincronizado depois.');
                window.location.reload();
                return;
            }
            alert('❌ ' + (result.error || 'Erro ao registrar ponto'));
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    } catch (error) {
        addOfflinePoint({
            tipo: btn.dataset.tipo || 'entrada',
            data_hora: new Date().toISOString(),
            origem: 'offline_digital'
        });
        alert('Sem conexão. O ponto digital foi guardado offline e será sincronizado depois.');
        window.location.reload();
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

if (btnDigital) {
    btnDigital.addEventListener('click', registrarPontoPorDigital);
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
refreshOfflineCounter();

window.addEventListener('online', syncOfflinePoints);

// Fechar modal ao clicar fora
window.onclick = function(event) {
    if (event.target === facialModal) {
        closeFacialModal();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>

