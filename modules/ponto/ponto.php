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
    <title>Bater Ponto - <?php echo htmlspecialchars($funcionario['nome'] ?? 'Usuário'); ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">

    <!-- Aplica tema antes de renderizar (evita flash) -->
    <script>
        (function(){
            var t = localStorage.getItem('pf_theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            font-family: var(--bs-font-sans-serif);
            padding: 1.5rem 1rem;
            transition: background .3s ease, color .3s ease;
        }
        [data-bs-theme="dark"] body {
            background: var(--pf-gradient-dark);
        }
        .pf-ponto-card { max-width: 540px; margin: 0 auto; position: relative; }
        .pf-ponto-header {
            background: var(--pf-gradient);
            border-radius: 1.5rem 1.5rem 0 0;
            padding: 2rem;
            text-align: center;
            color: #fff;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.25);
            position: relative;
        }
        .pf-ponto-theme-pos {
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 10;
        }
        .pf-ponto-body {
            background: var(--bg-primary);
            border-radius: 0 0 1.5rem 1.5rem;
            padding: 2rem;
            box-shadow: 0 25px 50px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
            border-top: none;
            color: var(--text-primary);
        }
        .pf-clock { font-size: 3.5rem; font-weight: 800; letter-spacing: -2px; }
        .pf-btn-ponto {
            padding: 1rem 1.5rem;
            border-radius: 1rem;
            font-size: 1rem;
            font-weight: 700;
            border: none;
            width: 100%;
            transition: transform .2s, box-shadow .2s;
            cursor: pointer;
            color: #fff;
        }
        .pf-btn-ponto:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.15); }
        .pf-btn-ponto:active { transform: translateY(0); }
        .btn-entrada  { background: linear-gradient(135deg,#10b981,#059669); }
        .btn-s-almoco { background: linear-gradient(135deg,#f59e0b,#d97706); }
        .btn-v-almoco { background: linear-gradient(135deg,#3b82f6,#1d4ed8); }
        .btn-saida    { background: linear-gradient(135deg,#ef4444,#dc2626); }
        .btn-ponto-disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
    </style>
</head>
<body>

<?php
$mensagem = $_SESSION['mensagem_ponto'] ?? '';
$tipoMsg  = $_SESSION['tipo_mensagem_ponto'] ?? 'info';
unset($_SESSION['mensagem_ponto'], $_SESSION['tipo_mensagem_ponto']);

// Registros de hoje do funcionário
$stmt = $db->prepare("SELECT tipo FROM pontos WHERE funcionario_id = :id AND DATE(data_hora) = CURDATE() ORDER BY data_hora ASC");
$stmt->execute([':id' => $funcionario_id]);
$pontosHoje = $stmt->fetchAll(PDO::FETCH_COLUMN);
$tiposRegistrados = array_flip($pontosHoje);
?>

<div class="pf-ponto-card">
    <!-- Header -->
    <div class="pf-ponto-header">
        <div class="pf-ponto-theme-pos">
            <div class="pf-theme-toggle" id="pfThemeToggle" title="Alternar tema">
                <div class="pf-theme-opt" data-theme="light" title="Modo claro">
                    <i class="fas fa-sun"></i>
                </div>
                <div class="pf-theme-opt" data-theme="dark" title="Modo escuro">
                    <i class="fas fa-moon"></i>
                </div>
            </div>
        </div>
        <div class="mb-2 opacity-75 small">Olá,</div>
        <h1 class="h3 fw-bold mb-0"><?php echo htmlspecialchars($funcionario['nome'] ?? ''); ?></h1>
        <div class="opacity-75 small mt-1"><?php echo htmlspecialchars($funcionario['cargo'] ?? ''); ?></div>
        <div class="pf-clock mt-3" id="pfRelógio">--:--:--</div>
        <div class="opacity-75 small" id="pfData"></div>
    </div>

    <!-- Body -->
    <div class="pf-ponto-body">

        <!-- Alerta de retorno -->
        <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $tipoMsg === 'success' ? 'success' : 'danger'; ?> d-flex align-items-center gap-2 mb-4">
            <i class="fas fa-<?php echo $tipoMsg === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($mensagem); ?>
        </div>
        <?php endif; ?>

        <!-- Botões de ponto -->
        <div class="d-grid gap-3">
            <form method="POST">
                <input type="hidden" name="tipo" value="entrada">
                <input type="hidden" name="latitude" id="lat_entrada" value="">
                <input type="hidden" name="longitude" id="lon_entrada" value="">
                <button type="submit" class="pf-btn-ponto btn-entrada <?php echo isset($tiposRegistrados['entrada']) ? 'btn-ponto-disabled' : ''; ?>">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    <?php echo isset($tiposRegistrados['entrada']) ? '✓ Entrada registrada' : 'Registrar Entrada'; ?>
                </button>
            </form>
            <form method="POST">
                <input type="hidden" name="tipo" value="saida_almoco">
                <button type="submit" class="pf-btn-ponto btn-s-almoco <?php echo (isset($tiposRegistrados['saida_almoco']) || !isset($tiposRegistrados['entrada'])) ? 'btn-ponto-disabled' : ''; ?>">
                    <i class="fas fa-utensils me-2"></i>
                    <?php echo isset($tiposRegistrados['saida_almoco']) ? '✓ Saída almoço registrada' : 'Saída para Almoço'; ?>
                </button>
            </form>
            <form method="POST">
                <input type="hidden" name="tipo" value="volta_almoco">
                <button type="submit" class="pf-btn-ponto btn-v-almoco <?php echo (isset($tiposRegistrados['volta_almoco']) || !isset($tiposRegistrados['saida_almoco'])) ? 'btn-ponto-disabled' : ''; ?>">
                    <i class="fas fa-redo me-2"></i>
                    <?php echo isset($tiposRegistrados['volta_almoco']) ? '✓ Volta almoço registrada' : 'Volta do Almoço'; ?>
                </button>
            </form>
            <form method="POST">
                <input type="hidden" name="tipo" value="saida">
                <button type="submit" class="pf-btn-ponto btn-saida <?php echo (isset($tiposRegistrados['saida']) || !isset($tiposRegistrados['entrada'])) ? 'btn-ponto-disabled' : ''; ?>">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    <?php echo isset($tiposRegistrados['saida']) ? '✓ Saída registrada' : 'Registrar Saída'; ?>
                </button>
            </form>
        </div>

        <!-- Registros de hoje -->
        <?php if (!empty($pontosHoje)): ?>
        <div class="mt-4 pt-3 border-top">
            <h6 class="fw-semibold text-muted mb-3"><i class="fas fa-list me-2"></i>Registros de hoje</h6>
            <div class="d-flex flex-wrap gap-2">
                <?php
                $nomes = ['entrada'=>'Entrada','saida_almoco'=>'Saída Almoço','volta_almoco'=>'Volta Almoço','saida'=>'Saída'];
                foreach ($pontosHoje as $t):
                ?>
                <span class="badge bg-success"><i class="fas fa-check me-1"></i><?php echo $nomes[$t] ?? $t; ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Link para extrato -->
        <div class="text-center mt-4">
            <a href="extrato.php" class="text-primary text-decoration-none small">
                <i class="fas fa-list-alt me-1"></i>Ver meu extrato completo
            </a>
        </div>

        <!-- Logout -->
        <div class="text-center mt-2">
            <a href="/logout.php" class="text-muted text-decoration-none small">
                <i class="fas fa-sign-out-alt me-1"></i>Sair
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    function tick() {
        var now = new Date();
        var relEl  = document.getElementById('pfRelógio');
        var dataEl = document.getElementById('pfData');
        if (relEl)  relEl.textContent  = now.toLocaleTimeString('pt-BR');
        if (dataEl) dataEl.textContent = now.toLocaleDateString('pt-BR', { weekday:'long', day:'2-digit', month:'long', year:'numeric' });
    }
    tick();
    setInterval(tick, 1000);

    // Geolocalização
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            document.querySelectorAll('[id^="lat_"]').forEach(function(el) { el.value = pos.coords.latitude; });
            document.querySelectorAll('[id^="lon_"]').forEach(function(el) { el.value = pos.coords.longitude; });
        });
    }
</script>
<script src="/assets/js/theme.js"></script>
</body>
</html>
