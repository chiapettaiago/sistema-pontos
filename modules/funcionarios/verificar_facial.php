<?php
// Endpoint legado e inseguro removido: ele aceitava qualquer foto como o
// primeiro funcionario ativo. O fluxo atual envia um descritor ao login_face.
header('Content-Type: application/json; charset=UTF-8');
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'Este fluxo facial foi desativado. Atualize a pagina e utilize o reconhecimento atual.',
    'code' => 'LEGACY_FACE_ENDPOINT_DISABLED',
], JSON_UNESCAPED_UNICODE);
