<?php
// modules/ponto/ponto.php - Bater Ponto COM RECONHECIMENTO FACIAL
$pageTitle = 'Bater Ponto';
$activePage = 'ponto';

// ============================================
// VERIFICAÇÕES ANTES DO HEADER
// ============================================
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Pega o ID do funcionário da sessão
$funcionario_id = $_SESSION['funcionario_id'] ?? null;

if (!$funcionario_id && isset($_SESSION['usuario_id'])) {
    $stmt = $db->prepare("SELECT id FROM funcionarios WHERE email = :email");
    $stmt->execute([':email' => $_SESSION['usuario_email']]);
    $func = $stmt->fetch();
    if ($func) {
        $funcionario_id = $func['id'];
        $_SESSION['funcionario_id'] = $funcionario_id;
    }
}

if (!$funcionario_id) {
    $_SESSION['mensagem'] = 'Perfil de funcionário não encontrado.';
    header('Location: ../../index.php');
    exit;
}

// Buscar dados do funcionário
$stmt = $db->prepare("SELECT f.*, 
                      fil.nome_fantasia as filial_nome 
                      FROM funcionarios f
                      LEFT JOIN filiais fil ON f.filial_id = fil.id
                      WHERE f.id = :id");
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    $_SESSION['mensagem'] = 'Funcionário não encontrado';
    header('Location: ../../index.php');
    exit;
}

