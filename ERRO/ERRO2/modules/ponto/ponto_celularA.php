<?php
// modules/ponto/ponto_celular.php - Bater ponto pelo celular (com Digital)
session_start();

if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Ponto Celular';
$activePage = 'ponto';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Buscar dados do funcionário
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
    echo "<div style='text-align: center; padding: 50px;'>
            <h2>Perfil não encontrado</h2>
            <a href='../../logout.php'>Sair</a>
          </div>";
    exit;
}

// Buscar dados do funcionário
$stmt = $db->prepare("SELECT f.*, fi.nome_fantasia as filial_nome 
                      FROM funcionarios f
                      LEFT JOIN filiais fi ON f.filial_id = fi.id
                      WHERE f.id = :id");
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

// Buscar pontos de hoje para saber qual o próximo tipo
$stmt = $db->prepare("SELECT tipo, data_hora FROM pontos 
                      WHERE funcionario_id = :id AND DATE(data_hora) = CURDATE() 
                      ORDER BY data_hora ASC");
$stmt->execute([':id' => $funcionario_id]);
$pontosHoje = $stmt->fetchAll();

$tiposRegistrados = [];
foreach ($pontosHoje as $ponto) {
    $tiposRegistrados[] = $ponto['tipo'];
}

$sequencia = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
$proximo_tipo = 'entrada';

if (in_array('entrada', $tiposRegistrados) && !in_array('saida_almoco', $tiposRegistrados)) {
    $proximo_tipo = 'saida_almoco';
} elseif (in_array('saida_almoco', $tiposRegistrados) && !in_array('volta_almoco', $tiposRegistrados)) {
    $proximo_tipo = 'volta_almoco';
} elseif (in_array('volta_almoco', $tiposRegistrados) && !in_array('saida', $tiposRegistrados)) {
    $proximo_tipo = 'saida';
} elseif (in_array('entrada', $tiposRegistrados) && 
          in_array('saida_almoco', $tiposRegistrados) && 
          in_array('volta_almoco', $tiposRegistrados) && 
          in_array('saida', $tiposRegistrados)) {
    $proximo_tipo = 'finalizado';
}

// Mapear horários
$horarios = [
    'entrada' => '--:--',
    'saida_almoco' => '--:--',
    'volta_almoco' => '--:--',
    'saida' => '--:--'
];
foreach ($pontosHoje as $ponto) {
    $horarios[$ponto['tipo']] = date('H:i', strtotime($ponto['data_hora']));
}

// Gerar token para a sessão (para biometria)
$biometric_token = bin2hex(random_bytes(32));
$_SESSION['biometric_token'] = $biometric_token;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Ponto Celular - PontoFácil</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 500px; margin: 0 auto; }
        .card {
            background: white;
            border-radius: 32px;
            padding: 32px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .icon { font-size: 64px; color: #667eea; margin-bottom: 20px; }
        h1 { font-size: 24px; color: #333; margin-bottom: 8px; }
        .subtitle { color: #666; margin-bottom: 24px; font-size: 14px; }
        .funcionario-info {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .funcionario-info h3 { font-size: 18px; margin-bottom: 4px; }
        .funcionario-info p { font-size: 12px; color: #666; }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin: 20px 0;
        }
        .status-item {
            text-align: center;
            padding: 10px;
            border-radius: 12px;
            background: #f3f4f6;
        }
        .status-item.completed { background: #d1fae5; color: #059669; }
        .status-item.pending { background: #fee2e2; color: #dc2626; }
        .status-item .time { font-size: 16px; font-weight: 700; display: block; }
        .status-item .label { font-size: 10px; display: block; }
        
        .btn-ponto {
            width: 100%;
            padding: 18px;
            border: none;
            border-radius: 20px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 12px;
        }
        .btn-entrada { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-almoco { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .btn-volta { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-saida { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .btn-digital { background: #1e293b; color: white; }
        .btn-desabilitado { background: #9ca3af; cursor: not-allowed; }
        .btn-ponto:hover:not(.btn-desabilitado) { transform: scale(1.02); }
        .btn-digital i { color: #38bdf8; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            border-radius: 24px;
            padding: 24px;
            max-width: 350px;
            width: 90%;
            text-align: center;
        }
        .modal-content .qr-code { width: 180px; height: 180px; margin: 20px auto; }
        .modal-content .qr-code img { width: 100%; height: 100%; }
        .btn-fechar { background: #e5e7eb; color: #333; border: none; padding: 10px 20px; border-radius: 30px; margin-top: 16px; cursor: pointer; }
        
        .alert { padding: 12px; border-radius: 12px; margin-bottom: 16px; font-size: 13px; display: none; }
        .alert-error { background: #fee2e2; color: #dc2626; }
        .alert-success { background: #d1fae5; color: #059669; }
        .loading { display: none; text-align: center; padding: 20px; }
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .gps-status { font-size: 11px; padding: 8px; border-radius: 20px; margin: 10px 0; }
        .gps-ok { background: #d1fae5; color: #059669; }
        .gps-error { background: #fee2e2; color: #dc2626; }
        
        .biometric-prompt {
            background: #e0e7ff;
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .biometric-prompt i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 12px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="icon">
                <i class="fas fa-fingerprint"></i>
            </div>
            <h1>Ponto pelo Celular</h1>
            <p class="subtitle">Use sua digital para registrar o ponto</p>
            
            <div id="mensagem" class="alert"></div>
            
            <div class="funcionario-info" id="funcionarioInfo">
                <h3 id="nomeFuncionario"><?php echo htmlspecialchars($funcionario['nome']); ?></h3>
                <p id="matriculaFuncionario">Matrícula: <?php echo htmlspecialchars($funcionario['matricula']); ?></p>
                <p id="filialFuncionario">Filial: <?php echo htmlspecialchars($funcionario['filial_nome']); ?></p>
            </div>
            
            <div id="gpsStatus" class="gps-status gps-error">
                <i class="fas fa-map-marker-alt"></i> <span>Capturando localização...</span>
            </div>
            
            <div class="status-grid" id="statusGrid">
                <div class="status-item <?php echo $horarios['entrada'] != '--:--' ? 'completed' : 'pending'; ?>">
                    <i class="fas fa-sign-in-alt"></i>
                    <span class="label">Entrada</span>
                    <span class="time" id="entradaHora"><?php echo $horarios['entrada']; ?></span>
                </div>
                <div class="status-item <?php echo $horarios['saida_almoco'] != '--:--' ? 'completed' : 'pending'; ?>">
                    <i class="fas fa-utensils"></i>
                    <span class="label">Saída Almoço</span>
                    <span class="time" id="saidaAlmocoHora"><?php echo $horarios['saida_almoco']; ?></span>
                </div>
                <div class="status-item <?php echo $horarios['volta_almoco'] != '--:--' ? 'completed' : 'pending'; ?>">
                    <i class="fas fa-undo-alt"></i>
                    <span class="label">Volta Almoço</span>
                    <span class="time" id="voltaAlmocoHora"><?php echo $horarios['volta_almoco']; ?></span>
                </div>
                <div class="status-item <?php echo $horarios['saida'] != '--:--' ? 'completed' : 'pending'; ?>">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="label">Saída</span>
                    <span class="time" id="saidaHora"><?php echo $horarios['saida']; ?></span>
                </div>
            </div>
            
            <?php if ($proximo_tipo !== 'finalizado'): ?>
                <div class="biometric-prompt" id="biometricPrompt">
                    <i class="fas fa-fingerprint"></i>
                    <strong>Autenticação Biométrica</strong>
                    <p style="font-size: 13px; margin-top: 8px;">Toque no botão abaixo e utilize sua digital ou Face ID</p>
                </div>
                
                <button class="btn-ponto btn-digital" id="btnDigital">
                    <i class="fas fa-fingerprint"></i> Autenticar com Digital
                </button>
                
                <button class="btn-ponto <?php 
                    echo $proximo_tipo == 'entrada' ? 'btn-entrada' : 
                        ($proximo_tipo == 'saida_almoco' ? 'btn-almoco' : 
                        ($proximo_tipo == 'volta_almoco' ? 'btn-volta' : 'btn-saida')); 
                ?>" id="btnManual" style="margin-top: 12px;">
                    <i class="fas <?php 
                        echo $proximo_tipo == 'entrada' ? 'fa-sign-in-alt' : 
                            ($proximo_tipo == 'saida_almoco' ? 'fa-utensils' : 
                            ($proximo_tipo == 'volta_almoco' ? 'fa-undo-alt' : 'fa-sign-out-alt')); 
                    ?>"></i>
                    Registrar sem Digital (Manual)
                </button>
            <?php else: ?>
                <button class="btn-ponto btn-desabilitado" disabled>
                    <i class="fas fa-check-circle"></i> Dia Finalizado!
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal de Autorização -->
    <div id="modalAutorizacao" class="modal">
        <div class="modal-content">
            <i class="fas fa-user-shield" style="font-size: 48px; color: #f59e0b; margin-bottom: 16px;"></i>
            <h3>Falha na Digital</h3>
            <p>Solicite ao gerente que escaneie seu QR Code para autorizar o ponto.</p>
            <div class="qr-code" id="qrCodeAutorizacao">
                <img id="qrCodeImg" src="" alt="QR Code">
            </div>
            <p style="font-size: 12px; color: #666;">Peça para o gerente escanear este QR Code</p>
            <button class="btn-fechar" onclick="fecharModal()">Fechar</button>
            <div id="loadingQR" class="loading" style="display: none;">
                <div class="spinner"></div>
                <p>Gerando QR Code...</p>
            </div>
        </div>
    </div>
    
    <div id="loading" class="loading">
        <div class="spinner"></div>
        <p>Processando...</p>
    </div>
    
    <script>
        let funcionarioId = <?php echo $funcionario_id; ?>;
        let tipoPonto = '<?php echo $proximo_tipo; ?>';
        let latitude = null;
        let longitude = null;
        let tokenAutorizacao = null;
        let biometricToken = '<?php echo $biometric_token; ?>';
        
        // Capturar GPS
        function capturarGPS() {
            const gpsDiv = document.getElementById('gpsStatus');
            
            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    latitude = position.coords.latitude;
                    longitude = position.coords.longitude;
                    gpsDiv.innerHTML = '<i class="fas fa-check-circle"></i> <span>Localização capturada ✓</span>';
                    gpsDiv.className = 'gps-status gps-ok';
                }, function(error) {
                    let msg = '';
                    switch(error.code) {
                        case 1: msg = 'Permissão negada'; break;
                        case 2: msg = 'Indisponível'; break;
                        case 3: msg = 'Timeout'; break;
                        default: msg = 'Erro';
                    }
                    gpsDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <span>GPS: ' + msg + '</span>';
                    gpsDiv.className = 'gps-status gps-error';
                });
            } else {
                gpsDiv.innerHTML = '<i class="fas fa-times-circle"></i> <span>GPS não suportado</span>';
                gpsDiv.className = 'gps-status gps-error';
            }
        }
        
        capturarGPS();
        
        // ============================================
        // FUNÇÃO DE AUTENTICAÇÃO COM DIGITAL (COMPLETA)
        // ============================================
        async function autenticarComDigital() {
            const loading = document.getElementById('loading');
            loading.style.display = 'block';
            
            if (!latitude || !longitude) {
                mostrarMensagem('Aguardando localização... Tente novamente.', 'error');
                loading.style.display = 'none';
                return;
            }
            
            // Verificar suporte a WebAuthn
            if (!window.PublicKeyCredential) {
                mostrarMensagem('Seu navegador não suporta autenticação biométrica.', 'error');
                loading.style.display = 'none';
                await solicitarAutorizacaoGerente();
                return;
            }
            
            try {
                // Verificar se o dispositivo tem biometria disponível
                const available = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
                if (!available) {
                    mostrarMensagem('Seu dispositivo não possui sensor biométrico configurado.', 'error');
                    loading.style.display = 'none';
                    await solicitarAutorizacaoGerente();
                    return;
                }
                
                // Criar desafio para autenticação
                const challenge = new Uint8Array(32);
                window.crypto.getRandomValues(challenge);
                
                // Configuração da autenticação biométrica
                const publicKeyCredentialRequestOptions = {
                    challenge: challenge,
                    rpId: window.location.hostname,
                    userVerification: 'required',
                    timeout: 60000
                };
                
                // Solicitar autenticação biométrica
                const credential = await navigator.credentials.get({
                    publicKey: publicKeyCredentialRequestOptions
                });
                
                if (credential) {
                    // Autenticação bem-sucedida
                    loading.style.display = 'none';
                    mostrarMensagem('✅ Autenticação biométrica confirmada!', 'success');
                    
                    // Registrar o ponto
                    await registrarPonto();
                } else {
                    throw new Error('Falha na autenticação');
                }
                
            } catch (err) {
                console.error('Erro na biometria:', err);
                loading.style.display = 'none';
                
                if (err.name === 'NotAllowedError') {
                    mostrarMensagem('Autenticação cancelada pelo usuário.', 'error');
                } else if (err.name === 'NotSupportedError') {
                    mostrarMensagem('WebAuthn não é suportado neste navegador.', 'error');
                } else {
                    mostrarMensagem('Falha na autenticação biométrica. Tente novamente ou use o modo manual.', 'error');
                }
                
                // Mostrar opção de autorização do gerente
                const autorizar = confirm('Deseja solicitar autorização do gerente?');
                if (autorizar) {
                    await solicitarAutorizacaoGerente();
                }
            }
        }
        
        // Registrar ponto
        async function registrarPonto() {
            if (tipoPonto === 'finalizado') {
                mostrarMensagem('Dia já finalizado!', 'error');
                return;
            }
            
            const loading = document.getElementById('loading');
            loading.style.display = 'block';
            
            try {
                const response = await fetch('registrar_ponto_celular.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        tipo: tipoPonto,
                        latitude: latitude,
                        longitude: longitude,
                        token_biometrico: biometricToken
                    })
                });
                
                const result = await response.json();
                loading.style.display = 'none';
                
                if (result.success) {
                    mostrarMensagem(result.message, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    mostrarMensagem(result.message, 'error');
                    if (result.solicitar_autorizacao) {
                        await solicitarAutorizacaoGerente();
                    }
                }
            } catch (err) {
                loading.style.display = 'none';
                mostrarMensagem('Erro: ' + err.message, 'error');
            }
        }
        
        // Registrar ponto manual (sem digital)
        async function registrarPontoManual() {
            if (tipoPonto === 'finalizado') {
                mostrarMensagem('Dia já finalizado!', 'error');
                return;
            }
            
            const loading = document.getElementById('loading');
            loading.style.display = 'block';
            
            try {
                const response = await fetch('registrar_ponto_celular.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        tipo: tipoPonto,
                        latitude: latitude,
                        longitude: longitude,
                        manual: true
                    })
                });
                
                const result = await response.json();
                loading.style.display = 'none';
                
                if (result.success) {
                    mostrarMensagem(result.message, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    mostrarMensagem(result.message, 'error');
                    if (result.solicitar_autorizacao) {
                        await solicitarAutorizacaoGerente();
                    }
                }
            } catch (err) {
                loading.style.display = 'none';
                mostrarMensagem('Erro: ' + err.message, 'error');
            }
        }
        
        // Solicitar autorização do gerente
        async function solicitarAutorizacaoGerente() {
            const modal = document.getElementById('modalAutorizacao');
            const loadingQR = document.getElementById('loadingQR');
            const qrImg = document.getElementById('qrCodeImg');
            
            modal.style.display = 'flex';
            loadingQR.style.display = 'block';
            qrImg.style.display = 'none';
            
            try {
                const response = await fetch('api_solicitar_autorizacao.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        funcionario_id: funcionarioId,
                        tipo: tipoPonto,
                        latitude: latitude,
                        longitude: longitude
                    })
                });
                
                const result = await response.json();
                loadingQR.style.display = 'none';
                
                if (result.success) {
                    tokenAutorizacao = result.token;
                    const qrUrl = 'https://quickchart.io/qr?text=' + encodeURIComponent(
                        window.location.origin + '/modules/ponto/autorizar_gerente.php?token=' + tokenAutorizacao
                    );
                    qrImg.src = qrUrl;
                    qrImg.style.display = 'block';
                    
                    // Aguardar autorização
                    await aguardarAutorizacao(tokenAutorizacao);
                } else {
                    alert('Erro: ' + result.message);
                    fecharModal();
                }
            } catch (err) {
                loadingQR.style.display = 'none';
                alert('Erro: ' + err.message);
                fecharModal();
            }
        }
        
        // Aguardar autorização do gerente
        async function aguardarAutorizacao(token) {
            const interval = setInterval(async () => {
                try {
                    const response = await fetch('api_verificar_autorizacao.php?token=' + token);
                    const result = await response.json();
                    
                    if (result.autorizado) {
                        clearInterval(interval);
                        fecharModal();
                        mostrarMensagem('✅ Ponto autorizado pelo gerente!', 'success');
                        await registrarPonto();
                    } else if (result.rejeitado) {
                        clearInterval(interval);
                        fecharModal();
                        mostrarMensagem('❌ Autorização negada pelo gerente', 'error');
                    }
                } catch (err) {
                    console.error('Erro ao verificar:', err);
                }
            }, 3000);
        }
        
        function mostrarMensagem(msg, tipo) {
            const msgDiv = document.getElementById('mensagem');
            msgDiv.textContent = msg;
            msgDiv.className = 'alert alert-' + tipo;
            msgDiv.style.display = 'block';
            setTimeout(() => {
                msgDiv.style.display = 'none';
            }, 5000);
        }
        
        function fecharModal() {
            document.getElementById('modalAutorizacao').style.display = 'none';
        }
        
        // Event listeners
        document.getElementById('btnDigital')?.addEventListener('click', autenticarComDigital);
        document.getElementById('btnManual')?.addEventListener('click', registrarPontoManual);
    </script>
</body>
</html>
