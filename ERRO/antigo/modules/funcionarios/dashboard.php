<?php
// modules/funcionario/dashboard.php - Dashboard Profissional do Funcionário
session_start();

// Verificar se está logado
if (!isset($_SESSION['funcionario_id'])) {
    header('Location: login_facial.php');
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$funcionario_id = $_SESSION['funcionario_id'];

// Buscar dados do funcionário
$stmt = $db->prepare("SELECT f.*, fi.nome_fantasia as filial_nome 
                      FROM funcionarios f
                      LEFT JOIN filiais fi ON f.filial_id = fi.id
                      WHERE f.id = :id");
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    session_destroy();
    header('Location: login_facial.php');
    exit;
}

// Horário atual
$hora_atual = date('H');
$saudacao = 'Bom dia';
if ($hora_atual >= 12 && $hora_atual < 18) {
    $saudacao = 'Boa tarde';
} elseif ($hora_atual >= 18) {
    $saudacao = 'Boa noite';
}

// Buscar pontos de hoje
$stmt = $db->prepare("SELECT tipo, data_hora FROM pontos 
                      WHERE funcionario_id = :id AND DATE(data_hora) = CURDATE() 
                      ORDER BY data_hora ASC");
$stmt->execute([':id' => $funcionario_id]);
$pontosHoje = $stmt->fetchAll();

// Mapear horários
$horarios = [
    'entrada' => null,
    'saida_almoco' => null,
    'volta_almoco' => null,
    'saida' => null
];
foreach ($pontosHoje as $ponto) {
    $horarios[$ponto['tipo']] = date('H:i', strtotime($ponto['data_hora']));
}

// Verificar próximo ponto
$tipos = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
$proximo_tipo = 'entrada';
$proximo_texto = 'Registrar Entrada';
$proximo_icone = 'fa-sign-in-alt';

if (count($pontosHoje) > 0) {
    $ultimo = $pontosHoje[count($pontosHoje) - 1]['tipo'];
    $indice = array_search($ultimo, $tipos);
    if ($indice !== false && $indice < 3) {
        $proximo_tipo = $tipos[$indice + 1];
        $proximo_texto = [
            'entrada' => 'Registrar Entrada',
            'saida_almoco' => 'Registrar Saída para Almoço',
            'volta_almoco' => 'Registrar Volta do Almoço',
            'saida' => 'Registrar Saída'
        ][$proximo_tipo];
        $proximo_icone = [
            'entrada' => 'fa-sign-in-alt',
            'saida_almoco' => 'fa-utensils',
            'volta_almoco' => 'fa-undo-alt',
            'saida' => 'fa-sign-out-alt'
        ][$proximo_tipo];
    } else {
        $proximo_tipo = 'finalizado';
        $proximo_texto = 'Dia Finalizado';
        $proximo_icone = 'fa-check-circle';
    }
}

// Calcular horas trabalhadas hoje
$horas_trabalhadas = '00:00';
if ($horarios['entrada'] && $horarios['saida']) {
    $entrada = strtotime($horarios['entrada']);
    $saida = strtotime($horarios['saida']);
    $total = $saida - $entrada;
    
    if ($horarios['saida_almoco'] && $horarios['volta_almoco']) {
        $almoco = strtotime($horarios['saida_almoco']);
        $volta = strtotime($horarios['volta_almoco']);
        $total -= ($volta - $almoco);
    }
    
    $horas_trabalhadas = floor($total / 3600) . ':' . str_pad(floor(($total % 3600) / 60), 2, '0', STR_PAD_LEFT);
}

// Buscar pontos do mês para estatísticas
$stmt = $db->prepare("SELECT 
    COUNT(DISTINCT DATE(data_hora)) as dias_trabalhados,
    COUNT(*) as total_pontos
    FROM pontos 
    WHERE funcionario_id = :id 
    AND MONTH(data_hora) = MONTH(CURDATE()) 
    AND YEAR(data_hora) = YEAR(CURDATE())");
$stmt->execute([':id' => $funcionario_id]);
$stats_mes = $stmt->fetch();

$dias_trabalhados = $stats_mes['dias_trabalhados'] ?? 0;
$total_pontos_mes = $stats_mes['total_pontos'] ?? 0;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($funcionario['nome']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }
        
        /* Top Bar */
        .top-bar {
            background: white;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .logo i {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-size: 24px;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .user-role {
            font-size: 11px;
            color: #666;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        .logout-btn {
            color: #ef4444;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: #fee2e2;
        }
        
        /* Container */
        .dashboard-container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 24px;
        }
        
        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 24px;
            padding: 32px;
            color: white;
            margin-bottom: 32px;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .welcome-card h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .welcome-card p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .welcome-card .date {
            margin-top: 16px;
            font-size: 13px;
            opacity: 0.8;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #667eea20, #764ba220);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #667eea;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }
        
        .stat-label {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }
        
        /* Main Grid */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .card {
            background: white;
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .card h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card h3 i {
            color: #667eea;
        }
        
        /* Botão Ponto */
        .btn-ponto {
            width: 100%;
            padding: 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 20px;
            font-size: 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .btn-ponto:hover:not(:disabled) {
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-ponto:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Horários do Dia */
        .horarios-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-top: 20px;
        }
        
        .horario-item {
            text-align: center;
            padding: 16px 8px;
            border-radius: 16px;
            transition: all 0.3s;
        }
        
        .horario-item.completed {
            background: #d1fae5;
            color: #059669;
        }
        
        .horario-item.pending {
            background: #f3f4f6;
            color: #9ca3af;
        }
        
        .horario-item .icon {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
        }
        
        .horario-item .label {
            font-size: 11px;
            display: block;
        }
        
        .horario-item .time {
            font-size: 20px;
            font-weight: 700;
            display: block;
            margin-top: 8px;
        }
        
        /* Ações Rápidas */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        
        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 16px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
            text-align: center;
        }
        
        .action-btn:hover {
            background: #667eea10;
            transform: translateY(-3px);
        }
        
        .action-btn i {
            font-size: 28px;
            color: #667eea;
        }
        
        .action-btn span {
            font-size: 13px;
            font-weight: 500;
        }
        
        /* Notificações */
        .notification-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f2f5;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-icon {
            width: 36px;
            height: 36px;
            background: #fef3c7;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f59e0b;
        }
        
        .notification-text {
            flex: 1;
        }
        
        .notification-title {
            font-size: 13px;
            font-weight: 500;
            color: #333;
        }
        
        .notification-desc {
            font-size: 11px;
            color: #666;
        }
        
        .empty-state {
            text-align: center;
            padding: 32px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 12px;
            display: block;
        }
        
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .main-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .horarios-grid {
                flex-wrap: wrap;
            }
            
            .dashboard-container {
                padding: 16px;
            }
            
            .welcome-card h1 {
                font-size: 22px;
            }
            
            .user-info {
                display: none;
            }
        }
        
        /* Animação de fade in */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stats-grid, .main-grid, .actions-card {
            animation: fadeInUp 0.5s ease forwards;
        }
        
        .stat-card:nth-child(1) { animation-delay: 0s; }
        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.2s; }
        .stat-card:nth-child(4) { animation-delay: 0.3s; }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">
            <i class="fas fa-clock"></i>
            <span>PontoFácil</span>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars(explode(' ', $funcionario['nome'])[0]); ?></div>
                <div class="user-role">Funcionário</div>
            </div>
            <div class="user-avatar">
                <?php echo strtoupper(substr($funcionario['nome'], 0, 1)); ?>
            </div>
            <a href="../../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Sair</span>
            </a>
        </div>
    </div>
    
    <div class="dashboard-container">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <h1><?php echo $saudacao . ', ' . explode(' ', $funcionario['nome'])[0]; ?>! 👋</h1>
            <p>Matrícula: <?php echo htmlspecialchars($funcionario['matricula']); ?> | 
               Filial: <?php echo htmlspecialchars($funcionario['filial_nome']); ?></p>
            <div class="date">
                <i class="fas fa-calendar-alt"></i> <?php echo date('l, d \\d\\e F \\d\\e Y'); ?>
                <i class="fas fa-clock" style="margin-left: 12px;"></i> <span id="relogio"><?php echo date('H:i:s'); ?></span>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                </div>
                <div class="stat-value"><?php echo $dias_trabalhados; ?></div>
                <div class="stat-label">Dias trabalhados no mês</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                </div>
                <div class="stat-value"><?php echo $horas_trabalhadas; ?></div>
                <div class="stat-label">Horas trabalhadas hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-fingerprint"></i></div>
                </div>
                <div class="stat-value"><?php echo count($pontosHoje); ?>/4</div>
                <div class="stat-label">Pontos registrados hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                </div>
                <div class="stat-value"><?php echo $total_pontos_mes; ?></div>
                <div class="stat-label">Total de pontos no mês</div>
            </div>
        </div>
        
        <!-- Main Grid -->
        <div class="main-grid">
            <!-- Bater Ponto Card -->
            <div class="card">
                <h3><i class="fas fa-fingerprint"></i> Bater Ponto</h3>
                
                <form method="POST" action="../ponto/registrar.php">
                    <input type="hidden" name="origem" value="dashboard">
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                    
                    <?php if ($proximo_tipo !== 'finalizado'): ?>
                    <button type="submit" name="acao" value="<?php echo $proximo_tipo; ?>" class="btn-ponto">
                        <i class="fas <?php echo $proximo_icone; ?>"></i> <?php echo $proximo_texto; ?>
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn-ponto" disabled>
                        <i class="fas fa-check-circle"></i> <?php echo $proximo_texto; ?>
                    </button>
                    <?php endif; ?>
                </form>
                
                <div class="horarios-grid">
                    <div class="horario-item <?php echo $horarios['entrada'] ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-sign-in-alt icon"></i>
                        <span class="label">Entrada</span>
                        <span class="time"><?php echo $horarios['entrada'] ?? '--:--'; ?></span>
                    </div>
                    <div class="horario-item <?php echo $horarios['saida_almoco'] ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-utensils icon"></i>
                        <span class="label">Saída Almoço</span>
                        <span class="time"><?php echo $horarios['saida_almoco'] ?? '--:--'; ?></span>
                    </div>
                    <div class="horario-item <?php echo $horarios['volta_almoco'] ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-undo-alt icon"></i>
                        <span class="label">Volta Almoço</span>
                        <span class="time"><?php echo $horarios['volta_almoco'] ?? '--:--'; ?></span>
                    </div>
                    <div class="horario-item <?php echo $horarios['saida'] ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-sign-out-alt icon"></i>
                        <span class="label">Saída</span>
                        <span class="time"><?php echo $horarios['saida'] ?? '--:--'; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Notificações / Avisos -->
            <div class="card">
                <h3><i class="fas fa-bell"></i> Notificações</h3>
                <div class="notification-item">
                    <div class="notification-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="notification-text">
                        <div class="notification-title">Horário de funcionamento</div>
                        <div class="notification-desc">Ponto permitido das 06:00 às 22:00</div>
                    </div>
                </div>
                <?php if (count($pontosHoje) < 4 && $proximo_tipo !== 'finalizado'): ?>
                <div class="notification-item">
                    <div class="notification-icon" style="background: #d1fae5; color: #059669;">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="notification-text">
                        <div class="notification-title">Próximo registro pendente</div>
                        <div class="notification-desc">Você ainda precisa registrar: <?php echo str_replace('Registrar ', '', $proximo_texto); ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($proximo_tipo === 'finalizado'): ?>
                <div class="notification-item">
                    <div class="notification-icon" style="background: #fee2e2; color: #dc2626;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="notification-text">
                        <div class="notification-title">Dia finalizado</div>
                        <div class="notification-desc">Você registrou todos os pontos de hoje. Bom descanso!</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Ações Rápidas -->
        <div class="card" style="margin-bottom: 0;">
            <h3><i class="fas fa-th-large"></i> Ações Rápidas</h3>
            <div class="actions-grid">
                <a href="../ponto/extrato.php" class="action-btn">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Meu Extrato</span>
                </a>
                <a href="../solicitacoes/index.php" class="action-btn">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Solicitações</span>
                </a>
                <a href="../cracha/index.php" class="action-btn">
                    <i class="fas fa-id-card"></i>
                    <span>Meu Crachá</span>
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Relógio em tempo real
        function atualizarRelogio() {
            const agora = new Date();
            const horas = String(agora.getHours()).padStart(2, '0');
            const minutos = String(agora.getMinutes()).padStart(2, '0');
            const segundos = String(agora.getSeconds()).padStart(2, '0');
            const relogio = document.getElementById('relogio');
            if (relogio) {
                relogio.textContent = `${horas}:${minutos}:${segundos}`;
            }
        }
        setInterval(atualizarRelogio, 1000);
        atualizarRelogio();
        
        // Capturar localização
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                document.getElementById('latitude').value = position.coords.latitude;
                document.getElementById('longitude').value = position.coords.longitude;
            }, function(error) {
                console.log('GPS não disponível');
            });
        }
    </script>
</body>
</html>