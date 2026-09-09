<?php
$pageTitle = 'Link público para bater ponto';
$activePage = 'link_publico';
require_once __DIR__ . '/../../includes/auth.php';
redirectIfNotLoggedIn();
if (!in_array($_SESSION['usuario_tipo'] ?? '', ['super_admin', 'admin_empresa'], true)) { http_response_code(403); exit('Acesso negado.'); }
$empresaId = (int) ($_SESSION['empresa_id'] ?? 0);
if (($_SESSION['usuario_tipo'] ?? '') === 'super_admin') $empresaId = (int) ($_GET['empresa'] ?? $empresaId);
$chave = $empresaId > 0 ? hash_hmac('sha256', (string) $empresaId, DB_PASS . '|ponto-publico') : '';
$publicBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(BASE_URL, '/');
$link = $publicBase . '/ponto-publico/?empresa=' . $empresaId . '&chave=' . $chave;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="pf-page-header"><div><h1><i class="fas fa-link"></i> Link público</h1><p>Disponibilize uma tela segura para registro facial sem login.</p></div><a class="btn btn-outline-primary" href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt me-1"></i> Abrir tela</a></div>
<div class="row g-4"><div class="col-12 col-xl-8"><section class="card border-0 shadow-sm h-100"><div class="card-body p-4 p-lg-5"><div class="d-flex align-items-start gap-3 mb-4"><div class="rounded-circle bg-primary-subtle text-primary p-3"><i class="fas fa-face-smile fa-lg"></i></div><div><h2 class="h4 mb-1">Endereço do quiosque facial</h2><p class="text-body-secondary mb-0">Abra este link em um tablet, celular ou computador no local de trabalho.</p></div></div><label for="publicLink" class="form-label fw-semibold">Link de acesso</label><div class="input-group input-group-lg"><input id="publicLink" class="form-control" value="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" readonly><button class="btn btn-primary" type="button" id="copyLink"><i class="fas fa-copy me-1"></i> Copiar</button></div><div id="copyFeedback" class="small text-success mt-2" hidden>Link copiado.</div><div class="alert alert-info mt-4 mb-0"><i class="fas fa-shield-halved me-2"></i><strong>Como funciona:</strong> o funcionário será identificado pela face cadastrada. Nenhum login ou seleção manual de nome é permitido.</div></div></section></div><div class="col-12 col-xl-4"><section class="card border-0 shadow-sm h-100"><div class="card-body p-4"><h2 class="h5"><i class="fas fa-circle-info text-primary me-2"></i>Antes de usar</h2><ul class="text-body-secondary ps-3 mb-0"><li class="mb-2">Use HTTPS em produção.</li><li class="mb-2">Cadastre a face de cada funcionário.</li><li>A câmera precisa ser autorizada no navegador.</li></ul></div></section></div></div>
<script>document.getElementById('copyLink').addEventListener('click',async()=>{const input=document.getElementById('publicLink');try{await navigator.clipboard.writeText(input.value)}catch(_){input.select();document.execCommand('copy')}document.getElementById('copyFeedback').hidden=false});</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
