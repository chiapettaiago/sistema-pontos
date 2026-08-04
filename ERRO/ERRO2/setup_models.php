<?php
$host = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($host, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Acesso restrito');
}

// setup_models.php - Script para baixar os modelos do face-api.js localmente
// Execute este script uma vez para baixar os modelos
// Acesse: http://localhost/setup_models.php

echo "<!DOCTYPE html>";
echo "<html lang='pt-br'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>Setup - Modelos de Reconhecimento Facial</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }";
echo "h1 { color: #667eea; }";
echo ".success { color: #10b981; }";
echo ".error { color: #ef4444; }";
echo ".warning { color: #f59e0b; }";
echo "hr { margin: 20px 0; }";
echo ".btn { display: inline-block; padding: 10px 20px; margin: 10px; text-decoration: none; border-radius: 5px; }";
echo ".btn-primary { background: #667eea; color: white; }";
echo ".btn-secondary { background: #6b7280; color: white; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<h1>🔧 Configurando Modelos do Reconhecimento Facial</h1>";

$models_dir = __DIR__ . '/assets/models/';

// Criar diretório se não existir
if (!file_exists($models_dir)) {
    mkdir($models_dir, 0777, true);
    echo "<p>✅ Pasta 'assets/models' criada</p>";
} else {
    echo "<p>✅ Pasta 'assets/models' já existe</p>";
}

// Função para baixar arquivo
function downloadFile($url, $path) {
    $ch = curl_init($url);
    $fp = fopen($path, 'wb');
    
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    fclose($fp);
    
    if ($result !== false && $httpCode === 200) {
        return ['success' => true, 'size' => filesize($path)];
    } else {
        if (file_exists($path)) {
            unlink($path);
        }
        return ['success' => false, 'error' => $error ?: "HTTP $httpCode"];
    }
}

// Lista de arquivos para baixar
$files = [
    'tiny_face_detector_model-weights_manifest.json' => 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/tiny_face_detector_model-weights_manifest.json',
    'tiny_face_detector_model-shard1' => 'https://github.com/justadudewhohacks/face-api.js/raw/master/weights/tiny_face_detector_model-shard1',
    'face_landmark_68_model-weights_manifest.json' => 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_landmark_68_model-weights_manifest.json',
    'face_landmark_68_model-shard1' => 'https://github.com/justadudewhohacks/face-api.js/raw/master/weights/face_landmark_68_model-shard1',
    'face_recognition_model-weights_manifest.json' => 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_recognition_model-weights_manifest.json',
    'face_recognition_model-shard1' => 'https://github.com/justadudewhohacks/face-api.js/raw/master/weights/face_recognition_model-shard1',
    'face_recognition_model-shard2' => 'https://github.com/justadudewhohacks/face-api.js/raw/master/weights/face_recognition_model-shard2'
];

echo "<h2>📥 Baixando modelos...</h2>";

$success = 0;
$errors = 0;

foreach ($files as $filename => $url) {
    $filepath = $models_dir . $filename;
    
    echo "<p>Baixando: <strong>$filename</strong>... ";
    
    // Verificar se arquivo já existe e tem tamanho razoável
    if (file_exists($filepath) && filesize($filepath) > 1000) {
        $size = filesize($filepath);
        echo "<span class='success'>✅ Já existe ({$size} bytes)</span></p>";
        $success++;
        continue;
    }
    
    $result = downloadFile($url, $filepath);
    
    if ($result['success']) {
        echo "<span class='success'>✅ OK ({$result['size']} bytes)</span></p>";
        $success++;
    } else {
        echo "<span class='error'>❌ FALHA - {$result['error']}</span></p>";
        $errors++;
    }
    
    // Pequena pausa para não sobrecarregar o servidor
    usleep(100000);
}

echo "<hr>";

// Resumo
echo "<h2>📊 Resumo:</h2>";
echo "<p><span class='success'>✅ Baixados com sucesso: $success arquivos</span></p>";
echo "<p><span class='error'>❌ Falhas: $errors arquivos</span></p>";

if ($errors > 0) {
    echo "<p class='warning'>⚠️ Alguns arquivos não puderam ser baixados. O sistema tentará usar a CDN como fallback.</p>";
    echo "<p>Isso não impede o funcionamento do sistema, pois os modelos podem ser carregados da internet.</p>";
}

// Verificar modelos
echo "<h2>🔍 Verificando modelos baixados:</h2>";
echo "<ul>";
foreach ($files as $filename => $url) {
    $filepath = $models_dir . $filename;
    if (file_exists($filepath)) {
        $size = filesize($filepath);
        echo "<li><span class='success'>✅ $filename - {$size} bytes</span></li>";
    } else {
        echo "<li><span class='error'>❌ $filename - Não encontrado</span></li>";
    }
}
echo "</ul>";

echo "<hr>";
echo "<div style='text-align: center;'>";
echo "<a href='modules/biometrico/cadastrar.php' class='btn btn-primary'>🔐 Ir para Cadastro Biométrico</a>";
echo "<a href='teste_camera.php' class='btn btn-secondary'>📷 Testar Câmera</a>";
echo "</div>";

echo "</body>";
echo "</html>";
?>

