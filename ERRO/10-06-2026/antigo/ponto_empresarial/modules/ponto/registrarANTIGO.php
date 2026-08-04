<?php
// modules/ponto/registrar.php - Registro de Ponto (CORRIGIDO)
$pageTitle = 'Registrar Ponto';
$activePage = 'ponto';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$funcionario_id = getCurrentUserId();
$hoje = date('Y-m-d');
$mensagem = '';
$tipo_mensagem = '';

// Buscar pontos de hoje
$query = "SELECT tipo, data_hora FROM pontos 
          WHERE funcionario_id = :id AND DATE(data_hora) = CURDATE()
          ORDER BY data_hora ASC";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $funcionario_id]);
$pontosHoje = $stmt->fetchAll();

$pontosMap = [];
foreach ($pontosHoje as $ponto) {
    $pontosMap[$ponto['tipo']] = $ponto['data_hora'];
}

// Buscar configurações de horário (com fallback)
$horarioMinimo = '06:00:00';
$horarioMaximo = '22:00:00';

try {
    $query = "SELECT chave, valor FROM configuracoes WHERE chave IN ('horario_minimo', 'horario_maximo')";
    $stmt = $db->query($query);
    $configs = $stmt->fetchAll();
    
    foreach ($configs as $config) {
        if ($config['chave'] == 'horario_minimo') {
            $horarioMinimo = $config['valor'];
        } elseif ($config['chave'] == 'horario_maximo') {
            $horarioMaximo = $config['valor'];
        }
    }
} catch (Exception $e) {
    // Se a tabela não existir ou não tiver os dados, usar valores padrão
    $horarioMinimo = '06:00:00';
    $horarioMaximo = '22:00:00';
}

// Processar registro de ponto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $tipo = $_POST['acao'];
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    
    // Validar horário
    $horaAtual = date('H:i:s');
    $horaMinima = $horarioMinimo;
    $horaMaxima = $horarioMaximo;
    
    if ($horaAtual < $horaMinima || $horaAtual > $horaMaxima) {
        $mensagem = 'Fora do horário permitido para bater ponto. Horário permitido: das ' . substr($horaMinima, 0, 5) . ' às ' . substr($horaMaxima, 0, 5);
        $tipo_mensagem = 'error';
    } else {
        // Validar sequência
        $ultimoTipo = null;
        if (!empty($pontosHoje)) {
            $ultimoTipo = end($pontosHoje)['tipo'];
        }
        
        $sequenciaValida = true;
        $mensagemErro = '';
        
        if ($tipo == 'entrada' && !empty($pontosHoje)) {
            $sequenciaValida = false;
            $mensagemErro = 'Você já registrou entrada hoje';
        } elseif ($tipo == 'saida_almoco' && $ultimoTipo != 'entrada') {
            $sequenciaValida = false;
            $mensagemErro = 'Para sair para almoço, você precisa ter registrado a entrada';
        } elseif ($tipo == 'volta_almoco' && $ultimoTipo != 'saida_almoco') {
            $sequenciaValida = false;
            $mensagemErro = 'Para voltar do almoço, você precisa ter registrado a saída';
        } elseif ($tipo == 'saida' && !in_array($ultimoTipo, ['volta_almoco', 'entrada'])) {
            $sequenciaValida = false;
            $mensagemErro = 'Sequência de ponto inválida';
        }
        
        if ($sequenciaValida) {
            try {
                $query = "INSERT INTO pontos (funcionario_id, filial_id, tipo, data_hora, latitude, longitude, origem) 
                          VALUES (:funcionario_id, :filial_id, :tipo, NOW(), :latitude, :longitude, 'web')";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':funcionario_id' => $funcionario_id,
                    ':filial_id' => $_SESSION['usuario_filial_id'],
                    ':tipo' => $tipo,
                    ':latitude' => $latitude,
                    ':longitude' => $longitude
                ]);
                
                logAcao($db, 'INSERT', 'pontos', $db->lastInsertId(), "Registrou ponto: $tipo");
                
                $mensagem = 'Ponto registrado com sucesso!';
                $tipo_mensagem = 'success';
                
                // Recarregar pontos
                header("Refresh:0");
                exit;
                
            } catch (Exception $e) {
                $mensagem = 'Erro ao registrar ponto: ' . $e->getMessage();
                $tipo_mensagem = 'error';
            }
        } else {
            $mensagem = $mensagemErro;
            $tipo_mensagem = 'error';
        }
    }
}
?>

