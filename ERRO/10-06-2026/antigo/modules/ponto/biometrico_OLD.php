<?php
// modules/ponto/biometrico.php - Registro de Ponto por Biometria
$pageTitle = 'Registro Biométrico';
$activePage = 'ponto';
require_once '../../includes/header.php';
require_once '../../config/database.php';

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
        <form method="POST" action="digital_registrar.php" id="formDigital">
            <button type="submit" class="btn-biometrico btn-digital" id="btnDigital">
                <i class="fas fa-fingerprint"></i> Registrar com Digital
            </button>
        </form>
        <?php endif; ?>
        
        <?php if ($biometrico_tipo == 'facial' || $biometrico_tipo == 'ambos'): ?>
        <button type="button" class="btn-biometrico btn-facial" id="btnFacial">
            <i class="fas fa-face-smile"></i> Registrar com Reconhecimento Facial
        </button>
        <?php endif; ?>
    </div>
</div>

<div id="facialModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 24px; padding: 24px; max-width: 500px; width: 90%;">
        <h3 style="margin-bottom: 16px;">Reconhecimento Facial</h3>
        <div class="video-container">
            <video id="video" autoplay muted></video>
            <canvas id="canvas" style="display: none;"></canvas>
            <div class="face-guide"></div>
            <div class="overlay">
                <span id="facialStatus">Posicione o rosto no círculo</span>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px;">
            <button id="capturarFace" class="btn btn-primary" style="flex: 1;">Capturar e Registrar</button>
            <button id="fecharFacial" class="btn btn-secondary" style="flex: 1;">Cancelar</button>
        </div>
    </div>
</div>

<script>
// Carregar face-api.js
const script = document.createElement('script');
script.src = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js';
script.onload = () => console.log('Face-api.js carregado');
document.head.appendChild(script);

let video = null;
let stream = null;
let modelsLoaded = false;

// Carregar modelos
async function loadModels() {
    const MODEL_URL = '/ponto_empresarial/assets/models';
    await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
    await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
    await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
    modelsLoaded = true;
    console.log('Modelos carregados!');
}

// Iniciar webcam
async function startWebcam() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: true });
        video = document.getElementById('video');
        video.srcObject = stream;
        await video.play();
    } catch (err) {
        console.error('Erro ao acessar webcam:', err);
        document.getElementById('facialStatus').innerHTML = 'Erro ao acessar webcam. Verifique as permissões.';
    }
}

// Parar webcam
function stopWebcam() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
}

// Botão Facial
document.getElementById('btnFacial')?.addEventListener('click', async () => {
    const modal = document.getElementById('facialModal');
    modal.style.display = 'flex';
    
    if (!modelsLoaded) {
        await loadModels();
    }
    
    await startWebcam();
});

// Fechar modal
document.getElementById('fecharFacial')?.addEventListener('click', () => {
    stopWebcam();
    document.getElementById('facialModal').style.display = 'none';
});

// Capturar face e registrar ponto
document.getElementById('capturarFace')?.addEventListener('click', async () => {
    if (!video || !modelsLoaded) return;
    
    const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
        .withFaceLandmarks()
        .withFaceDescriptor();
    
    if (!detection) {
        document.getElementById('facialStatus').innerHTML = '⚠️ Nenhum rosto detectado. Tente novamente.';
        return;
    }
    
    document.getElementById('facialStatus').innerHTML = '<span class="loading"></span> Processando...';
    
    try {
        const response = await fetch('/ponto_empresarial/api/biometrico.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'registrar_ponto_facial',
                descritor: Array.from(detection.descriptor)
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('facialStatus').innerHTML = '✅ Ponto registrado com sucesso!';
            setTimeout(() => {
                stopWebcam();
                document.getElementById('facialModal').style.display = 'none';
                window.location.reload();
            }, 1500);
        } else {
            document.getElementById('facialStatus').innerHTML = '❌ ' + (result.error || 'Erro ao registrar ponto');
        }
    } catch (error) {
        document.getElementById('facialStatus').innerHTML = '❌ Erro na comunicação com o servidor';
    }
});

// Botão Digital
document.getElementById('btnDigital')?.addEventListener('click', async (e) => {
    e.preventDefault();
    
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
            window.location.reload();
        } else {
            alert(result.error || 'Erro ao registrar ponto');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    } catch (error) {
        alert('Erro na comunicação com o servidor');
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>