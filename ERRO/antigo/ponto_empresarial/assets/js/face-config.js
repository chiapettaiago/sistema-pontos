// assets/js/face-config.js - Configuração do Reconhecimento Facial
// Detecta automaticamente o ambiente e configura os caminhos

const FaceConfig = (function() {
    // Detectar se está em localhost
    function isLocalhost() {
        const hostname = window.location.hostname;
        return hostname === 'localhost' || 
               hostname === '127.0.0.1' || 
               hostname === '::1' ||
               hostname.endsWith('.local') ||
               hostname.endsWith('.test');
    }
    
    // Obter URL base
    function getBaseUrl() {
        const protocol = window.location.protocol;
        const host = window.location.host;
        const path = window.location.pathname;
        const basePath = path.substring(0, path.lastIndexOf('/'));
        return protocol + '//' + host + basePath;
    }
    
    // Caminho dos modelos
    function getModelPath() {
        if (isLocalhost()) {
            // Localhost: usar arquivos locais
            return getBaseUrl() + '/assets/models/';
        } else {
            // Produção: usar CDN
            return 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/weights/';
        }
    }
    
    // Simular modelo local (fallback se os arquivos não existirem)
    async function loadModelsWithFallback() {
        const modelPath = getModelPath();
        
        console.log('Carregando modelos de:', modelPath);
        console.log('Ambiente:', isLocalhost() ? 'LOCAL' : 'PRODUÇÃO');
        
        try {
            // Tentar carregar modelos
            await faceapi.nets.tinyFaceDetector.loadFromUri(modelPath);
            await faceapi.nets.faceLandmark68Net.loadFromUri(modelPath);
            await faceapi.nets.faceRecognitionNet.loadFromUri(modelPath);
            
            console.log('✅ Modelos carregados com sucesso!');
            return { success: true, source: isLocalhost() ? 'local' : 'cdn' };
        } catch (err) {
            console.warn('Erro ao carregar modelos do caminho principal:', err);
            
            // Fallback: tentar CDN se estiver em localhost
            if (isLocalhost()) {
                console.log('Tentando carregar modelos da CDN...');
                try {
                    const cdnPath = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/weights/';
                    await faceapi.nets.tinyFaceDetector.loadFromUri(cdnPath);
                    await faceapi.nets.faceLandmark68Net.loadFromUri(cdnPath);
                    await faceapi.nets.faceRecognitionNet.loadFromUri(cdnPath);
                    
                    console.log('✅ Modelos carregados da CDN!');
                    return { success: true, source: 'cdn-fallback' };
                } catch (cdnErr) {
                    console.error('❌ Falha ao carregar modelos da CDN:', cdnErr);
                    throw cdnErr;
                }
            }
            throw err;
        }
    }
    
    // Verificar se a câmera está disponível
    async function checkCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return { available: false, error: 'Navegador não suporta acesso à câmera' };
        }
        
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            stream.getTracks().forEach(track => track.stop());
            return { available: true, error: null };
        } catch (err) {
            let errorMsg = '';
            if (err.name === 'NotAllowedError') {
                errorMsg = 'Permissão negada. Clique no cadeado/ícone de câmera e permita o acesso.';
            } else if (err.name === 'NotFoundError') {
                errorMsg = 'Nenhuma câmera encontrada. Verifique se sua webcam está conectada.';
            } else {
                errorMsg = err.message;
            }
            return { available: false, error: errorMsg };
        }
    }
    
    // Exportar configurações
    return {
        isLocalhost: isLocalhost(),
        getBaseUrl: getBaseUrl,
        getModelPath: getModelPath,
        loadModels: loadModelsWithFallback,
        checkCamera: checkCamera,
        FACE_API_VERSION: '0.22.2'
    };
})();

// Exportar para uso global
window.FaceConfig = FaceConfig;