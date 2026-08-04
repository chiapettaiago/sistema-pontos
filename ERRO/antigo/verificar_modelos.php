<?php
// verificar_modelos.php
$model_dir = __DIR__ . '/assets/models/';
$files = [
    'tiny_face_detector_model-weights_manifest.json',
    'tiny_face_detector_model-shard1',
    'face_landmark_68_model-weights_manifest.json',
    'face_landmark_68_model-shard1',
    'face_recognition_model-weights_manifest.json',
    'face_recognition_model-shard1'
];

echo "<h1>Verificação dos Modelos Face-API</h1>";
echo "<pre>";

foreach ($files as $file) {
    $path = $model_dir . $file;
    if (file_exists($path)) {
        $size = filesize($path);
        echo "✅ $file - OK (" . number_format($size) . " bytes)\n";
    } else {
        echo "❌ $file - NÃO ENCONTRADO!\n";
    }
}

echo "</pre>";
echo "<p>Pasta: " . realpath($model_dir) . "</p>";
?>