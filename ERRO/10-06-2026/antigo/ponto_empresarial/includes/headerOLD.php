<?php
// includes/header.php - CORRIGIDO COM BIBLIOTECAS DE DETECÇÃO FACIAL
// NÃO pode haver nada antes desta linha

if (!isset($skipAuth) || !$skipAuth) {
    require_once __DIR__ . '/auth.php';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#667eea">
    <title><?php echo $pageTitle ?? 'Ponto Fácil Empresarial'; ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS do Sistema -->
    <link rel="stylesheet" href="/ponto_empresarial/assets/css/style.css">
    <link rel="stylesheet" href="/ponto_empresarial/assets/css/dark-theme.css">
    
    <!-- ============================================ -->
    <!-- BIBLIOTECAS PARA DETECÇÃO FACIAL (ROSTO + OLHOS + ÍRIS) -->
    <!-- ============================================ -->
    <!-- TensorFlow.js - Base para machine learning -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.18.0/dist/tf.min.js"></script>
    
    <!-- Face-API.js - Detecção facial avançada -->
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/dist/face-api.min.js"></script>
    
    <style>
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-primary);
            margin-right: 16px;
        }
        
        .theme-toggle-btn {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 40px;
            padding: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .theme-option {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .theme-option.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        /* Loading para detecção facial */
        .facial-loading {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #1e293b;
            color: white;
            padding: 8px 16px;
            border-radius: 40px;
            font-size: 12px;
            z-index: 9999;
            display: none;
            align-items: center;
            gap: 8px;
            font-family: monospace;
        }
        
        .facial-loading i {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
        }
    </style>
</head>
<body class="light-theme">
    <div class="app-container">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2><?php echo $pageTitle ?? 'Dashboard'; ?></h2>
                </div>
                <div class="top-bar-actions">
                    <div class="datetime">
                        <span id="currentDate"></span>
                        <span id="currentTime"></span>
                    </div>
                    <div class="theme-toggle-btn" id="themeToggleBtn">
                        <div class="theme-option light-option" data-theme="light">
                            <i class="fas fa-sun"></i>
                        </div>
                        <div class="theme-option dark-option" data-theme="dark">
                            <i class="fas fa-moon"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="content-wrapper">
                <!-- Loading indicator para detecção facial -->
                <div id="facialLoading" class="facial-loading">
                    <i class="fas fa-spinner"></i>
                    <span>Carregando detecção facial...</span>
                </div>
                
                <script>
                    // ============================================
                    // INICIALIZAÇÃO DO SISTEMA
                    // ============================================
                    
                    // Relógio em tempo real
                    function atualizarRelogio() {
                        const agora = new Date();
                        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                        const dateElement = document.getElementById('currentDate');
                        const timeElement = document.getElementById('currentTime');
                        
                        if (dateElement) {
                            dateElement.textContent = agora.toLocaleDateString('pt-BR', options);
                        }
                        if (timeElement) {
                            timeElement.textContent = agora.toLocaleTimeString('pt-BR');
                        }
                    }
                    setInterval(atualizarRelogio, 1000);
                    atualizarRelogio();
                    
                    // Menu Toggle para mobile
                    const menuToggle = document.getElementById('menuToggle');
                    const sidebar = document.querySelector('.sidebar');
                    
                    if (menuToggle && sidebar) {
                        menuToggle.addEventListener('click', function() {
                            sidebar.classList.toggle('open');
                        });
                        
                        // Fechar menu ao clicar fora
                        document.addEventListener('click', function(event) {
                            if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('open')) {
                                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                                    sidebar.classList.remove('open');
                                }
                            }
                        });
                    }
                    
                    // Tema (Dark/Light)
                    const themeToggleBtn = document.getElementById('themeToggleBtn');
                    const lightOption = document.querySelector('.light-option');
                    const darkOption = document.querySelector('.dark-option');
                    
                    function setTheme(theme) {
                        document.body.classList.remove('light-theme', 'dark-theme');
                        document.body.classList.add(theme + '-theme');
                        localStorage.setItem('theme', theme);
                        
                        if (lightOption && darkOption) {
                            if (theme === 'light') {
                                lightOption.classList.add('active');
                                darkOption.classList.remove('active');
                            } else {
                                darkOption.classList.add('active');
                                lightOption.classList.remove('active');
                            }
                        }
                    }
                    
                    const savedTheme = localStorage.getItem('theme') || 'light';
                    setTheme(savedTheme);
                    
                    if (lightOption) {
                        lightOption.addEventListener('click', () => setTheme('light'));
                    }
                    if (darkOption) {
                        darkOption.addEventListener('click', () => setTheme('dark'));
                    }
                    
                    // ============================================
                    // CARREGAMENTO DOS MODELOS DE DETECÇÃO FACIAL
                    // CAMINHO CORRETO: /ponto_empresarial/assets/models/
                    // ============================================
                    
                    let faceApiLoaded = false;
                    const facialLoading = document.getElementById('facialLoading');
                    
                    async function carregarModelosFaciais() {
                        if (facialLoading) {
                            facialLoading.style.display = 'flex';
                            facialLoading.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Carregando detecção facial...</span>';
                        }
                        
                        try {
                            // Garantir que o TensorFlow está pronto
                            await tf.ready();
                            console.log('✅ TensorFlow.js inicializado com backend:', tf.getBackend());
                            
                            // CAMINHO CORRETO para os modelos
                            const modelPath = '/ponto_empresarial/assets/models';
                            console.log('📁 Carregando modelos de:', modelPath);
                            
                            // Carregar modelos do face-api.js
                            await faceapi.nets.tinyFaceDetector.loadFromUri(modelPath);
                            console.log('✅ TinyFaceDetector carregado');
                            
                            await faceapi.nets.faceLandmark68Net.loadFromUri(modelPath);
                            console.log('✅ FaceLandmark68Net carregado');
                            
                            await faceapi.nets.faceRecognitionNet.loadFromUri(modelPath);
                            console.log('✅ FaceRecognitionNet carregado');
                            
                            await faceapi.nets.faceExpressionNet.loadFromUri(modelPath);
                            console.log('✅ FaceExpressionNet carregado');
                            
                            faceApiLoaded = true;
                            
                            if (facialLoading) {
                                facialLoading.style.background = '#10b981';
                                facialLoading.innerHTML = '<i class="fas fa-check-circle"></i> <span>Detecção facial pronta!</span>';
                                setTimeout(() => {
                                    facialLoading.style.display = 'none';
                                }, 2000);
                            }
                            
                            console.log('✅ Todos os modelos faciais foram carregados com sucesso!');
                            
                        } catch (error) {
                            console.error('❌ Erro ao carregar modelos faciais:', error);
                            console.error('Verifique se os arquivos estão na pasta: /ponto_empresarial/assets/models/');
                            console.error('Arquivos necessários: tiny_face_detector_model-weights_manifest.json, tiny_face_detector_model-shard1, face_landmark_68_model-weights_manifest.json, face_landmark_68_model-shard1, face_recognition_model-weights_manifest.json, face_recognition_model-shard1');
                            
                            if (facialLoading) {
                                facialLoading.style.background = '#f59e0b';
                                facialLoading.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <span>Modo manual - sem detecção automática</span>';
                                setTimeout(() => {
                                    facialLoading.style.display = 'none';
                                }, 3000);
                            }
                        }
                    }
                    
                    // Iniciar carregamento dos modelos após a página carregar
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', carregarModelosFaciais);
                    } else {
                        carregarModelosFaciais();
                    }
                    
                    // Expor função para verificar se os modelos estão carregados
                    window.isFaceApiReady = function() {
                        return faceApiLoaded;
                    };
                    
                    // Expor função para obter detecção facial
                    window.detectarRosto = async function(videoElement) {
                        if (!faceApiLoaded || !videoElement) return null;
                        
                        try {
                            const deteccoes = await faceapi.detectAllFaces(
                                videoElement, 
                                new faceapi.TinyFaceDetectorOptions()
                            ).withFaceLandmarks().withFaceExpressions();
                            
                            return deteccoes;
                        } catch (error) {
                            console.error('Erro na detecção facial:', error);
                            return null;
                        }
                    };
                    
                    // Função para desenhar pontos faciais em um canvas
                    window.desenharPontosFaciais = function(canvas, deteccoes, videoElement) {
                        if (!canvas || !deteccoes || deteccoes.length === 0) return;
                        
                        const ctx = canvas.getContext('2d');
                        const rect = videoElement.getBoundingClientRect();
                        const scaleX = canvas.width / videoElement.videoWidth;
                        const scaleY = canvas.height / videoElement.videoHeight;
                        
                        const deteccao = deteccoes[0];
                        
                        // Limpar canvas
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        
                        // Desenhar caixa do rosto
                        const caixa = deteccao.box;
                        ctx.strokeStyle = '#10b981';
                        ctx.lineWidth = 3;
                        ctx.strokeRect(caixa.x * scaleX, caixa.y * scaleY, caixa.width * scaleX, caixa.height * scaleY);
                        
                        // Desenhar pontos dos olhos
                        if (deteccao.landmarks) {
                            const pontos = deteccao.landmarks.positions;
                            
                            ctx.fillStyle = '#ef4444';
                            // Olho esquerdo (índices 36-41)
                            for (let i = 36; i <= 41 && i < pontos.length; i++) {
                                ctx.beginPath();
                                ctx.arc(pontos[i].x * scaleX, pontos[i].y * scaleY, 3, 0, 2 * Math.PI);
                                ctx.fill();
                            }
                            // Olho direito (índices 42-47)
                            for (let i = 42; i <= 47 && i < pontos.length; i++) {
                                ctx.beginPath();
                                ctx.arc(pontos[i].x * scaleX, pontos[i].y * scaleY, 3, 0, 2 * Math.PI);
                                ctx.fill();
                            }
                            
                            // Desenhar íris (centro dos olhos)
                            ctx.fillStyle = '#fbbf24';
                            if (pontos.length > 40) {
                                const olhoEsqX = (pontos[36].x + pontos[39].x) / 2 * scaleX;
                                const olhoEsqY = (pontos[36].y + pontos[39].y) / 2 * scaleY;
                                ctx.beginPath();
                                ctx.arc(olhoEsqX, olhoEsqY, 4, 0, 2 * Math.PI);
                                ctx.fill();
                                
                                const olhoDirX = (pontos[42].x + pontos[45].x) / 2 * scaleX;
                                const olhoDirY = (pontos[42].y + pontos[45].y) / 2 * scaleY;
                                ctx.beginPath();
                                ctx.arc(olhoDirX, olhoDirY, 4, 0, 2 * Math.PI);
                                ctx.fill();
                            }
                        }
                        
                        // Expressão detectada
                        if (deteccao.expressions) {
                            const expressoes = deteccao.expressions;
                            const expressaoPrincipal = Object.entries(expressoes).reduce((a, b) => a[1] > b[1] ? a : b);
                            
                            ctx.font = '14px Arial';
                            ctx.fillStyle = '#10b981';
                            ctx.fillText(`Expressão: ${expressaoPrincipal[0]}`, caixa.x * scaleX, (caixa.y - 10) * scaleY);
                        }
                    };
                    
                    // Função para desenhar guia simples (fallback)
                    window.desenharGuiaFacial = function(canvas, videoElement) {
                        if (!canvas) return;
                        
                        const ctx = canvas.getContext('2d');
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        
                        const centerX = canvas.width / 2;
                        const centerY = canvas.height / 2;
                        const radius = Math.min(canvas.width, canvas.height) * 0.35;
                        
                        // Círculo guia
                        ctx.beginPath();
                        ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
                        ctx.strokeStyle = '#10b981';
                        ctx.lineWidth = 3;
                        ctx.stroke();
                        
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
                        
                        ctx.font = '14px Arial';
                        ctx.fillStyle = 'white';
                        ctx.shadowBlur = 4;
                        ctx.fillText('Centralize seu rosto', centerX - 85, centerY - radius - 10);
                        ctx.fillText('Mantenha os olhos nos pontos', centerX - 105, centerY + radius + 25);
                        ctx.shadowBlur = 0;
                    };
                    
                    // Função para avaliar qualidade da imagem
                    window.avaliarQualidadeImagem = function(imageData) {
                        let score = 0;
                        let feedback = [];
                        
                        // Verificar iluminação
                        let totalBrightness = 0;
                        for (let i = 0; i < imageData.data.length; i += 4) {
                            const brightness = (imageData.data[i] + imageData.data[i+1] + imageData.data[i+2]) / 3;
                            totalBrightness += brightness;
                        }
                        const avgBrightness = totalBrightness / (imageData.data.length / 4);
                        
                        if (avgBrightness < 50) {
                            feedback.push('Imagem muito escura');
                        } else if (avgBrightness > 200) {
                            feedback.push('Imagem muito clara');
                        } else {
                            score += 30;
                            feedback.push('Iluminação adequada ✓');
                        }
                        
                        // Verificar nitidez (simplificado)
                        let sharpness = 0;
                        for (let i = 0; i < imageData.data.length; i += 16) {
                            const diff = Math.abs(imageData.data[i] - imageData.data[i+4]);
                            sharpness += diff;
                        }
                        const avgSharpness = sharpness / (imageData.data.length / 16);
                        
                        if (avgSharpness > 30) {
                            score += 40;
                            feedback.push('Imagem nítida ✓');
                        } else {
                            feedback.push('Imagem pode estar borrada');
                        }
                        
                        return { score, feedback, isValid: score >= 50 };
                    };
                </script>