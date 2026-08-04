// assets/js/face-config.js - Configuração local do reconhecimento facial
// Mantém o carregamento apenas pelos modelos do projeto

const FaceConfig = (function () {
    function getBaseUrl() {
        const protocol = window.location.protocol;
        const host = window.location.host;
        const path = window.location.pathname;
        const basePath = path.substring(0, path.lastIndexOf('/'));
        return protocol + '//' + host + basePath;
    }

    function getModelPath() {
        return getBaseUrl() + '/assets/models/';
    }

    async function loadModels() {
        const modelPath = getModelPath();

        console.log('Carregando modelos de:', modelPath);

        await faceapi.nets.tinyFaceDetector.loadFromUri(modelPath);
        await faceapi.nets.faceLandmark68Net.loadFromUri(modelPath);
        await faceapi.nets.faceRecognitionNet.loadFromUri(modelPath);

        console.log('Modelos carregados com sucesso');
        return { success: true, source: 'local' };
    }

    async function checkCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return { available: false, error: 'Navegador nao suporta acesso a camera' };
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            stream.getTracks().forEach(track => track.stop());
            return { available: true, error: null };
        } catch (err) {
            let errorMsg = '';
            if (err.name === 'NotAllowedError') {
                errorMsg = 'Permissao negada. Clique no cadeado/icone de camera e permita o acesso.';
            } else if (err.name === 'NotFoundError') {
                errorMsg = 'Nenhuma camera encontrada. Verifique se sua webcam esta conectada.';
            } else {
                errorMsg = err.message;
            }
            return { available: false, error: errorMsg };
        }
    }

    return {
        getBaseUrl: getBaseUrl,
        getModelPath: getModelPath,
        loadModels: loadModels,
        checkCamera: checkCamera,
        FACE_API_VERSION: '0.22.2'
    };
})();

window.FaceConfig = FaceConfig;
