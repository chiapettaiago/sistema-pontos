<?php
$pageTitle = 'Minha biometria facial';
$activePage = 'funcionarios';
require_once __DIR__ . '/../../includes/auth_check.php';
forceAuthentication();
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/facial_recognition.php';

$funcionarioId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($funcionarioId <= 0) {
    $_SESSION['error'] = 'Funcionário inválido.';
    header('Location: ' . appUrl(appHomeRouteFor($_SESSION['usuario_tipo'] ?? 'funcionario')));
    exit;
}
$funcionarioLogadoId = (int) ($_SESSION['funcionario_id'] ?? 0);
$cadastroProprio = $funcionarioLogadoId === $funcionarioId;
requireAdmin();
$urlVoltar = $cadastroProprio ? appUrl(appHomeRouteFor($_SESSION['usuario_tipo'] ?? 'funcionario')) : rtrim(BASE_URL, '/') . '/modules/funcionarios/visualizar.php?id=' . $funcionarioId;

try {
    $usuarioTipo = appNormalizeUserType((string) ($_SESSION['usuario_tipo'] ?? ''));
    $empresaId = (int) ($_SESSION['empresa_id'] ?? 0);
    $sqlFuncionario = 'SELECT id, nome, facial_cadastrado, imagem_facial FROM funcionarios WHERE id = :id AND status = :status';
    $parametrosFuncionario = [':id' => $funcionarioId, ':status' => 'ativo'];
    if ($usuarioTipo === 'admin_empresa') {
        $sqlFuncionario .= ' AND empresa_id = :empresa_id';
        $parametrosFuncionario[':empresa_id'] = $empresaId;
    }
    $stmt = $pdo->prepare($sqlFuncionario);
    $stmt->execute($parametrosFuncionario);
    $funcionario = $stmt->fetch();
    if (!$funcionario) throw new RuntimeException('Funcionário não encontrado.');
} catch (Throwable $e) {
    error_log('Erro ao carregar cadastro facial: ' . $e->getMessage());
    $_SESSION['error'] = 'Não foi possível abrir o cadastro facial.';
    header('Location: ' . $urlVoltar);
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken();
    $descritor = (string) ($_POST['descritor'] ?? '');
    $amostraFacial = json_decode($descritor, true);
    $imagemBase64 = (string) ($_POST['imagem_base64'] ?? '');
    if (facialNormalizeDescriptor($amostraFacial) === null) {
        $erro = 'A captura não ficou válida. Posicione o rosto no guia e tente novamente.';
    } elseif (!preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', $imagemBase64, $imagemMatch)) {
        $erro = 'A imagem capturada não pôde ser validada. Tente novamente.';
    } else {
        $imageData = base64_decode($imagemMatch[1], true);
        if ($imageData === false || strlen($imageData) > 5 * 1024 * 1024) {
            $erro = 'A imagem capturada é inválida ou muito grande.';
        } else {
            $uploadDir = __DIR__ . '/../../uploads/facial/';
            $imagemNome = 'facial_' . $funcionarioId . '_' . time() . '.jpg';
            $imagemDestino = $uploadDir . $imagemNome;
            try {
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) throw new RuntimeException('Falha ao preparar imagens.');
                if (file_put_contents($imagemDestino, $imageData, LOCK_EX) === false) throw new RuntimeException('Falha ao salvar imagem.');
                $pdo->exec("CREATE TABLE IF NOT EXISTS biometricos_faciais (id INT NOT NULL AUTO_INCREMENT, funcionario_id INT NOT NULL, descritores LONGTEXT NOT NULL, modelo VARCHAR(50) DEFAULT 'face-api.js', ativo TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_bf_funcionario (funcionario_id), KEY idx_bf_ativo (ativo)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('UPDATE funcionarios SET descritor_facial = :descritor, imagem_facial = :imagem, facial_cadastrado = 1 WHERE id = :id');
                $stmt->execute([':descritor' => $descritor, ':imagem' => $imagemNome, ':id' => $funcionarioId]);
                $stmt = $pdo->prepare('DELETE FROM biometricos_faciais WHERE funcionario_id = :id');
                $stmt->execute([':id' => $funcionarioId]);
                $stmt = $pdo->prepare('INSERT INTO biometricos_faciais (funcionario_id, descritores, modelo, ativo) VALUES (:id, :descritores, :modelo, 1)');
                $stmt->execute([':id' => $funcionarioId, ':descritores' => json_encode([$amostraFacial], JSON_UNESCAPED_UNICODE), ':modelo' => 'face-api.js']);
                $pdo->commit();
                $imagemAnterior = basename((string) ($funcionario['imagem_facial'] ?? ''));
                if ($imagemAnterior !== '' && $imagemAnterior !== $imagemNome && is_file($uploadDir . $imagemAnterior)) @unlink($uploadDir . $imagemAnterior);
                $_SESSION['success'] = 'Biometria facial cadastrada com sucesso.';
                header('Location: ' . $urlVoltar);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if (isset($imagemDestino) && is_file($imagemDestino)) @unlink($imagemDestino);
                error_log('Erro ao salvar biometria facial: ' . $e->getMessage());
                $erro = 'Não foi possível salvar a biometria agora. Tente novamente.';
            }
        }
    }
}
require_once __DIR__ . '/../../includes/header.php';
?>
<script id="faceApiScript" defer src="<?= htmlspecialchars(rtrim(BASE_URL, '/'), ENT_QUOTES, 'UTF-8') ?>/assets/js/face-api.min.js"></script>
<style>
.face-page{max-width:920px;margin:0 auto}.face-card{overflow:hidden;border:0;border-radius:22px;box-shadow:0 14px 42px rgba(42,31,47,.09)}
.face-intro{display:flex;justify-content:space-between;align-items:center;gap:20px}.face-status-pill{white-space:nowrap;border-radius:999px;padding:7px 12px;font-size:.82rem;font-weight:700;background:rgba(24,188,168,.12);color:#087f72}
.face-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:22px 0}.face-step{display:flex;align-items:center;gap:9px;padding:11px;border-radius:12px;background:var(--bs-tertiary-bg);font-size:.86rem}.face-step span{width:25px;height:25px;flex:0 0 25px;display:grid;place-items:center;border-radius:50%;background:#75009f;color:#fff;font-weight:800}
.camera-shell{position:relative;max-width:600px;aspect-ratio:4/3;margin:0 auto;overflow:hidden;border-radius:20px;background:#171419;box-shadow:inset 0 0 0 1px rgba(255,255,255,.12)}
.camera-shell video{width:100%;height:100%;object-fit:cover;transform:scaleX(-1)}.face-guide{position:absolute;left:50%;top:45%;width:42%;aspect-ratio:.78;transform:translate(-50%,-50%);border:3px solid rgba(255,255,255,.8);border-radius:48% 48% 44% 44%;box-shadow:0 0 0 999px rgba(0,0,0,.2),0 0 25px rgba(255,255,255,.18);transition:.2s}
.camera-shell.ready .face-guide{border-color:#39d5c3;box-shadow:0 0 0 999px rgba(0,0,0,.16),0 0 30px rgba(57,213,195,.55)}
.camera-state{position:absolute;left:14px;right:14px;bottom:14px;display:flex;align-items:center;justify-content:center;gap:9px;min-height:46px;padding:10px 14px;border-radius:13px;background:rgba(18,15,20,.82);color:#fff;text-align:center;font-weight:700;backdrop-filter:blur(8px)}
.camera-state i{color:#cdb2d7}.camera-shell.ready .camera-state i{color:#39d5c3}.privacy-note{font-size:.82rem;color:var(--bs-secondary-color)}
@media(max-width:640px){.face-card .card-body{padding:20px!important}.face-intro{align-items:flex-start;flex-direction:column}.face-steps{grid-template-columns:1fr}.camera-shell{border-radius:15px}.face-guide{width:47%}}
</style>
<div class="face-page py-3 py-md-4"><div class="card face-card"><div class="card-body p-4 p-md-5">
    <div class="face-intro"><div><h1 class="h4 mb-1">Cadastre seu rosto</h1><p class="text-body-secondary mb-0"><?= htmlspecialchars($funcionario['nome']) ?> · leva menos de um minuto</p></div><span class="face-status-pill"><i class="fas fa-shield-halved me-1"></i><?= !empty($funcionario['facial_cadastrado']) ? 'Biometria ativa' : 'Ambiente seguro' ?></span></div>
    <div class="face-steps" aria-label="Instruções"><div class="face-step"><span>1</span> Olhe de frente</div><div class="face-step"><span>2</span> Retire óculos escuros</div><div class="face-step"><span>3</span> Fique em local iluminado</div></div>
    <?php if ($erro): ?><div class="alert alert-danger" role="alert"><i class="fas fa-circle-exclamation me-2"></i><?= htmlspecialchars($erro) ?></div><?php endif; ?>
    <form method="post" id="facialForm"><?= csrfField() ?><input type="hidden" name="descritor" id="descritor"><input type="hidden" name="imagem_base64" id="imagemBase64">
        <div class="camera-shell" id="cameraShell"><video id="video" autoplay muted playsinline aria-label="Prévia da câmera"></video><canvas id="captureCanvas" hidden></canvas><div class="face-guide" aria-hidden="true"></div><div class="camera-state" id="cameraState" role="status" aria-live="polite"><i class="fas fa-spinner fa-spin"></i><span>Preparando o reconhecimento facial…</span></div></div>
        <div class="d-grid gap-2 mt-4 mx-auto" style="max-width:600px"><button class="btn btn-primary btn-lg rounded-pill" type="button" id="captureButton" disabled><i class="fas fa-camera me-2"></i><span>Cadastrar meu rosto</span></button><a class="btn btn-link text-body-secondary" href="<?= htmlspecialchars($urlVoltar, ENT_QUOTES, 'UTF-8') ?>">Agora não</a></div>
    </form><p class="privacy-note text-center mt-3 mb-0"><i class="fas fa-lock me-1"></i>A imagem é usada somente para identificar seu acesso.</p>
</div></div></div>
<script>
(() => {
const modelPath=<?= json_encode(rtrim(BASE_URL, '/') . '/assets/models') ?>,video=document.getElementById('video'),shell=document.getElementById('cameraShell'),state=document.getElementById('cameraState'),button=document.getElementById('captureButton'),form=document.getElementById('facialForm'),canvas=document.getElementById('captureCanvas'),descriptorInput=document.getElementById('descritor'),imageInput=document.getElementById('imagemBase64');
let stream=null,scanning=false,saving=false,stableFrames=0,lastDetection=null,scanTimer=null;
function show(message,type='loading'){const icons={loading:'fa-spinner fa-spin',ready:'fa-circle-check',error:'fa-circle-exclamation',info:'fa-face-smile'};state.innerHTML='<i class="fas '+icons[type]+'"></i><span></span>';state.querySelector('span').textContent=message;shell.classList.toggle('ready',type==='ready')}
async function detect(){if(scanning||saving||!video.videoWidth)return;scanning=true;try{const faces=await faceapi.detectAllFaces(video,new faceapi.TinyFaceDetectorOptions({inputSize:224,scoreThreshold:.55})).withFaceLandmarks().withFaceDescriptors();if(faces.length===1){lastDetection=faces[0];stableFrames++;if(stableFrames>=2){button.disabled=false;show('Tudo certo! Agora toque no botão abaixo.','ready')}else show('Rosto encontrado. Fique parado por um instante.','info')}else{lastDetection=null;stableFrames=0;button.disabled=true;show(faces.length>1?'Deixe apenas uma pessoa no enquadramento.':'Centralize seu rosto dentro do guia.','info')}}catch(_){show('Não foi possível analisar a imagem. Tente novamente.','error')}finally{scanning=false}}
async function start(){button.disabled=true;button.dataset.action='';button.querySelector('span').textContent='Cadastrar meu rosto';button.querySelector('i').className='fas fa-camera me-2';show('Preparando o reconhecimento facial…');try{if(!window.isSecureContext&&!['localhost','127.0.0.1','::1'].includes(location.hostname))throw new Error('Abra esta página em HTTPS para usar a câmera.');await Promise.all([faceapi.nets.tinyFaceDetector.loadFromUri(modelPath),faceapi.nets.faceLandmark68Net.loadFromUri(modelPath),faceapi.nets.faceRecognitionNet.loadFromUri(modelPath)]);stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user',width:{ideal:720},height:{ideal:540}},audio:false});video.srcObject=stream;await video.play();show('Centralize seu rosto dentro do guia.','info');scanTimer=window.setInterval(detect,700)}catch(error){show(error.message||'Permita o acesso à câmera para continuar.','error');button.disabled=false;button.dataset.action='retry';button.querySelector('span').textContent='Tentar novamente';button.querySelector('i').className='fas fa-rotate-right me-2'}}
button.addEventListener('click',async()=>{if(button.dataset.action==='retry'){if(stream)stream.getTracks().forEach(track=>track.stop());await start();return}if(saving||!lastDetection)return;saving=true;button.disabled=true;button.querySelector('i').className='fas fa-spinner fa-spin me-2';button.querySelector('span').textContent='Salvando com segurança…';show('Captura concluída. Salvando seu cadastro…','ready');canvas.width=video.videoWidth;canvas.height=video.videoHeight;canvas.getContext('2d').drawImage(video,0,0,canvas.width,canvas.height);descriptorInput.value=JSON.stringify(Array.from(lastDetection.descriptor));imageInput.value=canvas.toDataURL('image/jpeg',.88);if(stream)stream.getTracks().forEach(track=>track.stop());form.submit()});
function boot(){if(typeof faceapi!=='undefined')return start();const script=document.getElementById('faceApiScript');script.addEventListener('load',start,{once:true});script.addEventListener('error',()=>show('Não foi possível carregar o reconhecimento facial.','error'),{once:true})}
window.addEventListener('beforeunload',()=>{if(scanTimer)window.clearInterval(scanTimer);if(stream)stream.getTracks().forEach(track=>track.stop())});document.readyState==='loading'?document.addEventListener('DOMContentLoaded',boot,{once:true}):boot();
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
