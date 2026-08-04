<?php
// modules/admin/empresas/editar.php - Editar Empresa (COM PLANO E ASSINATURA - SEM LOG)
$pageTitle = 'Editar Empresa';
$activePage = 'admin_empresas';
require_once '../../../includes/header.php';
require_once '../../../config/database.php';

if ($_SESSION['usuario_tipo'] !== 'super_admin') {
    header('Location: /index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;

// Buscar dados da empresa
$stmt = $db->prepare("SELECT * FROM empresas WHERE id = :id");
$stmt->execute([':id' => $id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    header('Location: index.php');
    exit;
}

// Buscar assinatura atual
$stmt = $db->prepare("SELECT a.*, p.nome as plano_nome 
                      FROM assinaturas a
                      JOIN planos p ON a.plano_id = p.id
                      WHERE a.empresa_id = :id AND a.status = 'ativa'
                      ORDER BY a.id DESC LIMIT 1");
$stmt->execute([':id' => $id]);
$assinatura_atual = $stmt->fetch();

// Buscar planos disponíveis
$planos = $db->query("SELECT id, nome, preco_mensal, preco_anual FROM planos WHERE ativo = 1 ORDER BY preco_mensal ASC")->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? '');
    $status = $_POST['status'] ?? 'ativa';
    
    // Dados do plano e assinatura
    $novo_plano_id = $_POST['plano_id'] ?? null;
    $ciclo = $_POST['ciclo'] ?? 'mensal';
    $data_fim = $_POST['data_fim'] ?? null;
    $alterar_assinatura = isset($_POST['alterar_assinatura']) ? true : false;
    
    $errors = [];
    if (empty($nome)) $errors[] = 'Nome da empresa é obrigatório';
    if (empty($email)) $errors[] = 'E-mail é obrigatório';
    
    // Formatar CNPJ
    $cnpj_formatado = $cnpj ? substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2) : null;
    
    // Verificar CNPJ duplicado (excluindo a própria empresa)
    if (!empty($cnpj)) {
        $check = $db->prepare("SELECT id FROM empresas WHERE cnpj = :cnpj AND id != :id");
        $check->execute([':cnpj' => $cnpj_formatado, ':id' => $id]);
        if ($check->fetch()) {
            $errors[] = 'CNPJ já cadastrado para outra empresa';
        }
    }
    
    // Formatar CEP
    $cep_formatado = $cep ? substr($cep, 0, 5) . '-' . substr($cep, 5, 3) : null;
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // ATUALIZAR DADOS DA EMPRESA
            $query = "UPDATE empresas SET 
                      nome = :nome,
                      cnpj = :cnpj,
                      email = :email,
                      telefone = :telefone,
                      cep = :cep,
                      endereco = :endereco,
                      numero = :numero,
                      complemento = :complemento,
                      bairro = :bairro,
                      cidade = :cidade,
                      estado = :estado,
                      status = :status
                      WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':nome' => $nome,
                ':cnpj' => $cnpj_formatado,
                ':email' => $email,
                ':telefone' => $telefone,
                ':cep' => $cep_formatado,
                ':endereco' => $endereco,
                ':numero' => $numero,
                ':complemento' => $complemento,
                ':bairro' => $bairro,
                ':cidade' => $cidade,
                ':estado' => $estado,
                ':status' => $status,
                ':id' => $id
            ]);
            
            // ATUALIZAR ASSINATURA (se solicitado)
            if ($alterar_assinatura && $novo_plano_id) {
                // Cancelar assinatura atual
                $stmt = $db->prepare("UPDATE assinaturas SET status = 'cancelada', cancelado_em = CURDATE() 
                                      WHERE empresa_id = :id AND status = 'ativa'");
                $stmt->execute([':id' => $id]);
                
                // Buscar valor do plano
                $stmt = $db->prepare("SELECT preco_mensal, preco_anual FROM planos WHERE id = :id");
                $stmt->execute([':id' => $novo_plano_id]);
                $plano = $stmt->fetch();
                $valor = ($ciclo == 'anual') ? $plano['preco_anual'] : $plano['preco_mensal'];
                
                // Calcular data de fim (se fornecida manualmente ou padrão)
                if ($data_fim) {
                    $data_fim_formatada = $data_fim;
                } else {
                    $data_fim_formatada = ($ciclo == 'anual') ? date('Y-m-d', strtotime('+1 year')) : date('Y-m-d', strtotime('+1 month'));
                }
                
                // Criar nova assinatura
                $query = "INSERT INTO assinaturas (empresa_id, plano_id, status, data_inicio, data_fim, ciclo, valor) 
                          VALUES (:empresa_id, :plano_id, 'ativa', CURDATE(), :data_fim, :ciclo, :valor)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':empresa_id' => $id,
                    ':plano_id' => $novo_plano_id,
                    ':data_fim' => $data_fim_formatada,
                    ':ciclo' => $ciclo,
                    ':valor' => $valor
                ]);
            }
            
            $db->commit();
            
            $success = "Empresa atualizada com sucesso!";
            if ($alterar_assinatura && $novo_plano_id) {
                $success .= " Plano e assinatura atualizados.";
            }
            
            // Recarregar dados
            $stmt = $db->prepare("SELECT * FROM empresas WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $empresa = $stmt->fetch();
            
            // Recarregar assinatura atual
            $stmt = $db->prepare("SELECT a.*, p.nome as plano_nome 
                                  FROM assinaturas a
                                  JOIN planos p ON a.plano_id = p.id
                                  WHERE a.empresa_id = :id AND a.status = 'ativa'
                                  ORDER BY a.id DESC LIMIT 1");
            $stmt->execute([':id' => $id]);
            $assinatura_atual = $stmt->fetch();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Erro ao atualizar: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>

<!-- O restante do HTML permanece igual ao código anterior -->
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
.form-main {
    padding: 24px;
}
.form-section {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border-color);
}
.form-section h4 {
    margin-bottom: 20px;
    color: var(--text-primary);
    font-size: 18px;
}
.form-section h4 i {
    color: var(--primary);
    margin-right: 8px;
}
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
    color: var(--text-primary);
}
.form-group input, .form-group select, .form-group textarea {
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    font-size: 14px;
    background: var(--bg-primary);
    color: var(--text-primary);
    transition: all 0.3s;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
.alert-info {
    background: #bfdbfe;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}
small {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 4px;
    display: block;
}
.cep-row {
    display: flex;
    gap: 16px;
    align-items: flex-end;
}
.cep-row .form-group:first-child {
    flex: 1;
}
.cep-row .form-group:last-child {
    flex-shrink: 0;
}
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}
.checkbox-label input {
    width: auto;
    margin: 0;
}
.assinatura-atual {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
}
.assinatura-atual p {
    margin: 4px 0;
}
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.status-ativa { background: #d1fae5; color: #059669; }
.status-cancelada { background: #fee2e2; color: #dc2626; }
.status-suspensa { background: #fef3c7; color: #d97706; }
</style>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-edit"></i> Editar Empresa</h3>
            <p>Altere os dados da empresa e gerencie a assinatura</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Assinatura Atual -->
        <div class="assinatura-atual">
            <strong><i class="fas fa-crown"></i> Assinatura Atual</strong>
            <?php if ($assinatura_atual): ?>
                <p><strong>Plano:</strong> <?php echo htmlspecialchars($assinatura_atual['plano_nome']); ?></p>
                <p><strong>Valor:</strong> R$ <?php echo number_format($assinatura_atual['valor'], 2, ',', '.'); ?> / <?php echo $assinatura_atual['ciclo']; ?></p>
                <p><strong>Início:</strong> <?php echo date('d/m/Y', strtotime($assinatura_atual['data_inicio'])); ?></p>
                <p><strong>Fim:</strong> <?php echo $assinatura_atual['data_fim'] ? date('d/m/Y', strtotime($assinatura_atual['data_fim'])) : '--'; ?></p>
                <p><strong>Status:</strong> 
                    <span class="status-badge status-<?php echo $assinatura_atual['status']; ?>">
                        <?php echo ucfirst($assinatura_atual['status']); ?>
                    </span>
                </p>
            <?php else: ?>
                <p>Nenhuma assinatura ativa</p>
            <?php endif; ?>
        </div>
        
        <form method="POST" action="" class="form-main" id="formEmpresa">
            <!-- Dados da Empresa -->
            <div class="form-section">
                <h4><i class="fas fa-building"></i> Dados da Empresa</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome da Empresa *</label>
                        <input type="text" name="nome" required value="<?php echo htmlspecialchars($empresa['nome']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Domínio</label>
                        <input type="text" value="<?php echo htmlspecialchars($empresa['dominio']); ?>.pontofacil.com" disabled>
                        <small>Domínio não pode ser alterado</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>E-mail *</label>
                        <input type="email" name="email" required value="<?php echo htmlspecialchars($empresa['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" id="telefone" value="<?php echo htmlspecialchars($empresa['telefone'] ?? ''); ?>" placeholder="(00) 00000-0000">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>CNPJ</label>
                        <input type="text" name="cnpj" id="cnpj" value="<?php echo htmlspecialchars($empresa['cnpj'] ?? ''); ?>" placeholder="00.000.000/0000-00">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="ativa" <?php echo $empresa['status'] == 'ativa' ? 'selected' : ''; ?>>Ativa</option>
                            <option value="inativa" <?php echo $empresa['status'] == 'inativa' ? 'selected' : ''; ?>>Inativa</option>
                            <option value="suspensa" <?php echo $empresa['status'] == 'suspensa' ? 'selected' : ''; ?>>Suspensa</option>
                            <option value="teste" <?php echo $empresa['status'] == 'teste' ? 'selected' : ''; ?>>Teste</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Endereço -->
            <div class="form-section">
                <h4><i class="fas fa-map-marker-alt"></i> Endereço</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>CEP</label>
                        <div class="cep-row">
                            <input type="text" name="cep" id="cep" value="<?php echo htmlspecialchars($empresa['cep'] ?? ''); ?>" placeholder="00000-000">
                            <button type="button" id="buscarCep" class="btn btn-secondary">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Endereço</label>
                        <input type="text" name="endereco" id="endereco" value="<?php echo htmlspecialchars($empresa['endereco'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" name="numero" id="numero" value="<?php echo htmlspecialchars($empresa['numero'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text" name="complemento" id="complemento" value="<?php echo htmlspecialchars($empresa['complemento'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Bairro</label>
                        <input type="text" name="bairro" id="bairro" value="<?php echo htmlspecialchars($empresa['bairro'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Cidade</label>
                        <input type="text" name="cidade" id="cidade" value="<?php echo htmlspecialchars($empresa['cidade'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado" id="estado">
                            <option value="">Selecione</option>
                            <?php
                            $estados = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                            foreach ($estados as $uf) {
                                $selected = ($empresa['estado'] ?? '') == $uf ? 'selected' : '';
                                echo "<option value=\"$uf\" $selected>$uf</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Alterar Plano e Assinatura -->
            <div class="form-section">
                <h4><i class="fas fa-crown"></i> Alterar Plano e Assinatura</h4>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="alterar_assinatura" id="alterarAssinatura" value="1">
                        <span>Alterar plano e assinatura</span>
                    </label>
                    <small>Marque esta opção para alterar o plano da empresa</small>
                </div>
                
                <div id="planoOptions" style="display: none;">
                    <div class="alert alert-info" style="margin: 16px 0;">
                        <i class="fas fa-info-circle"></i>
                        Ao alterar o plano, a assinatura atual será cancelada e uma nova será criada.
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Novo Plano *</label>
                            <select name="plano_id" id="planoId">
                                <option value="">Selecione um plano</option>
                                <?php foreach ($planos as $plano): ?>
                                <option value="<?php echo $plano['id']; ?>">
                                    <?php echo htmlspecialchars($plano['nome']); ?> - R$ <?php echo number_format($plano['preco_mensal'], 2, ',', '.'); ?>/mês
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Ciclo de Cobrança</label>
                            <select name="ciclo" id="ciclo">
                                <option value="mensal">Mensal</option>
                                <option value="anual">Anual (15% desconto)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Data de Fim (opcional)</label>
                            <input type="date" name="data_fim" id="dataFim">
                            <small>Deixe em branco para calcular automaticamente</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
                <a href="visualizar.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Mostrar/esconder opções de plano
document.getElementById('alterarAssinatura')?.addEventListener('change', function() {
    const planoOptions = document.getElementById('planoOptions');
    if (this.checked) {
        planoOptions.style.display = 'block';
        document.getElementById('planoId').required = true;
    } else {
        planoOptions.style.display = 'none';
        document.getElementById('planoId').required = false;
    }
});

// Atualizar data de fim baseada no ciclo
document.getElementById('ciclo')?.addEventListener('change', function() {
    const dataFim = document.getElementById('dataFim');
    if (!dataFim.value) {
        const hoje = new Date();
        if (this.value === 'anual') {
            hoje.setFullYear(hoje.getFullYear() + 1);
        } else {
            hoje.setMonth(hoje.getMonth() + 1);
        }
        dataFim.value = hoje.toISOString().split('T')[0];
    }
});

// Máscara para CNPJ
document.getElementById('cnpj')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 14) {
        value = value.replace(/^(\d{2})(\d)/, '$1.$2');
        value = value.replace(/^(\d{2}\.\d{3})(\d)/, '$1.$2');
        value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
        value = value.replace(/(\d{4})(\d)/, '$1-$2');
        e.target.value = value;
    }
});

// Máscara para Telefone
document.getElementById('telefone')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        if (value.length === 11) {
            value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        } else if (value.length === 10) {
            value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        }
        e.target.value = value;
    }
});

// Máscara para CEP
document.getElementById('cep')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 8) {
        value = value.replace(/^(\d{5})(\d)/, '$1-$2');
        e.target.value = value;
    }
});

// Buscar endereço por CEP
document.getElementById('buscarCep')?.addEventListener('click', function() {
    let cep = document.getElementById('cep').value.replace(/\D/g, '');
    const btn = this;
    const btnText = btn.innerHTML;
    
    if (cep.length !== 8) {
        alert('Digite um CEP válido com 8 dígitos');
        return;
    }
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
    btn.disabled = true;
    
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(response => response.json())
        .then(data => {
            if (!data.erro) {
                document.getElementById('endereco').value = data.logradouro || '';
                document.getElementById('bairro').value = data.bairro || '';
                document.getElementById('cidade').value = data.localidade || '';
                document.getElementById('estado').value = data.uf || '';
                document.getElementById('numero').focus();
                alert('Endereço encontrado com sucesso!');
            } else {
                alert('CEP não encontrado');
            }
        })
        .catch(error => {
            console.error('Erro ao buscar CEP:', error);
            alert('Erro ao buscar CEP. Tente novamente.');
        })
        .finally(() => {
            btn.innerHTML = btnText;
            btn.disabled = false;
        });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>