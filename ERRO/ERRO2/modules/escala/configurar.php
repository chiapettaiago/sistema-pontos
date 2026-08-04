<?php
// modules/escala/configurar.php - Configurar tipos de escala
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: 1678/login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa'])) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Configurar Tipos de Escala';
$activePage = 'escala';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$success = '';
$error = '';

// Processar adição de tipo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['adicionar'])) {
        $nome = trim($_POST['nome']);
        $descricao = trim($_POST['descricao']);
        $tipo = $_POST['tipo'];
        $dias_trabalhados = (int)$_POST['dias_trabalhados'];
        $dias_descanso = (int)$_POST['dias_descanso'];
        $horas_por_dia = (float)$_POST['horas_por_dia'];
        $trabalha_domingo = isset($_POST['trabalha_domingo']) ? 1 : 0;
        $trabalha_feriado = isset($_POST['trabalha_feriado']) ? 1 : 0;
        $noturno = isset($_POST['noturno']) ? 1 : 0;
        $adicional_noturno = (float)($_POST['adicional_noturno'] ?? 20);
        
        try {
            $stmt = $db->prepare("INSERT INTO escala_tipos 
                (empresa_id, nome, descricao, tipo, dias_trabalhados, dias_descanso, 
                 horas_por_dia, trabalha_domingo, trabalha_feriado, noturno, adicional_noturno) 
                VALUES 
                (:empresa_id, :nome, :descricao, :tipo, :dias_trabalhados, :dias_descanso,
                 :horas_por_dia, :trabalha_domingo, :trabalha_feriado, :noturno, :adicional_noturno)");
            
            $stmt->execute([
                ':empresa_id' => $empresa_id,
                ':nome' => $nome,
                ':descricao' => $descricao,
                ':tipo' => $tipo,
                ':dias_trabalhados' => $dias_trabalhados,
                ':dias_descanso' => $dias_descanso,
                ':horas_por_dia' => $horas_por_dia,
                ':trabalha_domingo' => $trabalha_domingo,
                ':trabalha_feriado' => $trabalha_feriado,
                ':noturno' => $noturno,
                ':adicional_noturno' => $adicional_noturno
            ]);
            
            $success = 'Tipo de escala adicionado com sucesso!';
            
        } catch (Exception $e) {
            $error = 'Erro ao adicionar: ' . $e->getMessage();
        }
    }
    
    // Editar tipo
    if (isset($_POST['editar'])) {
        $id = (int)$_POST['id'];
        $nome = trim($_POST['nome']);
        $descricao = trim($_POST['descricao']);
        $tipo = $_POST['tipo'];
        $dias_trabalhados = (int)$_POST['dias_trabalhados'];
        $dias_descanso = (int)$_POST['dias_descanso'];
        $horas_por_dia = (float)$_POST['horas_por_dia'];
        $trabalha_domingo = isset($_POST['trabalha_domingo']) ? 1 : 0;
        $trabalha_feriado = isset($_POST['trabalha_feriado']) ? 1 : 0;
        $noturno = isset($_POST['noturno']) ? 1 : 0;
        $adicional_noturno = (float)($_POST['adicional_noturno'] ?? 20);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE escala_tipos SET 
                nome = :nome, descricao = :descricao, tipo = :tipo,
                dias_trabalhados = :dias_trabalhados, dias_descanso = :dias_descanso,
                horas_por_dia = :horas_por_dia, trabalha_domingo = :trabalha_domingo,
                trabalha_feriado = :trabalha_feriado, noturno = :noturno,
                adicional_noturno = :adicional_noturno, ativo = :ativo
                WHERE id = :id AND empresa_id = :empresa_id");
            
            $stmt->execute([
                ':nome' => $nome,
                ':descricao' => $descricao,
                ':tipo' => $tipo,
                ':dias_trabalhados' => $dias_trabalhados,
                ':dias_descanso' => $dias_descanso,
                ':horas_por_dia' => $horas_por_dia,
                ':trabalha_domingo' => $trabalha_domingo,
                ':trabalha_feriado' => $trabalha_feriado,
                ':noturno' => $noturno,
                ':adicional_noturno' => $adicional_noturno,
                ':ativo' => $ativo,
                ':id' => $id,
                ':empresa_id' => $empresa_id
            ]);
            
            $success = 'Tipo de escala atualizado com sucesso!';
            
        } catch (Exception $e) {
            $error = 'Erro ao atualizar: ' . $e->getMessage();
        }
    }
    
    // Excluir tipo
    if (isset($_GET['excluir'])) {
        $id = (int)$_GET['excluir'];
        
        try {
            $stmt = $db->prepare("DELETE FROM escala_tipos WHERE id = :id AND empresa_id = :empresa_id");
            $stmt->execute([':id' => $id, ':empresa_id' => $empresa_id]);
            $success = 'Tipo de escala excluído com sucesso!';
            
        } catch (Exception $e) {
            $error = 'Erro ao excluir: ' . $e->getMessage();
        }
    }
}

