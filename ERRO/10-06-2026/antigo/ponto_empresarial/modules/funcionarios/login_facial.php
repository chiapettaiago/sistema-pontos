<?php
// modules/funcionario/login_facial.php - Login com Reconhecimento Facial
session_start();

// Se já estiver logado, vai para o dashboard
if (isset($_SESSION['funcionario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$modo = $_GET['modo'] ?? 'facial'; // facial ou email

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Login Funcionário - PontoFácil</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 480px;
        }
        
        .login-card {
            background: white;
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .logo-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        
        .logo h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 8px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 32px;
            background: #f3f4f6;
            padding: 6px;
            border-radius: 60px;
        }
        
        .tab {
            flex: 1;
            text-align: center;
            padding: 12px;
            border: none;
            background: transparent;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            color: #666;
        }
        
        .tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .input-group input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e5e5e5;
            border-radius: 16px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .camera-area {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .video-container {
            position: relative;
            width: 100%;
            max-width: 300px;
            margin: 0 auto;
            border-radius: 20px;
            overflow: hidden;
            background: #000;
        }
        
        video {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .camera-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            padding: 16px;
            text-align: center;
        }
        
        .btn-camera {
            background: white;
            color: #667eea;
            border: none;
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-camera:hover {
            transform: scale(1.05);
        }
        
        .foto-preview {
            width: 120px;
            height: 120px;
            border-radius: 60px;
            margin: 0 auto 16px;
            overflow: hidden;
            border: 3px solid #667eea;
        }
        
        .foto-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .switch-modo {
            text-align: center;
            margin-top: 20px;
        }
        
        .switch-modo a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        
        .info-mensagem {
            background: #d1fae5;
            color: #059669;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: center;
        }
        
        @media (max-width: 480px) {
            .login-card {
                padding: 24px;
            }
            
            .logo-icon {
                font-size: 48px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <div class="logo-icon">⏰</div>
                <h1>PontoFácil</h1>
                <p>Área do Funcionário</p>
            </div>
            
            <div class="tabs">
                <button class="tab <?php echo $modo == 'facial' ? 'active' : ''; ?>" onclick="window.location.href='?modo=facial'">
                    <i class="fas fa-camera"></i> Facial
                </button>
                <button class="tab <?php echo $modo == 'email' ? 'active' : ''; ?>" onclick="window.location.href='?modo=email'">
                    <i class="fas fa-envelope"></i> Email/Senha
                </button>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($modo == 'facial'): ?>
            <!-- Modo Reconhecimento Facial -->
            <div class="camera-area">
                <div class="video-container">
                    <video id="video" autoplay playsinline></video>
                    <div class="camera-overlay">
                        <button type="button" class="btn-camera" id="capturarFoto">
                            <i class="fas fa-camera"></i> Capturar e Validar
                        </button>
                    </div>
                </div>
                <canvas id="canvas" style="display: none;"></canvas>
                <div id="loading" class="loading">
                    <div class="spinner"></div>
                    <p>Validando reconhecimento facial...</p>
                </div>
                <div class="info-mensagem" style="margin-top: 16px;">
                    <i class="fas fa-info-circle"></i> 
                    Posicione seu rosto no centro da câmera
                </div>
            </div>
            
            <div class="switch-modo">
                <a href="#" onclick="window.location.href='?modo=email'">
                    <i class="fas fa-keyboard"></i> Usar email e senha
                </a>
            </div>
            
            <?php else: ?>
            <!-- Modo Email/Senha -->
            <form method="POST" action="processar_login.php">
                <div class="form-group">
                    <label>E-mail</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" required placeholder="seu@email.com" autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Senha</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="senha" required placeholder="••••••">
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </button>
            </form>
            
            <div class="switch-modo">
                <a href="#" onclick="window.location.href='?modo=facial'">
                    <i class="fas fa-camera"></i> Usar reconhecimento facial
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($modo == 'facial'): ?>
    <script>
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const capturarBtn = document.getElementById('capturarFoto');
        const loadingDiv = document.getElementById('loading');
        let stream = null;
        
        // Iniciar câmera
        async function iniciarCamera() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
            } catch (err) {
                alert('Erro ao acessar a câmera: ' + err.message);
                window.location.href = '?modo=email';
            }
        }
        
        // Capturar foto e validar
        capturarBtn.addEventListener('click', async function() {
            // Capturar frame do video
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Converter para base64
            const fotoBase64 = canvas.toDataURL('image/jpeg', 0.8);
            
            // Mostrar loading
            loadingDiv.style.display = 'block';
            capturarBtn.disabled = true;
            
            try {
                const response = await fetch('verificar_facial.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ foto: fotoBase64 })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Login bem-sucedido, redirecionar
                    window.location.href = 'dashboard.php';
                } else {
                    alert(result.message || 'Reconhecimento facial falhou. Tente novamente ou use email/senha.');
                    loadingDiv.style.display = 'none';
                    capturarBtn.disabled = false;
                }
            } catch (err) {
                alert('Erro na validação: ' + err.message);
                loadingDiv.style.display = 'none';
                capturarBtn.disabled = false;
            }
        });
        
        iniciarCamera();
        
        // Fechar camera ao sair
        window.addEventListener('beforeunload', function() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>