<?php
// modules/ponto/ponto.php - Tela de Ponto do Funcionário (VERSÃO CORRIGIDA)
session_start();

// Verificar se está logado
if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Registrar Ponto';
$activePage = 'ponto';

// Pega o ID do funcionário
$funcionario_id = $_SESSION['funcionario_id'] ?? null;

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Se não tiver funcionario_id, tenta buscar
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
    header('Location: ../../login.php');
    exit;
}

// Buscar pontos de hoje
$stmt = $db->prepare("SELECT tipo, data_hora FROM pontos 
                      WHERE funcionario_id = :id AND DATE(data_hora) = CURDATE() 
                      ORDER BY data_hora ASC");
$stmt->execute([':id' => $funcionario_id]);
$pontosHoje = $stmt->fetchAll();

// Mapear quais tipos já foram registrados hoje
$tiposRegistrados = [];
foreach ($pontosHoje as $ponto) {
    $tiposRegistrados[] = $ponto['tipo'];
}

// Definir a sequência correta
$sequencia = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];

// Calcular qual o próximo tipo de ponto
$proximo_tipo = null;
$proximo_texto = null;
$proximo_icone = null;
$proximo_cor = null;

// Verificar se todos os pontos foram registrados
$todosRegistrados = true;
foreach ($sequencia as $tipo) {
    if (!in_array($tipo, $tiposRegistrados)) {
        $todosRegistrados = false;
        $proximo_tipo = $tipo;
        break;
    }
}

// Se todos foram registrados, dia finalizado
if ($todosRegistrados) {
    $proximo_tipo = 'finalizado';
    $proximo_texto = 'Dia Finalizado';
    $proximo_icone = 'fa-check-circle';
    $proximo_cor = 'finalizado';
} else {
    // Definir texto e ícone baseado no próximo tipo
    switch ($proximo_tipo) {
        case 'entrada':
            $proximo_texto = 'Registrar Entrada';
            $proximo_icone = 'fa-sign-in-alt';
            $proximo_cor = 'entrada';
            break;
        case 'saida_almoco':
            $proximo_texto = 'Registrar Saída para Almoço';
            $proximo_icone = 'fa-utensils';
            $proximo_cor = 'almoco';
            break;
        case 'volta_almoco':
            $proximo_texto = 'Registrar Volta do Almoço';
            $proximo_icone = 'fa-undo-alt';
            $proximo_cor = 'volta';
            break;
        case 'saida':
            $proximo_texto = 'Registrar Saída';
            $proximo_icone = 'fa-sign-out-alt';
            $proximo_cor = 'saida';
            break;
    }
}

// Mapear horários para exibição
$horarios = [
    'entrada' => null,
    'saida_almoco' => null,
    'volta_almoco' => null,
    'saida' => null
];
foreach ($pontosHoje as $ponto) {
    $horarios[$ponto['tipo']] = date('H:i', strtotime($ponto['data_hora']));
}

// Mensagem de feedback
$mensagem = '';
$tipo_mensagem = '';
if (isset($_SESSION['mensagem_ponto'])) {
    $mensagem = $_SESSION['mensagem_ponto'];
    $tipo_mensagem = $_SESSION['tipo_mensagem_ponto'];
    unset($_SESSION['mensagem_ponto']);
    unset($_SESSION['tipo_mensagem_ponto']);
}