// Buscar tipos de escala
$stmt = $db->prepare("SELECT * FROM escala_tipos WHERE empresa_id = :empresa_id ORDER BY nome");
$stmt->execute([':empresa_id' => $empresa_id]);
$tipos = $stmt->fetchAll();

// Buscar tipo para edição
$edit_id = $_GET['edit'] ?? 0;
$edit_tipo = null;
if ($edit_id) {
    $stmt = $db->prepare("SELECT * FROM escala_tipos WHERE id = :id AND empresa_id = :empresa_id");
    $stmt->execute([':id' => $edit_id, ':empresa_id' => $empresa_id]);
    $edit_tipo = $stmt->fetch();
}
?>

<style>
.config-container {
    max-width: 1200px;
    margin: 0 auto;
}

.form-card {
    background: var(--bg-primary);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    margin-bottom: 24px;
}

.form-header {
    padding: 16px 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.form-header h3 {
    margin: 0;
    font-size: 18px;
}

.form-body {
    padding: 24px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
}

.form-group input,
.form-group select {
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--bg-primary);
    color: var(--text-primary);
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 8px;
}

.btn {
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
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

.btn-success {
    background: #10b981;
    color: white;
}

.btn-danger {
    background: #ef4444;
    color: white;
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

.tipos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.tipo-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
}

.tipo-header {
    padding: 16px;
    background: linear-gradient(135deg, #667eea20, #764ba220);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tipo-nome {
    font-weight: 700;
    font-size: 16px;
}

.tipo-body {
    padding: 16px;
}

.tipo-info {
    margin-bottom: 8px;
    font-size: 13px;
    display: flex;
    justify-content: space-between;
}

.tipo-actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--border-color);
}

.badge-ativo {
    background: #d1fae5;
    color: #059669;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 11px;
}

.badge-inativo {
    background: #fee2e2;
    color: #dc2626;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 11px;
}
</style>

