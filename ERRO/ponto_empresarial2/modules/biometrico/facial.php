<?php
// modules/biometrico/facial.php - Cadastro Facial (COMPLETO)
$pageTitle = 'Cadastro Facial';
$activePage = 'biometrico_cadastro';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

checkModuleAccess('funcionarios');

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

$amostras_necessarias = 5;
?>

<style>
.facial-container {
    max-width: 700px;
    margin: 0 auto;
}

.video-card, .progress-card {
    background: rgba(255,255,255,0.96);
    border-radius: 24px;
    padding: 24px;
    margin-bottom: 24px;
    border: 1px solid rgba(255,255,255,0.35);
    box-shadow: 0 20px 60px rgba(15, 23, 42, 0.16);
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

.guide-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    border-radius: 16px;
    padding: 14px 16px;
    margin: 16px 0 4px;
    font-size: 13px;
    line-height: 1.5;
    text-align: left;
}

.guide-box strong {
    color: #111827;
}

.stepper {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin: 16px 0 6px;
}

.step-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 12px 10px;
    font-size: 12px;
    color: #475569;
    text-align: center;
}

.step-item strong {
    display: block;
    color: #111827;
    margin-bottom: 4px;
    font-size: 13px;
}

.action-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
    width: 100%;
    padding: 12px 14px;
    border-radius: 14px;
    background: #f3f4f6;
    color: #111827;
    text-decoration: none;
    font-weight: 700;
    border: 1px solid #e5e7eb;
    margin-top: 10px;
}

@media (max-width: 640px) {
    .video-card, .progress-card {
        padding: 18px;
        border-radius: 18px;
    }

    .samples-list {
        gap: 10px;
    }

    .sample-dot {
        width: 38px;
        height: 38px;
    }
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

.alert {
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 16px;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
}
</style>

<div class="facial-container">
    <div class="video-card">
        <h3 style="margin-bottom: 16px;">
            <i class="fas fa-face-smile"></i> Cadastro Facial
        </h3>
        <p><strong>Funcionário:</strong> <?php echo htmlspecialchars($funcionario['nome']); ?> (<?php echo htmlspecialchars($funcionario['matricula']); ?>)</p>
        <p><strong>Amostras necessárias:</strong> <?php echo $amostras_necessarias; ?></p>
        
        <div class="guide-box">
            <strong>Como salvar corretamente:</strong> fique com o rosto centralizado, capture as 5 amostras e aguarde o envio final.
            Se já existir um cadastro facial anterior, este novo cadastro substitui o antigo para o mesmo funcionário.
        </div>
        <div class="stepper">
            <div class="step-item"><strong>1. Posicione</strong> rosto centralizado e bem iluminado.</div>
            <div class="step-item"><strong>2. Capture</strong> faça as 5 amostras com pequenas variações.</div>
            <div class="step-item"><strong>3. Salve</strong> aguarde o envio e a confirmação final.</div>
        </div>
        
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

        <a href="cadastrar.php" class="action-link">
            <i class="fas fa-arrow-left"></i> Voltar à lista
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
// Variáveis
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

// Criar dots de amostras
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
    const statusDiv = document.getElementById('statusText');
    statusDiv.innerHTML = msg;
    statusDiv.className = 'status-text';
    if (type) {
        statusDiv.classList.add(type);
    }
}

// Carregar modelos
async function loadModels() {
    const MODEL_URL = '../../assets/models';
    updateStatusText('<span class="loading"></span> Carregando modelos de IA...', '');
    
    try {
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
        
        modelsLoaded = true;
        updateStatusText('✅ Modelos carregados! Posicione o rosto no círculo.', 'success');
        document.getElementById('btnCapturar').disabled = false;
        return true;
    } catch (err) {
        console.error('Erro ao carregar modelos:', err);
        updateStatusText(`❌ Erro ao carregar modelos: ${err.message}`, 'error');
        return false;
    }
}

// Verificar câmera
async function checkCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        updateStatusText('❌ Seu navegador não suporta acesso à câmera.', 'error');
        return false;
    }
    
    try {
        const testStream = await navigator.mediaDevices.getUserMedia({ video: true });
        testStream.getTracks().forEach(track => track.stop());
        return true;
    } catch (err) {
        let errorMsg = '';
        if (err.name === 'NotAllowedError') {
            errorMsg = '❌ Permissão negada. Clique no cadeado/ícone de câmera e permita o acesso.';
        } else if (err.name === 'NotFoundError') {
            errorMsg = '❌ Nenhuma câmera encontrada. Verifique se sua webcam está conectada.';
        } else {
            errorMsg = `❌ Erro: ${err.message}`;
        }
        updateStatusText(errorMsg, 'error');
        return false;
    }
}

// Iniciar webcam
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
        updateStatusText(`❌ Erro ao acessar câmera: ${err.message}`, 'error');
    }
}

// Detectar faces
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

// Capturar amostra
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

// Finalizar cadastro
async function finalizarCadastro() {
    document.getElementById('btnCapturar').disabled = true;
    updateStatusText('<span class="loading"></span> Enviando dados...', '');
    
    try {
        const response = await fetch('/ponto_empresarial/api/biometrico.php', {
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

// Cancelar
document.getElementById('btnCancelar').addEventListener('click', () => {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
    if (detectionInterval) clearInterval(detectionInterval);
    window.location.href = 'cadastrar.php';
});

// Inicializar
async function init() {
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
