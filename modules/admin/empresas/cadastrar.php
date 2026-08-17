<?php
// modules/admin/empresas/cadastrar.php - Nova Empresa (COM BUSCA POR CEP)
$pageTitle = 'Nova Empresa';
$activePage = 'admin_empresas';
require_once '../../../includes/header.php';
require_once '../../../config/database.php';

if ($_SESSION['usuario_tipo'] !== 'super_admin') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Buscar planos
$planos = $db->query("SELECT id, nome, preco_mensal, preco_anual FROM planos WHERE ativo = 1 ORDER BY preco_mensal ASC")->fetchAll();

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
    $plano_id = $_POST['plano_id'] ?? '';
    $ciclo = $_POST['ciclo'] ?? 'mensal';
    $dominio = strtolower(trim($_POST['dominio'] ?? ''));
    
    $errors = [];
    if (empty($nome)) $errors[] = 'Nome da empresa é obrigatório';
    if (empty($email)) $errors[] = 'E-mail é obrigatório';
    if (empty($plano_id)) $errors[] = 'Plano é obrigatório';
    
    // Gerar domínio automaticamente se não informado
    if (empty($dominio)) {
        $dominio = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nome));
    }
    
    // Verificar domínio único
    $check = $db->prepare("SELECT id FROM empresas WHERE dominio = :dominio");
    $check->execute([':dominio' => $dominio]);
    if ($check->fetch()) {
        $errors[] = 'Domínio já existe. Escolha outro.';
    }
    
    // Verificar CNPJ duplicado
    if (!empty($cnpj)) {
        $cnpj_formatado = substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
        $check = $db->prepare("SELECT id FROM empresas WHERE cnpj = :cnpj AND id != :id");
        $check->execute([':cnpj' => $cnpj_formatado, ':id' => 0]);
        if ($check->fetch()) {
            $errors[] = 'CNPJ já cadastrado';
        }
    } else {
        $cnpj_formatado = null;
    }
    
    // Formatar CEP
    $cep_formatado = $cep ? substr($cep, 0, 5) . '-' . substr($cep, 5, 3) : null;
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Criar empresa
            $query = "INSERT INTO empresas (nome, cnpj, email, telefone, cep, endereco, numero, complemento, bairro, cidade, estado, dominio, status, data_ativacao) 
                      VALUES (:nome, :cnpj, :email, :telefone, :cep, :endereco, :numero, :complemento, :bairro, :cidade, :estado, :dominio, 'ativa', CURDATE())";
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
                ':dominio' => $dominio
            ]);
            $empresa_id = $db->lastInsertId();
            
            // Buscar valor do plano
            $stmt = $db->prepare("SELECT preco_mensal, preco_anual FROM planos WHERE id = :id");
            $stmt->execute([':id' => $plano_id]);
            $plano = $stmt->fetch();
            $valor = ($ciclo == 'anual') ? $plano['preco_anual'] : $plano['preco_mensal'];
            
            // Criar assinatura
            $query = "INSERT INTO assinaturas (empresa_id, plano_id, status, data_inicio, ciclo, valor) 
                      VALUES (:empresa_id, :plano_id, 'ativa', CURDATE(), :ciclo, :valor)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':empresa_id' => $empresa_id,
                ':plano_id' => $plano_id,
                ':ciclo' => $ciclo,
                ':valor' => $valor
            ]);
            
            // Criar usuário admin da empresa
            $senha_padrao = '123456';
            $senha_hash = password_hash($senha_padrao, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO usuarios_sistema (nome, email, senha, tipo, empresa_id, status) 
                      VALUES (:nome, :email, :senha, 'admin_empresa', :empresa_id, 'ativo')";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':nome' => $nome,
                ':email' => $email,
                ':senha' => $senha_hash,
                ':empresa_id' => $empresa_id
            ]);
            
            $db->commit();
            $success = "Empresa criada com sucesso! Senha do administrador: $senha_padrao";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Erro ao cadastrar: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>

