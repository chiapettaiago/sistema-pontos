<?php
// modules/solicitacoes/nova.php - Nova Solicitação
session_start();

if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle = 'Nova Solicitação';
$activePage = 'solicitacoes';
require_once '../../includes/header.php';
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
$stmt = $db->prepare("SELECT nome, matricula FROM funcionarios WHERE id = :id");
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

$tipos = [
    'ferias' => ['nome' => 'Férias', 'icone' => 'fa-umbrella-beach', 'periodo' => true],
    'abono' => ['nome' => 'Abono', 'icone' => 'fa-coins', 'periodo' => true],
    'licenca' => ['nome' => 'Licença', 'icone' => 'fa-heartbeat', 'periodo' => true],
    'justificativa' => ['nome' => 'Justificativa de Falta', 'icone' => 'fa-pencil-alt', 'periodo' => false],
    'alteracao_ponto' => ['nome' => 'Alteração de Ponto', 'icone' => 'fa-clock', 'periodo' => true]
];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    $descricao = trim($_POST['descricao'] ?? '');
    $data_inicio = $_POST['data_inicio'] ?? null;
    $data_fim = $_POST['data_fim'] ?? null;
    
    $errors = [];
    
    if (empty($tipo) || !isset($tipos[$tipo])) {
        $errors[] = 'Tipo de solicitação inválido';
    }
    
    if (empty($descricao)) {
        $errors[] = 'Descrição é obrigatória';
    }
    
    if ($tipos[$tipo]['periodo']) {
        if (empty($data_inicio)) {
            $errors[] = 'Data de início é obrigatória';
        }
        if (empty($data_fim)) {
            $errors[] = 'Data de fim é obrigatória';
        }
        if ($data_inicio && $data_fim && $data_inicio > $data_fim) {
            $errors[] = 'Data de início deve ser anterior à data de fim';
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("INSERT INTO solicitacoes 
                (funcionario_id, empresa_id, filial_id, tipo, descricao, data_inicio, data_fim, status, data_solicitacao) 
                VALUES 
                (:funcionario_id, :empresa_id, :filial_id, :tipo, :descricao, :data_inicio, :data_fim, 'pendente', NOW())");
            
            $stmt->execute([
                ':funcionario_id' => $funcionario_id,
                ':empresa_id' => $_SESSION['empresa_id'] ?? 1,
                ':filial_id' => $_SESSION['filial_id'] ?? null,
                ':tipo' => $tipo,
                ':descricao' => $descricao,
                ':data_inicio' => $data_inicio,
                ':data_fim' => $data_fim
            ]);
            
            $success = 'Solicitação enviada com sucesso!';
            
        } catch (Exception $e) {
            $error = 'Erro ao enviar solicitação: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>

<style>
.form-container {
    max-width: 700px;
    margin: 0 auto;
}

.form-card {
    background: var(--bg-primary);
    border-radius: 24px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.form-header {
    padding: 24px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-secondary);
}

.form-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

.form-header p {
    margin: 8px 0 0 0;
    color: var(--text-secondary);
    font-size: 14px;
}

.form-body {
    padding: 24px;
}

.form-group {
    margin-bottom: 24px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    font-size: 14px;
    color: var(--text-primary);
}

.form-group label i {
    margin-right: 6px;
    color: #667eea;
}

.form-group select,
.form-group textarea,
.form-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    font-size: 14px;
    background: var(--bg-primary);
    color: var(--text-primary);
    transition: all 0.3s;
}

.form-group select:focus,
.form-group textarea:focus,
.form-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

small {
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 4px;
    display: block;
}

.info-box {
    background: #d1fae5;
    color: #059669;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.info-box i {
    font-size: 20px;
}

.form-actions {
    display: flex;
    gap: 16px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
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

.btn-secondary:hover {
    background: var(--bg-tertiary);
}

.alert {
    padding: 12px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
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
}

.periodo-fields {
    display: none;
}

.periodo-fields.visible {
    display: block;
}

@media (max-width: 480px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    
    .form-body {
        padding: 16px;
    }
}
</style>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-plus-circle"></i> Nova Solicitação</h3>
            <p>Solicite férias, abono, licença ou justifique faltas</p>
        </div>
        
        <div class="form-body">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="index.php" class="btn btn-primary">Ver minhas solicitações</a>
                </div>
            <?php else: ?>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Atenção:</strong> Sua solicitação será analisada pela gestão. 
                        Você receberá uma notificação quando houver uma resposta.
                    </div>
                </div>
                
                <form method="POST" action="" id="solicitacaoForm">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Tipo de Solicitação *</label>
                        <select name="tipo" id="tipo" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($tipos as $key => $tipo): ?>
                                <option value="<?php echo $key; ?>" data-periodo="<?php echo $tipo['periodo'] ? '1' : '0'; ?>">
                                    <i class="fas <?php echo $tipo['icone']; ?>"></i> <?php echo $tipo['nome']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="periodoFields" class="periodo-fields">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> Data de Início</label>
                                <input type="date" name="data_inicio" id="data_inicio">
                                <small>Data em que o período começa</small>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> Data de Fim</label>
                                <input type="date" name="data_fim" id="data_fim">
                                <small>Data em que o período termina</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-comment"></i> Descrição / Justificativa *</label>
                        <textarea name="descricao" rows="5" placeholder="Descreva detalhadamente o motivo da sua solicitação..." required></textarea>
                        <small>Seja claro e objetivo na sua justificativa</small>
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
</div>

<script>
document.getElementById('tipo')?.addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    const precisaPeriodo = option.getAttribute('data-periodo') === '1';
    const periodoFields = document.getElementById('periodoFields');
    const dataInicio = document.getElementById('data_inicio');
    const dataFim = document.getElementById('data_fim');
    
    if (precisaPeriodo) {
        periodoFields.classList.add('visible');
        dataInicio.required = true;
        dataFim.required = true;
    } else {
        periodoFields.classList.remove('visible');
        dataInicio.required = false;
        dataFim.required = false;
        dataInicio.value = '';
        dataFim.value = '';
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>