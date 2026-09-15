<?php
declare(strict_types=1);

// Material comercial público, disponível apenas por acesso direto à rota.
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

$file = __DIR__ . '/output/pontofacil/PontoFacil-Apresentacao-Premium.pptx';
if (!is_file($file) || !is_readable($file)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Apresentação indisponível.');
}

header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
header('Content-Disposition: attachment; filename="PontoFacil-Apresentacao-Premium.pptx"');
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');

if ($method === 'HEAD') {
    exit;
}

readfile($file);