// Processar registro de ponto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $tipo = $_POST['acao'];
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    
    // VALIDAÇÃO: Verificar se o tipo já foi registrado hoje
    if (in_array($tipo, $tiposRegistrados)) {
        $_SESSION['mensagem_ponto'] = "Você já registrou " . str_replace('_', ' ', $tipo) . " hoje!";
        $_SESSION['tipo_mensagem_ponto'] = 'error';
        header('Location: ponto.php');
        exit;
    }
    
    // VALIDAÇÃO: Verificar sequência
    $indice_tipo = array_search($tipo, $sequencia);
    $ultimo_registrado = end($tiposRegistrados);
    $indice_ultimo = array_search($ultimo_registrado, $sequencia);
    
    // Se não for o primeiro e não for o próximo na sequência
    if (!empty($tiposRegistrados) && $indice_tipo != $indice_ultimo + 1) {
        $proximo_correto = $sequencia[$indice_ultimo + 1] ?? 'finalizar';
        $nome_proximo = str_replace('_', ' ', $proximo_correto);
        $_SESSION['mensagem_ponto'] = "Sequência incorreta! Próximo ponto deve ser: " . ucfirst($nome_proximo);
        $_SESSION['tipo_mensagem_ponto'] = 'error';
        header('Location: ponto.php');
        exit;
    }
    
    // Registrar ponto
    try {
        $stmt = $db->prepare("INSERT INTO pontos (funcionario_id, filial_id, tipo, data_hora, latitude, longitude, origem) 
                              VALUES (:funcionario_id, :filial_id, :tipo, NOW(), :latitude, :longitude, 'web')");
        $stmt->execute([
            ':funcionario_id' => $funcionario_id,
            ':filial_id' => $funcionario['filial_id'],
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 550px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            animation: fadeInUp 0.5s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 28px 24px;
            text-align: center;
            color: white;
        }
        
        .card-header h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .card-header p {
            opacity: 0.9;
            font-size: 13px;
        }
        
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
            font-weight: 500;
        }
        
        .datetime .time {
            font-family: monospace;
            font-size: 16px;
            font-weight: 600;
        }
        
        .card-body {
            padding: 28px;
        }
        
        .funcionario-info {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 16px;
            margin-bottom: 28px;
            text-align: center;
        }
        
        .funcionario-info h2 {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
        }
        
        .funcionario-info .matricula {
            font-size: 12px;
            color: #666;
        }
        
        .funcionario-info .filial {
            font-size: 11px;
            color: #667eea;
            margin-top: 6px;
        }
        
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
            transition: all 0.3s;
        }
        
        .status-item.completed {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #059669;
        }
        
        .status-item.pending {
            background: #f1f5f9;
            color: #94a3b8;
        }
        
        .status-item .icon {
            font-size: 22px;
            margin-bottom: 6px;
            display: block;
        }
        
        .status-item .label {
            font-size: 10px;
            font-weight: 500;
            display: block;
        }
        
        .status-item .time {
            font-size: 16px;
            font-weight: 700;
            display: block;
            margin-top: 6px;
        }
        
        .btn-ponto {
            width: 100%;
            border: none;
            border-radius: 20px;
            padding: 20px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .btn-ponto.entrada {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-ponto.almoco {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .btn-ponto.volta {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-ponto.saida {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .btn-ponto.finalizado {
            background: #9ca3af;
            color: white;
            cursor: not-allowed;
        }
        
        .btn-ponto:hover:not(.finalizado) {
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
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: #d1fae5;
            color: #059669;
            border-left: 4px solid #059669;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        
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
        
        .info-item i {
            width: 18px;
            color: #667eea;
        }
        
        .quick-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .quick-btn {
            background: #f1f5f9;
            border: none;
            padding: 8px 16px;
            border-radius: 40px;
            font-size: 12px;
            font-weight: 500;
            color: #475569;
            cursor: pointer;
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
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .loading-spinner {
            background: white;
            border-radius: 20px;
            padding: 25px;
            text-align: center;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 12px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 12px;
            }
            
            .card-header {
                padding: 20px;
            }
            
            .card-header h1 {
                font-size: 22px;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .status-grid {
                gap: 6px;
            }
            
            .status-item .icon {
                font-size: 18px;
            }
            
            .status-item .time {
                font-size: 13px;
            }
            
            .btn-ponto {
                padding: 16px;
                font-size: 16px;
            }
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
                    <div class="date">
                        <i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y'); ?>
                    </div>
                    <div class="time" id="relogio">
                        <i class="fas fa-clock"></i> <?php echo date('H:i:s'); ?>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="funcionario-info">
                    <h2><?php echo htmlspecialchars($funcionario['nome']); ?></h2>
                    <div class="matricula">
                        <i class="fas fa-id-badge"></i> Matrícula: <?php echo htmlspecialchars($funcionario['matricula']); ?>
                    </div>
                    <div class="filial">
                        <i class="fas fa-store"></i> Filial: <?php echo htmlspecialchars($funcionario['filial_nome']); ?>
                    </div>
                </div>
                
                <?php if ($mensagem): ?>
                <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                    <i class="fas <?php echo $tipo_mensagem == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo $mensagem; ?>
                </div>
                <?php endif; ?>
                
                <div class="status-grid">
                    <div class="status-item <?php echo $horarios['entrada'] ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-sign-in-alt icon"></i>
                        <span class="label">Entrada</span>
                        <span class="time"><?php echo $horarios['entrada'] ?? '--:--'; ?></span>
                    </div>
                    <div class="status-item <?php echo $horarios['saida_almoco'] ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-utensils icon"></i>
                        <span class="label">Saída Almoço</span>
                        <span class="time"><?php echo $horarios['saida_almoco'] ?? '--:--'; ?></span>
                    </div>
                    <div class="status-item <?php echo $horarios['volta_almoco'] ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-undo-alt icon"></i>
                        <span class="label">Volta Almoço</span>
                        <span class="time"><?php echo $horarios['volta_almoco'] ?? '--:--'; ?></span>
                    </div>
                    <div class="status-item <?php echo $horarios['saida'] ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-sign-out-alt icon"></i>
                        <span class="label">Saída</span>
                        <span class="time"><?php echo $horarios['saida'] ?? '--:--'; ?></span>
                    </div>
                </div>
                
                <?php if ($proximo_tipo !== 'finalizado'): ?>
                <form method="POST" action="" id="pontoForm">
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                    <input type="hidden" name="acao" value="<?php echo $proximo_tipo; ?>">
                    <button type="submit" class="btn-ponto <?php echo $proximo_cor; ?>">
                        <i class="fas <?php echo $proximo_icone; ?>"></i>
                        <?php echo $proximo_texto; ?>
                    </button>
                </form>
                <?php else: ?>
                <button class="btn-ponto finalizado" disabled>
                    <i class="fas fa-check-circle"></i>
                    ✅ Dia Finalizado! Volte amanhã.
                </button>
                <?php endif; ?>
                
                <div class="info-footer">
                    <div class="info-item">
                        <i class="fas fa-info-circle"></i>
                        <span>Horário permitido: 06:00 às 22:00</span>
                    </div>
                    <div class="info-item" id="gpsStatus">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Capturando localização...</span>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <a href="extrato.php" class="quick-btn">
                        <i class="fas fa-calendar-alt"></i> Meu Extrato
                    </a>
                    <a href="../solicitacoes/index.php" class="quick-btn">
                        <i class="fas fa-clipboard-list"></i> Solicitações
                    </a>
                    <a href="../../logout.php" class="quick-btn">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Registrando ponto...</p>
        </div>
    </div>
    
    <script>
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
        } else {
            document.getElementById('gpsStatus').innerHTML = '<i class="fas fa-times-circle"></i> <span>GPS não suportado</span>';
        }
        
        const form = document.getElementById('pontoForm');
        if (form) {
            form.addEventListener('submit', function() {
                document.getElementById('loadingOverlay').style.display = 'flex';
            });
        }
    </script>
</body>
</html>