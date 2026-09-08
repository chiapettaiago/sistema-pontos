<?php
// modules/ponto/ponto_celular.php - Bater ponto pelo celular (com biometria + fallback)
session_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Ponto Celular - PontoFácil</title>
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
        
        .subtitle {
            color: #666;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .funcionario-info {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .funcionario-info h3 {
            font-size: 16px;
            margin-bottom: 4px;
        }
        
        .funcionario-info p {
            font-size: 12px;
            color: #666;
        }
        
        .btn-ponto {
            width: 100%;
            padding: 18px;
            border: none;
            border-radius: 20px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-entrada {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-almoco {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .btn-volta {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-saida {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .btn-facial {
            background: #1e293b;
            color: white;
        }
        
        .btn-desabilitado {
            background: #9ca3af;
            cursor: not-allowed;
        }
        
        .btn-ponto:hover:not(.btn-desabilitado) {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin: 20px 0;
        }
        
        .status-item {
            text-align: center;
            padding: 10px;
            border-radius: 12px;
            background: #f3f4f6;
        }
        
        .status-item.completed {
            background: #d1fae5;
            color: #059669;
        }
        
        .status-item.pending {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .status-item .time {
            font-size: 16px;
            font-weight: 700;
            display: block;
        }
        
        .status-item .label {
            font-size: 10px;
            display: block;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 24px;
            padding: 24px;
            max-width: 350px;
            width: 90%;
            text-align: center;
        }
        
        .modal-content .qr-code {
            width: 200px;
            height: 200px;
            margin: 20px auto;
        }
        
        .modal-content .qr-code img {
            width: 100%;
            height: 100%;
        }
        
        .btn-fechar {
            background: #e5e7eb;
            color: #333;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            margin-top: 16px;
            cursor: pointer;
        }
        
        .alert {
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 13px;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #059669;
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
        
        .gps-status {
            font-size: 11px;
            padding: 8px;
            border-radius: 20px;
            margin: 10px 0;
        }
        
        .gps-ok {
            background: #d1fae5;
            color: #059669;
        }
        
        .gps-error {
            background: #fee2e2;
            color: #dc2626;
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
            <p class="subtitle">Registre seu ponto com biometria facial</p>
            
            <div id="mensagem" class="alert" style="display: none;"></div>
            
            <div class="funcionario-info" id="funcionarioInfo">
                <h3 id="nomeFuncionario">Carregando...</h3>
                <p id="matriculaFuncionario"></p>
                <p id="filialFuncionario"></p>
            </div>
            
            <div id="gpsStatus" class="gps-status gps-error">
                <i class="fas fa-map-marker-alt"></i>
                <span>Capturando localização...</span>
            </div>
            
            <div class="status-grid" id="statusGrid">
                <div class="status-item pending">
                    <i class="fas fa-sign-in-alt"></i>
                    <span class="label">Entrada</span>
                    <span class="time" id="entradaHora">--:--</span>
                </div>
                <div class="status-item pending">
                    <i class="fas fa-utensils"></i>
                    <span class="label">Saída Almoço</span>
                    <span class="time" id="saidaAlmocoHora">--:--</span>
                </div>
                <div class="status-item pending">
                    <i class="fas fa-undo-alt"></i>
                    <span class="label">Volta Almoço</span>
                    <span class="time" id="voltaAlmocoHora">--:--</span>
                </div>
                <div class="status-item pending">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="label">Saída</span>
                    <span class="time" id="saidaHora">--:--</span>
                </div>
            </div>
            
            <div id="botoesPonto">
                <button class="btn-ponto btn-entrada" id="btnEntrada">
                    <i class="fas fa-sign-in-alt"></i> Registrar Entrada
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Autorização do Gerente -->
    <div id="modalAutorizacao" class="modal">
        <div class="modal-content">
            <i class="fas fa-user-shield" style="font-size: 48px; color: #f59e0b; margin-bottom: 16px;"></i>
            <h3>Falha na Biometria</h3>
            <p>O reconhecimento facial falhou. Solicite a autorização do gerente.</p>
            <div class="qr-code" id="qrCodeAutorizacao">
                <img id="qrCodeImg" src="" alt="QR Code para autorização">
            </div>
            <p style="font-size: 12px; color: #666;">Peça para o gerente escanear este QR Code para liberar seu ponto</p>
            <button class="btn-fechar" onclick="fecharModal()">Fechar</button>
            <div id="loadingQR" class="loading" style="display: none;">
                <div class="spinner"></div>
                <p>Gerando QR Code...</p>
            </div>
        </div>
    </div>
    
    <div id="loading" class="loading">
        <div class="spinner"></div>
        <p>Processando...</p>
    </div>
    
    <script>
        let funcionarioId = null;
        let tipoPonto = null;
        let latitude = null;
        let longitude = null;
        let stream = null;
        
        // Inicialização
        document.addEventListener('DOMContentLoaded', async function() {
            await carregarDadosFuncionario();
            await capturarGPS();
        });
        
        // Carregar dados do funcionário
        async function carregarDadosFuncionario() {
            try {
                const response = await fetch('api_ponto_celular.php?acao=dados');
                const data = await response.json();
                
                if (data.success) {
                    funcionarioId = data.funcionario_id;
                    document.getElementById('nomeFuncionario').innerHTML = data.nome;
                    document.getElementById('matriculaFuncionario').innerHTML = 'Matrícula: ' + data.matricula;
                    document.getElementById('filialFuncionario').innerHTML = 'Filial: ' + data.filial;
                    
                    // Atualizar status dos pontos
                    atualizarStatus(data.horarios, data.proximo_tipo);
                } else {
                    window.location.href = '../../login.php';
                }
            } catch (err) {
                console.error('Erro:', err);
            }
        }
        
        // Capturar GPS
        async function capturarGPS() {
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
        
        // Atualizar status dos pontos
        function atualizarStatus(horarios, proximoTipo) {
            const tipos = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
            const ids = ['entradaHora', 'saidaAlmocoHora', 'voltaAlmocoHora', 'saidaHora'];
            
            for (let i = 0; i < tipos.length; i++) {
                const hora = horarios[tipos[i]];
                const element = document.getElementById(ids[i]);
                const parent = element.closest('.status-item');
                
                if (hora && hora !== '--:--') {
                    element.textContent = hora;
                    parent.classList.remove('pending');
                    parent.classList.add('completed');
                } else {
                    element.textContent = '--:--';
                    parent.classList.remove('completed');
                    parent.classList.add('pending');
                }
            }
            
            // Atualizar botões
            const botoesDiv = document.getElementById('botoesPonto');
            botoesDiv.innerHTML = '';
            
            if (proximoTipo === 'finalizado') {
                botoesDiv.innerHTML = '<button class="btn-ponto btn-desabilitado" disabled><i class="fas fa-check-circle"></i> Dia Finalizado!</button>';
                return;
            }
            
            let btnHTML = '';
            switch(proximoTipo) {
                case 'entrada':
                    btnHTML = '<button class="btn-ponto btn-entrada" onclick="tentarRegistrarPonto(\'entrada\')"><i class="fas fa-sign-in-alt"></i> Registrar Entrada</button>';
                    break;
                case 'saida_almoco':
                    btnHTML = '<button class="btn-ponto btn-almoco" onclick="tentarRegistrarPonto(\'saida_almoco\')"><i class="fas fa-utensils"></i> Registrar Saída para Almoço</button>';
                    break;
                case 'volta_almoco':
                    btnHTML = '<button class="btn-ponto btn-volta" onclick="tentarRegistrarPonto(\'volta_almoco\')"><i class="fas fa-undo-alt"></i> Registrar Volta do Almoço</button>';
                    break;
                case 'saida':
                    btnHTML = '<button class="btn-ponto btn-saida" onclick="tentarRegistrarPonto(\'saida\')"><i class="fas fa-sign-out-alt"></i> Registrar Saída</button>';
                    break;
            }
            botoesDiv.innerHTML = btnHTML;
        }
        
        // Tentar registrar ponto (com facial)
        async function tentarRegistrarPonto(tipo) {
            tipoPonto = tipo;
            
            if (!latitude || !longitude) {
                mostrarMensagem('Aguardando localização... Tente novamente.', 'error');
                return;
            }
            
            // Tentar reconhecimento facial
            const facialResultado = await tentarFacial();
            
            if (facialResultado.success) {
                // Sucesso no facial, registrar normalmente
                await registrarPonto();
            } else {
                // Falha no facial, solicitar autorização do gerente
                mostrarMensagem('Reconhecimento facial falhou. Solicite autorização do gerente.', 'error');
                await solicitarAutorizacaoGerente();
            }
        }
        
        // Tentar reconhecimento facial
        async function tentarFacial() {
            const loading = document.getElementById('loading');
            loading.style.display = 'block';
            
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                const video = document.createElement('video');
                video.srcObject = stream;
                await video.play();
                
                // Capturar foto
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);
                const fotoBase64 = canvas.toDataURL('image/jpeg', 0.8);
                
                stream.getTracks().forEach(track => track.stop());
                
                // Validar facial
                const response = await fetch('validar_facial_celular.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        funcionario_id: funcionarioId,
                        foto: fotoBase64
                    })
                });
                
                const result = await response.json();
                loading.style.display = 'none';
                return result;
                
            } catch (err) {
                loading.style.display = 'none';
                return { success: false, message: err.message };
            }
        }
        
        // Registrar ponto
        async function registrarPonto() {
            const loading = document.getElementById('loading');
            loading.style.display = 'block';
            
            try {
                const response = await fetch('registrar_ponto_celular.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        tipo: tipoPonto,
                        latitude: latitude,
                        longitude: longitude
                    })
                });
                
                const result = await response.json();
                loading.style.display = 'none';
                
                if (result.success) {
                    mostrarMensagem(result.message, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    mostrarMensagem(result.message, 'error');
                }
            } catch (err) {
                loading.style.display = 'none';
                mostrarMensagem('Erro: ' + err.message, 'error');
            }
        }
        
        // Solicitar autorização do gerente
        async function solicitarAutorizacaoGerente() {
            const modal = document.getElementById('modalAutorizacao');
            const loadingQR = document.getElementById('loadingQR');
            const qrImg = document.getElementById('qrCodeImg');
            
            modal.style.display = 'flex';
            loadingQR.style.display = 'block';
            qrImg.style.display = 'none';
            
            try {
                const response = await fetch('gerar_qr_autorizacao.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        funcionario_id: funcionarioId,
                        tipo: tipoPonto,
                        latitude: latitude,
                        longitude: longitude
                    })
                });
                
                const result = await response.json();
                loadingQR.style.display = 'none';
                
                if (result.success) {
                    qrImg.src = result.qr_code;
                    qrImg.style.display = 'block';
                    
                    // Verificar se o gerente já autorizou
                    verificarAutorizacao(result.token);
                } else {
                    alert('Erro ao gerar QR Code: ' + result.message);
                    fecharModal();
                }
            } catch (err) {
                loadingQR.style.display = 'none';
                alert('Erro: ' + err.message);
                fecharModal();
            }
        }
        
        // Verificar autorização do gerente
        async function verificarAutorizacao(token) {
            const checkInterval = setInterval(async () => {
                const response = await fetch('api_verificar_autorizacao.php?token=' + encodeURIComponent(token));
                const result = await response.json();
                
                if (result.autorizado) {
                    clearInterval(checkInterval);
                    fecharModal();
                    mostrarMensagem('✅ Ponto autorizado pelo gerente!', 'success');
                    await registrarPonto();
                }
            }, 3000);
        }
        
        function mostrarMensagem(msg, tipo) {
            const msgDiv = document.getElementById('mensagem');
            msgDiv.textContent = msg;
            msgDiv.className = 'alert alert-' + tipo;
            msgDiv.style.display = 'block';
            setTimeout(() => {
                msgDiv.style.display = 'none';
            }, 5000);
        }
        
        function fecharModal() {
            document.getElementById('modalAutorizacao').style.display = 'none';
        }
    </script>
</body>
</html>
