<?php
// modules/biometrico/facial.php - Cadastro Facial (ADAPTADO PARA LOCAL E PRODUÇÃO)
$pageTitle = 'Cadastro Facial';
$activePage = 'biometrico';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/config.php';

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

// Buscar número de amostras
$query = "SELECT valor FROM configuracoes WHERE chave = 'biometrico_amostras_faciais'";
$stmt = $db->query($query);
$amostras_necessarias = $stmt->fetch()['valor'] ?? 3;
?>

<style>
.facial-container {
    max-width: 700px;
    margin: 0 auto;
}

.video-card, .progress-card {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
}

.video-container {
    position: relative;
    width: 100%;
    max-width: 500px;
    margin: 0 auto;
    border-radius: 16px;
    overflow: hidden;
    background: #1f2937;
}

video {
    width: 100%;
    display: block;
    transform: scaleX(-1);
}

canvas {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

.face-guide {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 200px;
    height: 200px;
    border: 2px solid rgba(255, 255, 255, 0.5);
    border-radius: 50%;
    pointer-events: none;
}

.progress-bar-container {
    background: var(--bg-secondary);
    border-radius: 10px;
    height: 10px;
    margin: 20px 0;
    overflow: hidden;
}

.progress-bar {
    background: linear-gradient(135deg, #667eea, #764ba2);
    height: 100%;
    width: 0%;
    transition: width 0.3s;
    border-radius: 10px;
}

.status-text {
    margin: 15px 0;
    font-size: 14px;
    text-align: center;
    padding: 10px;
    border-radius: 8px;
}

.status-text.success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-text.error {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.status-text.warning {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.samples-list {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.sample-dot {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: bold;
    transition: all 0.3s;
}

.sample-dot.captured {
    background: #10b981;
    color: white;
}

.sample-dot.current {
    border: 2px solid #667eea;
    transform: scale(1.1);
}

.loading {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #667eea;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
    margin-right: 8px;
    vertical-align: middle;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.ambiente-info {
    background: var(--bg-secondary);
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 11px;
    text-align: center;
    margin-bottom: 16px;
    color: var(--text-secondary);
}
</style>

<div class="facial-container">
    <div class="video-card">
        <h3 style="margin-bottom: 16px;">
            <i class="fas fa-face-smile"></i> Cadastro Facial
        </h3>
        
        <!-- Informação do Ambiente -->
        <div class="ambiente-info" id="ambienteInfo">
            <i class="fas fa-info-circle"></i> 
            Carregando configurações...
        </div>
        
        <p><strong>Funcionário:</strong> <?php echo htmlspecialchars($funcionario['nome']); ?> (<?php echo htmlspecialchars($funcionario['matricula']); ?>)</p>
        <p><strong>Amostras necessárias:</strong> <?php echo $amostras_necessarias; ?></p>
        
        <div class="video-container">
            <video id="video" autoplay muted playsinline></video>
            <canvas id="canvas"></canvas>
            <div class="face-guide"></div>
        </div>
    </div>
    
    <div class="progress-card">
        <div class="progress-bar-container">
            <div class="progress-bar" id="progressBar"></div>
        </div>
        
        <div class="samples-list" id="samplesList"></div>
        
        <div class="status-text" id="statusText">
            <span class="loading"></span> Aguardando detecção facial...
        </div>
        
        <div style="display: flex; gap: 12px; margin-top: 20px;">
            <button id="btnCapturar" class="btn btn-primary" style="flex: 1;" disabled>
                <i class="fas fa-camera"></i> Capturar Amostra
            </button>
            <button id="btnCancelar" class="btn btn-secondary" style="flex: 1;">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
        
        <div id="resultado" style="margin-top: 20px; display: none;"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script src="/ponto_empresarial/assets/js/face-config.js"></script>
<script>
// ============================================
// VARIÁVEIS GLOBAIS
// ============================================
let video = document.getElementById('video');
let canvas = document.getElementById('canvas');
let ctx = canvas.getContext('2d');
let stream = null;
let descriptors = [];
let samplesCaptured = 0;
const totalSamples = <?php echo $amostras_necessarias; ?>;
let modelsLoaded = false;
let currentFuncionarioId = <?php echo $funcionario_id; ?>;
let detectionInterval = null;

// Elementos da UI
const ambienteInfo = document.getElementById('ambienteInfo');
const statusTextDiv = document.getElementById('statusText');

// ============================================
// ATUALIZAR INFORMAÇÕES DO AMBIENTE
// ============================================
function updateAmbienteInfo() {
    const isLocal = window.FaceConfig.isLocalhost;
    const modelPath = window.FaceConfig.getModelPath();
    
    ambienteInfo.innerHTML = `
        <i class="fas fa-${isLocal ? 'home' : 'cloud'}"></i> 
        Ambiente: <strong>${isLocal ? 'LOCAL (Desenvolvimento)' : 'PRODUÇÃO (Servidor)'}</strong><br>
        <small>Modelos: ${isLocal ? 'Arquivos locais' : 'CDN (Content Delivery Network)'}</small>
    `;
}

// ============================================
// FUNÇÕES DE UI
// ============================================
function createSamplesDots() {
    const container = document.getElementById('samplesList');
    container.innerHTML = '';
    for (let i = 0; i < totalSamples; i++) {
        const dot = document.createElement('div');
        dot.className = 'sample-dot';
        dot.textContent = i + 1;
        dot.id = `sample_${i}`;
        container.appendChild(dot);
    }
}

function updateSamplesUI() {
    for (let i = 0; i < samplesCaptured; i++) {
        const dot = document.getElementById(`sample_${i}`);
        if (dot) dot.classList.add('captured');
    }
    for (let i = samplesCaptured; i < totalSamples; i++) {
        const dot = document.getElementById(`sample_${i}`);
        if (dot) dot.classList.remove('captured');
    }
    
    if (samplesCaptured < totalSamples) {
        const currentDot = document.getElementById(`sample_${samplesCaptured}`);
        if (currentDot) currentDot.classList.add('current');
    }
    
    const progress = (samplesCaptured / totalSamples) * 100;
    document.getElementById('progressBar').style.width = progress + '%';
    updateStatusText(`Amostras capturadas: ${samplesCaptured} de ${totalSamples}`, '');
}

function updateStatusText(msg, type = '') {
    statusTextDiv.innerHTML = msg;
    statusTextDiv.className = 'status-text';
    if (type) {
        statusTextDiv.classList.add(type);
    }
}

// ============================================
// CARREGAR MODELOS (USANDO CONFIGURAÇÕES)
// ============================================
async function loadModels() {
    updateStatusText('<span class="loading"></span> Carregando modelos de reconhecimento facial...', '');
    
    try {
        const result = await window.FaceConfig.loadModels();
        
        if (result.success) {
            modelsLoaded = true;
            updateStatusText(`✅ Modelos carregados! (Fonte: ${result.source})`, 'success');
            document.getElementById('btnCapturar').disabled = false;
            return true;
        } else {
            throw new Error('Falha ao carregar modelos');
        }
    } catch (err) {
        console.error('Erro ao carregar modelos:', err);
        updateStatusText(`❌ Erro ao carregar modelos: ${err.message}. Verifique sua conexão com a internet.`, 'error');
        return false;
    }
}

// ============================================
// FUNÇÕES DA CÂMERA
// ============================================
async function checkCamera() {
    const result = await window.FaceConfig.checkCamera();
    
    if (!result.available) {
        updateStatusText(`❌ ${result.error}`, 'error');
        return false;
    }
    return true;
}

async function startWebcam() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                width: { ideal: 640 },
                height: { ideal: 480 },
                facingMode: 'user'
            } 
        });
        video.srcObject = stream;
        
        await new Promise((resolve) => {
            video.onloadedmetadata = () => {
                video.play();
                resolve();
            };
        });
        
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        updateStatusText('✅ Câmera ativada! Posicione o rosto no círculo.', 'success');
        startDetection();
        
    } catch (err) {
        let errorMsg = '';
        if (err.name === 'NotAllowedError') {
            errorMsg = '❌ Permissão negada. Clique no cadeado/ícone de câmera na barra de endereço e permita o acesso.';
        } else if (err.name === 'NotFoundError') {
            errorMsg = '❌ Nenhuma câmera encontrada. Verifique se sua webcam está conectada.';
        } else {
            errorMsg = `❌ Erro ao acessar câmera: ${err.message}`;
        }
        updateStatusText(errorMsg, 'error');
    }
}

// ============================================
// DETECÇÃO DE ROSTO
// ============================================
async function startDetection() {
    if (detectionInterval) clearInterval(detectionInterval);
    
    detectionInterval = setInterval(async () => {
        if (!video || video.paused || video.ended || !modelsLoaded) return;
        
        try {
            const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptor();
            
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            if (detection) {
                const box = detection.detection.box;
                ctx.strokeStyle = '#10b981';
                ctx.lineWidth = 2;
                ctx.strokeRect(box.x, box.y, box.width, box.height);
                faceapi.draw.drawFaceLandmarks(canvas, detection);
                
                window.currentDetection = detection;
                
                if (samplesCaptured < totalSamples) {
                    updateStatusText('✅ Rosto detectado! Clique em "Capturar Amostra".', 'success');
                }
            } else {
                window.currentDetection = null;
                if (samplesCaptured < totalSamples) {
                    updateStatusText('⚠️ Nenhum rosto detectado. Posicione o rosto no círculo.', 'warning');
                }
            }
        } catch (err) {
            console.error('Erro na detecção:', err);
        }
    }, 500);
}

// ============================================
// CAPTURAR E SALVAR
// ============================================
document.getElementById('btnCapturar').addEventListener('click', async () => {
    if (!window.currentDetection) {
        alert('Nenhum rosto detectado. Posicione o rosto no círculo verde e tente novamente.');
        return;
    }
    
    const descriptor = Array.from(window.currentDetection.descriptor);
    descriptors.push(descriptor);
    samplesCaptured++;
    
    updateSamplesUI();
    updateStatusText(`✅ Amostra ${samplesCaptured} capturada!`, 'success');
    
    if (samplesCaptured >= totalSamples) {
        await finalizarCadastro();
    } else {
        setTimeout(() => {
            updateStatusText('✅ Pronto para a próxima amostra!', 'success');
        }, 1000);
    }
});

async function finalizarCadastro() {
    document.getElementById('btnCapturar').disabled = true;
    updateStatusText('<span class="loading"></span> Enviando dados para o servidor...', '');
    
    // Obter URL base dinamicamente
    const baseUrl = window.FaceConfig.getBaseUrl();
    
    try {
        const response = await fetch(baseUrl + '/api/biometrico.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'salvar_facial',
                funcionario_id: currentFuncionarioId,
                descritores: descriptors
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('resultado').innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> ${result.message || 'Cadastro realizado com sucesso!'}
                </div>`;
            document.getElementById('resultado').style.display = 'block';
            updateStatusText('✅ Cadastro concluído! Redirecionando...', 'success');
            
            setTimeout(() => {
                if (stream) stream.getTracks().forEach(track => track.stop());
                window.location.href = 'cadastrar.php';
            }, 2000);
        } else {
            throw new Error(result.error || 'Erro desconhecido');
        }
    } catch (err) {
        document.getElementById('resultado').innerHTML = `
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> Erro: ${err.message}
            </div>`;
        document.getElementById('resultado').style.display = 'block';
        updateStatusText(`❌ ${err.message}`, 'error');
        document.getElementById('btnCapturar').disabled = false;
    }
}

// ============================================
// CANCELAR
// ============================================
document.getElementById('btnCancelar').addEventListener('click', () => {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
    if (detectionInterval) clearInterval(detectionInterval);
    window.location.href = 'cadastrar.php';
});

// ============================================
// INICIALIZAÇÃO
// ============================================
async function init() {
    // Atualizar informações do ambiente
    updateAmbienteInfo();
    
    createSamplesDots();
    updateSamplesUI();
    
    const cameraOk = await checkCamera();
    if (!cameraOk) {
        document.getElementById('btnCapturar').disabled = true;
        return;
    }
    
    const modelsOk = await loadModels();
    if (!modelsOk) {
        document.getElementById('btnCapturar').disabled = true;
        return;
    }
    
    await startWebcam();
}

init();
</script>

<?php require_once '../../includes/footer.php'; ?>