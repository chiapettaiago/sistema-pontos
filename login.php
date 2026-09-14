<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/router.php';

if (isset($_SESSION['usuario_id'])) appRedirectAfterLogin($_SESSION['usuario_tipo'] ?? 'funcionario');
$error = '';
$database = new Database();
$db = $database->getConnection();

function finalizarLoginSistema(array $u): void {
    session_regenerate_id(true);
    foreach (['id'=>'usuario_id','nome'=>'usuario_nome','email'=>'usuario_email','tipo'=>'usuario_tipo','empresa_id'=>'empresa_id'] as $key => $session) $_SESSION[$session] = $u[$key] ?? null;
    $_SESSION['empresa_nome'] = $u['empresa_nome'] ?? null; $_SESSION['funcionario_id'] = $u['funcionario_id'] ?? null; $_SESSION['filial_id'] = $u['funcionario_filial_id'] ?? null; $_SESSION['usuario_filial_id'] = $u['funcionario_filial_id'] ?? null; $_SESSION['tipo_login'] = 'sistema';
    $_SESSION['user_id'] = $u['id']; $_SESSION['user_nome'] = $u['nome']; $_SESSION['user_email'] = $u['email']; $_SESSION['user_tipo'] = $u['tipo'];
    $_SESSION['db_permissions'] = ['gerenciar_filiais'=>(bool)($u['pode_gerenciar_filiais'] ?? in_array($u['tipo'],['super_admin','admin_empresa'],true)),'gerenciar_funcionarios'=>(bool)($u['pode_gerenciar_funcionarios'] ?? in_array($u['tipo'],['super_admin','admin_empresa','gestor'],true)),'ver_relatorios'=>(bool)($u['pode_ver_relatorios'] ?? in_array($u['tipo'],['super_admin','admin_empresa','gestor','supervisor'],true))];
}
function finalizarLoginFuncionario(array $f): void {
    session_regenerate_id(true);
    $_SESSION['usuario_id']=$f['id']; $_SESSION['usuario_nome']=$f['nome']; $_SESSION['usuario_email']=$f['email']; $_SESSION['usuario_tipo']=appNormalizeUserType($f['tipo_usuario'] ?? 'funcionario');
    $_SESSION['empresa_id']=$f['empresa_id']; $_SESSION['empresa_nome']=$f['empresa_nome'] ?? null; $_SESSION['filial_id']=$f['filial_id'] ?? null;
    $_SESSION['usuario_filial_id']=$f['filial_id'] ?? null; $_SESSION['funcionario_id']=$f['id']; $_SESSION['tipo_login']='funcionario';
    $_SESSION['funcionario_nome']=$f['nome']; $_SESSION['funcionario_email']=$f['email']; $_SESSION['funcionario_empresa_id']=$f['empresa_id'];
    $_SESSION['db_permissions']=['gerenciar_filiais'=>(int)($f['pode_gerenciar_filiais']??0)===1,'gerenciar_funcionarios'=>(int)($f['pode_gerenciar_funcionarios']??0)===1,'ver_relatorios'=>(int)($f['pode_ver_relatorios']??0)===1];
}
function senhaConfere(string $in, string $saved): bool {
    if (password_verify($in, $saved)) return true;
    $saved = trim($saved);
    return strlen($saved) === 32 && ctype_xdigit($saved) ? hash_equals(strtolower($saved), md5($in)) : hash_equals($saved, $in);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken(); $email=trim($_POST['email'] ?? ''); $senha=$_POST['senha'] ?? '';
    if ($email === '' || $senha === '') $error='Informe e-mail e senha.';
    elseif (($remaining = loginThrottleRemaining($email)) > 0) $error='Muitas tentativas. Aguarde ' . (int) ceil($remaining / 60) . ' minuto(s).';
    else try {
        $stmt=$db->prepare("SELECT u.*, e.nome empresa_nome, f.id funcionario_id, f.filial_id funcionario_filial_id, f.pode_gerenciar_filiais, f.pode_gerenciar_funcionarios, f.pode_ver_relatorios FROM usuarios_sistema u LEFT JOIN empresas e ON e.id=u.empresa_id LEFT JOIN funcionarios f ON f.usuario_sistema_id=u.id AND f.status='ativo' WHERE u.email=:email AND u.status='ativo' LIMIT 1"); $stmt->execute([':email'=>$email]); $u=$stmt->fetch();
        if ($u && senhaConfere($senha,$u['senha'])) { if (password_needs_rehash($u['senha'], PASSWORD_DEFAULT)) $db->prepare('UPDATE usuarios_sistema SET senha=:senha WHERE id=:id')->execute([':senha'=>password_hash($senha,PASSWORD_DEFAULT),':id'=>$u['id']]); loginThrottleClear($email); finalizarLoginSistema($u); logAcao($db,'LOGIN','usuarios_sistema',$u['id'],"Login realizado: {$u['email']}"); appRedirectAfterLogin($u['tipo']); }
        $stmt=$db->prepare("SELECT f.*, e.nome_empresa empresa_nome FROM funcionarios f LEFT JOIN empresa e ON e.id=f.empresa_id WHERE f.email=:email AND f.status='ativo' LIMIT 1"); $stmt->execute([':email'=>$email]); $f=$stmt->fetch();
        if ($f && senhaConfere($senha,$f['senha'])) { if (password_needs_rehash($f['senha'], PASSWORD_DEFAULT)) $db->prepare('UPDATE funcionarios SET senha=:senha WHERE id=:id')->execute([':senha'=>password_hash($senha,PASSWORD_DEFAULT),':id'=>$f['id']]); loginThrottleClear($email); finalizarLoginFuncionario($f); logAcao($db,'LOGIN','funcionarios',$f['id'],"Login funcionario: {$f['email']}"); appRedirectAfterLogin($_SESSION['usuario_tipo']); }
        loginThrottleFailure($email); $error='E-mail ou senha inválidos.';
    } catch (Exception $e) { error_log('Erro ao fazer login: '.$e->getMessage()); $error='Não foi possível entrar agora. Tente novamente.'; }
}
$csrf_token=generateCSRFToken(); $baseUrl=rtrim(BASE_URL,'/');
?>
<!doctype html>
<html lang="pt-BR"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#f7f6f9">
<title>Entrar · Ponto Fácil</title>
<link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/public-responsive.css">
<link rel="icon" type="image/svg+xml" href="<?= $baseUrl ?>/assets/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
/* Tela de login pública — paleta e formas alinhadas ao design system do app (style.css). */
:root {
    --pf-primary: #6d28d9;
    --pf-primary-strong: #581c87;
    --pf-secondary: #0f9f91;
    --pf-accent: #8b5cf6;
    --pf-gradient: linear-gradient(135deg, #581c87 0%, #7c3aed 52%, #0f9f91 125%);
    --ink: #28232d;
    --muted: #7f7787;
    --card: #ffffff;
    --line: #e7e1e9;
    --bg-tertiary: #f0edf3;
    --success: #0f9f91;
    --danger: #f2646a;
}

* { box-sizing: border-box; }

html, body { height: 100%; }

body {
    margin: 0;
    min-height: 100dvh;
    font-family: 'Nunito Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: var(--ink);
    background: #f7f6f9;
    letter-spacing: -.005em;
}

body::before,
body::after {
    content: '';
    position: fixed;
    width: 44vw;
    height: 44vw;
    max-width: 620px;
    max-height: 620px;
    border-radius: 50%;
    filter: blur(60px);
    pointer-events: none;
}
body::before { background: var(--pf-primary); opacity: .10; top: -20vw; left: -14vw; }
body::after  { background: var(--pf-secondary); opacity: .10; right: -18vw; bottom: -22vw; }

.shell {
    position: relative;
    z-index: 1;
    width: min(1120px, 100%);
    min-height: 100dvh;
    margin: auto;
    padding: clamp(16px, 4vw, 32px);
    padding-block: max(16px, env(safe-area-inset-top)) max(16px, env(safe-area-inset-bottom));
    display: grid;
    grid-template-columns: 1.05fr .95fr;
    align-items: center;
    gap: clamp(24px, 5vw, 72px);
}
.shell > * { min-width: 0; max-width: 100%; }

/* Coluna institucional */
.brand { max-width: 480px; }
.brand-mark {
    display: grid;
    place-items: center;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--pf-gradient);
    color: #fff;
    font-size: 22px;
    box-shadow: 0 12px 30px rgba(109, 40, 217, .22);
}
.brand h1 {
    margin: 22px 0 12px;
    font-size: clamp(30px, 4.4vw, 50px);
    font-weight: 800;
    letter-spacing: -.035em;
    line-height: 1.06;
    color: var(--ink);
}
.brand p { margin: 0; max-width: 420px; color: var(--muted); font-size: 16px; line-height: 1.6; }
.highlights { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 32px; color: #55505c; font-size: 13px; }
.highlights i { color: var(--success); margin-right: 6px; }

/* Cartão de acesso */
.card {
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: 26px;
    padding: 28px;
    box-shadow: 0 20px 60px rgba(45, 27, 51, .10);
}
.card-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 18px; }
.card-head h2 { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: -.02em; color: var(--ink); }
.secure { display: inline-flex; align-items: center; gap: 5px; color: var(--muted); font-size: 12px; white-space: nowrap; }
.secure i { color: var(--success); }

