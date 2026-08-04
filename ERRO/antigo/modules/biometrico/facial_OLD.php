<?php
// modules/biometrico/facial.php - Cadastro Facial
$pageTitle = 'Cadastro Facial';
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

// Buscar número de amostras
$query = "SELECT valor FROM configuracoes WHERE chave = 'biometrico_amostras_faciais'";
$stmt = $db->query($query);
$amostras_necessarias = $stmt->fetch()['valor'] ?? 5;
?>

<style>
.facial-container {
    max-width: 600px;
    margin: 0 auto;
}

.video-card {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
}

.video-container {
    position: relative;
    width: 100%;
    max-width: 480px;
    margin: 0 auto;
    border-radius: 16px;
    overflow: hidden;
    background: #1f2937;
}

video {
    width: 100%;
    display: block;
}

canvas {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
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

.progress-card {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 24px;
    border: 1px solid var(--border-color);
    text-align: center;
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
    margin: 10px 0;
    font-size: 14px;
}

.samples-list {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.sample-dot {
    width: 40px;
    height: 40px;
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
</style>

<div class="facial-container">
    <div class="video-card">
        <h3 style="margin-bottom: 16px;">
            <i class="fas fa-face-smile"></i> Cadastro Facial
        </h3>
        <p><strong>Funcionário:</strong> <?php echo htmlspecialchars($funcionario['nome']); ?> (<?php echo htmlspecialchars($funcionario['matricula']); ?>)</p>
        
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
            Aguardando detecção facial...
        </div>
        
        <div style="display: flex; gap: 12px; margin-top: 20px;">
            <button id="btnCapturar" class="btn btn-primary" style="flex: 1;" disabled>
                <i class="fas fa-camera"></i> Capturar Amostra
            </button>
            <button id="btnCancelar" class="btn btn-secondary" style="flex: 1;">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
        
        <div id="resultado" style="margin-top: 20px; display: none;">
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Cadastro realizado com sucesso!
            </div>
        </div>
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

// Atualizar dots
function updateSamplesUI() {
    for (let i = 0; i < samplesCaptured; i++) {
        const dot = document.getElementById(`sample_${i}`);
        if (dot) dot.classList.add('captured');
    }
    for (let i = samplesCaptured; i < totalSamples; i++) {
        const dot = document.getElementById(`sample_${i}`);
        if (dot) dot.classList.remove('captured');
    }
    
    // Marcar atual
    if (samplesCaptured < totalSamples) {
        const currentDot = document.getElementById(`sample_${samplesCaptured}`);
        if (currentDot) currentDot.classList.add('current');
    }
    
    // Atualizar barra de progresso
    const progress = (samplesCaptured / totalSamples) * 100;
    document.getElementById('progressBar').style.width = progress + '%';
    document.getElementById('statusText').innerHTML = `Amostras capturadas: ${samplesCaptured} de ${totalSamples}`;
}

// Carregar modelos
async function loadModels() {
    const MODEL_URL = '/ponto_empresarial/assets/models';
    try {
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
        modelsLoaded = true;
        document.getElementById('statusText').innerHTML = '✅ Modelos carregados. Posicione o rosto no círculo.';
        document.getElementById('btnCapturar').disabled = false;
    } catch (err) {
        console.error('Erro ao carregar modelos:', err);
        document.getElementById('statusText').innerHTML = '❌ Erro ao carregar modelos. Verifique a pasta assets/models.';
    }
}

// Iniciar webcam
async function startWebcam() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: true });
        video.srcObject = stream;
        await video.play();
        
        // Configurar canvas para o tamanho do vídeo
        video.addEventListener('play', () => {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
        });
        
        // Iniciar detecção contínua
        detectFaces();
    } catch (err) {
        console.error('Erro ao acessar webcam:', err);
        document.getElementById('statusText').innerHTML = '❌ Erro ao acessar webcam. Verifique as permissões.';
    }
}

// Detectar faces continuamente
async function detectFaces() {
    if (!video || video.paused || video.ended) {
        requestAnimationFrame(detectFaces);
        return;
    }
    
    if (!modelsLoaded) {
        requestAnimationFrame(detectFaces);
        return;
    }
    
    const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
        .withFaceLandmarks()
        .withFaceDescriptor();
    
    // Desenhar no canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    if (detection) {
        // Desenhar bounding box
        const box = detection.detection.box;
        ctx.strokeStyle = '#10b981';
        ctx.lineWidth = 2;
        ctx.strokeRect(box.x, box.y, box.width, box.height);
        
        // Desenhar landmarks
        faceapi.draw.drawFaceLandmarks(canvas, detection);
        
        window.currentDetection = detection;
        document.getElementById('statusText').innerHTML = '✅ Rosto detectado. Clique em Capturar Amostra.';
    } else {
        window.currentDetection = null;
        document.getElementById('statusText').innerHTML = '⚠️ Nenhum rosto detectado. Posicione o rosto no círculo.';
    }
    
    requestAnimationFrame(detectFaces);
}

// Capturar amostra
document.getElementById('btnCapturar').addEventListener('click', async () => {
    if (!window.currentDetection) {
        alert('Nenhum rosto detectado. Posicione o rosto no círculo.');
        return;
    }
    
    const descriptor = Array.from(window.currentDetection.descriptor);
    descriptors.push(descriptor);
    samplesCaptured++;
    
    updateSamplesUI();
    
    if (samplesCaptured >= totalSamples) {
        // Finalizar cadastro
        await finalizarCadastro();
    } else {
        document.getElementById('statusText').innerHTML = `✅ Amostra ${samplesCaptured} capturada! Continue para a próxima.`;
    }
});

// Finalizar cadastro
async function finalizarCadastro() {
    document.getElementById('btnCapturar').disabled = true;
    document.getElementById('statusText').innerHTML = '📤 Enviando dados...';
    
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
            document.getElementById('resultado').style.display = 'block';
            document.getElementById('btnCapturar').disabled = true;
            document.getElementById('statusText').innerHTML = '✅ Cadastro concluído com sucesso!';
            
            setTimeout(() => {
                window.location.href = 'cadastrar.php';
            }, 2000);
        } else {
            alert('Erro ao salvar: ' + (result.error || 'Erro desconhecido'));
            document.getElementById('btnCapturar').disabled = false;
        }
    } catch (err) {
        alert('Erro na comunicação com o servidor');
        document.getElementById('btnCapturar').disabled = false;
    }
}

// Cancelar
document.getElementById('btnCancelar').addEventListener('click', () => {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
    window.location.href = 'cadastrar.php';
});

// Inicializar
createSamplesDots();
updateSamplesUI();
loadModels();
startWebcam();
</script>

<?php require_once '../../includes/footer.php'; ?>