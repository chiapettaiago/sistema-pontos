<?php
// modules/ponto/registrar.php - Registro de Ponto (APENAS FACIAL)
session_start();

// Verificar se está logado como funcionário
if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Registrar Ponto';
$activePage = 'ponto';

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Pega o ID do funcionário
$funcionario_id = $_SESSION['funcionario_id'] ?? null;

// Se não tiver funcionario_id, tenta buscar
if (!$funcionario_id && isset($_SESSION['usuario_id'])) {
    $stmt = $db->prepare("SELECT id FROM funcionarios WHERE email = :email");
    $stmt->execute([':email' => $_SESSION['usuario_email']]);
    $func = $stmt->fetch();
    if ($func) {
        $funcionario_id = $func['id'];
        $_SESSION['funcionario_id'] = $funcionario_id;
    }
}

if (!$funcionario_id) {
    echo "<div style='text-align: center; padding: 50px;'>
            <h2>Perfil não encontrado</h2>
            <p>Contacte o administrador.</p>
            <a href='../../logout.php'>Sair</a>
          </div>";
    exit;
}

// Buscar dados do funcionário
$stmt = $db->prepare("SELECT f.*, fi.nome_fantasia as filial_nome 
                      FROM funcionarios f
                      LEFT JOIN filiais fi ON f.filial_id = fi.id
                      WHERE f.id = :id");
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    session_destroy();
    header('Location: ../../login.php');
    exit;
}

// Buscar pontos de hoje
$stmt = $db->prepare("SELECT tipo, data_hora FROM pontos 
                      WHERE funcionario_id = :id AND DATE(data_hora) = CURDATE() 
                      ORDER BY data_hora ASC");
$stmt->execute([':id' => $funcionario_id]);
$pontosHoje = $stmt->fetchAll();

// Mapear tipos registrados
$tiposRegistrados = [];
foreach ($pontosHoje as $ponto) {
    $tiposRegistrados[] = $ponto['tipo'];
}

// Sequência correta
$sequencia = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];

// Calcular próximo ponto
$proximo_tipo = 'entrada';
$proximo_texto = 'Registrar Entrada';
$proximo_icone = 'fa-sign-in-alt';

if (in_array('entrada', $tiposRegistrados) && !in_array('saida_almoco', $tiposRegistrados)) {
    $proximo_tipo = 'saida_almoco';
    $proximo_texto = 'Registrar Saída para Almoço';
    $proximo_icone = 'fa-utensils';
} elseif (in_array('saida_almoco', $tiposRegistrados) && !in_array('volta_almoco', $tiposRegistrados)) {
    $proximo_tipo = 'volta_almoco';
    $proximo_texto = 'Registrar Volta do Almoço';
    $proximo_icone = 'fa-undo-alt';
} elseif (in_array('volta_almoco', $tiposRegistrados) && !in_array('saida', $tiposRegistrados)) {
    $proximo_tipo = 'saida';
    $proximo_texto = 'Registrar Saída';
    $proximo_icone = 'fa-sign-out-alt';
} elseif (in_array('entrada', $tiposRegistrados) && 
          in_array('saida_almoco', $tiposRegistrados) && 
          in_array('volta_almoco', $tiposRegistrados) && 
          in_array('saida', $tiposRegistrados)) {
    $proximo_tipo = 'finalizado';
    $proximo_texto = 'Dia Finalizado!';
    $proximo_icone = 'fa-check-circle';
}

// Mapear horários para exibição
$horarios = [
    'entrada' => '--:--',
    'saida_almoco' => '--:--',
    'volta_almoco' => '--:--',
    'saida' => '--:--'
];
foreach ($pontosHoje as $ponto) {
    $horarios[$ponto['tipo']] = date('H:i', strtotime($ponto['data_hora']));
}

