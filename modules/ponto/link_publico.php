<?php
$pageTitle = 'Links públicos para bater ponto';
$activePage = 'link_publico';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
redirectIfNotLoggedIn();
if (!in_array($_SESSION['usuario_tipo'] ?? '', ['super_admin', 'admin_empresa'], true)) { http_response_code(403); exit('Acesso negado.'); }

$empresaId = (int) ($_SESSION['empresa_id'] ?? 0);
if (($_SESSION['usuario_tipo'] ?? '') === 'super_admin') $empresaId = (int) ($_GET['empresa'] ?? $_POST['empresa_id'] ?? $empresaId);
if ($empresaId <= 0) { http_response_code(422); exit('Selecione uma empresa válida para gerar o link público.'); }

$db->exec("CREATE TABLE IF NOT EXISTS links_ponto_publico (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, empresa_id INT NOT NULL, criado_por INT NULL,
    expira_em DATETIME NOT NULL, criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_link_publico_empresa_criado (empresa_id, criado_em),
    KEY idx_link_publico_expiracao (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$duracoesPermitidas = [3600 => '1 hora', 28800 => '8 horas', 86400 => '1 dia', 604800 => '7 dias', 2592000 => '30 dias'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken();
    $duracao = (int) ($_POST['duracao'] ?? 2592000);
    if (!isset($duracoesPermitidas[$duracao])) $duracao = 2592000;
    $expiraEm = time() + $duracao;
    $stmt = $db->prepare('INSERT INTO links_ponto_publico (empresa_id, criado_por, expira_em) VALUES (:empresa_id, :criado_por, :expira_em)');
    $stmt->execute([':empresa_id' => $empresaId, ':criado_por' => (int) ($_SESSION['usuario_id'] ?? 0) ?: null, ':expira_em' => date('Y-m-d H:i:s', $expiraEm)]);
    $_SESSION['success'] = 'Novo link público gerado com sucesso.';
    $redirect = appUrl('/modules/ponto/link_publico') . (($_SESSION['usuario_tipo'] ?? '') === 'super_admin' ? '?empresa=' . $empresaId : '');
    header('Location: ' . $redirect); exit;
}

$stmt = $db->prepare('SELECT id, criado_em, expira_em, UNIX_TIMESTAMP(expira_em) AS expira_timestamp FROM links_ponto_publico WHERE empresa_id = :empresa_id ORDER BY criado_em DESC, id DESC LIMIT 20');
$stmt->execute([':empresa_id' => $empresaId]);
$linksGerados = $stmt->fetchAll();
if ($linksGerados === []) {
    $expiraEm = time() + 2592000;
    $stmt = $db->prepare('INSERT INTO links_ponto_publico (empresa_id, criado_por, expira_em) VALUES (:empresa_id, :criado_por, :expira_em)');
    $stmt->execute([':empresa_id' => $empresaId, ':criado_por' => (int) ($_SESSION['usuario_id'] ?? 0) ?: null, ':expira_em' => date('Y-m-d H:i:s', $expiraEm)]);
    $linksGerados[] = ['id' => (int) $db->lastInsertId(), 'criado_em' => date('Y-m-d H:i:s'), 'expira_em' => date('Y-m-d H:i:s', $expiraEm), 'expira_timestamp' => $expiraEm];
}

$publicBase = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(BASE_URL, '/');
foreach ($linksGerados as &$linkGerado) {
    $expiraTimestamp = (int) $linkGerado['expira_timestamp'];
    $assinatura = hash_hmac('sha256', $empresaId . '|' . $expiraTimestamp, APP_SIGNING_KEY . '|ponto-publico');
    $linkGerado['url'] = $publicBase . '/ponto-publico/?empresa=' . $empresaId . '&chave=' . rawurlencode($expiraTimestamp . '.' . $assinatura);
}
unset($linkGerado);
$success = $_SESSION['success'] ?? null; unset($_SESSION['success']);
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="pf-page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
  <div><h1><i class="fas fa-link me-2"></i>Links públicos</h1><p>Gere e acompanhe links para registro de ponto facial sem login.</p></div>
  <form method="post" class="d-flex gap-2 align-items-center"><?= csrfField() ?>
    <?php if (($_SESSION['usuario_tipo'] ?? '') === 'super_admin'): ?><input type="hidden" name="empresa_id" value="<?= $empresaId ?>"><?php endif; ?>
    <select name="duracao" class="form-select" aria-label="Validade do novo link"><?php foreach ($duracoesPermitidas as $segundos => $rotulo): ?><option value="<?= $segundos ?>" <?= $segundos === 2592000 ? 'selected' : '' ?>>Validade: <?= htmlspecialchars($rotulo) ?></option><?php endforeach; ?></select>
    <button class="btn btn-primary text-nowrap"><i class="fas fa-plus me-1"></i>Gerar link</button>
  </form>
</div>
<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
<div class="row g-4"><div class="col-12 col-xl-8"><section class="card border-0 shadow-sm"><div class="card-body p-4">
  <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h4 mb-1">Links gerados</h2><p class="text-body-secondary mb-0">Os 20 links mais recentes desta empresa.</p></div><span class="badge text-bg-light border"><?= count($linksGerados) ?> exibido(s)</span></div>
  <div class="vstack gap-3"><?php foreach ($linksGerados as $index => $item): $expirado = (int) $item['expira_timestamp'] <= time(); ?>
    <article class="border rounded-3 p-3 link-publico-item <?= $expirado ? 'opacity-75' : '' ?>" data-expires="<?= (int) $item['expira_timestamp'] ?>">
      <div class="d-flex justify-content-between align-items-start gap-3 mb-2 flex-wrap"><div><span class="badge status-badge <?= $expirado ? 'text-bg-secondary' : 'text-bg-success' ?>"><?= $expirado ? 'Expirado' : 'Ativo' ?></span><?php if ($index === 0): ?><span class="badge text-bg-primary ms-1">Mais recente</span><?php endif; ?></div><div class="text-end"><strong class="countdown d-block"><?= $expirado ? 'Expirado' : 'Calculando…' ?></strong><small class="text-body-secondary">Expira em <?= date('d/m/Y', (int) $item['expira_timestamp']) ?> às <?= date('H:i', (int) $item['expira_timestamp']) ?></small></div></div>
      <label class="form-label small fw-semibold" for="link-<?= (int) $item['id'] ?>">Link de acesso</label><div class="input-group"><input id="link-<?= (int) $item['id'] ?>" class="form-control" value="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>" readonly><button class="btn btn-outline-primary copy-link" type="button" data-target="link-<?= (int) $item['id'] ?>" <?= $expirado ? 'disabled' : '' ?>><i class="fas fa-copy me-1"></i>Copiar</button><a class="btn btn-outline-secondary <?= $expirado ? 'disabled' : '' ?>" href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i></a></div>
    </article><?php endforeach; ?></div>
</div></section></div><div class="col-12 col-xl-4"><section class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h5"><i class="fas fa-circle-info text-primary me-2"></i>Antes de usar</h2><ul class="text-body-secondary ps-3 mb-0"><li class="mb-2">Abra o link em um dispositivo no local de trabalho.</li><li class="mb-2">Use HTTPS e permita a câmera.</li><li class="mb-2">Cadastre previamente a face dos funcionários.</li><li>Ao chegar a zero, o link deixa de autorizar novas batidas.</li></ul></div></section></div></div>
<script>
(()=>{const format=s=>{if(s<=0)return'Expirado';const d=Math.floor(s/86400),h=Math.floor(s%86400/3600),m=Math.floor(s%3600/60),x=s%60;return d?`${d}d ${h}h ${m}min`:h?`${h}h ${m}min ${x}s`:`${m}min ${x}s`};const update=()=>{const now=Math.floor(Date.now()/1000);document.querySelectorAll('.link-publico-item').forEach(item=>{const left=Number(item.dataset.expires)-now;item.querySelector('.countdown').textContent=format(left);if(left<=0){const badge=item.querySelector('.status-badge');badge.textContent='Expirado';badge.className='badge status-badge text-bg-secondary';item.classList.add('opacity-75');item.querySelectorAll('.copy-link,a.btn').forEach(control=>{control.classList.add('disabled');if(control.tagName==='BUTTON')control.disabled=true})}})};document.querySelectorAll('.copy-link').forEach(button=>button.addEventListener('click',async()=>{const input=document.getElementById(button.dataset.target);try{await navigator.clipboard.writeText(input.value)}catch(_){input.select();document.execCommand('copy')}const original=button.innerHTML;button.innerHTML='<i class="fas fa-check me-1"></i>Copiado';setTimeout(()=>button.innerHTML=original,1800)}));update();setInterval(update,1000)})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
