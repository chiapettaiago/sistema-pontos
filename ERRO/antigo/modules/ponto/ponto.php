<?php
// modules/ponto/ponto.php - Tela de Ponto (VERSÃO CORRIGIDA)
session_start();

// Verificar se está logado
if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Registrar Ponto';
$activePage = 'ponto';

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Pega o ID do funcionário
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
            <p>Contacte o administrador.</p>
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

if (!$funcionario) {
    session_destroy();
    header('Location: 1678/login.php');
    exit;
}

// Buscar pontos de hoje - IGNORANDO horários inválidos
$stmt = $db->prepare("SELECT tipo, data_hora FROM pontos 
                      WHERE funcionario_id = :id 
                      AND DATE(data_hora) = CURDATE()
                      AND TIME(data_hora) >= '06:00:00'
                      ORDER BY data_hora ASC");
$stmt->execute([':id' => $funcionario_id]);
$pontosHoje = $stmt->fetchAll();

// Mapear tipos registrados (apenas com horário válido)
$tiposRegistrados = [];
foreach ($pontosHoje as $ponto) {
    $hora = date('H:i', strtotime($ponto['data_hora']));
    // Ignorar horários inválidos (00:00)
    if ($hora != '00:00' && $hora != '00:00:00') {
        $tiposRegistrados[] = $ponto['tipo'];
    }
}

// ============================================
// CALCULAR PRÓXIMO PONTO - LÓGICA CORRIGIDA
// ============================================
$proximo_tipo = 'entrada';
$proximo_texto = 'Registrar Entrada';

// Verificar cada tipo individualmente
$tem_entrada = in_array('entrada', $tiposRegistrados);
$tem_saida_almoco = in_array('saida_almoco', $tiposRegistrados);
$tem_volta_almoco = in_array('volta_almoco', $tiposRegistrados);
$tem_saida = in_array('saida', $tiposRegistrados);

// Lógica de próximo ponto
if (!$tem_entrada) {
    $proximo_tipo = 'entrada';
    $proximo_texto = 'Registrar Entrada';
} elseif ($tem_entrada && !$tem_saida_almoco) {
    $proximo_tipo = 'saida_almoco';
    $proximo_texto = 'Registrar Saída para Almoço';
} elseif ($tem_entrada && $tem_saida_almoco && !$tem_volta_almoco) {
    $proximo_tipo = 'volta_almoco';
    $proximo_texto = 'Registrar Volta do Almoço';
} elseif ($tem_entrada && $tem_saida_almoco && $tem_volta_almoco && !$tem_saida) {
    $proximo_tipo = 'saida';
    $proximo_texto = 'Registrar Saída';
} elseif ($tem_entrada && $tem_saida_almoco && $tem_volta_almoco && $tem_saida) {
    $proximo_tipo = 'finalizado';
    $proximo_texto = 'Dia Finalizado!';
}

// Para debug (remova depois)
error_log("=== DEBUG PONTO ===");
error_log("Tipos registrados: " . implode(', ', $tiposRegistrados));
error_log("tem_entrada: " . ($tem_entrada ? 'SIM' : 'NAO'));
error_log("tem_saida_almoco: " . ($tem_saida_almoco ? 'SIM' : 'NAO'));
error_log("tem_volta_almoco: " . ($tem_volta_almoco ? 'SIM' : 'NAO'));
error_log("tem_saida: " . ($tem_saida ? 'SIM' : 'NAO'));
error_log("Próximo tipo: " . $proximo_tipo);

// Mapear horários para exibição
$horarios = [
    'entrada' => '--:--',
    'saida_almoco' => '--:--',
    'volta_almoco' => '--:--',
    'saida' => '--:--'
];
foreach ($pontosHoje as $ponto) {
    $hora = date('H:i', strtotime($ponto['data_hora']));
    if ($hora != '00:00' && $hora != '00:00:00') {
        $horarios[$ponto['tipo']] = $hora;
    }
}

// Nomes e ícones
$nomes_botao = [
    'entrada' => 'Registrar Entrada',
    'saida_almoco' => 'Registrar Saída para Almoço',
    'volta_almoco' => 'Registrar Volta do Almoço',
    'saida' => 'Registrar Saída',
    'finalizado' => 'Dia Finalizado!'
];

$icones_botao = [
    'entrada' => 'fa-sign-in-alt',
    'saida_almoco' => 'fa-utensils',
    'volta_almoco' => 'fa-undo-alt',
    'saida' => 'fa-sign-out-alt',
    'finalizado' => 'fa-check-circle'
];

$cores_botao = [
    'entrada' => 'entrada',
    'saida_almoco' => 'almoco',
    'volta_almoco' => 'volta',
    'saida' => 'saida',
    'finalizado' => 'finalizado'
];

// Mensagem de feedback
$mensagem = '';
$tipo_mensagem = '';
if (isset($_SESSION['mensagem_ponto'])) {
    $mensagem = $_SESSION['mensagem_ponto'];
    $tipo_mensagem = $_SESSION['tipo_mensagem_ponto'];
    unset($_SESSION['mensagem_ponto']);
    unset($_SESSION['tipo_mensagem_ponto']);
}

// Processar registro de ponto manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $tipo = $_POST['acao'];
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    
    // Verificar se já registrou
    if (in_array($tipo, $tiposRegistrados)) {
        $_SESSION['mensagem_ponto'] = "Você já registrou " . str_replace('_', ' ', $tipo) . " hoje!";
        $_SESSION['tipo_mensagem_ponto'] = 'error';
        header('Location: ponto.php');
        exit;
    }
    
    // Validar sequência
    if ($tipo == 'saida_almoco' && !in_array('entrada', $tiposRegistrados)) {
        $_SESSION['mensagem_ponto'] = "Você precisa registrar a entrada primeiro!";
        $_SESSION['tipo_mensagem_ponto'] = 'error';
        header('Location: ponto.php');
        exit;
    }
    
    if ($tipo == 'volta_almoco' && !in_array('saida_almoco', $tiposRegistrados)) {
        $_SESSION['mensagem_ponto'] = "Você precisa registrar a saída para almoço primeiro!";
        $_SESSION['tipo_mensagem_ponto'] = 'error';
        header('Location: ponto.php');
        exit;
    }
    
    if ($tipo == 'saida' && !in_array('volta_almoco', $tiposRegistrados) && !in_array('entrada', $tiposRegistrados)) {
        $_SESSION['mensagem_ponto'] = "Você precisa registrar o almoço primeiro ou a entrada!";
        $_SESSION['tipo_mensagem_ponto'] = 'error';
        header('Location: ponto.php');
        exit;
    }
    
    // Registrar ponto
    try {
        $stmt = $db->prepare("INSERT INTO pontos (funcionario_id, filial_id, empresa_id, tipo, data_hora, latitude, longitude, origem) 
                              VALUES (:funcionario_id, :filial_id, :empresa_id, :tipo, NOW(), :latitude, :longitude, 'web')");
        $stmt->execute([
            ':funcionario_id' => $funcionario_id,
            ':filial_id' => $funcionario['filial_id'],
            ':empresa_id' => $funcionario['empresa_id'],
            ':tipo' => $tipo,
            ':latitude' => $latitude,
            ':longitude' => $longitude
        ]);
        
        $nomes = [
            'entrada' => 'Entrada',
            'saida_almoco' => 'Saída para Almoço',
            'volta_almoco' => 'Volta do Almoço',
            'saida' => 'Saída'
        ];
        
        $_SESSION['mensagem_ponto'] = "✅ " . $nomes[$tipo] . " registrada com sucesso!";
        $_SESSION['tipo_mensagem_ponto'] = 'success';
        
    } catch (Exception $e) {
        $_SESSION['mensagem_ponto'] = 'Erro ao registrar ponto: ' . $e->getMessage();
        $_SESSION['tipo_mensagem_ponto'] = 'error';
    }
    
    header('Location: ponto.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Bater Ponto - <?php echo htmlspecialchars($funcionario['nome']); ?></title>
    <link rel="icon" type="image/svg+xml" href="/ponto_empresarial/assets/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { max-width: 550px; margin: 0 auto; }
        
        .card {
            background: white;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            animation: fadeInUp 0.5s ease;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 28px 24px;
            text-align: center;
            color: white;
        }
        
        .card-header h1 { font-size: 26px; margin-bottom: 8px; }
        .card-header p { opacity: 0.9; font-size: 13px; }
        
        .datetime {
            margin-top: 16px;
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .datetime .date, .datetime .time {
            background: rgba(255,255,255,0.2);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 13px;
        }
        
        .datetime .time { font-family: monospace; font-size: 16px; font-weight: 600; }
        
        .card-body { padding: 28px; }
        
        .funcionario-info {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 16px;
            margin-bottom: 28px;
            text-align: center;
        }
        
        .funcionario-info h2 { font-size: 18px; font-weight: 700; color: #333; }
        .funcionario-info .matricula { font-size: 12px; color: #666; }
        .funcionario-info .filial { font-size: 11px; color: #667eea; margin-top: 6px; }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 28px;
        }
        
        .status-item {
            text-align: center;
            padding: 12px 6px;
            border-radius: 16px;
        }
        
        .status-item.completed {
            background: #d1fae5;
            color: #059669;
        }
        
        .status-item.pending {
            background: #f1f5f9;
            color: #94a3b8;
        }
        
        .status-item .icon { font-size: 22px; margin-bottom: 6px; display: block; }
        .status-item .label { font-size: 10px; font-weight: 500; display: block; }
        .status-item .time { font-size: 16px; font-weight: 700; display: block; margin-top: 6px; }
        
        .botoes-ponto {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .btn-ponto {
            width: 100%;
            border: none;
            border-radius: 20px;
            padding: 18px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .btn-entrada { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-almoco { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .btn-volta { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-saida { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .btn-facial {
            background: #1e293b;
            color: white;
            border: 1px solid #334155;
        }
        .btn-facial i { color: #38bdf8; }
        .btn-finalizado {
            background: #9ca3af;
            color: white;
            cursor: not-allowed;
        }
        
        .btn-ponto:hover:not(.btn-finalizado) {
            transform: scale(1.02);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success { background: #d1fae5; color: #059669; border-left: 4px solid #059669; }
        .alert-error { background: #fee2e2; color: #dc2626; border-left: 4px solid #dc2626; }
        
        .info-footer {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            color: #64748b;
            margin-bottom: 8px;
        }
        
        .info-item i { width: 18px; color: #667eea; }
        
        .quick-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .quick-btn {
            background: #f1f5f9;
            padding: 8px 16px;
            border-radius: 40px;
            font-size: 12px;
            font-weight: 500;
            color: #475569;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        
        .quick-btn:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        
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
            flex-direction: column;
        }
        
        .camera-container {
            background: #000;
            border-radius: 24px;
            overflow: hidden;
            max-width: 500px;
            width: 90%;
        }
        
        video {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .camera-buttons {
            display: flex;
            gap: 12px;
            padding: 20px;
            background: #1e293b;
        }
        
        .btn-capturar {
            flex: 1;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .btn-fechar-camera {
            flex: 1;
            background: #ef4444;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .loading-camera {
            display: none;
            text-align: center;
            padding: 20px;
            color: white;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #fff3;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 12px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 640px) {
            body {
                padding: 10px;
                min-height: 100svh;
            }

            .container {
                width: 100%;
            }

            .card {
                border-radius: 20px;
            }

            .status-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .quick-actions,
            .camera-buttons {
                flex-direction: column;
            }

            .quick-btn,
            .btn-capturar,
            .btn-fechar-camera {
                justify-content: center;
                width: 100%;
            }

            .camera-container {
                width: calc(100vw - 20px);
                max-height: calc(100svh - 20px);
                border-radius: 18px;
            }

            video {
                max-height: 60svh;
                object-fit: cover;
            }
        }
        
        @media (max-width: 480px) {
            .card-header { padding: 20px; }
            .card-header h1 { font-size: 22px; }
            .card-body { padding: 20px; }
            .btn-ponto { padding: 14px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-fingerprint"></i> Bater Ponto</h1>
                <p>Registre sua jornada de trabalho</p>
                <div class="datetime">
                    <div class="date"><i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y'); ?></div>
                    <div class="time" id="relogio"><i class="fas fa-clock"></i> <?php echo date('H:i:s'); ?></div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="funcionario-info">
                    <h2><?php echo htmlspecialchars($funcionario['nome']); ?></h2>
                    <div class="matricula"><i class="fas fa-id-badge"></i> Matrícula: <?php echo htmlspecialchars($funcionario['matricula']); ?></div>
                    <div class="filial"><i class="fas fa-store"></i> Filial: <?php echo htmlspecialchars($funcionario['filial_nome']); ?></div>
                </div>
                
                <?php if ($mensagem): ?>
                <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                    <i class="fas <?php echo $tipo_mensagem == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo $mensagem; ?>
                </div>
                <?php endif; ?>
                
                <div class="status-grid">
                    <div class="status-item <?php echo $horarios['entrada'] != '--:--' ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-sign-in-alt icon"></i>
                        <span class="label">Entrada</span>
                        <span class="time"><?php echo $horarios['entrada']; ?></span>
                    </div>
                    <div class="status-item <?php echo $horarios['saida_almoco'] != '--:--' ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-utensils icon"></i>
                        <span class="label">Saída Almoço</span>
                        <span class="time"><?php echo $horarios['saida_almoco']; ?></span>
                    </div>
                    <div class="status-item <?php echo $horarios['volta_almoco'] != '--:--' ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-undo-alt icon"></i>
                        <span class="label">Volta Almoço</span>
                        <span class="time"><?php echo $horarios['volta_almoco']; ?></span>
                    </div>
                    <div class="status-item <?php echo $horarios['saida'] != '--:--' ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-sign-out-alt icon"></i>
                        <span class="label">Saída</span>
                        <span class="time"><?php echo $horarios['saida']; ?></span>
                    </div>
                </div>
                
                <?php if ($proximo_tipo !== 'finalizado'): ?>
                <div class="botoes-ponto">
                    <button class="btn-ponto btn-facial" id="btnFacial" data-tipo="<?php echo $proximo_tipo; ?>">
                        <i class="fas fa-camera"></i> 
                        🔒 <?php echo $proximo_texto; ?> com Facial
                    </button>
                    
                    <form method="POST" action="" id="formManual">
                        <input type="hidden" name="latitude" id="latitude">
                        <input type="hidden" name="longitude" id="longitude">
                        <input type="hidden" name="acao" value="<?php echo $proximo_tipo; ?>">
                        <button type="submit" class="btn-ponto btn-<?php echo $cores_botao[$proximo_tipo]; ?>">
                            <i class="fas <?php echo $icones_botao[$proximo_tipo]; ?>"></i>
                            <?php echo $proximo_texto; ?> (Manual)
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <button class="btn-ponto btn-finalizado" disabled>
                    <i class="fas fa-check-circle"></i> ✅ Dia Finalizado! Volte amanhã.
                </button>
                <?php endif; ?>
                
                <div class="info-footer">
                    <div class="info-item"><i class="fas fa-info-circle"></i> <span>Horário permitido: 06:00 às 22:00</span></div>
                    <div class="info-item" id="gpsStatus"><i class="fas fa-map-marker-alt"></i> <span>Capturando localização...</span></div>
                </div>
                
                <div class="quick-actions">
                    <a href="extrato.php" class="quick-btn"><i class="fas fa-calendar-alt"></i> Meu Extrato</a>
                    <a href="../solicitacoes/index.php" class="quick-btn"><i class="fas fa-clipboard-list"></i> Solicitações</a>
                    <a href="../../logout.php" class="quick-btn"><i class="fas fa-sign-out-alt"></i> Sair</a>
                </div>
            </div>
        </div>
    </div>
    
    <div id="cameraModal" class="camera-modal">
        <div class="camera-container">
            <video id="video" autoplay playsinline></video>
            <canvas id="canvas" style="display: none;"></canvas>
            <div class="camera-buttons">
                <button class="btn-capturar" id="capturarFoto">📸 Capturar e Validar</button>
                <button class="btn-fechar-camera" id="fecharCamera">❌ Fechar</button>
            </div>
            <div id="loadingCamera" class="loading-camera">
                <div class="spinner"></div>
                <p>Validando reconhecimento facial...</p>
            </div>
        </div>
    </div>
    
    <script src="/ponto_empresarial/assets/js/face-api.min.js"></script>
    <script>
        let stream = null;
        let tipoPonto = null;
        let faceModelsLoaded = false;
        
        function atualizarRelogio() {
            const agora = new Date();
            const horas = String(agora.getHours()).padStart(2, '0');
            const minutos = String(agora.getMinutes()).padStart(2, '0');
            const segundos = String(agora.getSeconds()).padStart(2, '0');
            const relogio = document.getElementById('relogio');
            if (relogio) {
                relogio.innerHTML = `<i class="fas fa-clock"></i> ${horas}:${minutos}:${segundos}`;
            }
        }
        setInterval(atualizarRelogio, 1000);
        
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                document.getElementById('latitude').value = position.coords.latitude;
                document.getElementById('longitude').value = position.coords.longitude;
                document.getElementById('gpsStatus').innerHTML = '<i class="fas fa-check-circle"></i> <span>Localização capturada</span>';
            }, function(error) {
                document.getElementById('gpsStatus').innerHTML = '<i class="fas fa-exclamation-triangle"></i> <span>GPS não disponível</span>';
            });
        }
        
        const btnFacial = document.getElementById('btnFacial');
        const cameraModal = document.getElementById('cameraModal');
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const capturarBtn = document.getElementById('capturarFoto');
        const fecharBtn = document.getElementById('fecharCamera');
        const loadingCamera = document.getElementById('loadingCamera');
        
        if (btnFacial) {
            btnFacial.addEventListener('click', async function() {
                tipoPonto = this.getAttribute('data-tipo');
                console.log('Tipo de ponto:', tipoPonto);
                
                if (!tipoPonto || tipoPonto === 'finalizado') {
                    alert('Nenhum ponto pendente para registrar');
                    return;
                }
                
                await abrirCamera();
            });
        }

        async function carregarModelosFaciais() {
            if (faceModelsLoaded) {
                return true;
            }

            if (!window.faceapi) {
                console.error('face-api.js nao foi carregado');
                return false;
            }

            const localModelUrl = new URL('../../assets/models', window.location.href).href.replace(/\/$/, '');
            const modelSources = [
                localModelUrl,
                '/ponto_empresarial/assets/models',
                'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@master/weights'
            ];

            for (const modelUrl of modelSources) {
                try {
                    await faceapi.nets.tinyFaceDetector.loadFromUri(modelUrl);
                    await faceapi.nets.faceLandmark68Net.loadFromUri(modelUrl);
                    await faceapi.nets.faceRecognitionNet.loadFromUri(modelUrl);
                    faceModelsLoaded = true;
                    return true;
                } catch (err) {
                    console.warn('Falha ao carregar modelos faciais em ' + modelUrl, err);
                }
            }

            return false;
        }
        
        async function abrirCamera() {
            cameraModal.style.display = 'flex';
            loadingCamera.style.display = 'block';
            capturarBtn.disabled = true;

            try {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('Navegador nao suporta acesso a camera');
                }

                if (!window.isSecureContext && !['localhost', '127.0.0.1', '::1'].includes(window.location.hostname)) {
                    throw new Error('No celular, a camera exige HTTPS ou acesso via localhost');
                }

                const modelosOk = await carregarModelosFaciais();
                if (!modelosOk) {
                    throw new Error('Nao foi possivel carregar os modelos de reconhecimento facial');
                }

                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user' },
                    audio: false
                });
                video.srcObject = stream;
                await video.play().catch(() => {});
                loadingCamera.style.display = 'none';
                capturarBtn.disabled = false;
            } catch (err) {
                alert('Erro ao acessar a câmera: ' + err.message);
                fecharCamera();
            }
        }
        
        capturarBtn.addEventListener('click', async function() {
            if (!tipoPonto) {
                alert('Tipo de ponto não definido');
                fecharCamera();
                return;
            }
            
            loadingCamera.style.display = 'block';
            capturarBtn.disabled = true;

            const modelosOk = await carregarModelosFaciais();
            if (!modelosOk) {
                alert('Nao foi possivel carregar o reconhecimento facial. Tente novamente.');
                loadingCamera.style.display = 'none';
                capturarBtn.disabled = false;
                return;
            }

            const detection = await faceapi
                .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!detection) {
                alert('Nenhum rosto detectado. Centralize o rosto, melhore a iluminacao e tente novamente.');
                loadingCamera.style.display = 'none';
                capturarBtn.disabled = false;
                return;
            }
            
            const context = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const fotoBase64 = canvas.toDataURL('image/jpeg', 0.8);
            
            const latitude = document.getElementById('latitude')?.value || null;
            const longitude = document.getElementById('longitude')?.value || null;
            
            const dados = {
                tipo: tipoPonto,
                foto: fotoBase64,
                descritor: Array.from(detection.descriptor),
                latitude: latitude,
                longitude: longitude
            };
            
            try {
                const response = await fetch('processar_facial.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dados)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.location.href = 'ponto.php';
                } else {
                    alert('❌ ' + result.message);
                    loadingCamera.style.display = 'none';
                    capturarBtn.disabled = false;
                }
            } catch (err) {
                alert('Erro ao processar: ' + err.message);
                loadingCamera.style.display = 'none';
                capturarBtn.disabled = false;
            }
        });
        
        function fecharCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            cameraModal.style.display = 'none';
            video.srcObject = null;
            loadingCamera.style.display = 'none';
            capturarBtn.disabled = false;
        }
        
        fecharBtn.addEventListener('click', fecharCamera);
    </script>
</body>
</html>