.camera {
    position: relative;
    aspect-ratio: 4 / 3;
    overflow: hidden;
    border-radius: 18px;
    background: #171419;
    border: 1px solid var(--line);
}
video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); display: block; }
.face-guide {
    position: absolute;
    width: 46%;
    aspect-ratio: 1;
    top: 45%;
    left: 50%;
    transform: translate(-50%, -50%);
    border: 2px solid rgba(255, 255, 255, .75);
    border-radius: 48% 48% 44% 44%;
    box-shadow: 0 0 0 999px rgba(0, 0, 0, .18);
    transition: .25s;
}
.camera.detected .face-guide { border-color: var(--success); box-shadow: 0 0 0 999px rgba(0, 0, 0, .18), 0 0 30px rgba(15, 159, 145, .55); }
.camera-label {
    position: absolute;
    inset: auto 12px 12px;
    padding: 9px 12px;
    border-radius: 12px;
    background: rgba(23, 20, 25, .78);
    color: #f8fafc;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.4;
    text-align: center;
    backdrop-filter: blur(8px);
}

.status {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 46px;
    margin: 14px 0;
    padding: 11px 13px;
    border: 1px solid rgba(109, 40, 217, .16);
    border-radius: 12px;
    background: rgba(109, 40, 217, .06);
    color: var(--pf-primary-strong);
    font-size: 13.5px;
    font-weight: 600;
    line-height: 1.4;
}
.status i { flex: 0 0 18px; width: 18px; text-align: center; font-size: 15px; }
.status span { display: block; min-width: 0; }
.status.success { background: rgba(15, 159, 145, .10); border-color: rgba(15, 159, 145, .24); color: #0b6e64; }
.status.success i { color: var(--success); }
.status.error { background: rgba(242, 100, 106, .10); border-color: rgba(242, 100, 106, .24); color: #b83d43; }
.status.error i { color: var(--danger); }

button { font: inherit; }
.primary {
    width: 100%;
    min-height: 50px;
    border: 0;
    border-radius: 999px;
    color: #fff;
    background: var(--pf-gradient);
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 10px 24px rgba(109, 40, 217, .22);
    transition: transform .18s ease, filter .18s ease, box-shadow .18s ease;
}
.primary:hover { transform: translateY(-1px); filter: brightness(1.06); box-shadow: 0 14px 30px rgba(109, 40, 217, .28); }
.primary:disabled { opacity: .6; cursor: wait; transform: none; }

.divider { display: flex; align-items: center; gap: 12px; color: var(--muted); font-size: 12px; margin: 18px 0; }
.divider::before, .divider::after { content: ''; height: 1px; flex: 1; background: var(--line); }

.password-toggle { width: 100%; padding: 6px 0; border: 0; background: transparent; color: var(--pf-primary); font-weight: 700; cursor: pointer; }
.password { display: none; margin-top: 16px; }
.password.open { display: block; }

label { display: block; margin: 0 0 7px; color: #55505c; font-size: 13px; font-weight: 700; }
.field { position: relative; margin-bottom: 14px; }
.field i { position: absolute; left: 14px; top: 39px; color: var(--muted); font-size: 14px; }
.field input {
    width: 100%;
    height: 48px;
    border: 1px solid var(--line);
    border-radius: 13px;
    background: var(--bg-tertiary);
    color: var(--ink);
    outline: 0;
    padding: 0 14px 0 40px;
    font: inherit;
    transition: border-color .18s ease, box-shadow .18s ease;
}
.field input:focus { border-color: var(--pf-primary); background: #fff; box-shadow: 0 0 0 3px rgba(109, 40, 217, .14); }

.alert {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 15px;
    padding: 11px 13px;
    border-radius: 12px;
    background: rgba(242, 100, 106, .10);
    color: #b83d43;
    font-size: 13px;
    font-weight: 600;
}

.footer { margin: 18px 0 0; color: var(--muted); font-size: 12px; text-align: center; }

/* ===== Responsivo ===== */

/* Telas até tablet: empilha em uma coluna, card centralizado. */
@media (max-width: 900px) {
    .shell {
        grid-template-columns: 1fr;
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-height: 100dvh;
        gap: 20px;
    }
    .brand { max-width: none; text-align: center; margin: 0 auto; }
    .brand-mark { margin: 0 auto; width: 46px; height: 46px; font-size: 19px; }
    .brand h1 { font-size: clamp(26px, 6vw, 34px); margin-top: 14px; }
    .brand p { margin: 0 auto; }
    .highlights { justify-content: center; margin-top: 18px; }
    .card { width: min(480px, 100%); margin: 0 auto; padding: 20px; border-radius: 22px; }
}

/* Login com senha aberto em tela pequena: permite rolar em vez de cortar. */
html.password-open, html.password-open body { overflow-y: auto; }
html.password-open .shell { min-height: 100dvh; height: auto; }
@media (max-width: 900px) {
    .shell { height: 100dvh; overflow: hidden; }
    html.password-open .shell { height: auto; overflow: visible; padding-block: 20px; }
}

/* Marketing sai de cena quando não há espaço vertical (celular + teclado, landscape curto). */
@media (max-width: 900px) and (max-height: 680px) {
    .brand { display: none; }
    .camera { aspect-ratio: auto; height: min(30dvh, 190px); }
    .footer { display: none; }
    .card { padding: 16px; }
}

/* Telefones bem pequenos. */
@media (max-width: 380px) {
    .shell { padding: 14px; }
    .highlights { gap: 12px; font-size: 11px; }
    .card { padding: 16px; border-radius: 18px; }
    .field input { height: 46px; }
}

/* Desktop com pouca altura (notebooks pequenos, zoom alto). */
@media (min-width: 901px) and (max-height: 720px) {
    .shell { gap: 40px; padding-block: 14px; }
    .card { padding: 22px; }
    .camera { aspect-ratio: auto; height: min(38dvh, 240px); }
    .card-head { margin-bottom: 12px; }
    .status { margin: 10px 0; }
    .divider { margin: 12px 0; }
    .password { margin-top: 12px; }
    .footer { margin: 10px 0 0; }
    .highlights { margin-top: 20px; }
}

@media (prefers-reduced-motion: reduce) {
    * { transition-duration: .01ms !important; }
}
</style></head><body>
<main class="shell"><section class="brand"><div class="brand-mark"><i class="fa-regular fa-clock"></i></div><h1>O ponto começa com um olhar.</h1><p>Entre de forma rápida e segura com o reconhecimento facial. Seus dados e sua jornada, sempre no controle.</p><div class="highlights"><span><i class="fa-solid fa-circle-check"></i>Leitura segura</span><span><i class="fa-solid fa-circle-check"></i>Acesso rápido</span><span><i class="fa-solid fa-circle-check"></i>Pronto para celular</span></div></section>
<section class="card" aria-labelledby="login-title"><div class="card-head"><h2 id="login-title">Reconhecimento facial</h2><span class="secure"><i class="fa-solid fa-shield-halved"></i>Ambiente seguro</span></div><?php if($error): ?><div class="alert"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?><div class="camera" id="camera"><video id="video" autoplay muted playsinline aria-label="Prévia da câmera"></video><div class="face-guide"></div><div class="camera-label" id="cameraLabel">Preparando a câmera…</div></div><div id="status" class="status"><i class="fa-solid fa-spinner fa-spin"></i><span>Carregando reconhecimento facial…</span></div><button class="primary" id="faceButton" type="button" disabled><i class="fa-solid fa-camera"></i> Reconhecer e entrar</button><div class="divider">ou</div><button class="password-toggle" id="passkeyButton" type="button"><i class="fa-solid fa-fingerprint"></i> Entrar com biometria do dispositivo</button><div class="divider">ou</div><button class="password-toggle" id="passwordToggle" type="button"><i class="fa-solid fa-key"></i> Entrar com e-mail e senha</button><form class="password" id="passwordForm" method="post" novalidate><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><div class="field"><label for="email">E-mail</label><i class="fa-regular fa-envelope"></i><input id="email" name="email" type="email" autocomplete="email" required></div><div class="field"><label for="senha">Senha</label><i class="fa-solid fa-lock"></i><input id="senha" name="senha" type="password" autocomplete="current-password" required></div><button class="primary" type="submit"><i class="fa-solid fa-arrow-right-to-bracket"></i> Entrar com senha</button></form><p class="footer">A câmera e a biometria do dispositivo requerem HTTPS (ou localhost).</p></section></main>
<script src="<?= $baseUrl ?>/assets/js/face-api.min.js"></script><script>
(()=>{const b=<?= json_encode($baseUrl) ?>,v=document.querySelector('#video'),c=document.querySelector('#camera'),l=document.querySelector('#cameraLabel'),s=document.querySelector('#status'),btn=document.querySelector('#faceButton'),passwordToggle=document.querySelector('#passwordToggle');let ready=false,stream,busy=false,timer,stable=0;const say=(text,type='info')=>{s.className='status '+type;s.innerHTML='<i class="fa-solid '+(type==='error'?'fa-circle-exclamation':type==='success'?'fa-circle-check':'fa-spinner fa-spin')+'"></i><span>'+text+'</span>';l.textContent=text},stop=()=>{if(timer)clearInterval(timer);if(stream)stream.getTracks().forEach(t=>t.stop())};async function models(){await Promise.all([faceapi.nets.tinyFaceDetector.loadFromUri(b+'/assets/models'),faceapi.nets.faceLandmark68Net.loadFromUri(b+'/assets/models'),faceapi.nets.faceRecognitionNet.loadFromUri(b+'/assets/models')]);ready=true}async function find(){if(busy||!ready||!v.videoWidth)return;try{const f=await faceapi.detectSingleFace(v,new faceapi.TinyFaceDetectorOptions({inputSize:224,scoreThreshold:.55})).withFaceLandmarks().withFaceDescriptor();if(f){stable++;c.classList.add('detected');say(stable>=2?'Rosto detectado. Pronto para entrar.':'Rosto detectado. Mantenha-se na marcação.','success')}else{stable=0;c.classList.remove('detected');say('Posicione o rosto dentro da marcação.')}}catch(_){}}async function start(){try{if(!window.isSecureContext&&!['localhost','127.0.0.1','::1'].includes(location.hostname))throw Error('Use HTTPS para liberar a câmera.');await models();stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user',width:{ideal:720},height:{ideal:540}},audio:false});v.srcObject=stream;await v.play();btn.disabled=false;say('Posicione o rosto dentro da marcação.');timer=setInterval(find,850)}catch(e){say(e.message||'Não foi possível iniciar a câmera. Use e-mail e senha.','error')}}async function login(){if(busy)return;busy=true;btn.disabled=true;say('Validando sua identidade…');try{const f=await faceapi.detectSingleFace(v,new faceapi.TinyFaceDetectorOptions({inputSize:224,scoreThreshold:.55})).withFaceLandmarks().withFaceDescriptor();if(!f)throw Error('Nenhum rosto detectado. Ajuste sua posição e tente novamente.');const r=await fetch(b+'/api/login_face',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({descritor:Array.from(f.descriptor)})}),data=await r.json();if(!r.ok||!data.success)throw Error(data.message||'Rosto não reconhecido.');stop();passwordToggle.disabled=true;const nomeUsuario=data.usuario&&data.usuario.nome?String(data.usuario.nome):'Usuário';const nomeTemporario=document.createElement('span');nomeTemporario.textContent=nomeUsuario;const nomeSeguro=nomeTemporario.innerHTML;l.textContent='Olá, '+nomeUsuario+'! Rosto reconhecido com sucesso.';const redirect=data.redirect||b+'/modules/ponto/ponto';for(let seconds=5;seconds>0;seconds--){s.className='status success';s.innerHTML='<i class="fa-solid fa-circle-check"></i><span><strong>Olá, '+nomeSeguro+'! Rosto reconhecido com sucesso.</strong> Carregando… Login em '+seconds+'s.</span>';btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Carregando '+nomeSeguro+'… '+seconds+'s';await new Promise(resolve=>setTimeout(resolve,1000))}location.assign(redirect)}catch(e){say(e.message||'Falha ao validar o rosto.','error');busy=false;btn.disabled=false}}btn.onclick=login;passwordToggle.onclick=()=>document.querySelector('#passwordForm').classList.toggle('open');addEventListener('beforeunload',stop);start()})();
</script>
<script>
document.querySelector('#passwordToggle').addEventListener('click', function () {
    const form = document.querySelector('#passwordForm');
    requestAnimationFrame(function () {
        const opened = form.classList.contains('open');
        document.documentElement.classList.toggle('password-open', opened);
        if (opened) {
            document.querySelector('#email').focus();
        }
    });
});
</script>
<script src="<?= $baseUrl ?>/assets/js/passkey.js"></script>
<script>
document.querySelector('#passkeyButton').addEventListener('click',async function(){const button=this,status=document.querySelector('#status');button.disabled=true;status.className='status';status.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i><span>Aguardando a biometria do dispositivo…</span>';try{if(!window.PublicKeyCredential||!navigator.credentials)throw Error('Este navegador não oferece suporte a login biométrico.');let response=await fetch('<?= $baseUrl ?>/api/passkey?acao=login_opcoes',{cache:'no-store'}),result=await response.json();if(!response.ok||!result.success)throw Error(result.message);const credential=await navigator.credentials.get(PFPasskey.prepareGet(result.data.options));response=await fetch('<?= $baseUrl ?>/api/passkey',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'login_concluir',id:PFPasskey.encode(credential.rawId),clientDataJSON:PFPasskey.encode(credential.response.clientDataJSON),authenticatorData:PFPasskey.encode(credential.response.authenticatorData),signature:PFPasskey.encode(credential.response.signature),userHandle:PFPasskey.encode(credential.response.userHandle)})});result=await response.json();if(!response.ok||!result.success)throw Error(result.message);status.className='status success';status.innerHTML='<i class="fa-solid fa-circle-check"></i><span>Biometria confirmada. Entrando…</span>';location.assign(result.data.redirect)}catch(error){status.className='status error';status.innerHTML='<i class="fa-solid fa-circle-exclamation"></i><span></span>';status.querySelector('span').textContent=error.message||'Não foi possível entrar com este dispositivo.';button.disabled=false}});
</script>
</body></html>
