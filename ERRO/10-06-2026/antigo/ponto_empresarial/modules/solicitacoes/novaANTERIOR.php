<?php
// modules/solicitacoes/nova.php - Criar Nova Solicitação (VERSÃO COMPLETA)
$pageTitle = 'Nova Solicitação';
$activePage = 'solicitacoes';

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

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
    $_SESSION['mensagem'] = 'Perfil não encontrado';
    header('Location: ../../index.php');
    exit;
}

$error = '';
$success = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $data_inicio = $_POST['data_inicio'] ?? '';
    $data_fim = $_POST['data_fim'] ?? '';
    
    $errors = [];
    
    if (empty($tipo)) $errors[] = 'Selecione o tipo de solicitação';
    if (empty($titulo)) $errors[] = 'Informe um título';
    if (empty($descricao)) $errors[] = 'Descreva o motivo';
    if (empty($data_inicio)) $errors[] = 'Informe a data de início';
    
    if ($data_inicio && $data_fim && $data_fim < $data_inicio) {
        $errors[] = 'Data final não pode ser anterior à data inicial';
    }
    
    if (empty($errors)) {
        try {
            $query = "INSERT INTO solicitacoes 
                      (funcionario_id, empresa_id, filial_id, tipo, titulo, descricao, data_inicio, data_fim, status, created_at) 
                      VALUES (:funcionario_id, :empresa_id, :filial_id, :tipo, :titulo, :descricao, :data_inicio, :data_fim, 'pendente', NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':funcionario_id' => $funcionario_id,
                ':empresa_id' => $_SESSION['empresa_id'] ?? 1,
                ':filial_id' => $_SESSION['filial_id'] ?? null,
                ':tipo' => $tipo,
                ':titulo' => $titulo,
                ':descricao' => $descricao,
                ':data_inicio' => $data_inicio,
                ':data_fim' => $data_fim ?: null
            ]);
            
            $success = true;
            
        } catch (Exception $e) {
            $error = 'Erro: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

require_once '../../includes/header.php';
?>

<style>
.form-container {
    max-width: 800px;
    margin: 0 auto;
}

.form-card {
    background: var(--bg-primary);
    border-radius: 24px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.form-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-secondary);
}

.form-header h3 {
    margin: 0;
    font-size: 20px;
}

.form-main {
    padding: 24px;
}

.tipo-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-top: 16px;
}

