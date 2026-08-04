<?php
// modules/admin/planos/index.php - Gerenciar Planos (CORRIGIDO)
$pageTitle = 'Planos';
$activePage = 'admin_planos';
require_once '../../../includes/header.php';
require_once '../../../config/database.php';

if ($_SESSION['usuario_tipo'] !== 'super_admin') {
    header('Location: /ponto_empresarial/index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao == 'salvar') {
        $nome = $_POST['nome'];
        $slug = strtolower(str_replace(' ', '_', $nome));
        $preco_mensal = $_POST['preco_mensal'];
        $preco_anual = $_POST['preco_anual'] ?: null;
        $recurso_funcionarios = $_POST['recurso_funcionarios'] ?: 0;
        
        $recursos = [
            'recurso_departamentos', 'recurso_horas_extras', 'recurso_banco_horas',
            'recurso_justificativas', 'recurso_relatorios_avancados', 'recurso_multi_gestores',
            'recurso_qrcode', 'recurso_exportacao_excel', 'recurso_exportacao_pdf',
            'recurso_backup', 'recurso_api', 'recurso_suporte_prioritario'
        ];
        
        $query = "INSERT INTO planos (nome, slug, preco_mensal, preco_anual, recurso_funcionarios, " 
                . implode(', ', $recursos) . ", ordem) VALUES (
                    :nome, :slug, :preco_mensal, :preco_anual, :recurso_funcionarios,
                    :departamentos, :horas_extras, :banco_horas, :justificativas, :relatorios_avancados,
                    :multi_gestores, :qrcode, :exportacao_excel, :exportacao_pdf, :backup, :api, :suporte, 0)";
        
        $stmt = $db->prepare($query);
        $params = [
            ':nome' => $nome,
            ':slug' => $slug,
            ':preco_mensal' => $preco_mensal,
            ':preco_anual' => $preco_anual,
            ':recurso_funcionarios' => $recurso_funcionarios,
            ':departamentos' => isset($_POST['recurso_departamentos']) ? 1 : 0,
            ':horas_extras' => isset($_POST['recurso_horas_extras']) ? 1 : 0,
            ':banco_horas' => isset($_POST['recurso_banco_horas']) ? 1 : 0,
            ':justificativas' => isset($_POST['recurso_justificativas']) ? 1 : 0,
            ':relatorios_avancados' => isset($_POST['recurso_relatorios_avancados']) ? 1 : 0,
            ':multi_gestores' => isset($_POST['recurso_multi_gestores']) ? 1 : 0,
            ':qrcode' => isset($_POST['recurso_qrcode']) ? 1 : 0,
            ':exportacao_excel' => isset($_POST['recurso_exportacao_excel']) ? 1 : 0,
            ':exportacao_pdf' => isset($_POST['recurso_exportacao_pdf']) ? 1 : 0,
            ':backup' => isset($_POST['recurso_backup']) ? 1 : 0,
            ':api' => isset($_POST['recurso_api']) ? 1 : 0,
            ':suporte' => isset($_POST['recurso_suporte_prioritario']) ? 1 : 0
        ];
        
        $stmt->execute($params);
        $success = "Plano criado com sucesso!";
    }
    
    if ($acao == 'editar') {
        $id = $_POST['id'];
        $nome = $_POST['nome'];
        $preco_mensal = $_POST['preco_mensal'];
        $preco_anual = $_POST['preco_anual'] ?: null;
        $recurso_funcionarios = $_POST['recurso_funcionarios'] ?: 0;
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        $query = "UPDATE planos SET 
                  nome = :nome,
                  preco_mensal = :preco_mensal,
                  preco_anual = :preco_anual,
                  recurso_funcionarios = :recurso_funcionarios,
                  recurso_departamentos = :departamentos,
                  recurso_horas_extras = :horas_extras,
                  recurso_banco_horas = :banco_horas,
                  recurso_justificativas = :justificativas,
                  recurso_relatorios_avancados = :relatorios_avancados,
                  recurso_multi_gestores = :multi_gestores,
                  recurso_qrcode = :qrcode,
                  recurso_exportacao_excel = :exportacao_excel,
                  recurso_exportacao_pdf = :exportacao_pdf,
                  recurso_backup = :backup,
                  recurso_api = :api,
                  recurso_suporte_prioritario = :suporte,
                  ativo = :ativo
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':id' => $id,
            ':nome' => $nome,
            ':preco_mensal' => $preco_mensal,
            ':preco_anual' => $preco_anual,
            ':recurso_funcionarios' => $recurso_funcionarios,
            ':departamentos' => isset($_POST['recurso_departamentos']) ? 1 : 0,
            ':horas_extras' => isset($_POST['recurso_horas_extras']) ? 1 : 0,
            ':banco_horas' => isset($_POST['recurso_banco_horas']) ? 1 : 0,
            ':justificativas' => isset($_POST['recurso_justificativas']) ? 1 : 0,
            ':relatorios_avancados' => isset($_POST['recurso_relatorios_avancados']) ? 1 : 0,
            ':multi_gestores' => isset($_POST['recurso_multi_gestores']) ? 1 : 0,
            ':qrcode' => isset($_POST['recurso_qrcode']) ? 1 : 0,
            ':exportacao_excel' => isset($_POST['recurso_exportacao_excel']) ? 1 : 0,
            ':exportacao_pdf' => isset($_POST['recurso_exportacao_pdf']) ? 1 : 0,
            ':backup' => isset($_POST['recurso_backup']) ? 1 : 0,
            ':api' => isset($_POST['recurso_api']) ? 1 : 0,
            ':suporte' => isset($_POST['recurso_suporte_prioritario']) ? 1 : 0,
            ':ativo' => $ativo
        ]);
        
        $success = "Plano atualizado com sucesso!";
    }
    
    if ($acao == 'excluir') {
        $id = $_POST['id'];
        $stmt = $db->prepare("DELETE FROM planos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $success = "Plano excluído com sucesso!";
    }
}

