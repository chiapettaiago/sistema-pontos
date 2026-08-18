<?php
// modules/ponto/ponto.php - Tela de Ponto (VERSÃO CORRIGIDA)
require_once '../../includes/config.php';

// Verificar se está logado
if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
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
    header('Location: ../../login.php');
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
$tem_extra_entrada = in_array('extra_entrada', $tiposRegistrados);
$tem_extra_saida = in_array('extra_saida', $tiposRegistrados);

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
} elseif ($tem_saida && !$tem_extra_entrada) {
    $proximo_tipo = 'extra_entrada';
    $proximo_texto = 'Registrar Entrada Extra';
} elseif ($tem_extra_entrada && !$tem_extra_saida) {
    $proximo_tipo = 'extra_saida';
    $proximo_texto = 'Registrar Saída Extra';
} else {
    $proximo_tipo = 'finalizado';
    $proximo_texto = 'Dia Finalizado!';
}

// Mapear horários para exibição
$horarios = [
    'entrada' => '--:--',
    'saida_almoco' => '--:--',
    'volta_almoco' => '--:--',
    'saida' => '--:--',
    'extra_entrada' => '--:--',
    'extra_saida' => '--:--'
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
    'extra_entrada' => 'Registrar Entrada Extra',
    'extra_saida' => 'Registrar Saída Extra',
    'finalizado' => 'Dia Finalizado!'
];