<div class="ponto-container">
    <div class="ponto-card">
        <div class="ponto-header">
            <h2><i class="fas fa-fingerprint"></i> Registro de Ponto</h2>
            <div class="datetime-info">
                <div class="date"><?php echo date('d/m/Y'); ?></div>
                <div class="time" id="currentTimeLarge"><?php echo date('H:i:s'); ?></div>
            </div>
        </div>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <i class="fas <?php echo $tipo_mensagem == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>
        
        <div class="ponto-status">
            <div class="status-info">
                <h3>Status do Dia</h3>
                <div class="status-grid">
                    <div class="status-item <?php echo isset($pontosMap['entrada']) ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Entrada</span>
                        <strong><?php echo isset($pontosMap['entrada']) ? date('H:i', strtotime($pontosMap['entrada'])) : '--:--'; ?></strong>
                    </div>
                    <div class="status-item <?php echo isset($pontosMap['saida_almoco']) ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-utensils"></i>
                        <span>Saída Almoço</span>
                        <strong><?php echo isset($pontosMap['saida_almoco']) ? date('H:i', strtotime($pontosMap['saida_almoco'])) : '--:--'; ?></strong>
                    </div>
                    <div class="status-item <?php echo isset($pontosMap['volta_almoco']) ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-undo-alt"></i>
                        <span>Volta Almoço</span>
                        <strong><?php echo isset($pontosMap['volta_almoco']) ? date('H:i', strtotime($pontosMap['volta_almoco'])) : '--:--'; ?></strong>
                    </div>
                    <div class="status-item <?php echo isset($pontosMap['saida']) ? 'completed' : 'pending'; ?>">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Saída</span>
                        <strong><?php echo isset($pontosMap['saida']) ? date('H:i', strtotime($pontosMap['saida'])) : '--:--'; ?></strong>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="ponto-actions">
            <form method="POST" action="" id="pontoForm">
                <input type="hidden" name="latitude" id="latitude">
                <input type="hidden" name="longitude" id="longitude">
                
                <?php if (!isset($pontosMap['entrada'])): ?>
                <button type="submit" name="acao" value="entrada" class="btn-ponto btn-entrada">
                    <i class="fas fa-sign-in-alt"></i> Registrar Entrada
                </button>
                <?php elseif (!isset($pontosMap['saida_almoco'])): ?>
                <button type="submit" name="acao" value="saida_almoco" class="btn-ponto btn-almoco">
                    <i class="fas fa-utensils"></i> Saída para Almoço
                </button>
                <?php elseif (!isset($pontosMap['volta_almoco'])): ?>
                <button type="submit" name="acao" value="volta_almoco" class="btn-ponto btn-volta">
                    <i class="fas fa-undo-alt"></i> Volta do Almoço
                </button>
                <?php elseif (!isset($pontosMap['saida'])): ?>
                <button type="submit" name="acao" value="saida" class="btn-ponto btn-saida">
                    <i class="fas fa-sign-out-alt"></i> Registrar Saída
                </button>
                <?php else: ?>
                <div class="day-completed">
                    <i class="fas fa-check-circle"></i>
                    <p>Dia finalizado!</p>
                    <small>Você já registrou todos os pontos de hoje.</small>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="ponto-info">
            <div class="info-item">
                <i class="fas fa-info-circle"></i>
                <span>Horário permitido: <?php echo substr($horarioMinimo, 0, 5); ?> às <?php echo substr($horarioMaximo, 0, 5); ?></span>
            </div>
            <div class="info-item">
                <i class="fas fa-map-marker-alt"></i>
                <span id="gpsStatus">Capturando localização...</span>
            </div>
        </div>
    </div>
</div>

<style>
.ponto-container {
    max-width: 600px;
    margin: 0 auto;
}

.ponto-card {
    background: var(--bg-primary);
    border-radius: 24px;
    padding: 32px;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border-color);
}

.ponto-header {
    text-align: center;
    margin-bottom: 32px;
}

.ponto-header h2 {
    color: var(--text-primary);
    margin-bottom: 16px;
}

.datetime-info {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    padding: 16px;
    border-radius: 16px;
}

.datetime-info .date {
    font-size: 14px;
    opacity: 0.9;
}

.datetime-info .time {
    font-size: 32px;
    font-weight: bold;
    margin-top: 8px;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-top: 16px;
}

.status-item {
    text-align: center;
    padding: 12px;
    border-radius: 12px;
    background: var(--bg-secondary);
}

.status-item.completed {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-item.pending {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.status-item i {
    font-size: 24px;
    margin-bottom: 8px;
    display: block;
}

.status-item span {
    font-size: 12px;
    display: block;
}

.status-item strong {
    font-size: 18px;
    margin-top: 4px;
    display: block;
}

.btn-ponto {
    width: 100%;
    padding: 20px;
    border: none;
    border-radius: 16px;
    font-size: 18px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    margin-bottom: 16px;
}

.btn-ponto:hover {
    transform: translateY(-2px);
}

.btn-entrada {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
}

.btn-almoco {
    background: var(--warning);
    color: white;
}

.btn-volta {
    background: var(--success);
    color: white;
}

.btn-saida {
    background: var(--danger);
    color: white;
}

.day-completed {
    text-align: center;
    padding: 40px;
    background: rgba(16, 185, 129, 0.1);
    border-radius: 16px;
    color: var(--success);
}

.day-completed i {
    font-size: 48px;
    margin-bottom: 16px;
}

.ponto-info {
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid var(--border-color);
}

.info-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.alert {
    padding: 12px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
    border: 1px solid rgba(239, 68, 68, 0.2);
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
}
</style>

<script>
// Atualizar relógio
function updateClock() {
    const now = new Date();
    const timeElement = document.getElementById('currentTimeLarge');
    if (timeElement) {
        timeElement.textContent = now.toLocaleTimeString('pt-BR');
    }
}
setInterval(updateClock, 1000);

// Capturar localização GPS
if ("geolocation" in navigator) {
    navigator.geolocation.getCurrentPosition(function(position) {
        document.getElementById('latitude').value = position.coords.latitude;
        document.getElementById('longitude').value = position.coords.longitude;
        document.getElementById('gpsStatus').innerHTML = '<i class="fas fa-check-circle"></i> Localização capturada';
        document.getElementById('gpsStatus').style.color = '#10b981';
    }, function(error) {
        document.getElementById('gpsStatus').innerHTML = '<i class="fas fa-exclamation-triangle"></i> Não foi possível capturar localização';
        document.getElementById('gpsStatus').style.color = '#f59e0b';
    });
} else {
    document.getElementById('gpsStatus').innerHTML = '<i class="fas fa-times-circle"></i> GPS não suportado';
}
</script>

<?php require_once '../../includes/footer.php'; ?>