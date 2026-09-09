<?php
// modules/configuracoes/regras.php - Regras de Ponto (CORRIGIDO)
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa'])) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Regras de Ponto';
$activePage = 'configuracoes';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$success = '';
$error = '';

// Verificar se as colunas existem na tabela e adicionar se necessário
try {
    // Verificar e adicionar colunas faltantes
    $colunas = [
        'horas_extras_50' => "ALTER TABLE config_horarios ADD COLUMN horas_extras_50 BIT(1) DEFAULT 1",
        'horas_extras_100' => "ALTER TABLE config_horarios ADD COLUMN horas_extras_100 BIT(1) DEFAULT 1",
        'desconto_atraso' => "ALTER TABLE config_horarios ADD COLUMN desconto_atraso BIT(1) DEFAULT 1",
        'permitir_hora_negativa' => "ALTER TABLE config_horarios ADD COLUMN permitir_hora_negativa BIT(1) DEFAULT 0",
        'justificativa_obrigatoria' => "ALTER TABLE config_horarios ADD COLUMN justificativa_obrigatoria BIT(1) DEFAULT 0",
        'bloquear_ponto_fora_horario' => "ALTER TABLE config_horarios ADD COLUMN bloquear_ponto_fora_horario BIT(1) DEFAULT 0",
        'permitir_ponto_remoto' => "ALTER TABLE config_horarios ADD COLUMN permitir_ponto_remoto BIT(1) DEFAULT 1",
        'validar_gps' => "ALTER TABLE config_horarios ADD COLUMN validar_gps BIT(1) DEFAULT 0",
        'ponto_apenas_empresa' => "ALTER TABLE config_horarios ADD COLUMN ponto_apenas_empresa BIT(1) DEFAULT 1",
        'intervalo_minimo_batidas' => "ALTER TABLE config_horarios ADD COLUMN intervalo_minimo_batidas INT DEFAULT 120",
        'facial_obrigatorio' => "ALTER TABLE config_horarios ADD COLUMN facial_obrigatorio BIT(1) DEFAULT 0",
        'intervalo_minimo_almoco' => "ALTER TABLE config_horarios ADD COLUMN intervalo_minimo_almoco INT DEFAULT 60",
        'intervalo_maximo_almoco' => "ALTER TABLE config_horarios ADD COLUMN intervalo_maximo_almoco INT DEFAULT 120",
        'tempo_minimo_entrada' => "ALTER TABLE config_horarios ADD COLUMN tempo_minimo_entrada INT DEFAULT 30",
        'dias_para_justificar' => "ALTER TABLE config_horarios ADD COLUMN dias_para_justificar INT DEFAULT 7"
    ];
    
    foreach ($colunas as $coluna => $sql) {
        try {
            $db->exec($sql);
        } catch (PDOException $e) {
            // Coluna já existe, ignorar erro
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                // Log outros erros
                error_log("Erro ao adicionar coluna $coluna: " . $e->getMessage());
            }
        }
    }
} catch (Exception $e) {
    // Tabela pode não existir ainda
}

// Buscar configurações atuais
$stmt = $db->prepare("SELECT * FROM config_horarios WHERE empresa_id = :empresa_id");
$stmt->execute([':empresa_id' => $empresa_id]);
$config = $stmt->fetch();

// Se não existir, criar configuração padrão
if (!$config) {
    $stmt = $db->prepare("INSERT INTO config_horarios (empresa_id) VALUES (:empresa_id)");
    $stmt->execute([':empresa_id' => $empresa_id]);
    
    $stmt = $db->prepare("SELECT * FROM config_horarios WHERE empresa_id = :empresa_id");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $config = $stmt->fetch();
}

// Definir valores padrão para evitar warnings
$defaults = [
    'horas_extras_50' => 1,
    'horas_extras_100' => 1,
    'desconto_atraso' => 1,
    'permitir_hora_negativa' => 0,
    'justificativa_obrigatoria' => 0,
    'bloquear_ponto_fora_horario' => 0,
    'permitir_ponto_remoto' => 1,
    'validar_gps' => 0,
    'ponto_apenas_empresa' => 1,
    'intervalo_minimo_batidas' => 120,
    'facial_obrigatorio' => 0,
    'intervalo_minimo_almoco' => 60,
    'intervalo_maximo_almoco' => 120,
    'tempo_minimo_entrada' => 30,
    'dias_para_justificar' => 7
];