// Buscar pontos de hoje
$stmt = $db->prepare("SELECT * FROM pontos 
                      WHERE funcionario_id = :funcionario_id 
                      AND DATE(data_hora) = CURDATE() 
                      ORDER BY data_hora ASC");
$stmt->execute([':funcionario_id' => $funcionario_id]);
$pontos_hoje = $stmt->fetchAll();

// Verificar qual o próximo tipo de ponto
$tipos = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
$proximo_tipo = 'entrada';

if (count($pontos_hoje) > 0) {
    $ultimo = $pontos_hoje[count($pontos_hoje) - 1]['tipo'];
    $indice = array_search($ultimo, $tipos);
    if ($indice !== false && $indice < 3) {
        $proximo_tipo = $tipos[$indice + 1];
    } else {
        $proximo_tipo = 'finalizado';
    }
}

require_once '../../includes/header.php';
?>

<style>
.ponto-container {
    max-width: 600px;
    margin: 0 auto;
}

.ponto-card {
    background: var(--bg-primary);
    border-radius: 24px;
    padding: 32px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    border: 1px solid var(--border-color);
}

.ponto-header {
    text-align: center;
    margin-bottom: 30px;
}

.ponto-header h2 {
    font-size: 28px;
    margin-bottom: 16px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.relogio {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 20px;
    border-radius: 20px;
    text-align: center;
    margin-bottom: 30px;
}

.relogio .data {
    font-size: 16px;
    opacity: 0.9;
    margin-bottom: 8px;
}

.relogio .hora {
    font-size: 48px;
    font-weight: bold;
    letter-spacing: 4px;
}

.info-funcionario {
    background: var(--bg-secondary);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
    text-align: center;
}

.info-funcionario h3 {
    margin-bottom: 8px;
    color: var(--text-primary);
}

.info-funcionario p {
    color: var(--text-secondary);
    font-size: 14px;
    margin: 4px 0;
}

.foto-funcionario {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin: 0 auto 15px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.foto-funcionario i {
    font-size: 50px;
    color: white;
}

.foto-funcionario img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 30px;
}

.status-item {
    text-align: center;
    padding: 12px;
    border-radius: 12px;
    background: var(--bg-secondary);
}

.status-item.completed {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid #10b981;
}

.status-item.pending {
    background: rgba(239, 68, 68, 0.1);
}

.status-item i {
    font-size: 24px;
    margin-bottom: 8px;
    display: block;
}

.status-item.completed i { color: #10b981; }
.status-item.pending i { color: #9ca3af; }

.status-item span {
    font-size: 12px;
    display: block;
    margin-bottom: 4px;
}

.status-item strong {
    font-size: 18px;
    font-weight: bold;
}

.status-item.completed strong { color: #10b981; }
.status-item.pending strong { color: #9ca3af; }

.btn-ponto {
    width: 100%;
    padding: 20px;
    font-size: 20px;
    font-weight: bold;
    border: none;
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.3s;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.btn-ponto:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.btn-ponto:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-entrada {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-almoco {
    background: #f59e0b;
    color: white;
}

.btn-volta {
    background: #10b981;
    color: white;
}

.btn-saida {
    background: #ef4444;
    color: white;
}

.btn-facial {
    width: 100%;
    padding: 14px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 12px;
}

.btn-facial:hover {
    background: #2563eb;
}

.btn-dashboard {
    width: 100%;
    padding: 14px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-dashboard:hover {
    background: var(--bg-tertiary);
}

.btn-logout {
    width: 100%;
    padding: 12px;
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 14px;
    margin-top: 16px;
}

.btn-logout:hover {
    color: #ef4444;
}

.pontos-list {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

.pontos-list h3 {
    margin-bottom: 16px;
    font-size: 18px;
}

.ponto-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    border-bottom: 1px solid var(--border-color);
}

.ponto-tipo {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ponto-hora {
    font-weight: bold;
    font-size: 18px;
    color: #667eea;
}

.day-completed {
    text-align: center;
    padding: 40px;
    background: rgba(16, 185, 129, 0.1);
    border-radius: 16px;
    margin-bottom: 20px;
}

.day-completed i {
    font-size: 48px;
    color: #10b981;
    margin-bottom: 16px;
}

.alert {
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    text-align: center;
}

.alert-success {
    background: #d1fae5;
    color: #059669;
}

.alert-error {
    background: #fee2e2;
    color: #dc2626;
}

/* Modal da Câmera */
.camera-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.95);
    z-index: 2000;
    align-items: center;
    justify-content: center;
}

.camera-container {
    background: white;
    border-radius: 24px;
    padding: 20px;
    max-width: 500px;
    width: 90%;
    text-align: center;
}

.camera-container video {
    width: 100%;
    border-radius: 16px;
    background: #000;
    margin-bottom: 16px;
}

.camera-buttons {
    display: flex;
    gap: 12px;
}

.camera-buttons button {
    flex: 1;
    padding: 12px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    border: none;
}

.btn-capturar {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-fechar-camera {
    background: #e5e5e5;
    color: #333;
}

.loading {
    display: none;
    text-align: center;
    padding: 20px;
}

.spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #667eea;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 480px) {
    .ponto-card {
        padding: 20px;
    }
    
    .status-grid {
        gap: 8px;
    }
    
    .status-item i {
        font-size: 18px;
    }
    
    .status-item strong {
        font-size: 14px;
    }
    
    .relogio .hora {
        font-size: 32px;
    }
}
</style>

<div class="ponto-container">
    <div class="ponto-card">
        <div class="ponto-header">
            <h2>⏰ Bater Ponto</h2>
            <p>Sistema de Ponto Eletrônico</p>
        </div>
        
        <div class="relogio">
            <div class="data"><?php echo date('d/m/Y'); ?></div>
            <div class="hora" id="relogioDigital"><?php echo date('H:i:s'); ?></div>
        </div>
        
        <div class="info-funcionario">
            <div class="foto-funcionario">
                <?php if (!empty($funcionario['foto']) && file_exists('../../' . $funcionario['foto'])): ?>
                    <img src="../../<?php echo $funcionario['foto']; ?>" alt="Foto">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
            </div>
            <h3><?php echo htmlspecialchars($funcionario['nome']); ?></h3>
            <p>Matrícula: <?php echo htmlspecialchars($funcionario['matricula']); ?> | Filial: <?php echo htmlspecialchars($funcionario['filial_nome'] ?? 'Não definida'); ?></p>
        </div>
        
        <div id="alertMessage"></div>
        
        <!-- Status do Dia -->
        <div class="status-grid">
            <div class="status-item <?php echo isset($pontos_hoje[0]) && $pontos_hoje[0]['tipo'] == 'entrada' ? 'completed' : 'pending'; ?>">
                <i class="fas fa-sign-in-alt"></i>
                <span>Entrada</span>
                <strong>
                    <?php 
                    $entrada = array_values(array_filter($pontos_hoje, function($p) { return $p['tipo'] == 'entrada'; }));
                    echo !empty($entrada) ? date('H:i', strtotime($entrada[0]['data_hora'])) : '--:--';
                    ?>
                </strong>
            </div>
            <div class="status-item <?php echo isset($pontos_hoje[1]) && $pontos_hoje[1]['tipo'] == 'saida_almoco' ? 'completed' : 'pending'; ?>">
                <i class="fas fa-utensils"></i>
                <span>Saída Almoço</span>
                <strong>
                    <?php 
                    $saida_almoco = array_values(array_filter($pontos_hoje, function($p) { return $p['tipo'] == 'saida_almoco'; }));
                    echo !empty($saida_almoco) ? date('H:i', strtotime($saida_almoco[0]['data_hora'])) : '--:--';
                    ?>
                </strong>
            </div>
            <div class="status-item <?php echo isset($pontos_hoje[2]) && $pontos_hoje[2]['tipo'] == 'volta_almoco' ? 'completed' : 'pending'; ?>">
                <i class="fas fa-undo-alt"></i>
                <span>Volta Almoço</span>
                <strong>
                    <?php 
                    $volta_almoco = array_values(array_filter($pontos_hoje, function($p) { return $p['tipo'] == 'volta_almoco'; }));
                    echo !empty($volta_almoco) ? date('H:i', strtotime($volta_almoco[0]['data_hora'])) : '--:--';
                    ?>
                </strong>
            </div>
            <div class="status-item <?php echo isset($pontos_hoje[3]) && $pontos_hoje[3]['tipo'] == 'saida' ? 'completed' : 'pending'; ?>">
                <i class="fas fa-sign-out-alt"></i>
                <span>Saída</span>
                <strong>
                    <?php 
                    $saida = array_values(array_filter($pontos_hoje, function($p) { return $p['tipo'] == 'saida'; }));
                    echo !empty($saida) ? date('H:i', strtotime($saida[0]['data_hora'])) : '--:--';
                    ?>
                </strong>
            </div>
        </div>
        
        <?php if ($proximo_tipo !== 'finalizado'): ?>
            <!-- Botão de Ponto normal -->
            <form method="POST" action="processar.php" id="pontoForm">
                <input type="hidden" name="tipo" value="<?php echo $proximo_tipo; ?>">
                <button type="submit" class="btn-ponto btn-<?php 
                    echo $proximo_tipo == 'entrada' ? 'entrada' : 
                         ($proximo_tipo == 'saida_almoco' ? 'almoco' : 
                         ($proximo_tipo == 'volta_almoco' ? 'volta' : 'saida')); 
                ?>">
                    <i class="fas 
                        <?php echo $proximo_tipo == 'entrada' ? 'fa-sign-in-alt' : 
                             ($proximo_tipo == 'saida_almoco' ? 'fa-utensils' : 
                             ($proximo_tipo == 'volta_almoco' ? 'fa-undo-alt' : 'fa-sign-out-alt')); ?>">
                    </i>
                    <?php 
                    switch($proximo_tipo) {
                        case 'entrada': echo 'REGISTRAR ENTRADA'; break;
                        case 'saida_almoco': echo 'REGISTRAR SAÍDA PARA ALMOÇO'; break;
                        case 'volta_almoco': echo 'REGISTRAR VOLTA DO ALMOÇO'; break;
                        case 'saida': echo 'REGISTRAR SAÍDA'; break;
                    }
                    ?>
                </button>
            </form>
            
            <!-- Botão de Reconhecimento Facial -->
            <button class="btn-facial" onclick="abrirCamera()">
                <i class="fas fa-camera"></i> Usar Reconhecimento Facial
            </button>
        <?php else: ?>
            <div class="day-completed">
                <i class="fas fa-check-circle"></i>
                <h3>Dia Finalizado!</h3>
                <p>Você já registrou todos os pontos de hoje.</p>
            </div>
        <?php endif; ?>
        
        <!-- Botão Dashboard -->
        <a href="../../index.php" class="btn-dashboard">
            <i class="fas fa-tachometer-alt"></i> Ir para Dashboard
        </a>
        
        <!-- Botão Sair -->
        <button onclick="sairSistema()" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i> Sair do Sistema
        </button>
        
        <!-- Lista de Pontos -->
        <div class="pontos-list">
            <h3><i class="fas fa-history"></i> Registros de hoje:</h3>
            <?php if (count($pontos_hoje) > 0): ?>
                <?php foreach ($pontos_hoje as $ponto): ?>
                <div class="ponto-item">
                    <div class="ponto-tipo">
                        <?php 
                        $icones = [
                            'entrada' => '✅',
                            'saida_almoco' => '🍽️',
                            'volta_almoco' => '🔄',
                            'saida' => '🏁'
                        ];
                        $labels = [
                            'entrada' => 'Entrada',
                            'saida_almoco' => 'Saída Almoço',
                            'volta_almoco' => 'Volta Almoço',
                            'saida' => 'Saída'
                        ];
                        ?>
                        <span><?php echo $icones[$ponto['tipo']]; ?></span>
                        <span><?php echo $labels[$ponto['tipo']]; ?></span>
                    </div>
                    <div class="ponto-hora"><?php echo date('H:i:s', strtotime($ponto['data_hora'])); ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                    <i class="fas fa-info-circle"></i> Nenhum ponto registrado hoje
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal da Câmera -->
<div id="cameraModal" class="camera-modal">
    <div class="camera-container">
        <video id="video" autoplay playsinline></video>
        <canvas id="canvas" style="display: none;"></canvas>
        <div id="loadingCamera" class="loading">
            <div class="spinner"></div>
            <p>Validando reconhecimento facial...</p>
        </div>
        <div class="camera-buttons">
            <button class="btn-capturar" onclick="capturarFoto()">Capturar e Validar</button>
            <button class="btn-fechar-camera" onclick="fecharCamera()">Cancelar</button>
        </div>
    </div>
</div>

<script>
let stream = null;
let video = document.getElementById('video');
let modal = document.getElementById('cameraModal');
let loading = document.getElementById('loadingCamera');

// Atualizar relógio
function atualizarRelogio() {
    const agora = new Date();
    const horas = String(agora.getHours()).padStart(2, '0');
    const minutos = String(agora.getMinutes()).padStart(2, '0');
    const segundos = String(agora.getSeconds()).padStart(2, '0');
    document.getElementById('relogioDigital').innerHTML = `${horas}:${minutos}:${segundos}`;
}
setInterval(atualizarRelogio, 1000);
atualizarRelogio();

// Abrir câmera
async function abrirCamera() {
    modal.style.display = 'flex';
    loading.style.display = 'none';
    
    // Verificar se o próximo tipo de ponto existe
    const proximoTipo = '<?php echo $proximo_tipo; ?>';
    if (proximoTipo === 'finalizado') {
        mostrarMensagem('Dia já finalizado!', 'error');
        fecharCamera();
        return;
    }
    
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: true });
        video.srcObject = stream;
    } catch (err) {
        mostrarMensagem('Erro ao acessar a câmera: ' + err.message, 'error');
        fecharCamera();
    }
}

// Fechar câmera
function fecharCamera() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    modal.style.display = 'none';
    video.srcObject = null;
}

// Capturar foto e registrar ponto
async function capturarFoto() {
    const canvas = document.getElementById('canvas');
    const context = canvas.getContext('2d');
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    // Converter para base64
    const fotoBase64 = canvas.toDataURL('image/jpeg', 0.8);
    const proximoTipo = '<?php echo $proximo_tipo; ?>';
    
    loading.style.display = 'block';
    
    try {
        // Enviar para validação e registro
        const response = await fetch('processar_facial.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tipo: proximoTipo,
                foto: fotoBase64,
                funcionario_id: <?php echo $funcionario_id; ?>
            })
        });
        
        const result = await response.json();
        loading.style.display = 'none';
        
        if (result.success) {
            mostrarMensagem(result.message, 'success');
            fecharCamera();
            setTimeout(() => window.location.reload(), 2000);
        } else {
            mostrarMensagem(result.message, 'error');
        }
    } catch (err) {
        loading.style.display = 'none';
        mostrarMensagem('Erro ao processar: ' + err.message, 'error');
    }
}

// Mostrar mensagem
function mostrarMensagem(msg, tipo) {
    const alertDiv = document.getElementById('alertMessage');
    const className = tipo === 'success' ? 'alert-success' : 'alert-error';
    alertDiv.innerHTML = `<div class="alert ${className}">${msg}</div>`;
    setTimeout(() => {
        alertDiv.innerHTML = '';
    }, 3000);
}

// Sair do sistema
function sairSistema() {
    if (confirm('Deseja realmente sair do sistema?')) {
        window.location.href = '../../logout.php';
    }
}

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal.style.display === 'flex') {
        fecharCamera();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>