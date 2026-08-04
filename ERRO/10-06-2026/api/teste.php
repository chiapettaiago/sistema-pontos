<?php
// api/teste.php - Teste simples da API
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'message' => 'API funcionando!']);
?>