// Garantir que todas as chaves existem
foreach ($defaults as $key => $default) {
    if (!isset($config[$key])) {
        $config[$key] = $default;
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $horas_extras_50 = isset($_POST['horas_extras_50']) ? 1 : 0;
    $horas_extras_100 = isset($_POST['horas_extras_100']) ? 1 : 0;
    $desconto_atraso = isset($_POST['desconto_atraso']) ? 1 : 0;
    $permitir_hora_negativa = isset($_POST['permitir_hora_negativa']) ? 1 : 0;
    $justificativa_obrigatoria = isset($_POST['justificativa_obrigatoria']) ? 1 : 0;
    $bloquear_ponto_fora_horario = isset($_POST['bloquear_ponto_fora_horario']) ? 1 : 0;
    $permitir_ponto_remoto = isset($_POST['permitir_ponto_remoto']) ? 1 : 0;
    $validar_gps = isset($_POST['validar_gps']) ? 1 : 0;
    $ponto_apenas_empresa = isset($_POST['ponto_apenas_empresa']) ? 1 : 0;
    $intervalo_minimo_batidas = max(120, (int)($_POST['intervalo_minimo_batidas'] ?? 120));
    $facial_obrigatorio = isset($_POST['facial_obrigatorio']) ? 1 : 0;
    $intervalo_minimo_almoco = (int)($_POST['intervalo_minimo_almoco'] ?? 60);
    $intervalo_maximo_almoco = (int)($_POST['intervalo_maximo_almoco'] ?? 120);
    $tempo_minimo_entrada = (int)($_POST['tempo_minimo_entrada'] ?? 30);
    $dias_para_justificar = (int)($_POST['dias_para_justificar'] ?? 7);
    
    // Validar valores
    if ($intervalo_minimo_almoco < 30) $intervalo_minimo_almoco = 30;
    if ($intervalo_maximo_almoco < 60) $intervalo_maximo_almoco = 60;
    if ($tempo_minimo_entrada < 15) $tempo_minimo_entrada = 15;
    if ($dias_para_justificar < 1) $dias_para_justificar = 1;
    if ($dias_para_justificar > 30) $dias_para_justificar = 30;
    
    try {
        $stmt = $db->prepare("UPDATE config_horarios SET 
            horas_extras_50 = :horas_extras_50,
            horas_extras_100 = :horas_extras_100,
            desconto_atraso = :desconto_atraso,
            permitir_hora_negativa = :permitir_hora_negativa,
            justificativa_obrigatoria = :justificativa_obrigatoria,
            bloquear_ponto_fora_horario = :bloquear_ponto_fora_horario,
            permitir_ponto_remoto = :permitir_ponto_remoto,
            validar_gps = :validar_gps,
            ponto_apenas_empresa = :ponto_apenas_empresa,
            intervalo_minimo_batidas = :intervalo_minimo_batidas,
            facial_obrigatorio = :facial_obrigatorio,
            intervalo_minimo_almoco = :intervalo_minimo_almoco,
            intervalo_maximo_almoco = :intervalo_maximo_almoco,
            tempo_minimo_entrada = :tempo_minimo_entrada,
            dias_para_justificar = :dias_para_justificar
            WHERE empresa_id = :empresa_id");
        
        $stmt->execute([
            ':horas_extras_50' => $horas_extras_50,
            ':horas_extras_100' => $horas_extras_100,
            ':desconto_atraso' => $desconto_atraso,
            ':permitir_hora_negativa' => $permitir_hora_negativa,
            ':justificativa_obrigatoria' => $justificativa_obrigatoria,
            ':bloquear_ponto_fora_horario' => $bloquear_ponto_fora_horario,
            ':permitir_ponto_remoto' => $permitir_ponto_remoto,
            ':validar_gps' => $validar_gps,
            ':ponto_apenas_empresa' => $ponto_apenas_empresa,
            ':intervalo_minimo_batidas' => $intervalo_minimo_batidas,
            ':facial_obrigatorio' => $facial_obrigatorio,
            ':intervalo_minimo_almoco' => $intervalo_minimo_almoco,
            ':intervalo_maximo_almoco' => $intervalo_maximo_almoco,
            ':tempo_minimo_entrada' => $tempo_minimo_entrada,
            ':dias_para_justificar' => $dias_para_justificar,
            ':empresa_id' => $empresa_id
        ]);
        
        // Atualizar valores na variável $config
        $config['horas_extras_50'] = $horas_extras_50;
        $config['horas_extras_100'] = $horas_extras_100;
        $config['desconto_atraso'] = $desconto_atraso;
        $config['permitir_hora_negativa'] = $permitir_hora_negativa;
        $config['justificativa_obrigatoria'] = $justificativa_obrigatoria;
        $config['bloquear_ponto_fora_horario'] = $bloquear_ponto_fora_horario;
        $config['permitir_ponto_remoto'] = $permitir_ponto_remoto;
        $config['validar_gps'] = $validar_gps;
        $config['ponto_apenas_empresa'] = $ponto_apenas_empresa;
        $config['intervalo_minimo_batidas'] = $intervalo_minimo_batidas;
        $config['facial_obrigatorio'] = $facial_obrigatorio;
        $config['intervalo_minimo_almoco'] = $intervalo_minimo_almoco;
        $config['intervalo_maximo_almoco'] = $intervalo_maximo_almoco;
        $config['tempo_minimo_entrada'] = $tempo_minimo_entrada;
        $config['dias_para_justificar'] = $dias_para_justificar;
        
        $success = 'Regras de ponto atualizadas com sucesso!';
        
    } catch (Exception $e) {
        $error = 'Erro ao salvar: ' . $e->getMessage();
    }
}
?>

<style>
.config-container {
    max-width: 1000px;
    margin: 0 auto;
}

.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.module-title h2 {
    margin: 0;
    font-size: 24px;
}

.module-title p {
    margin: 8px 0 0 0;
    color: var(--text-secondary);
    font-size: 14px;
}

.config-card {
    background: var(--bg-primary);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    margin-bottom: 24px;
    overflow: hidden;
}

.config-header {
    padding: 16px 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.config-header i {
    margin-right: 8px;
}

.config-header h3 {
    margin: 0;
    font-size: 18px;
}

.config-body {
    padding: 24px;
}

.config-group {
    margin-bottom: 24px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border-color);
}

.config-group:last-child {
    border-bottom: none;
    padding-bottom: 0;
    margin-bottom: 0;
}

.config-group h4 {
    margin-bottom: 16px;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.config-group h4 i {
    color: #667eea;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 8px;
    font-weight: 500;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group input,
.form-group select {
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-group small {
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 4px;
}

.switch-group {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
}

.switch-group:last-child {
    border-bottom: none;
}

.switch-label {
    font-weight: 500;
}

.switch-label small {
    font-size: 11px;
    color: var(--text-secondary);
    display: block;
    font-weight: normal;
    margin-top: 4px;
}

.switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #667eea;
}

input:checked + .slider:before {
    transform: translateX(24px);
}

.btn {
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.alert {
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.alert-info {
    background: #bfdbfe;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}

.form-actions {
    display: flex;
    gap: 16px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}

.info-box {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.info-box p {
    margin: 0;
    font-size: 13px;
    color: #92400e;
}

@media (max-width: 768px) {
    .switch-group {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="config-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-gavel"></i> Regras de Ponto</h2>
            <p>Configure as regras para cálculo de horas, atrasos e justificativas</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <!-- Regras de Horas Extras -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-chart-line"></i>
                <h3>Horas Extras</h3>
            </div>
            <div class="config-body">
                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> Horas extras são calculadas automaticamente baseadas na carga horária diária/semanal configurada.</p>
                </div>
                
                <div class="switch-group">
                    <div class="switch-label">
                        Horas Extras 50%
                        <small>Horas trabalhadas além da carga horária (dias úteis)</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="horas_extras_50" value="1" <?php echo $config['horas_extras_50'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="switch-group">
                    <div class="switch-label">
                        Horas Extras 100%
                        <small>Horas trabalhadas em domingos e feriados</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="horas_extras_100" value="1" <?php echo $config['horas_extras_100'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Regras de Atrasos e Descontos -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Atrasos e Descontos</h3>
            </div>
            <div class="config-body">
                <div class="switch-group">
                    <div class="switch-label">
                        Descontar Atrasos
                        <small>Descontar minutos de atraso do saldo de horas</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="desconto_atraso" value="1" <?php echo $config['desconto_atraso'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="switch-group">
                    <div class="switch-label">
                        Permitir Hora Negativa
                        <small>Permitir saldo de horas negativo no banco de horas</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="permitir_hora_negativa" value="1" <?php echo $config['permitir_hora_negativa'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Regras de Justificativas -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-pencil-alt"></i>
                <h3>Justificativas</h3>
            </div>
            <div class="config-body">
                <div class="switch-group">
                    <div class="switch-label">
                        Justificativa Obrigatória
                        <small>Exigir justificativa para atrasos e faltas</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="justificativa_obrigatoria" value="1" <?php echo $config['justificativa_obrigatoria'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-day"></i> Dias para Justificar</label>
                        <input type="number" name="dias_para_justificar" value="<?php echo $config['dias_para_justificar']; ?>" min="1" max="30">
                        <small>Prazo máximo em dias para justificar faltas/atrasos</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Regras de Ponto -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-fingerprint"></i>
                <h3>Regras de Registro</h3>
            </div>
            <div class="config-body">
                <div class="switch-group">
                    <div class="switch-label">
                        Bloquear Ponto Fora do Horário
                        <small>Impedir registro de ponto fora do horário permitido</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="bloquear_ponto_fora_horario" value="1" <?php echo $config['bloquear_ponto_fora_horario'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="switch-group">
                    <div class="switch-label">
                        Permitir Ponto Remoto
                        <small>Permitir registro de ponto fora da empresa</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="permitir_ponto_remoto" value="1" <?php echo $config['permitir_ponto_remoto'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="switch-group">
                    <div class="switch-label">
                        Validar GPS
                        <small>Exigir localização GPS no registro de ponto</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="validar_gps" value="1" <?php echo $config['validar_gps'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="switch-group">
                    <div class="switch-label">
                        Permitir ponto somente na empresa
                        <small>Bloquear o registro quando o colaborador estiver fora do raio da filial.</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="ponto_apenas_empresa" value="1" <?php echo $config['ponto_apenas_empresa'] ? 'checked' : ''; ?>><span class="slider"></span>
                    </label>
                </div>
                
                <div class="switch-group">
                    <div class="switch-label">
                        Reconhecimento Facial Obrigatório
                        <small>Exigir reconhecimento facial para registrar ponto</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="facial_obrigatorio" value="1" <?php echo $config['facial_obrigatorio'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Intervalos e Tempos -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-hourglass-half"></i>
                <h3>Intervalos e Tempos</h3>
            </div>
            <div class="config-body">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-utensils"></i> Intervalo Mínimo de Almoço</label>
                        <input type="number" name="intervalo_minimo_almoco" value="<?php echo $config['intervalo_minimo_almoco']; ?>" min="30" max="180" step="30">
                        <small>Tempo mínimo obrigatório para o intervalo de almoço (minutos)</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> Intervalo Máximo de Almoço</label>
                        <input type="number" name="intervalo_maximo_almoco" value="<?php echo $config['intervalo_maximo_almoco']; ?>" min="60" max="240" step="30">
                        <small>Tempo máximo permitido para o intervalo (minutos)</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-sign-in-alt"></i> Tempo Mínimo entre Registros</label>
                        <input type="number" name="tempo_minimo_entrada" value="<?php echo $config['tempo_minimo_entrada']; ?>" min="15" max="120" step="5">
                        <small>Tempo mínimo entre registros consecutivos (minutos)</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-stopwatch"></i> Intervalo mínimo entre batidas</label>
                        <input type="number" name="intervalo_minimo_batidas" value="<?php echo $config['intervalo_minimo_batidas']; ?>" min="120" max="1440" step="30">
                        <small>Bloqueia novas batidas antes desse intervalo (mínimo: 120 minutos).</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumo das Regras -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-list-check"></i>
                <h3>Resumo das Regras</h3>
            </div>
            <div class="config-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 12px;">
                    <div>
                        <strong><i class="fas fa-chart-line"></i> Horas Extras:</strong><br>
                        <?php if ($config['horas_extras_50']): ?>✅ 50% ativa<br><?php endif; ?>
                        <?php if ($config['horas_extras_100']): ?>✅ 100% ativa<br><?php endif; ?>
                        <?php if (!$config['horas_extras_50'] && !$config['horas_extras_100']): ?>❌ Desativadas<br><?php endif; ?>
                    </div>
                    <div>
                        <strong><i class="fas fa-exclamation-triangle"></i> Atrasos:</strong><br>
                        <?php if ($config['desconto_atraso']): ?>✅ Desconto ativo<br><?php else: ?>❌ Desconto inativo<br><?php endif; ?>
                        <?php if ($config['justificativa_obrigatoria']): ?>✅ Justificativa obrigatória<br><?php endif; ?>
                    </div>
                    <div>
                        <strong><i class="fas fa-fingerprint"></i> Registro:</strong><br>
                        <?php if ($config['facial_obrigatorio']): ?>✅ Facial obrigatório<br><?php endif; ?>
                        <?php if ($config['validar_gps']): ?>✅ GPS obrigatório<br><?php endif; ?>
                        <?php if ($config['permitir_ponto_remoto']): ?>✅ Ponto remoto permitido<br><?php endif; ?>
                    </div>
                    <div>
                        <strong><i class="fas fa-hourglass-half"></i> Intervalos:</strong><br>
                        Almoço: <?php echo $config['intervalo_minimo_almoco']; ?>-<?php echo $config['intervalo_maximo_almoco']; ?> min<br>
                        Prazo justificativa: <?php echo $config['dias_para_justificar']; ?> dias
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Salvar Regras
            </button>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
