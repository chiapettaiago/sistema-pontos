<?php
/**
 * CADASTRO FACIAL COMPLETO - COM FACE-API.JS
 * Arquivo: modules/funcionarios/cadastro_facial.php
 */

// Forçar autenticação
require_once __DIR__ . '/../../includes/auth_check.php';
forceAuthentication();

// Verificar ID do funcionário
$funcionario_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($funcionario_id <= 0) {
    $_SESSION['error'] = 'ID do funcionário inválido.';
    header('Location: ' . BASE_URL . '/modules/funcionarios/index.php');
    exit;
}

$funcionarioLogadoId = (int) ($_SESSION['funcionario_id'] ?? 0);
if ($funcionarioLogadoId !== $funcionario_id) {
    requireAdmin();
}

// Carregar configurações
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

// A aplicação pode estar instalada em um subdiretório (ex.: /sistema-pontos).
// Nunca use uma URL iniciada em / aqui, pois ela descartaria esse prefixo.
$urlVisualizacao = rtrim(BASE_URL, '/') . '/modules/funcionarios/visualizar?id=' . $funcionario_id;

if ($funcionario_id <= 0) {
    $_SESSION['error'] = 'ID do funcionário inválido.';
    header('Location: ' . BASE_URL . '/modules/funcionarios/index.php');
    exit;
}

// Buscar dados do funcionário
try {
    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = ?");
    $stmt->execute([$funcionario_id]);
    $funcionario = $stmt->fetch();
    
    if (!$funcionario) {
        $_SESSION['error'] = 'Funcionário não encontrado.';
        header('Location: ' . BASE_URL . '/modules/funcionarios/index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar funcionário: " . $e->getMessage());
    $_SESSION['error'] = 'Erro ao carregar dados do funcionário.';
    header('Location: ' . BASE_URL . '/modules/funcionarios/index.php');
    exit;
}

// Processar POST
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken();
    
    $descritor = $_POST['descritor'] ?? '';
    $imagem_base64 = $_POST['imagem_base64'] ?? '';
    $amostraFacial = json_decode($descritor, true);
    
    if (!is_array($amostraFacial) || count($amostraFacial) !== 128) {
        $erro = 'Nenhum dado facial capturado. Tente novamente.';
    } else {
        try {
            // Criar pasta se não existir
            $upload_dir = __DIR__ . '/../../uploads/facial/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Salvar imagem
            $imagem_nome = null;
            if (!empty($imagem_base64)) {
                $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imagem_base64));
                if ($image_data !== false) {
                    $imagem_nome = 'facial_' . $funcionario_id . '_' . time() . '.jpg';
                    file_put_contents($upload_dir . $imagem_nome, $image_data);
                }
            }
            
            // Verificar e criar colunas se necessário
            $stmt_check = $pdo->query("SHOW COLUMNS FROM funcionarios LIKE 'descritor_facial'");
            if ($stmt_check->rowCount() == 0) {
                $pdo->exec("ALTER TABLE funcionarios ADD COLUMN descritor_facial TEXT NULL");
                $pdo->exec("ALTER TABLE funcionarios ADD COLUMN imagem_facial VARCHAR(255) NULL");
                $pdo->exec("ALTER TABLE funcionarios ADD COLUMN facial_cadastrado TINYINT(1) DEFAULT 0");
            }
            
            // A autenticação facial consulta esta tabela.
            $pdo->exec("CREATE TABLE IF NOT EXISTS biometricos_faciais (
                id INT NOT NULL AUTO_INCREMENT,
                funcionario_id INT NOT NULL,
                descritores LONGTEXT NOT NULL,
                modelo VARCHAR(50) DEFAULT 'face-api.js',
                ativo TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_bf_funcionario (funcionario_id),
                KEY idx_bf_ativo (ativo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->beginTransaction();

            // Salvar no cadastro legado para compatibilidade com telas existentes.
            $sql = "UPDATE funcionarios SET 
                        descritor_facial = :descritor,
                        imagem_facial = :imagem,
                        facial_cadastrado = 1
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':descritor' => $descritor,
                ':imagem' => $imagem_nome,
                ':id' => $funcionario_id
            ]);

            $stmt = $pdo->prepare('DELETE FROM biometricos_faciais WHERE funcionario_id = :id');
            $stmt->execute([':id' => $funcionario_id]);
            $stmt = $pdo->prepare('INSERT INTO biometricos_faciais (funcionario_id, descritores, modelo, ativo) VALUES (:id, :descritores, :modelo, 1)');
            $stmt->execute([
                ':id' => $funcionario_id,
                ':descritores' => json_encode([$amostraFacial], JSON_UNESCAPED_UNICODE),
                ':modelo' => 'face-api.js'
            ]);
            $pdo->commit();
            
            $_SESSION['success'] = '✅ Cadastro facial realizado com sucesso!';
            header('Location: ' . $urlVisualizacao);
            exit;
            
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erro ao salvar facial: " . $e->getMessage());
            $erro = 'Erro ao salvar no banco de dados: ' . $e->getMessage();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erro geral: " . $e->getMessage());
            $erro = 'Erro inesperado: ' . $e->getMessage();
        }
    }
}