.tipo-card {
    background: var(--bg-secondary);
    border: 2px solid var(--border-color);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.tipo-card:hover {
    border-color: #667eea;
    transform: translateY(-3px);
}

.tipo-card.selected {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.15);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

.tipo-card i {
    font-size: 32px;
    color: #667eea;
    margin-bottom: 12px;
    display: block;
}

.tipo-card .tipo-nome {
    font-weight: 700;
    font-size: 16px;
    margin-bottom: 4px;
}

.tipo-card .tipo-desc {
    font-size: 11px;
    color: var(--text-secondary);
}

.tipo-radio {
    display: none;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 14px;
}

.form-group input,
.form-group textarea {
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: 14px;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #667eea;
}

.form-group small {
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 4px;
}

.form-actions {
    display: flex;
    gap: 16px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}

.btn {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.alert {
    padding: 12px 20px;
    border-radius: 12px;
    margin: 20px 24px 0 24px;
    font-size: 14px;
}

.alert-error {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.alert-success {
    background: #d1fae5;
    color: #059669;
    border: 1px solid #a7f3d0;
    margin-bottom: 20px;
}

.alert-info {
    background: #e0e7ff;
    color: #4338ca;
    border: 1px solid #c7d2fe;
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.success-actions {
    display: flex;
    gap: 16px;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .tipo-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-plus-circle"></i> Nova Solicitação</h3>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Solicitação enviada com sucesso! Aguarde a aprovação do gestor.
            </div>
            <div style="padding: 0 24px 24px 24px;">
                <div class="success-actions">
                    <a href="index.php" class="btn btn-primary">Ver Minhas Solicitações</a>
                    <a href="nova.php" class="btn btn-secondary">Nova Solicitação</a>
                </div>
            </div>
        <?php else: ?>
        
        <form method="POST" action="" id="formSolicitacao" class="form-main">
            <div class="alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Informação importante:</strong><br>
                    Sua solicitação será analisada pelo seu gestor.
                </div>
            </div>
            
            <!-- Tipos de Solicitação -->
            <div class="form-group">
                <label>Tipo de Solicitação *</label>
                <div class="tipo-grid">
                    <div class="tipo-card" data-tipo="ferias">
                        <input type="radio" name="tipo" value="ferias" class="tipo-radio">
                        <i class="fas fa-umbrella-beach"></i>
                        <div class="tipo-nome">Férias</div>
                        <div class="tipo-desc">Período de descanso</div>
                    </div>
                    <div class="tipo-card" data-tipo="abono">
                        <input type="radio" name="tipo" value="abono" class="tipo-radio">
                        <i class="fas fa-gift"></i>
                        <div class="tipo-nome">Abono</div>
                        <div class="tipo-desc">Folga remunerada</div>
                    </div>
                    <div class="tipo-card" data-tipo="licenca">
                        <input type="radio" name="tipo" value="licenca" class="tipo-radio">
                        <i class="fas fa-notes-medical"></i>
                        <div class="tipo-nome">Licença Médica</div>
                        <div class="tipo-desc">Afastamento por saúde</div>
                    </div>
                    <div class="tipo-card" data-tipo="justificativa">
                        <input type="radio" name="tipo" value="justificativa" class="tipo-radio">
                        <i class="fas fa-comment"></i>
                        <div class="tipo-nome">Justificativa</div>
                        <div class="tipo-desc">Justificar falta/atraso</div>
                    </div>
                    <div class="tipo-card" data-tipo="atestado">
                        <input type="radio" name="tipo" value="atestado" class="tipo-radio">
                        <i class="fas fa-file-medical"></i>
                        <div class="tipo-nome">Atestado Médico</div>
                        <div class="tipo-desc">Anexar atestado</div>
                    </div>
                    <div class="tipo-card" data-tipo="outros">
                        <input type="radio" name="tipo" value="outros" class="tipo-radio">
                        <i class="fas fa-ellipsis-h"></i>
                        <div class="tipo-nome">Outros</div>
                        <div class="tipo-desc">Outros assuntos</div>
                    </div>
                </div>
            </div>
            
            <!-- Título -->
            <div class="form-group">
                <label>Título da Solicitação *</label>
                <input type="text" name="titulo" required placeholder="Ex: Solicitação de férias">
            </div>
            
            <!-- Datas -->
            <div class="form-row">
                <div class="form-group">
                    <label>Data de Início *</label>
                    <input type="date" name="data_inicio" id="data_inicio" required>
                </div>
                <div class="form-group">
                    <label>Data de Fim</label>
                    <input type="date" name="data_fim" id="data_fim">
                    <small>Deixe em branco para um único dia</small>
                </div>
            </div>
            
            <!-- Descrição -->
            <div class="form-group">
                <label>Descrição / Justificativa *</label>
                <textarea name="descricao" rows="5" required placeholder="Descreva detalhadamente o motivo da sua solicitação..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Enviar Solicitação
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
// Seleção dos cards de tipo
const tipoCards = document.querySelectorAll('.tipo-card');
const radios = document.querySelectorAll('input[name="tipo"]');

tipoCards.forEach(card => {
    card.addEventListener('click', function() {
        const tipo = this.dataset.tipo;
        const radio = this.querySelector('input[type="radio"]');
        
        // Remover selected de todos
        tipoCards.forEach(c => c.classList.remove('selected'));
        
        // Adicionar selected no clicado
        this.classList.add('selected');
        
        // Marcar o radio
        radio.checked = true;
        
        // Validar se é férias para exigir data fim
        const dataFim = document.getElementById('data_fim');
        if (tipo === 'ferias') {
            dataFim.required = true;
            dataFim.placeholder = 'Obrigatório para férias';
        } else {
            dataFim.required = false;
        }
    });
});

// Data mínima = hoje
const hoje = new Date().toISOString().split('T')[0];
document.getElementById('data_inicio').min = hoje;

// Validação de datas
const dataInicio = document.getElementById('data_inicio');
const dataFim = document.getElementById('data_fim');

dataInicio.addEventListener('change', function() {
    if (dataFim.value && dataFim.value < this.value) {
        dataFim.value = '';
    }
    dataFim.min = this.value;
});

dataFim.addEventListener('change', function() {
    if (dataInicio.value && this.value < dataInicio.value) {
        alert('Data final não pode ser anterior à data inicial');
        this.value = '';
    }
});

// Se houver erro de validação, manter os valores
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$success && isset($_POST['tipo'])): ?>
document.querySelector('input[name="tipo"][value="<?php echo $_POST['tipo']; ?>"]').checked = true;
document.querySelector('.tipo-card[data-tipo="<?php echo $_POST['tipo']; ?>"]').classList.add('selected');
document.querySelector('input[name="titulo"]').value = "<?php echo addslashes($_POST['titulo']); ?>";
document.querySelector('input[name="data_inicio"]').value = "<?php echo $_POST['data_inicio']; ?>";
document.querySelector('input[name="data_fim"]').value = "<?php echo $_POST['data_fim']; ?>";
document.querySelector('textarea[name="descricao"]').value = "<?php echo addslashes($_POST['descricao']); ?>";
<?php endif; ?>
</script>

<?php require_once '../../includes/footer.php'; ?>