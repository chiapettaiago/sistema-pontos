<?php
$pageTitle = 'Biometria do dispositivo';
$activePage = 'perfil';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';

if (empty($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$db = (new Database())->getConnection();
require_once __DIR__ . '/../../includes/passkey.php';
passkeyEnsureTable($db);
$identidade = passkeySessionIdentity();
$stmt = $db->prepare('SELECT id,nome,ultimo_uso,created_at FROM credenciais_passkey WHERE usuario_tipo=:tipo AND usuario_id=:id ORDER BY created_at DESC');
$stmt->execute([':tipo'=>$identidade['tipo'], ':id'=>$identidade['id']]);
$dispositivos = $stmt->fetchAll();
$baseUrl = rtrim(BASE_URL, '/');
?>
<div class="pf-page-header"><h1><i class="fas fa-fingerprint me-2 text-primary"></i>Biometria do dispositivo</h1><p class="text-muted">Use impressão digital, reconhecimento do aparelho, Windows Hello ou PIN para entrar.</p></div>
<div class="row g-4">
    <div class="col-lg-7"><div class="card"><div class="card-body p-4">
        <h2 class="h5">Cadastrar este dispositivo</h2>
        <p class="text-muted">A biometria permanece protegida no aparelho. O sistema armazena somente uma chave pública.</p>
        <div class="mb-3"><label for="passkeyName" class="form-label">Nome do dispositivo</label><input id="passkeyName" class="form-control" maxlength="100" value="Meu dispositivo"></div>
        <button id="registerPasskey" class="btn btn-primary"><i class="fas fa-fingerprint me-1"></i>Cadastrar biometria deste dispositivo</button>
        <div id="passkeyStatus" class="alert mt-3 d-none" role="status"></div>
    </div></div></div>
    <div class="col-lg-5"><div class="card"><div class="card-body p-4"><h2 class="h5">Dispositivos cadastrados</h2>
        <?php if (!$dispositivos): ?><p class="text-muted mb-0">Nenhum dispositivo cadastrado.</p><?php else: ?><div class="list-group list-group-flush"><?php foreach ($dispositivos as $item): ?><div class="list-group-item px-0"><strong><?= htmlspecialchars($item['nome']) ?></strong><small class="d-block text-muted">Cadastrado em <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?><?= $item['ultimo_uso'] ? ' · Último uso '.date('d/m/Y H:i', strtotime($item['ultimo_uso'])) : '' ?></small></div><?php endforeach; ?></div><?php endif; ?>
    </div></div></div>
</div>
<script src="<?= $baseUrl ?>/assets/js/passkey.js"></script>
<script>
const registerButton=document.querySelector('#registerPasskey'),statusBox=document.querySelector('#passkeyStatus');
const showStatus=(message,ok=false)=>{statusBox.textContent=message;statusBox.className='alert mt-3 '+(ok?'alert-success':'alert-danger')};
registerButton.addEventListener('click',async()=>{registerButton.disabled=true;statusBox.className='alert mt-3 alert-info';statusBox.textContent='Aguardando confirmação biométrica do dispositivo…';try{if(!window.PublicKeyCredential||!navigator.credentials)throw Error('Este navegador não oferece suporte a passkeys.');let response=await fetch('<?= $baseUrl ?>/api/passkey.php?acao=cadastro_opcoes',{cache:'no-store'}),result=await response.json();if(!response.ok||!result.success)throw Error(result.message);const options=PFPasskey.prepareCreate(result.data.options);const credential=await navigator.credentials.create(options);response=await fetch('<?= $baseUrl ?>/api/passkey.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'cadastro_concluir',nome:document.querySelector('#passkeyName').value,clientDataJSON:PFPasskey.encode(credential.response.clientDataJSON),attestationObject:PFPasskey.encode(credential.response.attestationObject),transports:credential.response.getTransports?credential.response.getTransports():[]})});result=await response.json();if(!response.ok||!result.success)throw Error(result.message);showStatus(result.message,true);setTimeout(()=>location.reload(),1200)}catch(error){showStatus(error.message||'Não foi possível cadastrar este dispositivo.')}finally{registerButton.disabled=false}});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
