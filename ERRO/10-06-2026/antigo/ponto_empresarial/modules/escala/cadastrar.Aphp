<?php
// modules/escala/cadastrar.php - Cadastrar escala para funcionário
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa', 'gestor'])) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Cadastrar Escala';
$activePage = 'escala';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$success = '';
$error = '';

// Buscar funcionários
$stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios 
                      WHERE empresa_id = :empresa_id AND status = 'ativo' 
                      ORDER BY nome");
$stmt->execute([':empresa_id' => $empresa_id]);
$funcionarios = $stmt->fetchAll();

// Buscar tipos de escala
$stmt = $db->prepare("SELECT * FROM escala_tipos 
                      WHERE empresa_id = :empresa_id AND ativo = 1 
                      ORDER BY nome");
$stmt->execute([':empresa_id' => $empresa_id]);
$tipos_escala = $stmt->fetchAll();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $funcionario_id = $_POST['funcionario_id'] ?? 0;
    $escala_tipo_id = $_POST['escala_tipo_id'] ?? 0;
    $data_inicio = $_POST['data_inicio'] ?? date('Y-m-d');
    $data_fim = $_POST['data_fim'] ?? null;
    $dias_trabalho = $_POST['dias_trabalho'] ?? '';
    $dias_folga = $_POST['dias_folga'] ?? '';
    
    $errors = [];
    
    if (!$funcionario_id) $errors[] = 'Selecione um funcionário';
    if (!$escala_tipo_id) $errors[] = 'Selecione um tipo de escala';
    if (!$data_inicio) $errors[] = 'Informe a data de início';
    
    // Verificar se funcionário já tem escala ativa
    $stmt = $db->prepare("SELECT id FROM funcionario_escala 
                          WHERE funcionario_id = :funcionario_id AND ativo = 1");
    $stmt->execute([':funcionario_id' => $funcionario_id]);
    if ($stmt->fetch()) {
        $errors[] = 'Este funcionário já possui uma escala ativa. Edite a escala existente ou desative-a primeiro.';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("INSERT INTO funcionario_escala 
                (funcionario_id, escala_tipo_id, data_inicio, data_fim, dias_trabalho, dias_folga, ativo) 
                VALUES 
                (:funcionario_id, :escala_tipo_id, :data_inicio, :data_fim, :dias_trabalho, :dias_folga, 1)");
            
            $stmt->execute([
                ':funcionario_id' => $funcionario_id,
                ':escala_tipo_id' => $escala_tipo_id,
                ':data_inicio' => $data_inicio,
                ':data_fim' => $data_fim,
                ':dias_trabalho' => $dias_trabalho,
                ':dias_folga' => $dias_folga
            ]);
            
            $success = 'Escala cadastrada com sucesso!';
            
        } catch (Exception $e) {
            $error = 'Erro ao cadastrar: ' . $e->getMessage();
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
    border-radius: 20px;
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.form-header {
    padding: 20px 24px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.form-header h3 {
    margin: 0;
}

.form-body {
    padding: 24px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 14px;
}

.form-group select,
.form-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.info-box {
    background: #e0e7ff;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
    font-size: 13px;
    color: #1e40af;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    width: 100%;
    justify-content: center;
}

.btn-primary:hover {
    transform: translateY(-2px);
}

.alert {
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    color: #059669;
}

.alert-error {
    background: #fee2e2;
    color: #dc2626;
}
</style>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-calendar-plus"></i> Cadastrar Escala de Trabalho</h3>
        </div>
        <div class="form-body">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <a href="index.php" class="btn btn-primary">Voltar para lista</a>
            <?php elseif ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
                <a href="javascript:history.back()" class="btn btn-primary">Voltar</a>
            <?php else: ?>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i> 
                    Cada funcionário pode ter apenas uma escala ativa por vez. Para alterar, edite a escala existente.
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Funcionário *</label>
                        <select name="funcionario_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($funcionarios as $func): ?>
                                <option value="<?php echo $func['id']; ?>">
                                    <?php echo htmlspecialchars($func['nome'] . ' (' . $func['matricula'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Tipo de Escala *</label>
                        <select name="escala_tipo_id" id="escala_tipo_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($tipos_escala as $tipo): ?>
                                <option value="<?php echo $tipo['id']; ?>">
                                    <?php echo htmlspecialchars($tipo['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Data de Início *</label>
                            <input type="date" name="data_inicio" required>
                        </div>
                        <div class="form-group">
                            <label>Data de Fim (opcional)</label>
                            <input type="date" name="data_fim">
                        </div>
                    </div>
                    
                    <div id="dias_personalizados" style="display: none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Dias de Trabalho (1=Segunda, 2=Terça...)</label>
                                <input type="text" name="dias_trabalho" placeholder="Ex: 1,2,3,4,5">
                            </div>
                            <div class="form-group">
                                <label>Dias de Folga</label>
                                <input type="text" name="dias_folga" placeholder="Ex: 6,7">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Cadastrar Escala
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('escala_tipo_id')?.addEventListener('change', function() {
    const tipo = this.options[this.selectedIndex]?.text || '';
    const divPersonalizado = document.getElementById('dias_personalizados');
    if (tipo.includes('Personalizado')) {
        divPersonalizado.style.display = 'block';
    } else {
        divPersonalizado.style.display = 'none';
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>