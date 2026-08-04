<?php
// modules/escala/editar.php - Editar escala do funcionário
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

$pageTitle = 'Editar Escala';
$activePage = 'escala';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$id = $_GET['id'] ?? 0;
$success = '';
$error = '';

// Buscar escala
$stmt = $db->prepare("SELECT fe.*, f.nome as funcionario_nome, et.nome as escala_nome
                      FROM funcionario_escala fe
                      JOIN funcionarios f ON fe.funcionario_id = f.id
                      JOIN escala_tipos et ON fe.escala_tipo_id = et.id
                      WHERE fe.id = :id");
$stmt->execute([':id' => $id]);
$escala = $stmt->fetch();

if (!$escala) {
    header('Location: index.php');
    exit;
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['salvar'])) {
        $funcionario_id = $_POST['funcionario_id'] ?? $escala['funcionario_id'];
        $escala_tipo_id = $_POST['escala_tipo_id'] ?? $escala['escala_tipo_id'];
        $data_inicio = $_POST['data_inicio'] ?? $escala['data_inicio'];
        $data_fim = $_POST['data_fim'] ?? $escala['data_fim'];
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE funcionario_escala SET 
                funcionario_id = :funcionario_id,
                escala_tipo_id = :escala_tipo_id,
                data_inicio = :data_inicio,
                data_fim = :data_fim,
                ativo = :ativo
                WHERE id = :id");
            
            $stmt->execute([
                ':funcionario_id' => $funcionario_id,
                ':escala_tipo_id' => $escala_tipo_id,
                ':data_inicio' => $data_inicio,
                ':data_fim' => $data_fim ?: null,
                ':ativo' => $ativo,
                ':id' => $id
            ]);
            
            $success = 'Escala atualizada com sucesso!';
            
        } catch (Exception $e) {
            $error = 'Erro ao atualizar: ' . $e->getMessage();
        }
    }
    
    if (isset($_POST['excluir'])) {
        $stmt = $db->prepare("DELETE FROM funcionario_escala WHERE id = :id");
        $stmt->execute([':id' => $id]);
        header('Location: index.php');
        exit;
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

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 12px;
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
}

.btn-danger {
    background: #ef4444;
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
}

.alert-error {
    background: #fee2e2;
    color: #dc2626;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    flex-wrap: wrap;
}
</style>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-edit"></i> Editar Escala: <?php echo htmlspecialchars($escala['funcionario_nome']); ?></h3>
        </div>
        <div class="form-body">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <a href="index.php" class="btn btn-primary">Voltar para lista</a>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Funcionário</label>
                        <select name="funcionario_id" required>
                            <?php foreach ($funcionarios as $func): ?>
                                <option value="<?php echo $func['id']; ?>" <?php echo $escala['funcionario_id'] == $func['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($func['nome'] . ' (' . $func['matricula'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Tipo de Escala</label>
                        <select name="escala_tipo_id" required>
                            <?php foreach ($tipos_escala as $tipo): ?>
                                <option value="<?php echo $tipo['id']; ?>" <?php echo $escala['escala_tipo_id'] == $tipo['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Data de Início</label>
                            <input type="date" name="data_inicio" value="<?php echo $escala['data_inicio']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Data de Fim</label>
                            <input type="date" name="data_fim" value="<?php echo $escala['data_fim']; ?>">
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="ativo" value="1" id="ativo" <?php echo $escala['ativo'] ? 'checked' : ''; ?>>
                        <label for="ativo">Escala Ativa</label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="salvar" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Alterações
                        </button>
                        <button type="submit" name="excluir" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir esta escala?')">
                            <i class="fas fa-trash-alt"></i> Excluir
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

<?php require_once '../../includes/footer.php'; ?>