// Carregar header
require_once __DIR__ . '/../../includes/header.php';
?>

<script id="faceApiScript" defer src="<?php echo htmlspecialchars(rtrim(BASE_URL, '/')); ?>/assets/js/face-api.min.js"></script>
    
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', sans-serif;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 20px;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 16px 16px 0 0;
            margin: -30px -30px 20px -30px;
        }
        
        .video-container {
            position: relative;
            display: inline-block;
            width: 100%;
            max-width: 500px;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
            margin: 0 auto;
        }
        
        video, canvas {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 12px;
        }
        
        #overlay {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            color: white;
            background: rgba(0,0,0,0.6);
            padding: 12px;
            font-size: 14px;
            backdrop-filter: blur(4px);
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-success {
            background: #22c55e;
            color: white;
        }
        
        .btn-success:hover {
            background: #16a34a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(34,197,94,0.4);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102,126,234,0.4);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-ok {
            background: #d1fae5;
            color: #059669;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }
        
        .preview-captured {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #22c55e;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .status-info {
            background: #dbeafe;
            color: #2563eb;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 640px) {
            .card {
                padding: 15px;
            }
            .btn {
                padding: 10px 20px;
                font-size: 13px;
            }
            .btn-group {
                flex-direction: column;
            }
            .btn-group .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2 style="margin: 0; font-size: 20px;">
                    <i class="fas fa-user-face"></i> Cadastro Facial
                </h2>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">
                    <?= htmlspecialchars($funcionario['nome']) ?> 
                    (ID: <?= $funcionario['id'] ?>)
                </p>
            </div>
            
            <div class="card-body">
                <!-- Status do cadastro -->
                <div class="status-info">
                    <strong>Status:</strong>
                    <span class="status-badge <?= !empty($funcionario['facial_cadastrado']) ? 'status-ok' : 'status-pending' ?>">
                        <?= !empty($funcionario['facial_cadastrado']) ? '✅ Cadastrado' : '⏳ Pendente' ?>
                    </span>
                </div>
                
                <!-- Mensagens -->
                <?php if ($erro): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Erro:</strong> <?= htmlspecialchars($erro) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($sucesso): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($sucesso) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Vídeo -->
                <div style="text-align: center;">
                    <div class="video-container" id="videoContainer">
                        <video id="video" autoplay muted playsinline></video>
                        <canvas id="canvas" style="display: none;"></canvas>
                        <div id="overlay">
                            <span id="statusText">📷 Aguardando...</span>
                        </div>
                        <div id="previewIndicator" style="display: none;" class="preview-captured">
                            ✅ Capturado
                        </div>
                    </div>
                </div>
                
                <!-- Formulário -->
                <form method="POST" id="facialForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="descritor" id="descritor">
                    <input type="hidden" name="imagem_base64" id="imagem_base64">
                    
                    <div class="btn-group">
                        <button type="button" id="btnCapturar" class="btn btn-success">
                            <i class="fas fa-camera"></i> Capturar
                        </button>
                        <button type="button" id="btnCancelar" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                    
                    <div style="text-align: center; margin-top: 15px;">
                        <button type="submit" id="btnSalvar" class="btn btn-primary" disabled>
                            <i class="fas fa-save"></i> Salvar Cadastro
                        </button>
                    </div>
                </form>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="<?= htmlspecialchars($urlVisualizacao, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary" style="background: #6b7280;">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // ========================================
        //  CONFIGURAÇÕES DO FACE-API
        // ========================================
        const MODEL_PATH = <?= json_encode(rtrim(BASE_URL, '/') . '/assets/models') ?>;
        const VIDEO_WIDTH = 500;
        const VIDEO_HEIGHT = 375;
        
        // Elementos DOM
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const statusText = document.getElementById('statusText');
        const btnCapturar = document.getElementById('btnCapturar');
        const btnCancelar = document.getElementById('btnCancelar');
        const btnSalvar = document.getElementById('btnSalvar');
        const inputDescritor = document.getElementById('descritor');
        const inputImagem = document.getElementById('imagem_base64');
        const previewIndicator = document.getElementById('previewIndicator');
        const videoContainer = document.getElementById('videoContainer');
        
        let stream = null;
        let capturaRealizada = false;
        let descritorAtual = null;
        let imgBase64 = null;
        let faceDetector = null;
        
        // ========================================
        //  INICIALIZAR FACE-API
        // ========================================
        async function carregarFaceAPI() {
            try {
                statusText.textContent = '⏳ Carregando modelos...';
                
                await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_PATH);
                await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_PATH);
                await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_PATH);
                
                statusText.textContent = '✅ Modelos carregados! Iniciando câmera...';
                await iniciarCamera();
                
            } catch (err) {
                console.error('Erro ao carregar Face API:', err);
                statusText.textContent = '❌ Erro ao carregar modelos. Verifique a pasta /public/models/';
                btnCapturar.disabled = true;
            }
        }
        
        // ========================================
        //  INICIAR CÂMERA
        // ========================================
        async function iniciarCamera() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        width: VIDEO_WIDTH,
                        height: VIDEO_HEIGHT,
                        facingMode: 'user'
                    },
                    audio: false
                });
                
                video.srcObject = stream;
                await video.play();
                
                statusText.textContent = '✅ Câmera ativa. Posicione o rosto no quadro.';
                btnCapturar.disabled = false;
                
            } catch (err) {
                console.error('Erro ao acessar câmera:', err);
                statusText.textContent = '❌ Erro ao acessar a câmera. Permita o acesso no navegador.';
                btnCapturar.disabled = true;
            }
        }
        
        // ========================================
        //  DETECTAR FACE E GERAR DESCRITOR
        // ========================================
        async function detectarFace() {
            try {
                statusText.textContent = '⏳ Detectando rosto...';
                
                // Configurar canvas
                canvas.width = video.videoWidth || VIDEO_WIDTH;
                canvas.height = video.videoHeight || VIDEO_HEIGHT;
                const ctx = canvas.getContext('2d');
                
                // Detectar face
                const detections = await faceapi.detectSingleFace(video, 
                    new faceapi.TinyFaceDetectorOptions({
                        inputSize: 224,
                        scoreThreshold: 0.5
                    }))
                    .withFaceLandmarks()
                    .withFaceDescriptor();
                
                if (!detections) {
                    statusText.textContent = '❌ Nenhuma face detectada. Tente novamente.';
                    return null;
                }
                
                // Desenhar no canvas
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                
                // Desenhar landmarks
                const drawOptions = {
                    drawLandmarks: true,
                    drawBoundingBox: true,
                    boxColor: '#22c55e'
                };
                
                const displaySize = { width: canvas.width, height: canvas.height };
                const resizedDetections = faceapi.resizeResults(detections, displaySize);
                
                faceapi.draw.drawDetections(canvas, resizedDetections);
                faceapi.draw.drawFaceLandmarks(canvas, resizedDetections);
                
                // Gerar descritor
                const descriptor = Array.from(detections.descriptor);
                const jsonDescritor = JSON.stringify(descriptor);
                
                // Gerar imagem base64
                const imgData = canvas.toDataURL('image/jpeg', 0.9);
                
                statusText.textContent = '✅ Face detectada com sucesso!';
                
                return {
                    descriptor: jsonDescritor,
                    image: imgData,
                    landmarks: detections.landmarks
                };
                
            } catch (err) {
                console.error('Erro na detecção:', err);
                statusText.textContent = '❌ Erro na detecção: ' + err.message;
                return null;
            }
        }
        
        // ========================================
        //  CAPTURAR FACE
        // ========================================
        btnCapturar.addEventListener('click', async function() {
            if (!stream) {
                alert('Câmera não está ativa. Aguarde o carregamento.');
                return;
            }
            
            const result = await detectarFace();
            
            if (result) {
                capturaRealizada = true;
                descritorAtual = result.descriptor;
                imgBase64 = result.image;
                
                inputDescritor.value = result.descriptor;
                inputImagem.value = result.image;
                
                btnSalvar.disabled = false;
                previewIndicator.style.display = 'block';
                
                statusText.textContent = '✅ Captura realizada! Clique em "Salvar Cadastro".';
                btnCapturar.textContent = '📸 Recapturar';
                
                // Mostrar preview
                const previewImg = document.createElement('img');
                previewImg.src = result.image;
                previewImg.style.cssText = `
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    border-radius: 12px;
                    object-fit: cover;
                    z-index: 2;
                `;
                previewImg.id = 'previewOverlay';
                
                const oldPreview = document.getElementById('previewOverlay');
                if (oldPreview) oldPreview.remove();
                
                videoContainer.appendChild(previewImg);
                video.style.opacity = '0.3';
                
            } else {
                statusText.textContent = '❌ Tente novamente. Posicione o rosto bem iluminado.';
            }
        });
        
        // ========================================
        //  CANCELAR
        // ========================================
        btnCancelar.addEventListener('click', function() {
            window.location.href = <?= json_encode($urlVisualizacao) ?>;
        });
        
        // ========================================
        //  VALIDAR FORMULÁRIO
        // ========================================
        document.getElementById('facialForm').addEventListener('submit', function(e) {
            if (!capturaRealizada || !inputDescritor.value) {
                e.preventDefault();
                alert('❌ Capture uma imagem facial primeiro!');
                return false;
            }
            return true;
        });
        
        // ========================================
        //  INICIAR
        // ========================================
        let inicializacaoIniciada = false;

        async function iniciarCadastroFacial() {
            if (inicializacaoIniciada) return;

            // Em documentos legados o DOMContentLoaded pode já ter ocorrido
            // quando este script é alcançado. Aguarde explicitamente o
            // carregamento do FaceAPI em vez de deixar a tela em "Aguardando".
            if (typeof faceapi === 'undefined') {
                statusText.textContent = '⏳ Carregando reconhecimento facial...';
                const scriptFaceApi = document.getElementById('faceApiScript');
                if (scriptFaceApi && !scriptFaceApi.dataset.listenerAttached) {
                    scriptFaceApi.dataset.listenerAttached = 'true';
                    scriptFaceApi.addEventListener('load', iniciarCadastroFacial, { once: true });
                    scriptFaceApi.addEventListener('error', function() {
                        statusText.textContent = '❌ Não foi possível carregar o reconhecimento facial.';
                        btnCapturar.disabled = true;
                    }, { once: true });
                }
                return;
            }

            inicializacaoIniciada = true;
            await carregarFaceAPI();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', iniciarCadastroFacial, { once: true });
        } else {
            iniciarCadastroFacial();
        }
        
        // ========================================
        //  CLEANUP
        // ========================================
        window.addEventListener('beforeunload', function() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        });
    </script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