<div class="config-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-cog"></i> Configurar Tipos de Escala</h2>
            <p>Defina os tipos de escala disponíveis na empresa</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Formulário para adicionar/editar tipo -->
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas <?php echo $edit_tipo ? 'fa-edit' : 'fa-plus'; ?>"></i> 
                <?php echo $edit_tipo ? 'Editar Tipo de Escala' : 'Novo Tipo de Escala'; ?>
            </h3>
        </div>
        <div class="form-body">
            <form method="POST" action="">
                <?php if ($edit_tipo): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_tipo['id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome da Escala *</label>
                        <input type="text" name="nome" required value="<?php echo $edit_tipo ? htmlspecialchars($edit_tipo['nome']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="5x2" <?php echo $edit_tipo && $edit_tipo['tipo'] == '5x2' ? 'selected' : ''; ?>>5x2 (5 dias trabalho, 2 folga)</option>
                            <option value="6x1" <?php echo $edit_tipo && $edit_tipo['tipo'] == '6x1' ? 'selected' : ''; ?>>6x1 (6 dias trabalho, 1 folga)</option>
                            <option value="12x36" <?php echo $edit_tipo && $edit_tipo['tipo'] == '12x36' ? 'selected' : ''; ?>>12x36 (12 horas trabalho, 36 descanso)</option>
                            <option value="plantao" <?php echo $edit_tipo && $edit_tipo['tipo'] == 'plantao' ? 'selected' : ''; ?>>Plantão</option>
                            <option value="personalizado" <?php echo $edit_tipo && $edit_tipo['tipo'] == 'personalizado' ? 'selected' : ''; ?>>Personalizado</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="descricao" rows="2" style="width: 100%;"><?php echo $edit_tipo ? htmlspecialchars($edit_tipo['descricao']) : ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Dias Trabalhados (por semana)</label>
                        <input type="number" name="dias_trabalhados" value="<?php echo $edit_tipo ? $edit_tipo['dias_trabalhados'] : 5; ?>" min="0" max="7">
                    </div>
                    <div class="form-group">
                        <label>Dias de Descanso</label>
                        <input type="number" name="dias_descanso" value="<?php echo $edit_tipo ? $edit_tipo['dias_descanso'] : 2; ?>" min="0" max="7">
                    </div>
                    <div class="form-group">
                        <label>Horas por Dia</label>
                        <input type="number" name="horas_por_dia" value="<?php echo $edit_tipo ? $edit_tipo['horas_por_dia'] : 8; ?>" step="0.5" min="0" max="24">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="trabalha_domingo" value="1" id="trabalha_domingo" <?php echo $edit_tipo && $edit_tipo['trabalha_domingo'] ? 'checked' : ''; ?>>
                            <label for="trabalha_domingo">Trabalha aos Domingos</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="trabalha_feriado" value="1" id="trabalha_feriado" <?php echo $edit_tipo && $edit_tipo['trabalha_feriado'] ? 'checked' : ''; ?>>
                            <label for="trabalha_feriado">Trabalha em Feriados</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="noturno" value="1" id="noturno" <?php echo $edit_tipo && $edit_tipo['noturno'] ? 'checked' : ''; ?>>
                            <label for="noturno">Escala Noturna</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="ativo" value="1" id="ativo" <?php echo $edit_tipo ? ($edit_tipo['ativo'] ? 'checked' : '') : 'checked'; ?>>
                            <label for="ativo">Ativo</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Adicional Noturno (%)</label>
                        <input type="number" name="adicional_noturno" value="<?php echo $edit_tipo ? $edit_tipo['adicional_noturno'] : 20; ?>" step="5" min="0" max="100">
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="submit" name="<?php echo $edit_tipo ? 'editar' : 'adicionar'; ?>" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $edit_tipo ? 'Salvar Alterações' : 'Adicionar Tipo'; ?>
                    </button>
                    <?php if ($edit_tipo): ?>
                        <a href="configurar.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Tipos -->
    <div class="tipos-grid">
        <?php foreach ($tipos as $tipo): ?>
            <div class="tipo-card">
                <div class="tipo-header">
                    <span class="tipo-nome"><?php echo htmlspecialchars($tipo['nome']); ?></span>
                    <span class="<?php echo $tipo['ativo'] ? 'badge-ativo' : 'badge-inativo'; ?>">
                        <?php echo $tipo['ativo'] ? 'Ativo' : 'Inativo'; ?>
                    </span>
                </div>
                <div class="tipo-body">
                    <div class="tipo-info">
                        <span>Tipo:</span>
                        <strong><?php echo strtoupper($tipo['tipo']); ?></strong>
                    </div>
                    <div class="tipo-info">
                        <span>Jornada:</span>
                        <strong><?php echo $tipo['dias_trabalhados']; ?>x<?php echo $tipo['dias_descanso']; ?> - <?php echo $tipo['horas_por_dia']; ?>h/dia</strong>
                    </div>
                    <?php if ($tipo['noturno']): ?>
                        <div class="tipo-info">
                            <span>Adicional Noturno:</span>
                            <strong><?php echo $tipo['adicional_noturno']; ?>%</strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($tipo['descricao']): ?>
                        <div class="tipo-info">
                            <span>Descrição:</span>
                            <span><?php echo htmlspecialchars(substr($tipo['descricao'], 0, 50)); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="tipo-actions">
                        <a href="?edit=<?php echo $tipo['id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                        <a href="?excluir=<?php echo $tipo['id']; ?>" class="btn btn-danger" onclick="return confirm('Tem certeza?')">
                            <i class="fas fa-trash-alt"></i> Excluir
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>