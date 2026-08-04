<?php
// modules/configuracoes/horarios.php - Configuração de Horários
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

$pageTitle = 'Configuração de Horários';
$activePage = 'configuracoes';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$success = '';
$error = '';

// Buscar configurações atuais
$stmt = $db->prepare("SELECT * FROM config_horarios WHERE empresa_id = :empresa_id");
$stmt->execute([':empresa_id' => $empresa_id]);
$config = $stmt->fetch();

if (!$config) {
    // Criar configuração padrão
    $stmt = $db->prepare("INSERT INTO config_horarios (empresa_id) VALUES (:empresa_id)");
    $stmt->execute([':empresa_id' => $empresa_id]);
    
    $stmt = $db->prepare("SELECT * FROM config_horarios WHERE empresa_id = :empresa_id");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $config = $stmt->fetch();
}

// Buscar exceções de horário
$stmt = $db->prepare("SELECT * FROM config_excecoes_horario WHERE empresa_id = :empresa_id AND data >= CURDATE() ORDER BY data ASC");
$stmt->execute([':empresa_id' => $empresa_id]);
$excecoes = $stmt->fetchAll();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['salvar_config'])) {
        $horario_entrada = $_POST['horario_entrada'] ?? '08:00:00';
        $horario_saida_almoco = $_POST['horario_saida_almoco'] ?? '12:00:00';
        $horario_volta_almoco = $_POST['horario_volta_almoco'] ?? '13:00:00';
        $horario_saida = $_POST['horario_saida'] ?? '17:00:00';
        $tolerancia_entrada = (int)($_POST['tolerancia_entrada'] ?? 5);
        $tolerancia_saida = (int)($_POST['tolerancia_saida'] ?? 5);
        $carga_horaria_diaria = (int)($_POST['carga_horaria_diaria'] ?? 8);
        $carga_horaria_semanal = (int)($_POST['carga_horaria_semanal'] ?? 44);
        
        try {
            $stmt = $db->prepare("UPDATE config_horarios SET 
                horario_entrada = :horario_entrada,
                horario_saida_almoco = :horario_saida_almoco,
                horario_volta_almoco = :horario_volta_almoco,
                horario_saida = :horario_saida,
                tolerancia_entrada = :tolerancia_entrada,
                tolerancia_saida = :tolerancia_saida,
                carga_horaria_diaria = :carga_horaria_diaria,
                carga_horaria_semanal = :carga_horaria_semanal
                WHERE empresa_id = :empresa_id");
            
            $stmt->execute([
                ':horario_entrada' => $horario_entrada,
                ':horario_saida_almoco' => $horario_saida_almoco,
                ':horario_volta_almoco' => $horario_volta_almoco,
                ':horario_saida' => $horario_saida,
                ':tolerancia_entrada' => $tolerancia_entrada,
                ':tolerancia_saida' => $tolerancia_saida,
                ':carga_horaria_diaria' => $carga_horaria_diaria,
                ':carga_horaria_semanal' => $carga_horaria_semanal,
                ':empresa_id' => $empresa_id
            ]);
            
            $success = 'Configurações de horário salvas com sucesso!';
            
            // Recarregar dados
            $stmt = $db->prepare("SELECT * FROM config_horarios WHERE empresa_id = :empresa_id");
            $stmt->execute([':empresa_id' => $empresa_id]);
            $config = $stmt->fetch();
            
        } catch (Exception $e) {
            $error = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
    
    // Adicionar nova exceção
    if (isset($_POST['adicionar_excecao'])) {
        $data = $_POST['data'] ?? '';
        $descricao = trim($_POST['descricao'] ?? '');
        $entrada = $_POST['entrada'] ?? null;
        $saida_almoco = $_POST['saida_almoco'] ?? null;
        $volta_almoco = $_POST['volta_almoco'] ?? null;
        $saida = $_POST['saida'] ?? null;
        
        if ($data && $descricao) {
            try {
                $stmt = $db->prepare("INSERT INTO config_excecoes_horario 
                    (empresa_id, data, descricao, entrada, saida_almoco, volta_almoco, saida) 
                    VALUES 
                    (:empresa_id, :data, :descricao, :entrada, :saida_almoco, :volta_almoco, :saida)");
                
                $stmt->execute([
                    ':empresa_id' => $empresa_id,
                    ':data' => $data,
                    ':descricao' => $descricao,
                    ':entrada' => $entrada ?: null,
                    ':saida_almoco' => $saida_almoco ?: null,
                    ':volta_almoco' => $volta_almoco ?: null,
                    ':saida' => $saida ?: null
                ]);
                
                $success = 'Exceção adicionada com sucesso!';
                
                // Recarregar exceções
                $stmt = $db->prepare("SELECT * FROM config_excecoes_horario WHERE empresa_id = :empresa_id AND data >= CURDATE() ORDER BY data ASC");
                $stmt->execute([':empresa_id' => $empresa_id]);
                $excecoes = $stmt->fetchAll();
                
            } catch (Exception $e) {
                $error = 'Erro ao adicionar exceção: ' . $e->getMessage();
            }
        } else {
            $error = 'Preencha a data e a descrição da exceção';
        }
    }
    
    // Remover exceção
    if (isset($_GET['remover'])) {
        $id = (int)$_GET['remover'];
        
        try {
            $stmt = $db->prepare("DELETE FROM config_excecoes_horario WHERE id = :id AND empresa_id = :empresa_id");
            $stmt->execute([':id' => $id, ':empresa_id' => $empresa_id]);
            
            $success = 'Exceção removida com sucesso!';
            
            // Recarregar exceções
            $stmt = $db->prepare("SELECT * FROM config_excecoes_horario WHERE empresa_id = :empresa_id AND data >= CURDATE() ORDER BY data ASC");
            $stmt->execute([':empresa_id' => $empresa_id]);
            $excecoes = $stmt->fetchAll();
            
        } catch (Exception $e) {
            $error = 'Erro ao remover exceção: ' . $e->getMessage();
        }
    }
}

// Calcular carga horária total
$carga_total = $config['carga_horaria_semanal'] . 'h semanais';
$carga_diaria = $config['carga_horaria_diaria'] . 'h diárias';

// Horários formatados
$entrada = substr($config['horario_entrada'], 0, 5);
$saida_almoco = substr($config['horario_saida_almoco'], 0, 5);
$volta_almoco = substr($config['horario_volta_almoco'], 0, 5);
$saida = substr($config['horario_saida'], 0, 5);
?>

<style>
.config-container {
    max-width: 1200px;
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

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
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

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
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

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table th {
    background: var(--bg-secondary);
    font-weight: 600;
    font-size: 13px;
}

.data-table tr:hover {
    background: var(--bg-secondary);
}

.status-ativo {
    color: #10b981;
}

.status-inativo {
    color: #ef4444;
}

.excecao-card {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.excecao-info {
    flex: 1;
}

.excecao-data {
    font-weight: 600;
    font-size: 14px;
}

.excecao-descricao {
    font-size: 12px;
    color: var(--text-secondary);
}

.excecao-horarios {
    font-size: 11px;
    color: #667eea;
}

.form-actions {
    display: flex;
    gap: 16px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}

.horario-demo {
    background: var(--bg-secondary);
    border-radius: 16px;
    padding: 16px;
    margin-top: 20px;
    text-align: center;
}

.horario-demo span {
    display: inline-block;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 8px 16px;
    border-radius: 30px;
    margin: 4px;
    font-size: 13px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .excecao-card {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="config-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-clock"></i> Configuração de Horários</h2>
            <p>Defina os horários padrão de funcionamento da empresa</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Configuração Principal -->
    <div class="config-card">
        <div class="config-header">
            <i class="fas fa-calendar-alt"></i>
            <h3>Horários Padrão</h3>
        </div>
        <div class="config-body">
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-sign-in-alt"></i> Horário de Entrada</label>
                        <input type="time" name="horario_entrada" value="<?php echo $entrada; ?>" step="60">
                        <small>Horário padrão para entrada dos funcionários</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-utensils"></i> Saída para Almoço</label>
                        <input type="time" name="horario_saida_almoco" value="<?php echo $saida_almoco; ?>" step="60">
                        <small>Horário de saída para o intervalo de almoço</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-undo-alt"></i> Volta do Almoço</label>
                        <input type="time" name="horario_volta_almoco" value="<?php echo $volta_almoco; ?>" step="60">
                        <small>Horário de retorno do intervalo</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-sign-out-alt"></i> Horário de Saída</label>
                        <input type="time" name="horario_saida" value="<?php echo $saida; ?>" step="60">
                        <small>Horário padrão de saída</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-hourglass-start"></i> Tolerância de Entrada</label>
                        <input type="number" name="tolerancia_entrada" value="<?php echo $config['tolerancia_entrada']; ?>" step="1" min="0" max="60">
                        <small>Minutos de tolerância após o horário de entrada (não conta como atraso)</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-hourglass-end"></i> Tolerância de Saída</label>
                        <input type="number" name="tolerancia_saida" value="<?php echo $config['tolerancia_saida']; ?>" step="1" min="0" max="60">
                        <small>Minutos de tolerância antes do horário de saída</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-chart-line"></i> Carga Horária Diária</label>
                        <input type="number" name="carga_horaria_diaria" value="<?php echo $config['carga_horaria_diaria']; ?>" step="0.5" min="1" max="12">
                        <small>Horas trabalhadas por dia (padrão: 8h)</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar-week"></i> Carga Horária Semanal</label>
                        <input type="number" name="carga_horaria_semanal" value="<?php echo $config['carga_horaria_semanal']; ?>" step="0.5" min="20" max="60">
                        <small>Horas trabalhadas por semana (padrão: 44h)</small>
                    </div>
                </div>

                <div class="horario-demo">
                    <strong>📅 Resumo do Horário Padrão:</strong><br>
                    <span>🕐 Entrada: <?php echo $entrada; ?></span>
                    <span>🍽️ Saída Almoço: <?php echo $saida_almoco; ?></span>
                    <span>🔄 Volta Almoço: <?php echo $volta_almoco; ?></span>
                    <span>🏁 Saída: <?php echo $saida; ?></span>
                    <span>⏱️ Tolerância: <?php echo $config['tolerancia_entrada']; ?>min</span>
                    <span>📊 Carga: <?php echo $config['carga_horaria_diaria']; ?>h/dia | <?php echo $config['carga_horaria_semanal']; ?>h/semana</span>
                </div>

                <div class="form-actions">
                    <button type="submit" name="salvar_config" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Configurações
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Exceções de Horário -->
    <div class="config-card">
        <div class="config-header">
            <i class="fas fa-calendar-day"></i>
            <h3>Exceções de Horário</h3>
        </div>
        <div class="config-body">
            <p style="margin-bottom: 16px; color: var(--text-secondary);">
                Configure horários especiais para dias específicos (ex: treinamentos, eventos, feriados com ponto facultativo)
            </p>

            <!-- Formulário para adicionar exceção -->
            <form method="POST" action="" style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color);">
                <div class="form-row">
                    <div class="form-group">
                        <label>Data</label>
                        <input type="date" name="data" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Descrição</label>
                        <input type="text" name="descricao" required placeholder="Ex: Treinamento, Evento, etc">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Horário de Entrada (opcional)</label>
                        <input type="time" name="entrada" step="60">
                    </div>
                    <div class="form-group">
                        <label>Saída para Almoço (opcional)</label>
                        <input type="time" name="saida_almoco" step="60">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Volta do Almoço (opcional)</label>
                        <input type="time" name="volta_almoco" step="60">
                    </div>
                    <div class="form-group">
                        <label>Horário de Saída (opcional)</label>
                        <input type="time" name="saida" step="60">
                    </div>
                </div>
                <button type="submit" name="adicionar_excecao" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Adicionar Exceção
                </button>
            </form>

            <!-- Lista de Exceções -->
            <h4 style="margin-bottom: 16px;">📌 Próximas Exceções</h4>
            
            <?php if (empty($excecoes)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Nenhuma exceção agendada para os próximos dias.
                </div>
            <?php else: ?>
                <?php foreach ($excecoes as $exc): ?>
                    <div class="excecao-card">
                        <div class="excecao-info">
                            <div class="excecao-data">
                                <i class="fas fa-calendar-day"></i> <?php echo date('d/m/Y', strtotime($exc['data'])); ?>
                                <span style="font-size: 11px; color: var(--text-secondary);">
                                    (<?php 
                                    $dias = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                                    echo $dias[date('w', strtotime($exc['data']))];
                                    ?>)
                                </span>
                            </div>
                            <div class="excecao-descricao">
                                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($exc['descricao']); ?>
                            </div>
                            <div class="excecao-horarios">
                                <?php if ($exc['entrada']): ?>
                                    <i class="fas fa-sign-in-alt"></i> Entrada: <?php echo substr($exc['entrada'], 0, 5); ?>
                                <?php endif; ?>
                                <?php if ($exc['saida_almoco']): ?>
                                    | 🍽️ Saída Almoço: <?php echo substr($exc['saida_almoco'], 0, 5); ?>
                                <?php endif; ?>
                                <?php if ($exc['volta_almoco']): ?>
                                    | 🔄 Volta Almoço: <?php echo substr($exc['volta_almoco'], 0, 5); ?>
                                <?php endif; ?>
                                <?php if ($exc['saida']): ?>
                                    | 🏁 Saída: <?php echo substr($exc['saida'], 0, 5); ?>
                                <?php endif; ?>
                                <?php if (!$exc['entrada'] && !$exc['saida']): ?>
                                    <i class="fas fa-clock"></i> Horário padrão
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="excecao-actions">
                            <a href="?remover=<?php echo $exc['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza que deseja remover esta exceção?')">
                                <i class="fas fa-trash-alt"></i> Remover
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Informações Adicionais -->
    <div class="config-card">
        <div class="config-header">
            <i class="fas fa-info-circle"></i>
            <h3>Informações Importantes</h3>
        </div>
        <div class="config-body">
            <ul style="margin: 0; padding-left: 20px; color: var(--text-secondary);">
                <li><strong>Atraso:</strong> Considera-se atraso quando a entrada é registrada após o horário padrão + tolerância</li>
                <li><strong>Falta:</strong> Considera-se falta quando o funcionário não registra entrada no dia</li>
                <li><strong>Horas Extras:</strong> Minutos trabalhados além da carga horária diária/semanal</li>
                <li><strong>Exceções:</strong> Horários especiais têm prioridade sobre os horários padrão</li>
                <li><strong>Feriados:</strong> Configure feriados no menu "Feriados e Exceções"</li>
            </ul>
        </div>
    </div>
</div>

<script>
// Data mínima para exceções é hoje
document.querySelector('input[name="data"]').min = new Date().toISOString().split('T')[0];
</script>

<?php require_once '../../includes/footer.php'; ?>