<?php
// modules/ponto/ponto_mobile.php - Bater ponto pelo celular via QR Code
session_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Ponto Mobile - PontoFácil</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 32px;
            padding: 32px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .icon {
            font-size: 64px;
            color: #667eea;
            margin-bottom: 20px;
        }
        
        h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 8px;
        }
        
        p {
            color: #666;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 16px;
            margin: 20px 0;
            text-align: left;
        }
        
        .info-box i {
            color: #667eea;
            margin-right: 8px;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #333;
        }
        
        .qr-preview {
            background: white;
            padding: 20px;
            border-radius: 16px;
            margin: 20px 0;
            border: 1px solid #e5e5e5;
        }
        
        .qr-preview img {
            width: 180px;
            height: 180px;
            margin: 0 auto;
            display: block;
        }
        
        .gps-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 12px;
            padding: 8px;
            border-radius: 20px;
            margin-top: 16px;
        }
        
        .gps-ok {
            background: #d1fae5;
            color: #059669;
        }
        
        .gps-error {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .resultado {
            margin-top: 20px;
            padding: 16px;
            border-radius: 16px;
            display: none;
        }
        
        .resultado-success {
            background: #d1fae5;
            color: #059669;
        }
        
        .resultado-error {
            background: #fee2e2;
            color: #dc2626;
        }
        
        @media (max-width: 480px) {
            .card {
                padding: 24px;
            }
            
            .qr-preview img {
                width: 150px;
                height: 150px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="icon">
                <i class="fas fa-mobile-alt"></i>
            </div>
            <h1>Ponto pelo Celular</h1>
            <p>Escaneie o QR Code do seu crachá para bater o ponto</p>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i> 
                <strong>Como funciona:</strong><br>
                1. Abra a câmera do seu celular<br>
                2. Escaneie o QR Code do seu crachá (no aplicativo ou papel)<br>
                3. O sistema capturará sua localização<br>
                4. Registro será feito automaticamente
            </div>
            
            <div id="gpsStatus" class="gps-status gps-error">
                <i class="fas fa-map-marker-alt"></i>
                <span>Capturando localização...</span>
            </div>
            
            <div class="qr-preview">
                <div id="loadingQR" class="loading">
                    <div class="spinner"></div>
                    <p>Carregando QR Code...</p>
                </div>
                <img id="qrCodeImg" src="" alt="QR Code" style="display: none;">
                <p style="margin-top: 10px; font-size: 12px; color: #999;">
                    <i class="fas fa-camera"></i> Aponte a câmera para o QR Code
                </p>
            </div>
            
            <button class="btn btn-primary" id="btnScanner">
                <i class="fas fa-camera"></i> Abrir Câmera para Escanear
            </button>
            
            <button class="btn btn-secondary" id="btnManual">
                <i class="fas fa-qrcode"></i> Ver Meu QR Code
            </button>
            
            <div id="resultado" class="resultado"></div>
        </div>
    </div>
    
    <!-- Importar biblioteca para leitura de QR Code -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    
    <script>
        let latitude = null;
        let longitude = null;
        let html5QrCode = null;
        
        // Capturar localização GPS
        function capturarGPS() {
            const gpsDiv = document.getElementById('gpsStatus');
            
            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    latitude = position.coords.latitude;
                    longitude = position.coords.longitude;
                    gpsDiv.innerHTML = '<i class="fas fa-check-circle"></i> <span>Localização capturada ✓</span>';
                    gpsDiv.className = 'gps-status gps-ok';
                }, function(error) {
                    let msg = '';
                    switch(error.code) {
                        case 1: msg = 'Permissão negada'; break;
                        case 2: msg = 'Indisponível'; break;
                        case 3: msg = 'Timeout'; break;
                        default: msg = 'Erro';
                    }
                    gpsDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <span>GPS: ' + msg + '</span>';
                    gpsDiv.className = 'gps-status gps-error';
                });
            } else {
                gpsDiv.innerHTML = '<i class="fas fa-times-circle"></i> <span>GPS não suportado</span>';
                gpsDiv.className = 'gps-status gps-error';
            }
        }
        
        capturarGPS();
        
        // Função para registrar ponto
        async function registrarPonto(dadosQR) {
            const resultadoDiv = document.getElementById('resultado');
            resultadoDiv.style.display = 'block';
            resultadoDiv.innerHTML = '<div class="loading"><div class="spinner"></div><p>Processando registro...</p></div>';
            resultadoDiv.className = 'resultado';
            
            if (!latitude || !longitude) {
                resultadoDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Aguardando localização... Tente novamente.';
                resultadoDiv.className = 'resultado resultado-error';
                return;
            }
            
            try {
                const response = await fetch('api_ponto_mobile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        dados: dadosQR,
                        latitude: latitude,
                        longitude: longitude
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    resultadoDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + result.message;
                    resultadoDiv.className = 'resultado resultado-success';
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    resultadoDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + result.message;
                    resultadoDiv.className = 'resultado resultado-error';
                }
            } catch (err) {
                resultadoDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erro ao processar: ' + err.message;
                resultadoDiv.className = 'resultado resultado-error';
            }
        }
        
        // Abrir scanner de QR Code
        document.getElementById('btnScanner').addEventListener('click', function() {
            const scannerContainer = document.createElement('div');
            scannerContainer.id = 'scannerContainer';
            scannerContainer.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: black; z-index: 1000;';
            
            const videoContainer = document.createElement('div');
            videoContainer.id = 'videoContainer';
            videoContainer.style.cssText = 'width: 100%; height: 100%;';
            scannerContainer.appendChild(videoContainer);
            
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '<i class="fas fa-times"></i> Fechar';
            closeBtn.style.cssText = 'position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: #ef4444; color: white; border: none; padding: 12px 24px; border-radius: 30px; font-size: 16px; z-index: 1001; cursor: pointer;';
            closeBtn.onclick = function() {
                if (html5QrCode) {
                    html5QrCode.stop().then(() => {
                        scannerContainer.remove();
                    });
                } else {
                    scannerContainer.remove();
                }
            };
            scannerContainer.appendChild(closeBtn);
            
            document.body.appendChild(scannerContainer);
            
            html5QrCode = new Html5Qrcode("videoContainer");
            const qrCodeSuccessCallback = (decodedText, decodedResult) => {
                html5QrCode.stop();
                scannerContainer.remove();
                registrarPonto(decodedText);
            };
            
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                qrCodeSuccessCallback
            ).catch(err => {
                console.error("Erro ao iniciar scanner:", err);
                scannerContainer.remove();
                alert('Erro ao acessar a câmera. Verifique as permissões.');
            });
        });
        
        // Ver meu QR Code
        document.getElementById('btnManual').addEventListener('click', function() {
            window.location.href = '../cracha/meu_cracha.php';
        });
    </script>
</body>
</html>