// Mensagem de feedback
$mensagem = '';
$tipo_mensagem = '';
if (isset($_SESSION['mensagem_ponto'])) {
    $mensagem = $_SESSION['mensagem_ponto'];
    $tipo_mensagem = $_SESSION['tipo_mensagem_ponto'];
    unset($_SESSION['mensagem_ponto']);
    unset($_SESSION['tipo_mensagem_ponto']);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Bater Ponto - <?php echo htmlspecialchars($funcionario['nome']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { max-width: 550px; margin: 0 auto; }
        
        .card {
            background: white;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            animation: fadeInUp 0.5s ease;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 28px 24px;
            text-align: center;
            color: white;
        }
        
        .card-header h1 { font-size: 26px; margin-bottom: 8px; }
        .card-header p { opacity: 0.9; font-size: 13px; }
        
        .datetime {
            margin-top: 16px;
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .datetime .date, .datetime .time {
            background: rgba(255,255,255,0.2);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 13px;
        }
        
        .datetime .time { font-family: monospace; font-size: 16px; font-weight: 600; }
        
        .card-body { padding: 28px; }
        
        .funcionario-info {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 16px;
            margin-bottom: 28px;
            text-align: center;
        }
        
        .funcionario-info h2 { font-size: 18px; font-weight: 700; color: #333; }
        .funcionario-info .matricula { font-size: 12px; color: #666; }
        .funcionario-info .filial { font-size: 11px; color: #667eea; margin-top: 6px; }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 28px;
        }
        
        .status-item {
            text-align: center;
            padding: 12px 6px;
            border-radius: 16px;
        }
        
        .status-item.completed {
            background: #d1fae5;
            color: #059669;
        }
        
        .status-item.pending {
            background: #f1f5f9;
            color: #94a3b8;
        }
        
        .status-item .icon { font-size: 22px; margin-bottom: 6px; display: block; }
        .status-item .label { font-size: 10px; font-weight: 500; display: block; }
        .status-item .time { font-size: 16px; font-weight: 700; display: block; margin-top: 6px; }
        
        .btn-ponto {
            width: 100%;
            border: none;
            border-radius: 20px;
            padding: 20px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: linear-gradient(135deg, #1e293b, #0f172a);
            color: white;
        }
        
        .btn-ponto i { color: #38bdf8; font-size: 24px; }
        
        .btn-ponto:hover:enabled {
            transform: scale(1.02);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            background: linear-gradient(135deg, #334155, #1e293b);
        }
        
        .btn-ponto:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success { background: #d1fae5; color: #059669; border-left: 4px solid #059669; }
        .alert-error { background: #fee2e2; color: #dc2626; border-left: 4px solid #dc2626; }
        
        .info-footer {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            color: #64748b;
            margin-bottom: 8px;
        }
        
        .info-item i { width: 18px; color: #667eea; }
        
        .quick-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .quick-btn {
            background: #f1f5f9;
            padding: 8px 16px;
            border-radius: 40px;
            font-size: 12px;
            font-weight: 500;
            color: #475569;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        
        .quick-btn:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        
        /* Modal da Câmera */
        .camera-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        
        .camera-container {
            position: relative;
            background: #000;
            border-radius: 24px;
            overflow: hidden;
            max-width: 500px;
            width: 90%;
        }
        
        video {
            width: 100%;
            height: auto;
            display: block;
        }
        
        #faceOverlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 10;
        }
        
        .camera-buttons {
            display: flex;
            gap: 12px;
            padding: 20px;
            background: #1e293b;
        }
        
        .btn-capturar {
            flex: 1;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .btn-fechar {
            flex: 1;
            background: #ef4444;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .instrucao-rosto {
            text-align: center;
            padding: 12px;
            background: rgba(0,0,0,0.7);
            color: white;
            font-size: 13px;
            position: absolute;
            bottom: 80px;
            left: 0;
            right: 0;
            z-index: 20;
            border-radius: 0 0 24px 24px;
        }
        
        .status-detect {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            z-index: 20;
        }
        
        .btn-capturar:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .loading-camera {
            display: none;
            text-align: center;
            padding: 20px;
            color: white;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #fff3;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 12px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 480px) {
            .card-header { padding: 20px; }
            .card-header h1 { font-size: 22px; }
            .card-body { padding: 20px; }
            .btn-ponto { padding: 16px; font-size: 16px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-fingerprint"></i> Bater Ponto</h1>
                <p>Registre sua jornada de trabalho</p>
                <div class="datetime">
                    <div class="date"><i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y'); ?></div>
                    <div class="time" id="relogio"><i class="fas fa-clock"></i> <?php echo date('H:i:s'); ?></div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="funcionario-info">
                    <h2><?php echo htmlspecialchars($funcionario['nome']); ?></h2>
                    <div class="matricula"><i class="fas fa-id-badge"></i> Matrícula: <?php echo htmlspecialchars($funcionario['matricula']); ?></div>
                    <div class="filial"><i class="fas fa-store"></i> Filial: <?php echo htmlspecialchars($funcionario['filial_nome']); ?></div>
                </div>
                
                <?php if ($mensagem): ?>
                <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                    <i class="fas <?php echo $tipo_mensagem == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo $mensagem; ?>
                </div>
                <?php endif; ?>
                
                <div class="status-grid">
                    <div class="status-item <?php echo $horarios['entrada'] != '--:--' ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-sign-in-alt icon"></i>
                        <span class="label">Entrada</span>
                        <span class="time"><?php echo $horarios['entrada']; ?></span>
                    </div>
                    <div class="status-item <?php echo $horarios['saida_almoco'] != '--:--' ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-utensils icon"></i>
                        <span class="label">Saída Almoço</span>
                        <span class="time"><?php echo $horarios['saida_almoco']; ?></span>
                    </div>
                    <div class="status-item <?php echo $horarios['volta_almoco'] != '--:--' ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-undo-alt icon"></i>
                        <span class="label">Volta Almoço</span>
                        <span class="time"><?php echo $horarios['volta_almoco']; ?></span>
                    </div>
                    <div class="status-item <?php echo $horarios['saida'] != '--:--' ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-sign-out-alt icon"></i>
                        <span class="label">Saída</span>
                        <span class="time"><?php echo $horarios['saida']; ?></span>
                    </div>
                </div>
                
                <?php if ($proximo_tipo !== 'finalizado'): ?>
                <button class="btn-ponto" id="btnFacial" data-tipo="<?php echo $proximo_tipo; ?>">
                    <i class="fas fa-camera"></i> 
                    🔒 <?php echo $proximo_texto; ?> (Reconhecimento Facial)
                </button>
                <?php else: ?>
                <button class="btn-ponto" disabled>
                    <i class="fas fa-check-circle"></i> ✅ Dia Finalizado! Volte amanhã.
                </button>
                <?php endif; ?>
                
                <div class="info-footer">
                    <div class="info-item"><i class="fas fa-info-circle"></i> <span>Horário permitido: 06:00 às 22:00</span></div>
                    <div class="info-item" id="gpsStatus"><i class="fas fa-map-marker-alt"></i> <span>Capturando localização...</span></div>
                </div>
                
                <div class="quick-actions">
                    <a href="extrato.php" class="quick-btn"><i class="fas fa-calendar-alt"></i> Meu Extrato</a>
                    <a href="../solicitacoes/index.php" class="quick-btn"><i class="fas fa-clipboard-list"></i> Solicitações</a>
                    <a href="../../logout.php" class="quick-btn"><i class="fas fa-sign-out-alt"></i> Sair</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal da Câmera -->
    <div id="cameraModal" class="camera-modal">
        <div class="camera-container">
            <video id="video" autoplay playsinline></video>
            <canvas id="canvas" style="display: none;"></canvas>
            <div class="camera-buttons">
                <button class="btn-capturar" id="capturarBtn">📸 Capturar e Validar</button>
                <button class="btn-fechar" id="fecharBtn">❌ Fechar</button>
            </div>
            <div class="instrucao-rosto">
                <i class="fas fa-face-smile"></i> Centralize seu rosto no círculo e mantenha os olhos nos pontos
            </div>
            <div id="statusDetect" class="status-detect">🔄 Aguardando câmera...</div>
            <div id="loadingCamera" class="loading-camera">
                <div class="spinner"></div>
                <p>Validando reconhecimento facial...</p>
            </div>
        </div>
    </div>
    
    <script>
        let stream = null;
        let tipoPonto = null;
        const funcionarioId = <?php echo $funcionario_id; ?>;
        
        // Elementos
        const btnFacial = document.getElementById('btnFacial');
        const cameraModal = document.getElementById('cameraModal');
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const capturarBtn = document.getElementById('capturarBtn');
        const fecharBtn = document.getElementById('fecharBtn');
        const loadingCamera = document.getElementById('loadingCamera');
        const statusDetect = document.getElementById('statusDetect');
        
        // Relógio
        function atualizarRelogio() {
            const agora = new Date();
            const horas = String(agora.getHours()).padStart(2, '0');
            const minutos = String(agora.getMinutes()).padStart(2, '0');
            const segundos = String(agora.getSeconds()).padStart(2, '0');
            const relogio = document.getElementById('relogio');
            if (relogio) {
                relogio.innerHTML = `<i class="fas fa-clock"></i> ${horas}:${minutos}:${segundos}`;
            }
        }
        setInterval(atualizarRelogio, 1000);
        
        // GPS
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                localStorage.setItem('latitude', position.coords.latitude);
                localStorage.setItem('longitude', position.coords.longitude);
                document.getElementById('gpsStatus').innerHTML = '<i class="fas fa-check-circle"></i> <span>Localização capturada</span>';
            }, function(error) {
                document.getElementById('gpsStatus').innerHTML = '<i class="fas fa-exclamation-triangle"></i> <span>GPS não disponível</span>';
            });
        }
        
        // Criar overlay para guia facial
        function criarOverlay() {
            const videoContainer = document.querySelector('.camera-container');
            if (!videoContainer) return null;
            
            let overlay = document.getElementById('faceOverlay');
            if (overlay) overlay.remove();
            
            overlay = document.createElement('canvas');
            overlay.id = 'faceOverlay';
            overlay.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:10;';
            videoContainer.style.position = 'relative';
            videoContainer.appendChild(overlay);
            
            return overlay;
        }
        
        // Desenhar guia facial
        function desenharGuia(overlay, videoElement) {
            if (!overlay || !videoElement) return;
            
            const rect = videoElement.getBoundingClientRect();
            if (rect.width === 0) return;
            
            overlay.width = rect.width;
            overlay.height = rect.height;
            const ctx = overlay.getContext('2d');
            ctx.clearRect(0, 0, overlay.width, overlay.height);
            
            const centerX = overlay.width / 2;
            const centerY = overlay.height / 2;
            const radius = Math.min(overlay.width, overlay.height) * 0.35;
            
            // Círculo externo
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
            ctx.strokeStyle = '#10b981';
            ctx.lineWidth = 3;
            ctx.stroke();
            
            // Círculo interno
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius * 0.85, 0, 2 * Math.PI);
            ctx.strokeStyle = 'rgba(16, 185, 129, 0.5)';
            ctx.lineWidth = 1.5;
            ctx.stroke();
            
            // Pontos dos olhos
            const olhoY = centerY - radius * 0.2;
            ctx.fillStyle = '#ef4444';
            ctx.beginPath();
            ctx.arc(centerX - radius * 0.35, olhoY, 5, 0, 2 * Math.PI);
            ctx.fill();
            ctx.beginPath();
            ctx.arc(centerX + radius * 0.35, olhoY, 5, 0, 2 * Math.PI);
            ctx.fill();
            
            // Íris
            ctx.fillStyle = '#fbbf24';
            ctx.beginPath();
            ctx.arc(centerX - radius * 0.35, olhoY, 3, 0, 2 * Math.PI);
            ctx.fill();
            ctx.beginPath();
            ctx.arc(centerX + radius * 0.35, olhoY, 3, 0, 2 * Math.PI);
            ctx.fill();
        }
        
        // Cortar apenas o rosto
        async function cortarRosto(videoElement) {
            return new Promise((resolve) => {
                const videoWidth = videoElement.videoWidth;
                const videoHeight = videoElement.videoHeight;
                
                // Centralizar 50% da imagem (foco no rosto)
                const cropSize = Math.min(videoWidth, videoHeight) * 0.55;
                const cropX = (videoWidth - cropSize) / 2;
                const cropY = (videoHeight - cropSize) / 2;
                
                const finalCanvas = document.createElement('canvas');
                finalCanvas.width = 400;
                finalCanvas.height = 400;
                const ctx = finalCanvas.getContext('2d');
                
                ctx.drawImage(videoElement, cropX, cropY, cropSize, cropSize, 0, 0, 400, 400);
                resolve(finalCanvas.toDataURL('image/jpeg', 0.95));
            });
        }
        
        // Abrir câmera
        if (btnFacial) {
            btnFacial.addEventListener('click', async function() {
                tipoPonto = this.dataset.tipo;
                cameraModal.style.display = 'flex';
                
                try {
                    const constraints = {
                        video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' }
                    };
                    
                    stream = await navigator.mediaDevices.getUserMedia(constraints);
                    video.srcObject = stream;
                    
                    await new Promise((resolve) => {
                        video.onloadedmetadata = () => {
                            video.play();
                            resolve();
                        };
                    });
                    
                    if (statusDetect) {
                        statusDetect.innerHTML = '✅ Câmera ativa - Centralize o rosto';
                        statusDetect.style.backgroundColor = 'rgba(16, 185, 129, 0.8)';
                    }
                    
                    const overlay = criarOverlay();
                    
                    function atualizarGuia() {
                        if (cameraModal.style.display === 'flex' && overlay) {
                            desenharGuia(overlay, video);
                            requestAnimationFrame(atualizarGuia);
                        }
                    }
                    atualizarGuia();
                    
                } catch (err) {
                    alert('Erro ao acessar a câmera: ' + err.message);
                    if (statusDetect) {
                        statusDetect.innerHTML = '❌ Erro na câmera';
                        statusDetect.style.backgroundColor = 'rgba(239, 68, 68, 0.8)';
                    }
                    fecharCamera();
                }
            });
        }
        
        // Capturar e registrar ponto
        capturarBtn.addEventListener('click', async function() {
            if (!tipoPonto) {
                alert('Tipo de ponto não definido');
                return;
            }
            
            if (!video.videoWidth || !video.videoHeight) {
                alert('Aguarde a câmera carregar');
                return;
            }
            
            loadingCamera.style.display = 'block';
            capturarBtn.disabled = true;
            
            if (statusDetect) {
                statusDetect.innerHTML = '📸 Capturando foto...';
            }
            
            try {
                const fotoBase64 = await cortarRosto(video);
                const latitude = localStorage.getItem('latitude') || null;
                const longitude = localStorage.getItem('longitude') || null;
                
                const response = await fetch('processar_facial.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        tipo: tipoPonto,
                        foto: fotoBase64,
                        funcionario_id: funcionarioId,
                        latitude: latitude,
                        longitude: longitude
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.location.href = 'registrar.php';
                } else {
                    alert('❌ ' + result.message);
                    loadingCamera.style.display = 'none';
                    capturarBtn.disabled = false;
                    if (statusDetect) {
                        statusDetect.innerHTML = '❌ Falha na validação';
                        statusDetect.style.backgroundColor = 'rgba(239, 68, 68, 0.8)';
                        setTimeout(() => {
                            if (cameraModal.style.display === 'flex') {
                                statusDetect.innerHTML = '✅ Câmera ativa';
                                statusDetect.style.backgroundColor = 'rgba(16, 185, 129, 0.8)';
                            }
                        }, 2000);
                    }
                }
            } catch (err) {
                console.error('Erro:', err);
                alert('Erro ao processar: ' + err.message);
                loadingCamera.style.display = 'none';
                capturarBtn.disabled = false;
            }
        });
        
        function fecharCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            cameraModal.style.display = 'none';
            video.srcObject = null;
            loadingCamera.style.display = 'none';
            capturarBtn.disabled = false;
            const overlay = document.getElementById('faceOverlay');
            if (overlay) overlay.remove();
        }
        
        fecharBtn.addEventListener('click', fecharCamera);
        
        // Fechar com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && cameraModal.style.display === 'flex') {
                fecharCamera();
            }
        });
    </script>
</body>
</html>