<style>
.form-section {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border-color);
}

.form-section h4 {
    margin-bottom: 20px;
    color: #333;
    font-size: 18px;
}

.form-section h4 i {
    color: #667eea;
    margin-right: 8px;
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
</style>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-plus-circle"></i> Nova Empresa</h3>
            <p>Preencha os dados da empresa e selecione o plano de assinatura</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="form-main" id="formEmpresa">
            <!-- Dados da Empresa -->
            <div class="form-section">
                <h4><i class="fas fa-building"></i> Dados da Empresa</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome da Empresa *</label>
                        <input type="text" name="nome" required value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Domínio *</label>
                        <input type="text" name="dominio" placeholder="exemplo" value="<?php echo htmlspecialchars($_POST['dominio'] ?? ''); ?>">
                        <small>.pontofacil.com</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>E-mail *</label>
                        <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" id="telefone" placeholder="(00) 00000-0000" value="<?php echo htmlspecialchars($_POST['telefone'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>CNPJ</label>
                        <input type="text" name="cnpj" id="cnpj" placeholder="00.000.000/0000-00" value="<?php echo htmlspecialchars($_POST['cnpj'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Endereço com Busca por CEP -->
            <div class="form-section">
                <h4><i class="fas fa-map-marker-alt"></i> Endereço</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>CEP</label>
                        <div class="cep-row">
                            <input type="text" name="cep" id="cep" placeholder="00000-000" value="<?php echo htmlspecialchars($_POST['cep'] ?? ''); ?>">
                            <button type="button" id="buscarCep" class="btn btn-secondary">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Endereço</label>
                        <input type="text" name="endereco" id="endereco" value="<?php echo htmlspecialchars($_POST['endereco'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" name="numero" id="numero" value="<?php echo htmlspecialchars($_POST['numero'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text" name="complemento" id="complemento" value="<?php echo htmlspecialchars($_POST['complemento'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Bairro</label>
                        <input type="text" name="bairro" id="bairro" value="<?php echo htmlspecialchars($_POST['bairro'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Cidade</label>
                        <input type="text" name="cidade" id="cidade" value="<?php echo htmlspecialchars($_POST['cidade'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado" id="estado">
                            <option value="">Selecione</option>
                            <?php
                            $estados = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                            foreach ($estados as $uf) {
                                $selected = ($_POST['estado'] ?? '') == $uf ? 'selected' : '';
                                echo "<option value=\"$uf\" $selected>$uf</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Plano e Assinatura -->
            <div class="form-section">
                <h4><i class="fas fa-crown"></i> Plano e Assinatura</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Plano *</label>
                        <select name="plano_id" required>
                            <option value="">Selecione um plano</option>
                            <?php foreach ($planos as $plano): ?>
                            <option value="<?php echo $plano['id']; ?>" <?php echo ($_POST['plano_id'] ?? '') == $plano['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($plano['nome']); ?> - R$ <?php echo number_format($plano['preco_mensal'], 2, ',', '.'); ?>/mês
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ciclo de Cobrança</label>
                        <select name="ciclo">
                            <option value="mensal" <?php echo ($_POST['ciclo'] ?? '') == 'mensal' ? 'selected' : ''; ?>>Mensal</option>
                            <option value="anual" <?php echo ($_POST['ciclo'] ?? '') == 'anual' ? 'selected' : ''; ?>>Anual (15% desconto)</option>
                        </select>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Informação:</strong> O administrador da empresa receberá as credenciais de acesso.
                    Senha padrão: <strong>123456</strong> (recomendamos alterar no primeiro acesso)
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Criar Empresa
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
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
                
                // Focar no campo número após buscar
                document.getElementById('numero').focus();
                
                showNotification('Endereço encontrado com sucesso!', 'success');
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

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.maxWidth = '300px';
    notification.innerHTML = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>

<?php require_once '../../../includes/footer.php'; ?>