$icones_botao = [
    'entrada' => 'fa-sign-in-alt',
    'saida_almoco' => 'fa-utensils',
    'volta_almoco' => 'fa-undo-alt',
    'saida' => 'fa-sign-out-alt',
    'extra_entrada' => 'fa-sign-in-alt',
    'extra_saida' => 'fa-sign-out-alt',
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
    
    if ($tipo == 'extra_entrada' && !in_array('saida', $tiposRegistrados)) {
        $_SESSION['mensagem_ponto'] = "Você precisa registrar a saída primeiro!";
        $_SESSION['tipo_mensagem_ponto'] = 'error';
        header('Location: ponto.php');
        exit;
    }
    
    if ($tipo == 'extra_saida' && !in_array('extra_entrada', $tiposRegistrados)) {
        $_SESSION['mensagem_ponto'] = "Você precisa registrar a entrada extra primeiro!";
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
            'saida' => 'Saída',
            'extra_entrada' => 'Entrada Extra',
            'extra_saida' => 'Saída Extra'
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
    <title>Bater Ponto - <?php echo htmlspecialchars($funcionario['nome'] ?? 'Usuário'); ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #7b68ee 0%, #483d8b 100%);
            font-family: 'Inter', sans-serif;
            padding: 1.5rem 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .pf-card {
            background: #ffffff;
            border-radius: 20px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .pf-header {
            background: linear-gradient(135deg, #8172e2 0%, #6a4eb2 100%);
            padding: 30px 20px;
            text-align: center;
            color: white;
        }
        .pf-header h2 {
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 24px;
        }
        .pf-body {
            padding: 25px;
            background: #ffffff;
        }
        .pf-emp-box {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 25px;
        }
        .pf-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        .pf-grid-item {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 15px 5px;
            text-align: center;
        }
        .btn-facial {
            background: #1e293b;
            color: white;
            border-radius: 12px;
            padding: 15px;
            font-weight: 600;
            width: 100%;
            border: none;
            margin-bottom: 12px;
            transition: transform 0.2s;
        }
        .btn-facial:hover { transform: translateY(-2px); color: white; }
        .btn-manual {
            background: linear-gradient(135deg, #7c6df7 0%, #664fbe 100%);
            color: white;
            border-radius: 12px;
            padding: 15px;
            font-weight: 600;
            width: 100%;
            border: none;
            transition: transform 0.2s;
            box-shadow: 0 4px 15px rgba(124, 109, 247, 0.4);
        }
        .btn-manual:hover { transform: translateY(-2px); color: white; }
        .pf-footer-info {
            font-size: 13px;
            color: #6c757d;
            margin-top: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        .pf-nav-btn {
            background: #f8f9fa;
            color: #495057;
            border: none;
            font-size: 13px;
            font-weight: 500;
            padding: 10px 15px;
            border-radius: 50px;
            text-decoration: none;
            transition: background 0.2s;
        }
        .pf-nav-btn:hover { background: #e9ecef; color: #212529; }
    </style>
</head>
<body>

<div class="pf-card">
    <!-- Header -->
    <div class="pf-header">
        <h2><i class="fas fa-fingerprint me-2"></i>Bater Ponto</h2>
        <div class="opacity-75" style="font-size: 14px; margin-bottom: 15px;">Registre sua jornada de trabalho</div>
        <div class="d-flex justify-content-center gap-3">
            <div class="badge rounded-pill" style="background: rgba(255,255,255,0.2); font-size: 14px; padding: 8px 16px; font-weight: normal;">
                <i class="far fa-calendar-alt me-2"></i><span id="pfDataDisplay">--/--/----</span>
            </div>
            <div class="badge rounded-pill" style="background: rgba(255,255,255,0.2); font-size: 14px; padding: 8px 16px; font-weight: normal;">
                <i class="far fa-clock me-2"></i><span id="pfRelógioDisplay">--:--:--</span>
            </div>
        </div>
    </div>

    <!-- Body -->
    <div class="pf-body">
        <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $tipoMsg === 'success' ? 'success' : 'danger'; ?> d-flex align-items-center gap-2 mb-4" style="border-radius: 12px; font-size: 14px;">
            <i class="fas fa-<?php echo $tipoMsg === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($mensagem); ?>
        </div>
        <?php endif; ?>

        <!-- Employee Info -->
        <div class="pf-emp-box">
            <h5 class="fw-bold mb-1" style="color: #343a40; font-size: 18px;"><?php echo htmlspecialchars($funcionario['nome'] ?? ''); ?></h5>
            <div style="font-size: 13px; color: #6c757d; margin-bottom: 4px;">
                <i class="far fa-id-badge me-1"></i> Matrícula: <?php echo htmlspecialchars($funcionario['matricula'] ?? 'Não informada'); ?>
            </div>
            <div style="font-size: 13px; color: #667eea; font-weight: 500;">
                <i class="fas fa-store me-1"></i> Filial: <?php echo htmlspecialchars($funcionario['filial_nome'] ?? 'Não informada'); ?>
            </div>
        </div>

        <!-- Punches Grid -->
        <div class="pf-grid">
            <?php
            $itens_ponto = [
                'entrada' => ['icon' => 'fa-sign-in-alt', 'label' => 'Entrada'],
                'saida_almoco' => ['icon' => 'fa-utensils', 'label' => 'Saída Almoço'],
                'volta_almoco' => ['icon' => 'fa-undo', 'label' => 'Volta Almoço'],
                'saida' => ['icon' => 'fa-sign-out-alt', 'label' => 'Saída']
            ];
            
            // Mostrar horas extras se já houve saída registrada ou se já existe ponto extra
            if (in_array('saida', $tiposRegistrados) || in_array('extra_entrada', $tiposRegistrados) || in_array('extra_saida', $tiposRegistrados)) {
                $itens_ponto['extra_entrada'] = ['icon' => 'fa-sign-in-alt', 'label' => 'Entrada Extra'];
                $itens_ponto['extra_saida'] = ['icon' => 'fa-sign-out-alt', 'label' => 'Saída Extra'];
            }

            foreach($itens_ponto as $tipo => $dados) {
                $registrado = in_array($tipo, $tiposRegistrados);
                $hora = $registrado ? $horarios[$tipo] : '--:--';
                $iconColor = $registrado ? '#7c6df7' : '#adb5bd';
                $textColor = $registrado ? '#343a40' : '#adb5bd';
                
                echo '<div class="pf-grid-item">';
                echo '<i class="fas '.$dados['icon'].' mb-2" style="font-size: 24px; color: '.$iconColor.';"></i>';
                echo '<div style="font-size: 11px; color: #6c757d; margin-bottom: 5px; font-weight: 500;">'.$dados['label'].'</div>';
                echo '<div style="font-size: 15px; font-weight: 700; color: '.$textColor.';">'.$hora.'</div>';
                echo '</div>';
            }
            ?>
        </div>

        <!-- Action Buttons -->
        <form method="POST" id="formPonto">
            <input type="hidden" name="acao" value="<?php echo htmlspecialchars($proximo_tipo); ?>">
            <input type="hidden" name="latitude" id="lat_val" value="">
            <input type="hidden" name="longitude" id="lon_val" value="">
            
            <button type="button" class="btn-facial" onclick="alert('Funcionalidade de reconhecimento facial em desenvolvimento.');">
                <i class="fas fa-camera text-info me-2"></i> 
                <i class="fas fa-lock text-warning me-2"></i> Registrar Entrada com Facial
            </button>
            
            <?php if ($proximo_tipo !== 'finalizado'): ?>
            <button type="submit" class="btn-manual">
                <i class="fas <?php echo $icones_botao[$proximo_tipo] ?? 'fa-sign-in-alt'; ?> me-2"></i> <?php echo $nomes_botao[$proximo_tipo] ?? 'Registrar'; ?> (Manual)
            </button>
            <?php else: ?>
            <button type="button" class="btn-manual" disabled style="opacity: 0.6; cursor: not-allowed; box-shadow: none;">
                <i class="fas fa-check-circle me-2"></i> Dia Finalizado!
            </button>
            <?php endif; ?>
        </form>

        <!-- Footer Info -->
        <div class="pf-footer-info">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-info-circle me-2" style="color: #7c6df7;"></i> Horário permitido: 06:00 às 22:00
            </div>
            <div class="d-flex align-items-center" id="locStatus">
                <i class="fas fa-spinner fa-spin me-2" style="color: #7c6df7;"></i> Capturando localização...
            </div>
        </div>

        <!-- Bottom Navigation -->
        <div class="d-flex justify-content-center gap-2 mt-4">
            <a href="extrato.php" class="pf-nav-btn"><i class="fas fa-calendar-alt me-1"></i> Meu Extrato</a>
            <a href="#" class="pf-nav-btn"><i class="fas fa-clipboard-list me-1"></i> Solicitações</a>
            <a href="/logout.php" class="pf-nav-btn"><i class="fas fa-sign-out-alt me-1"></i> Sair</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    function tick() {
        var now = new Date();
        var relEl  = document.getElementById('pfRelógioDisplay');
        var dataEl = document.getElementById('pfDataDisplay');
        if (relEl)  relEl.textContent  = now.toLocaleTimeString('pt-BR');
        if (dataEl) dataEl.textContent = now.toLocaleDateString('pt-BR');
    }
    tick();
    setInterval(tick, 1000);

    // Geolocalização
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            var latEl = document.getElementById('lat_val');
            var lonEl = document.getElementById('lon_val');
            if(latEl) latEl.value = pos.coords.latitude;
            if(lonEl) lonEl.value = pos.coords.longitude;
            var locEl = document.getElementById('locStatus');
            if (locEl) {
                locEl.innerHTML = '<i class="fas fa-check-circle me-2" style="color: #7c6df7;"></i> Localização capturada';
            }
        }, function(err) {
            var locEl = document.getElementById('locStatus');
            if (locEl) {
                locEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2 text-warning"></i> Localização não permitida';
            }
        });
    } else {
        var locEl = document.getElementById('locStatus');
        if (locEl) {
            locEl.innerHTML = '<i class="fas fa-exclamation-circle me-2 text-danger"></i> Localização não suportada';
        }
    }
})();
</script>
</body>
</html>
