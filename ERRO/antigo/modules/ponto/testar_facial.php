<?php
// testar_facial.php - Testar se o processar_facial.php está funcionando
session_start();
header('Content-Type: application/json');

// Simular uma sessão para teste
if (!isset($_SESSION['funcionario_id'])) {
    $_SESSION['funcionario_id'] = 1; // Coloque um ID de funcionário que existe
    $_SESSION['usuario_id'] = 1;
}

// Chamar o processar_facial com dados de teste
$testData = [
    'tipo' => 'entrada',
    'foto' => null, // sem foto para teste
    'funcionario_id' => 1
];

$ch = curl_init('http://localhost:8080/ponto_empresarial/modules/ponto/processar_facial.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Response: " . $response . "\n";
?>