// Buscar planos
$planos = [];
try {
    $stmt = $db->query("SELECT * FROM planos ORDER BY preco_mensal ASC");
    $planos = $stmt->fetchAll();
} catch (PDOException $e) {
    // Se a tabela não existir, mostrar mensagem
    $error = "Tabela 'planos' não encontrada. Execute o script SQL para criar as tabelas.";
}
?>

<style>
.plano-card {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
    transition: var(--transition);
}

.plano-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.plano-header {
    text-align: center;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}

.plano-nome {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 8px;
}

.plano-preco {
    font-size: 32px;
    font-weight: 700;
    color: var(--primary);
}

.plano-preco small {
    font-size: 14px;
    font-weight: normal;
    color: var(--text-secondary);
}

.plano-recursos {
    margin: 20px 0;
    min-height: 200px;
}

.recurso-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 0;
    font-size: 13px;
}

.recurso-item.ativo { color: var(--success); }
.recurso-item.inativo { color: var(--text-muted); }

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
    padding: 32px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.recurso-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-top: 16px;
}

.recurso-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.text-danger {
    color: #dc2626;
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-crown"></i> Planos</h2>
        <p>Gerencie os planos disponíveis para as empresas</p>
    </div>
    <div class="module-actions">
        <button onclick="abrirModalNovoPlano()" class="btn btn-primary">
            <i class="fas fa-plus"></i> Novo Plano
        </button>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Planos em Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 24px;">
    <?php foreach ($planos as $plano): ?>
    <div class="plano-card">
        <div class="plano-header">
            <div class="plano-nome"><?php echo htmlspecialchars($plano['nome']); ?></div>
            <div class="plano-preco">
                R$ <?php echo number_format($plano['preco_mensal'], 2, ',', '.'); ?>
                <small>/mês</small>
            </div>
            <?php if ($plano['preco_anual']): ?>
            <div style="font-size: 12px; color: var(--success); margin-top: 8px;">
                <i class="fas fa-tag"></i> Anual: R$ <?php echo number_format($plano['preco_anual'], 2, ',', '.'); ?>
            </div>
            <?php endif; ?>
            <div style="font-size: 11px; margin-top: 8px;">
                <span class="status-badge status-<?php echo $plano['ativo'] ? 'ativa' : 'inativa'; ?>">
                    <?php echo $plano['ativo'] ? 'Ativo' : 'Inativo'; ?>
                </span>
            </div>
        </div>
        
        <div class="plano-recursos">
            <div class="recurso-item <?php echo $plano['recurso_funcionarios'] == 0 ? 'ativo' : ''; ?>">
                <i class="fas <?php echo $plano['recurso_funcionarios'] == 0 ? 'fa-infinity' : 'fa-user-check'; ?>"></i>
                <?php echo $plano['recurso_funcionarios'] == 0 ? 'Funcionários ilimitados' : "Até {$plano['recurso_funcionarios']} funcionários"; ?>
            </div>
            <div class="recurso-item <?php echo $plano['recurso_departamentos'] ? 'ativo' : 'inativo'; ?>">
                <i class="fas <?php echo $plano['recurso_departamentos'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                Controle por departamentos
            </div>
            <div class="recurso-item <?php echo $plano['recurso_horas_extras'] ? 'ativo' : 'inativo'; ?>">
                <i class="fas <?php echo $plano['recurso_horas_extras'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                Controle de horas extras
            </div>
            <div class="recurso-item <?php echo $plano['recurso_banco_horas'] ? 'ativo' : 'inativo'; ?>">
                <i class="fas <?php echo $plano['recurso_banco_horas'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                Banco de horas
            </div>
            <div class="recurso-item <?php echo $plano['recurso_relatorios_avancados'] ? 'ativo' : 'inativo'; ?>">
                <i class="fas <?php echo $plano['recurso_relatorios_avancados'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                Relatórios avançados
            </div>
            <div class="recurso-item <?php echo $plano['recurso_multi_gestores'] ? 'ativo' : 'inativo'; ?>">
                <i class="fas <?php echo $plano['recurso_multi_gestores'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                Múltiplos gestores
            </div>
            <div class="recurso-item <?php echo $plano['recurso_qrcode'] ? 'ativo' : 'inativo'; ?>">
                <i class="fas <?php echo $plano['recurso_qrcode'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                QR Code para registro
            </div>
            <div class="recurso-item <?php echo $plano['recurso_exportacao_excel'] ? 'ativo' : 'inativo'; ?>">
                <i class="fas <?php echo $plano['recurso_exportacao_excel'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                Exportação Excel/PDF
            </div>
            <div class="recurso-item <?php echo $plano['recurso_suporte_prioritario'] ? 'ativo' : 'inativo'; ?>">
                <i class="fas <?php echo $plano['recurso_suporte_prioritario'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                Suporte prioritário
            </div>
        </div>
        
        <div style="display: flex; gap: 12px;">
            <button onclick="editarPlano(<?php echo $plano['id']; ?>)" class="btn btn-secondary" style="flex: 1;">
                <i class="fas fa-edit"></i> Editar
            </button>
            <button onclick="excluirPlano(<?php echo $plano['id']; ?>)" class="btn btn-danger" style="flex: 1;">
                <i class="fas fa-trash"></i> Excluir
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal Novo/Editar Plano -->
<div id="modalPlano" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle">Novo Plano</h3>
        <form method="POST" action="" id="formPlano">
            <input type="hidden" name="acao" id="formAcao" value="salvar">
            <input type="hidden" name="id" id="planoId">
            
            <div class="form-group">
                <label>Nome do Plano *</label>
                <input type="text" name="nome" id="planoNome" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Preço Mensal (R$) *</label>
                    <input type="number" step="0.01" name="preco_mensal" id="precoMensal" required>
                </div>
                <div class="form-group">
                    <label>Preço Anual (R$)</label>
                    <input type="number" step="0.01" name="preco_anual" id="precoAnual">
                    <small>Deixe em branco para não oferecer</small>
                </div>
            </div>
            
            <div class="form-group">
                <label>Limite de Funcionários</label>
                <input type="number" name="recurso_funcionarios" id="recursoFuncionarios" value="0">
                <small>0 = Ilimitado</small>
            </div>
            
            <div class="form-group">
                <label>Recursos Inclusos</label>
                <div class="recurso-grid">
                    <label class="recurso-checkbox">
                        <input type="checkbox" name="recurso_departamentos" id="recursoDepartamentos">
                        <span>Departamentos</span>
                    </label>
                    <label class="recurso-checkbox">
                        <input type="checkbox" name="recurso_horas_extras" id="recursoHorasExtras">
                        <span>Horas Extras</span>
                    </label>
                    <label class="recurso-checkbox">
                        <input type="checkbox" name="recurso_banco_horas" id="recursoBancoHoras">
                        <span>Banco de Horas</span>
                    </label>
                    <label class="recurso-checkbox">
                        <input type="checkbox" name="recurso_justificativas" id="recursoJustificativas">
                        <span>Justificativas</span>
                    </label>
                    <label class="recurso-checkbox">
                        <input type="checkbox" name="recurso_relatorios_avancados" id="recursoRelatoriosAvancados">
                        <span>Relatórios Avançados</span>
                    </label>
                    <label class="recurso-checkbox">
                        <input type="checkbox" name="recurso_multi_gestores" id="recursoMultiGestores">
                        <span>Múltiplos Gestores</span>
                    </label>
                    <label class="recurso-checkbox">
                        <input type="checkbox" name="recurso_qrcode" id="recursoQrcode">
                        <span>QR Code</span>
                    </label>
                    <label class="recurso-checkbox">
                        <input type="checkbox" name="recurso_exportacao_excel" id="recursoExportacaoExcel">
                        <span>Exportação Excel/PDF</span>
                    </label>
                    <label class="recurso-checkbox">
                        <input type="checkbox" name="recurso_backup" id="recursoBackup">
                        <span>Backup Automático</span>
                    </label>
                    <label class="recurso-checkbox">
                        <input type="checkbox" name="recurso_api" id="recursoApi">
                        <span>API para Integração</span>
                    </label>
                    <label class="recurso-checkbox">
                        <input type="checkbox" name="recurso_suporte_prioritario" id="recursoSuporte">
                        <span>Suporte Prioritário</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group" id="ativoGroup" style="display: none;">
                <label>
                    <input type="checkbox" name="ativo" id="planoAtivo" value="1" checked>
                    Plano ativo
                </label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Salvar Plano</button>
                <button type="button" onclick="fecharModal()" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalNovoPlano() {
    document.getElementById('modalTitle').textContent = 'Novo Plano';
    document.getElementById('formAcao').value = 'salvar';
    document.getElementById('planoId').value = '';
    document.getElementById('planoNome').value = '';
    document.getElementById('precoMensal').value = '';
    document.getElementById('precoAnual').value = '';
    document.getElementById('recursoFuncionarios').value = 0;
    document.getElementById('ativoGroup').style.display = 'none';
    
    document.querySelectorAll('#modalPlano input[type="checkbox"]').forEach(cb => cb.checked = false);
    
    document.getElementById('modalPlano').style.display = 'flex';
}

function editarPlano(id) {
    // Buscar dados do plano via AJAX
    fetch(`../../../api/planos.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('modalTitle').textContent = 'Editar Plano';
            document.getElementById('formAcao').value = 'editar';
            document.getElementById('planoId').value = data.id;
            document.getElementById('planoNome').value = data.nome;
            document.getElementById('precoMensal').value = data.preco_mensal;
            document.getElementById('precoAnual').value = data.preco_anual;
            document.getElementById('recursoFuncionarios').value = data.recurso_funcionarios;
            document.getElementById('ativoGroup').style.display = 'block';
            document.getElementById('planoAtivo').checked = data.ativo == 1;
            
            document.getElementById('recursoDepartamentos').checked = data.recurso_departamentos == 1;
            document.getElementById('recursoHorasExtras').checked = data.recurso_horas_extras == 1;
            document.getElementById('recursoBancoHoras').checked = data.recurso_banco_horas == 1;
            document.getElementById('recursoJustificativas').checked = data.recurso_justificativas == 1;
            document.getElementById('recursoRelatoriosAvancados').checked = data.recurso_relatorios_avancados == 1;
            document.getElementById('recursoMultiGestores').checked = data.recurso_multi_gestores == 1;
            document.getElementById('recursoQrcode').checked = data.recurso_qrcode == 1;
            document.getElementById('recursoExportacaoExcel').checked = data.recurso_exportacao_excel == 1;
            document.getElementById('recursoBackup').checked = data.recurso_backup == 1;
            document.getElementById('recursoApi').checked = data.recurso_api == 1;
            document.getElementById('recursoSuporte').checked = data.recurso_suporte_prioritario == 1;
            
            document.getElementById('modalPlano').style.display = 'flex';
        })
        .catch(error => {
            alert('Erro ao carregar dados do plano: ' + error);
        });
}

function excluirPlano(id) {
    if (confirm('Tem certeza que deseja excluir este plano?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
    }
}

function fecharModal() {
    document.getElementById('modalPlano').style.display = 'none';
}
</script>

<?php require_once '../../../includes/footer.php'; ?>