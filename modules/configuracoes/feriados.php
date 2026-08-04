<?php
// modules/configuracoes/feriados.php - Feriados e Exceções
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

$pageTitle = 'Feriados e Exceções';
$activePage = 'configuracoes';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$success = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adicionar feriado
    if (isset($_POST['adicionar'])) {
        $data = $_POST['data'] ?? '';
        $nome = trim($_POST['nome'] ?? '');
        $tipo = $_POST['tipo'] ?? 'nacional';
        $abonado = isset($_POST['abonado']) ? 1 : 0;
        $observacao = trim($_POST['observacao'] ?? '');
        
        if ($data && $nome) {
            try {
                $stmt = $db->prepare("INSERT INTO config_feriados 
                    (empresa_id, data, nome, tipo, abonado, observacao) 
                    VALUES 
                    (:empresa_id, :data, :nome, :tipo, :abonado, :observacao)");
                
                $stmt->execute([
                    ':empresa_id' => $empresa_id,
                    ':data' => $data,
                    ':nome' => $nome,
                    ':tipo' => $tipo,
                    ':abonado' => $abonado,
                    ':observacao' => $observacao
                ]);
                
                $success = 'Feriado adicionado com sucesso!';
                
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $error = 'Já existe um feriado cadastrado nesta data!';
                } else {
                    $error = 'Erro ao adicionar: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'Preencha a data e o nome do feriado';
        }
    }
    
    // Adicionar feriado padrão (nacionais)
    if (isset($_POST['adicionar_padroes'])) {
        $feriados_nacionais = [
            ['data' => date('Y') . '-01-01', 'nome' => 'Confraternização Universal', 'tipo' => 'nacional', 'abonado' => 1],
            ['data' => date('Y') . '-04-21', 'nome' => 'Tiradentes', 'tipo' => 'nacional', 'abonado' => 1],
            ['data' => date('Y') . '-05-01', 'nome' => 'Dia do Trabalhador', 'tipo' => 'nacional', 'abonado' => 1],
            ['data' => date('Y') . '-09-07', 'nome' => 'Independência do Brasil', 'tipo' => 'nacional', 'abonado' => 1],
            ['data' => date('Y') . '-10-12', 'nome' => 'Nossa Senhora Aparecida', 'tipo' => 'nacional', 'abonado' => 1],
            ['data' => date('Y') . '-11-02', 'nome' => 'Finados', 'tipo' => 'nacional', 'abonado' => 1],
            ['data' => date('Y') . '-11-15', 'nome' => 'Proclamação da República', 'tipo' => 'nacional', 'abonado' => 1],
            ['data' => date('Y') . '-12-25', 'nome' => 'Natal', 'tipo' => 'nacional', 'abonado' => 1],
        ];
        
        // Carnaval (data móvel)
        $ano = date('Y');
        $carnaval = date('Y-m-d', strtotime("{$ano}-02-". (42 - date('w', strtotime("{$ano}-02-01"))) . " -2 days"));
        $feriados_nacionais[] = ['data' => $carnaval, 'nome' => 'Carnaval', 'tipo' => 'pontofacultativo', 'abonado' => 1];
        
        $count = 0;
        foreach ($feriados_nacionais as $feriado) {
            try {
                $stmt = $db->prepare("INSERT IGNORE INTO config_feriados 
                    (empresa_id, data, nome, tipo, abonado) 
                    VALUES 
                    (:empresa_id, :data, :nome, :tipo, :abonado)");
                
                $stmt->execute([
                    ':empresa_id' => $empresa_id,
                    ':data' => $feriado['data'],
                    ':nome' => $feriado['nome'],
                    ':tipo' => $feriado['tipo'],
                    ':abonado' => $feriado['abonado']
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $count++;
                }
            } catch (Exception $e) {
                // Ignora duplicados
            }
        }
        
        $success = "{$count} feriados nacionais adicionados com sucesso!";
    }
    
    // Editar feriado
    if (isset($_POST['editar'])) {
        $id = (int)$_POST['id'];
        $data = $_POST['data'] ?? '';
        $nome = trim($_POST['nome'] ?? '');
        $tipo = $_POST['tipo'] ?? 'nacional';
        $abonado = isset($_POST['abonado']) ? 1 : 0;
        $observacao = trim($_POST['observacao'] ?? '');
        
        if ($id && $data && $nome) {
            try {
                $stmt = $db->prepare("UPDATE config_feriados SET 
                    data = :data,
                    nome = :nome,
                    tipo = :tipo,
                    abonado = :abonado,
                    observacao = :observacao
                    WHERE id = :id AND empresa_id = :empresa_id");
                
                $stmt->execute([
                    ':data' => $data,
                    ':nome' => $nome,
                    ':tipo' => $tipo,
                    ':abonado' => $abonado,
                    ':observacao' => $observacao,
                    ':id' => $id,
                    ':empresa_id' => $empresa_id
                ]);
                
                $success = 'Feriado atualizado com sucesso!';
                $_POST = [];
                
            } catch (Exception $e) {
                $error = 'Erro ao atualizar: ' . $e->getMessage();
            }
        }
    }
    
    // Remover feriado
    if (isset($_GET['remover'])) {
        $id = (int)$_GET['remover'];
        
        try {
            $stmt = $db->prepare("DELETE FROM config_feriados WHERE id = :id AND empresa_id = :empresa_id");
            $stmt->execute([':id' => $id, ':empresa_id' => $empresa_id]);
            
            $success = 'Feriado removido com sucesso!';
            
        } catch (Exception $e) {
            $error = 'Erro ao remover: ' . $e->getMessage();
        }
    }
}

// Buscar feriados
$ano_filtro = $_GET['ano'] ?? date('Y');
$stmt = $db->prepare("SELECT * FROM config_feriados 
                      WHERE empresa_id = :empresa_id 
                      AND YEAR(data) = :ano 
                      ORDER BY data ASC");
$stmt->execute([
    ':empresa_id' => $empresa_id,
    ':ano' => $ano_filtro
]);
$feriados = $stmt->fetchAll();

// Buscar feriado para edição
$edit_id = $_GET['edit'] ?? 0;
$edit_feriado = null;
if ($edit_id) {
    $stmt = $db->prepare("SELECT * FROM config_feriados WHERE id = :id AND empresa_id = :empresa_id");
    $stmt->execute([':id' => $edit_id, ':empresa_id' => $empresa_id]);
    $edit_feriado = $stmt->fetch();
}
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
.form-group select,
.form-group textarea {
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-group small {
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 4px;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
}

.checkbox-group input {
    width: 18px;
    height: 18px;
    margin: 0;
}

.btn {
    padding: 10px 20px;
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

.feriados-table {
    width: 100%;
    border-collapse: collapse;
}

.feriados-table th,
.feriados-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.feriados-table th {
    background: var(--bg-secondary);
    font-weight: 600;
    font-size: 13px;
}

.feriados-table tr:hover {
    background: var(--bg-secondary);
}

.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

.badge-nacional {
    background: #d1fae5;
    color: #059669;
}

.badge-estadual {
    background: #bfdbfe;
    color: #1e40af;
}

.badge-municipal {
    background: #fed7aa;
    color: #c2410c;
}

.badge-pontofacultativo {
    background: #fef3c7;
    color: #d97706;
}

.badge-abonado {
    background: #d1fae5;
    color: #059669;
}

.badge-nao-abonado {
    background: #fee2e2;
    color: #dc2626;
}

.filters-bar {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    border: 1px solid var(--border-color);
}

.ano-select {
    display: flex;
    gap: 8px;
    align-items: center;
}

.ano-select select {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--bg-primary);
    color: var(--text-primary);
}

.form-actions {
    display: flex;
    gap: 16px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    display: block;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--bg-primary);
    border-radius: 24px;
    padding: 24px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
    margin: 0;
}

.modal-footer {
    margin-top: 20px;
    padding-top: 12px;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

@media (max-width: 768px) {
    .feriados-table {
        font-size: 12px;
    }
    
    .feriados-table th,
    .feriados-table td {
        padding: 8px;
    }
    
    .filters-bar {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<div class="config-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-calendar-times"></i> Feriados e Exceções</h2>
            <p>Cadastre feriados nacionais, estaduais, municipais e dias com horário especial</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Filtro de Ano -->
    <div class="filters-bar">
        <div class="ano-select">
            <label>Ano:</label>
            <select id="anoFilter" onchange="window.location.href='feriados.php?ano='+this.value">
                <?php for ($i = date('Y')-2; $i <= date('Y')+2; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo $ano_filtro == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <button class="btn btn-primary" onclick="document.getElementById('modalAdd').style.display='flex'">
                <i class="fas fa-plus"></i> Novo Feriado
            </button>
            <button class="btn btn-secondary" onclick="adicionarFeriadosPadrao()" style="margin-left: 8px;">
                <i class="fas fa-calendar-alt"></i> Adicionar Feriados Nacionais
            </button>
        </div>
    </div>

    <!-- Lista de Feriados -->
    <div class="config-card">
        <div class="config-header">
            <i class="fas fa-list"></i>
            <h3>Feriados Cadastrados - <?php echo $ano_filtro; ?></h3>
        </div>
        <div class="config-body" style="padding: 0;">
            <?php if (empty($feriados)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>Nenhum feriado cadastrado para o ano de <?php echo $ano_filtro; ?></p>
                    <button class="btn btn-primary" onclick="document.getElementById('modalAdd').style.display='flex'">
                        <i class="fas fa-plus"></i> Adicionar Feriado
                    </button>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="feriados-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Dia da Semana</th>
                                <th>Nome do Feriado</th>
                                <th>Tipo</th>
                                <th>Abonado</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $dias_semana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
                            foreach ($feriados as $feriado): 
                                $dia_semana = $dias_semana[date('w', strtotime($feriado['data']))];
                                $tipos = [
                                    'nacional' => 'Nacional',
                                    'estadual' => 'Estadual',
                                    'municipal' => 'Municipal',
                                    'pontofacultativo' => 'Ponto Facultativo'
                                ];
                            ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($feriado['data'])); ?></td>
                                    <td><?php echo $dia_semana; ?></td>
                                    <td><?php echo htmlspecialchars($feriado['nome']); ?></td>
                                    <td><span class="badge badge-<?php echo $feriado['tipo']; ?>"><?php echo $tipos[$feriado['tipo']]; ?></span></td>
                                    <td>
                                        <?php if ($feriado['abonado']): ?>
                                            <span class="badge badge-abonado">✅ Abonado</span>
                                        <?php else: ?>
                                            <span class="badge badge-nao-abonado">❌ Não abonado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?edit=<?php echo $feriado['id']; ?>&ano=<?php echo $ano_filtro; ?>" class="btn btn-sm btn-secondary" style="padding: 4px 10px;">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?remover=<?php echo $feriado['id']; ?>&ano=<?php echo $ano_filtro; ?>" class="btn btn-sm btn-danger" style="padding: 4px 10px;" onclick="return confirm('Tem certeza que deseja remover este feriado?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
                <li><strong>Feriados Nacionais:</strong> São automaticamente considerados como dia não trabalhado</li>
                <li><strong>Ponto Facultativo:</strong> Funcionário pode optar por trabalhar (não é descontado falta)</li>
                <li><strong>Feriado Abonado:</strong> Dia pago mesmo sem trabalhar</li>
                <li><strong>Feriado Não Abonado:</strong> Pode ser descontado ou compensado</li>
                <li><strong>Exceções:</strong> Dias com horário especial substituem os feriados padrão</li>
            </ul>
        </div>
    </div>
</div>

<!-- Modal Adicionar Feriado -->
<div id="modalAdd" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Adicionar Feriado</h3>
        </div>
        <form method="POST" action="">
            <div class="form-group" style="margin-bottom: 16px;">
                <label>Data</label>
                <input type="date" name="data" required>
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <label>Nome do Feriado</label>
                <input type="text" name="nome" required placeholder="Ex: Natal, Ano Novo, etc">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Tipo</label>
                    <select name="tipo">
                        <option value="nacional">Nacional</option>
                        <option value="estadual">Estadual</option>
                        <option value="municipal">Municipal</option>
                        <option value="pontofacultativo">Ponto Facultativo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div class="checkbox-group">
                        <input type="checkbox" name="abonado" value="1" id="abonadoAdd" checked>
                        <label for="abonadoAdd">Feriado Abonado (dia pago)</label>
                    </div>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <label>Observação</label>
                <textarea name="observacao" rows="2" placeholder="Informações adicionais sobre este feriado"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalAdd').style.display='none'">Cancelar</button>
                <button type="submit" name="adicionar" class="btn btn-primary">Adicionar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Feriado -->
<?php if ($edit_feriado): ?>
<div id="modalEdit" class="modal" style="display: flex;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Editar Feriado</h3>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="id" value="<?php echo $edit_feriado['id']; ?>">
            <div class="form-group" style="margin-bottom: 16px;">
                <label>Data</label>
                <input type="date" name="data" value="<?php echo $edit_feriado['data']; ?>" required>
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <label>Nome do Feriado</label>
                <input type="text" name="nome" value="<?php echo htmlspecialchars($edit_feriado['nome']); ?>" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Tipo</label>
                    <select name="tipo">
                        <option value="nacional" <?php echo $edit_feriado['tipo'] == 'nacional' ? 'selected' : ''; ?>>Nacional</option>
                        <option value="estadual" <?php echo $edit_feriado['tipo'] == 'estadual' ? 'selected' : ''; ?>>Estadual</option>
                        <option value="municipal" <?php echo $edit_feriado['tipo'] == 'municipal' ? 'selected' : ''; ?>>Municipal</option>
                        <option value="pontofacultativo" <?php echo $edit_feriado['tipo'] == 'pontofacultativo' ? 'selected' : ''; ?>>Ponto Facultativo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div class="checkbox-group">
                        <input type="checkbox" name="abonado" value="1" id="abonadoEdit" <?php echo $edit_feriado['abonado'] ? 'checked' : ''; ?>>
                        <label for="abonadoEdit">Feriado Abonado (dia pago)</label>
                    </div>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <label>Observação</label>
                <textarea name="observacao" rows="2" placeholder="Informações adicionais sobre este feriado"><?php echo htmlspecialchars($edit_feriado['observacao'] ?? ''); ?></textarea>
            </div>
            <div class="modal-footer">
                <a href="feriados.php?ano=<?php echo $ano_filtro; ?>" class="btn btn-secondary">Cancelar</a>
                <button type="submit" name="editar" class="btn btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function adicionarFeriadosPadrao() {
    if (confirm('Deseja adicionar todos os feriados nacionais padrão para este ano? Isso não irá duplicar feriados já existentes.')) {
        document.getElementById('formFeriadosPadrao').submit();
    }
}

// Fechar modais clicando fora
window.onclick = function(event) {
    const modalAdd = document.getElementById('modalAdd');
    const modalEdit = document.getElementById('modalEdit');
    if (event.target === modalAdd) {
        modalAdd.style.display = 'none';
    }
    if (event.target === modalEdit) {
        modalEdit.style.display = 'none';
    }
}

<?php if (!$edit_feriado): ?>
document.getElementById('modalEdit')?.style.display = 'none';
<?php endif; ?>
</script>

<form id="formFeriadosPadrao" method="POST" action="" style="display: none;">
    <input type="hidden" name="adicionar_padroes" value="1">
</form>

<?php require_once '../../includes/footer.php'; ?>