<?php
session_start();

if (!empty($_SESSION['usuario_id'])) {
    if (($_SESSION['usuario_tipo'] ?? '') === 'super_admin') {
        header('Location: ../../modules/admin/dashboard.php');
    } elseif (in_array(($_SESSION['usuario_tipo'] ?? ''), ['admin_empresa', 'gestor'], true)) {
        header('Location: ../../modules/dashboard_empresa/index.php');
    } else {
        header('Location: ../../modules/ponto/ponto.php');
    }
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

function cosineSimilarity($vecA, $vecB) {
    if (!is_array($vecA) || !is_array($vecB) || count($vecA) !== count($vecB)) {
        return 0;
    }

    $dot = 0;
    $normA = 0;
    $normB = 0;
    for ($i = 0; $i < count($vecA); $i++) {
        $a = (float) $vecA[$i];
        $b = (float) $vecB[$i];
        $dot += $a * $b;
        $normA += $a * $a;
        $normB += $b * $b;
    }

    if ($normA <= 0 || $normB <= 0) {
        return 0;
    }

    return $dot / (sqrt($normA) * sqrt($normB));
}

function senhaConfereFace(string $senhaDigitada, string $senhaSalva): bool {
    if (password_verify($senhaDigitada, $senhaSalva)) {
        return true;
    }

    $senhaSalvaLimpa = trim($senhaSalva);

    if (strlen($senhaSalvaLimpa) === 32 && ctype_xdigit($senhaSalvaLimpa)) {
        return hash_equals(strtolower($senhaSalvaLimpa), md5($senhaDigitada));
    }

    return hash_equals($senhaSalvaLimpa, $senhaDigitada);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'login_email') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $error = 'Preencha email e senha.';
    } else {
        try {
            $stmt = $db->prepare("SELECT u.*, e.nome as empresa_nome
                                  FROM usuarios_sistema u
                                  LEFT JOIN empresas e ON u.empresa_id = e.id
                                  WHERE u.email = :email AND u.status = 'ativo'
                                  LIMIT 1");
            $stmt->execute([':email' => $email]);
            $usuarioSistema = $stmt->fetch();

            if ($usuarioSistema && senhaConfereFace($senha, $usuarioSistema['senha'])) {
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $usuarioSistema['id'];
                $_SESSION['usuario_nome'] = $usuarioSistema['nome'];
                $_SESSION['usuario_email'] = $usuarioSistema['email'];
                $_SESSION['usuario_tipo'] = $usuarioSistema['tipo'];
                $_SESSION['empresa_id'] = $usuarioSistema['empresa_id'] ?? null;
                $_SESSION['empresa_nome'] = $usuarioSistema['empresa_nome'] ?? null;
                $_SESSION['funcionario_id'] = $usuarioSistema['funcionario_id'] ?? null;
                $_SESSION['tipo_login'] = 'sistema';

                if ($usuarioSistema['tipo'] === 'super_admin') {
                    header('Location: ../../modules/admin/dashboard.php');
                } elseif ($usuarioSistema['tipo'] === 'admin_empresa' || $usuarioSistema['tipo'] === 'gestor') {
                    header('Location: ../../modules/dashboard_empresa/index.php');
                } else {
                    header('Location: ../../modules/ponto/ponto.php');
                }
                exit;
            }

            $stmt = $db->prepare("SELECT f.*, e.nome_empresa as empresa_nome, fi.nome_fantasia as filial_nome
                                  FROM funcionarios f
                                  LEFT JOIN empresa e ON f.empresa_id = e.id
                                  LEFT JOIN filiais fi ON f.filial_id = fi.id
                                  WHERE f.email = :email AND f.status = 'ativo'
                                  LIMIT 1");
            $stmt->execute([':email' => $email]);
            $funcionario = $stmt->fetch();

            if ($funcionario && senhaConfereFace($senha, $funcionario['senha'])) {
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $funcionario['id'];
                $_SESSION['usuario_nome'] = $funcionario['nome'];
                $_SESSION['usuario_email'] = $funcionario['email'];
                $_SESSION['usuario_tipo'] = 'funcionario';
                $_SESSION['funcionario_id'] = $funcionario['id'];
                $_SESSION['empresa_id'] = $funcionario['empresa_id'] ?? null;
                $_SESSION['empresa_nome'] = $funcionario['empresa_nome'] ?? null;
                $_SESSION['filial_id'] = $funcionario['filial_id'] ?? null;
                header('Location: ../../modules/ponto/ponto.php');
                exit;
            }

            $error = 'Email ou senha invalidos.';
        } catch (Exception $e) {
            error_log('Erro login email: ' . $e->getMessage());
            $error = 'Nao foi possivel fazer login agora.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login do Funcionário</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a, #667eea 55%, #764ba2);
        }
        .card {
            width: 100%;
            max-width: 520px;
            background: rgba(255,255,255,0.96);
            border-radius: 28px;
            padding: 28px;
            box-shadow: 0 20px 60px rgba(15,23,42,.28);
        }
        .title { text-align: center; margin-bottom: 18px; }
        .title h1 { font-size: 26px; color: #111827; }
        .title p { color: #6b7280; margin-top: 6px; font-size: 14px; }
        .tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 18px 0 22px; }
        .tab {
            border: 0; border-radius: 16px; padding: 12px; cursor: pointer;
            font-weight: 700; font-size: 15px; background: #e5e7eb; color: #374151;
        }
        .tab.active { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; }
        .panel { display: none; }
        .panel.active { display: block; }
        .note {
            background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;
            border-radius: 16px; padding: 12px 14px; font-size: 13px; line-height: 1.5; margin-bottom: 16px;
        }
        .alert {
            border-radius: 14px; padding: 12px 14px; margin-bottom: 16px; font-size: 14px;
        }
        .alert.error { background: #fee2e2; color: #991b1b; }
        .alert.success { background: #d1fae5; color: #065f46; }
        .camera-wrap {
            position: relative; overflow: hidden; border-radius: 20px; background: #111827;
        }
        video { width: 100%; display: block; min-height: 280px; object-fit: cover; }
        .face-guide {
            position: absolute; inset: 50% auto auto 50%; width: 220px; height: 220px;
            transform: translate(-50%, -50%); border: 2px solid rgba(255,255,255,.55); border-radius: 50%;
            pointer-events: none;
        }
        .camera-overlay {
            position: absolute; left: 0; right: 0; bottom: 0; padding: 16px;
            background: linear-gradient(transparent, rgba(0,0,0,.72)); color: #fff; text-align: center;
        }
        .status { margin-top: 12px; padding: 12px; border-radius: 14px; font-size: 13px; }
        .status.info { background: #dbeafe; color: #1e40af; }
        .status.success { background: #d1fae5; color: #065f46; }
        .status.error { background: #fee2e2; color: #991b1b; }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 8px; font-weight: 700; color: #374151; font-size: 14px; }
        input {
            width: 100%; min-height: 48px; border-radius: 14px; border: 1px solid #d1d5db;
            padding: 12px 14px 12px 44px; font-size: 16px; outline: none;
        }
        .input-group { position: relative; }
        .input-group i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #9ca3af; }
        .btn {
            width: 100%; min-height: 50px; border: 0; border-radius: 16px; font-size: 16px;
            font-weight: 800; cursor: pointer; margin-top: 6px;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; }
        .btn-secondary { background: #111827; color: #fff; }
        .btn:disabled { opacity: .65; cursor: not-allowed; }
        .small-link { text-align: center; margin-top: 14px; }
        .small-link a { color: #4f46e5; text-decoration: none; font-weight: 700; font-size: 14px; }
        .back-login {
            display: block;
            width: 100%;
            margin-top: 14px;
            text-align: center;
            padding: 13px 14px;
            border-radius: 16px;
            background: #f3f4f6;
            color: #111827;
            text-decoration: none;
            font-weight: 800;
            border: 1px solid #e5e7eb;
        }
        .loading { display: inline-block; width: 18px; height: 18px; border: 2px solid currentColor; border-top-color: transparent; border-radius: 50%; animation: spin .7s linear infinite; vertical-align: -3px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 520px) {
            .card { padding: 20px; border-radius: 22px; }
            .tabs { grid-template-columns: 1fr; }
            .face-guide { width: 180px; height: 180px; }
            video { min-height: 240px; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">
            <h1>Login do Funcionário</h1>
            <p>Escolha entre reconhecimento facial ou email e senha</p>
        </div>

        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button type="button" class="tab" data-tab="facial"><i class="fas fa-camera"></i> Facial</button>
            <button type="button" class="tab active" data-tab="email"><i class="fas fa-envelope"></i> Email/Senha</button>
        </div>

        <div class="note">
            <strong>Observação:</strong> o login facial usa a biometria previamente cadastrada do funcionário.
            Se a câmera não abrir no celular, use HTTPS ou teste em <code>localhost</code>.
        </div>

        <div class="small-link" style="margin-top:-2px;">
            <a href="../../login.php">Usar login principal do sistema</a>
        </div>

        <div id="panel-facial" class="panel">
            <div class="camera-wrap">
                <video id="video" autoplay muted playsinline></video>
                <div class="face-guide"></div>
                <div class="camera-overlay">
                    <button type="button" id="btnLoginFace" class="btn btn-secondary">
                        <i class="fas fa-face-smile"></i> Capturar e Entrar
                    </button>
                </div>
            </div>
            <canvas id="canvas" style="display:none"></canvas>
            <div id="faceStatus" class="status info">Carregando reconhecimento facial...</div>
            <a href="#" class="back-login" id="btnUseEmailMobile"><i class="fas fa-keyboard"></i> Voltar ao login normal</a>
            <div class="small-link"><a href="#" id="btnUseEmail">Usar email e senha</a></div>
        </div>

        <div id="panel-email" class="panel">
            <form method="POST" action="">
                <input type="hidden" name="acao" value="login_email">
                <div class="form-group">
                    <label>E-mail</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="seu@email.com" autocomplete="username">
                    </div>
                </div>
                <div class="form-group">
                    <label>Senha</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="senha" placeholder="••••••••" autocomplete="current-password">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-right-to-bracket"></i> Entrar</button>
            </form>
            <a href="#" class="back-login" id="btnUseFaceMobile"><i class="fas fa-camera"></i> Voltar ao reconhecimento facial</a>
            <div class="small-link"><a href="#" id="btnUseFace">Usar reconhecimento facial</a></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script>
        const tabs = document.querySelectorAll('.tab');
        const panelFacial = document.getElementById('panel-facial');
        const panelEmail = document.getElementById('panel-email');
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const btnLoginFace = document.getElementById('btnLoginFace');
        const faceStatus = document.getElementById('faceStatus');
        const btnUseEmail = document.getElementById('btnUseEmail');
        const btnUseFace = document.getElementById('btnUseFace');
        const btnUseEmailMobile = document.getElementById('btnUseEmailMobile');
        const btnUseFaceMobile = document.getElementById('btnUseFaceMobile');
        let stream = null;
        let modelsLoaded = false;
        let detectionTimer = null;
        let stableFaces = 0;
        let loginInProgress = false;
        let cameraStarted = false;

        function setStatus(message, type = 'info') {
            faceStatus.className = 'status ' + type;
            faceStatus.textContent = message;
        }

        function showTab(name) {
            tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === name));
            panelFacial.classList.toggle('active', name === 'facial');
            panelEmail.classList.toggle('active', name === 'email');

            if (name === 'facial') {
                ensureFaceMode();
            } else {
                stopCamera();
            }
        }

        tabs.forEach(tab => tab.addEventListener('click', () => showTab(tab.dataset.tab)));
        btnUseEmail.addEventListener('click', (e) => { e.preventDefault(); showTab('email'); });
        btnUseFace.addEventListener('click', (e) => { e.preventDefault(); showTab('facial'); });
        if (btnUseEmailMobile) btnUseEmailMobile.addEventListener('click', (e) => { e.preventDefault(); showTab('email'); });
        if (btnUseFaceMobile) btnUseFaceMobile.addEventListener('click', (e) => { e.preventDefault(); showTab('facial'); });

        async function loadModels() {
            if (modelsLoaded || !window.faceapi) return modelsLoaded;
            const sources = [
                '/ponto_empresarial/assets/models',
                '../../assets/models'
            ];
            for (const source of sources) {
                try {
                    await faceapi.nets.tinyFaceDetector.loadFromUri(source);
                    await faceapi.nets.faceLandmark68Net.loadFromUri(source);
                    await faceapi.nets.faceRecognitionNet.loadFromUri(source);
                    modelsLoaded = true;
                    setStatus('Reconhecimento facial pronto. Posicione o rosto na câmera.', 'success');
                    return true;
                } catch (err) {
                    console.warn('Falha ao carregar modelos em', source, err);
                }
            }
            setStatus('Não foi possível carregar os modelos faciais agora.', 'error');
            return false;
        }

        async function startCamera() {
            try {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('Seu navegador não permite uso da câmera.');
                }
                if (!window.isSecureContext && !['localhost', '127.0.0.1', '::1'].includes(window.location.hostname)) {
                    throw new Error('No celular, a câmera precisa de HTTPS ou acesso via localhost.');
                }
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                video.srcObject = stream;
                await video.play();
                setStatus('Câmera ativa. Aguardando o rosto.', 'success');
                startAutoDetection();
            } catch (err) {
                const mobileTip = /HTTPS|localhost/.test(err.message)
                    ? ' Use HTTPS ou abra pelo localhost para liberar a câmera.'
                    : ' Se preferir, use o login por email e senha.';
                setStatus('Falha ao abrir câmera: ' + err.message + mobileTip, 'error');
            }
        }

        async function stopCamera() {
            if (detectionTimer) {
                clearInterval(detectionTimer);
                detectionTimer = null;
            }
            stableFaces = 0;
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            video.srcObject = null;
            cameraStarted = false;
        }

        function startAutoDetection() {
            if (detectionTimer) clearInterval(detectionTimer);
            detectionTimer = setInterval(async () => {
                if (loginInProgress || !modelsLoaded || !video.videoWidth || video.paused || video.ended) {
                    return;
                }

                try {
                    const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 }))
                        .withFaceLandmarks()
                        .withFaceDescriptor();

                    if (detection) {
                        stableFaces++;
                        setStatus('Rosto detectado. Mantendo leitura...', 'success');
                        if (stableFaces >= 3) {
                            loginFacial(true);
                        }
                    } else {
                        stableFaces = 0;
                        setStatus('Posicione o rosto no centro da câmera.', 'info');
                    }
                } catch (err) {
                    console.warn(err);
                }
            }, 700);
        }

        async function loginFacial(auto = false) {
            if (loginInProgress) return;
            loginInProgress = true;
            btnLoginFace.disabled = true;
            btnLoginFace.innerHTML = '<span class="loading"></span> Validando...';
            setStatus(auto ? 'Reconhecimento confirmado. Entrando...' : 'Capturando face...', 'info');

            try {
                if (!modelsLoaded) {
                    const ok = await loadModels();
                    if (!ok) throw new Error('Modelos faciais indisponíveis');
                }

                const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 }))
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (!detection) {
                    throw new Error('Nenhum rosto detectado');
                }

                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

                const response = await fetch('../../api/login_face.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        descritor: Array.from(detection.descriptor)
                    })
                });

                const result = await response.json();

                if (result.success) {
                    setStatus('Login realizado com sucesso.', 'success');
                    window.location.href = '../../modules/ponto/ponto.php';
                    return;
                }

                throw new Error(result.message || result.error || 'Falha no login facial');
            } catch (err) {
                stableFaces = 0;
                setStatus(err.message + ' Tente novamente ou use o login normal.', 'error');
                loginInProgress = false;
                btnLoginFace.disabled = false;
                btnLoginFace.innerHTML = '<i class="fas fa-face-smile"></i> Capturar e Entrar';
            } finally {
                if (loginInProgress) {
                    btnLoginFace.disabled = false;
                    btnLoginFace.innerHTML = '<i class="fas fa-face-smile"></i> Capturar e Entrar';
                }
                loginInProgress = false;
            }
        }

        btnLoginFace.addEventListener('click', loginFacial);
        async function ensureFaceMode() {
            if (cameraStarted) return;
            cameraStarted = true;
            await loadModels();
            await startCamera();
        }

        const initialTab = new URLSearchParams(window.location.search).get('tab') === 'facial' ? 'facial' : 'email';
        showTab(initialTab);

        window.addEventListener('beforeunload', stopCamera);
    </script>
</body>
</html>
