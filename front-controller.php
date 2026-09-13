<?php
declare(strict_types=1);

/**
 * Front controller HTTP.
 * Resolve URLs públicas sem expor a extensão  ou caminhos físicos.
 */

$projectRoot = realpath(__DIR__);
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$requestPath = rawurldecode((string) (parse_url($requestUri, PHP_URL_PATH) ?: '/'));
$query = (string) (parse_url($requestUri, PHP_URL_QUERY) ?? '');
$frontScriptName = (string) (parse_url($_SERVER['SCRIPT_NAME'] ?? '/front-controller', PHP_URL_PATH) ?: '/front-controller');
$basePath = rtrim(str_replace('\\', '/', dirname($frontScriptName)), '/');
if ($basePath === '/' || $basePath === '.') $basePath = '';

if ($projectRoot === false || str_contains($requestPath, "\0") || preg_match('#(?:^|/)\.\.(?:/|$)#', $requestPath)) {
    http_response_code(400);
    exit('Requisição inválida.');
}

// Nunca aceite caminhos físicos como parte de uma URL pública.
if (preg_match('#^/(?:var|home|etc|usr|opt|srv)/#i', $requestPath)) {
    http_response_code(404);
    exit('Página não encontrada.');
}

// Canonicaliza somente a URL recebida, sem usar SCRIPT_FILENAME ou DOCUMENT_ROOT.
if (preg_match('#\.php/?$#i', $requestPath)) {
    $canonicalPath = preg_replace('#/index\.php/?$#i', '/', $requestPath);
    if ($canonicalPath === $requestPath) {
        $canonicalPath = preg_replace('#\.php/?$#i', '', $requestPath);
    }
    $canonicalPath = $canonicalPath === '' ? '/' : $canonicalPath;
    header('Location: ' . $canonicalPath . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
}

$routePath = $requestPath;
if ($basePath !== '' && ($requestPath === $basePath || str_starts_with($requestPath, $basePath . '/'))) {
    $routePath = substr($requestPath, strlen($basePath));
}
$route = '/' . trim($routePath, '/');
if ($route === '/') {
    $relativeTarget = 'index.php';
} elseif ($route === '/ponto-publico') {
    $relativeTarget = 'modules/ponto/ponto_publico.php';
} else {
    $relativeTarget = ltrim($route, '/') . '.php';
}

$blockedRoutes = [
    'front-controller.php', 'check_tables.php', 'criar_tabelas.php',
    'debug_funcao.php', 'diagnostic_facial.php', 'diagnostico.php',
    'scratch.php', 'setup_models.php', 'verificar_modelos.php',
];
if (in_array($relativeTarget, $blockedRoutes, true)) {
    http_response_code(404);
    exit('Página não encontrada.');
}

$target = realpath($projectRoot . DIRECTORY_SEPARATOR . $relativeTarget);
if ($target === false || !is_file($target) || !str_starts_with($target, $projectRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('Página não encontrada.');
}

$_SERVER['SCRIPT_FILENAME'] = $target;
$_SERVER['SCRIPT_NAME'] = $basePath . ($route === '/' ? '/index.php' : $route . '.php');
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

// Muitos módulos legados usam includes relativos ao próprio diretório.
chdir(dirname($target));
require $target;
