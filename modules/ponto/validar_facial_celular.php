<?php

header('Content-Type: application/json; charset=UTF-8');
http_response_code(422);

echo json_encode([
    'success' => false,
    'message' => 'Use o reconhecimento facial principal ou solicite autorizacao ao gerente.',
], JSON_UNESCAPED_UNICODE);
