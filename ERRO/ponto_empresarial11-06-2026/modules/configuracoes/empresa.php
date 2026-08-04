<?php
// modules/configuracoes/empresa.php - Dados da Empresa
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

$pageTitle = 'Dados da Empresa';
$activePage = 'configuracoes';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$success = '';
$error = '';

// Buscar dados atuais
$stmt = $db->prepare("SELECT * FROM config_empresa WHERE empresa_id = :empresa_id");
$stmt->execute([':empresa_id' => $empresa_id]);
$config = $stmt->fetch();

if (!$config) {
    // Criar configuração padrão
    $stmt = $db->prepare("INSERT INTO config_empresa (empresa_id) VALUES (:empresa_id)");
    $stmt->execute([':empresa_id' => $empresa_id]);
    
    $stmt = $db->prepare("SELECT * FROM config_empresa WHERE empresa_id = :empresa_id");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $config = $stmt->fetch();
}

// Buscar dados da empresa principal
$stmt = $db->prepare("SELECT nome_empresa FROM empresa WHERE id = :id");
$stmt->execute([':id' => $empresa_id]);
$empresa = $stmt->fetch();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_empresa = trim($_POST['nome_empresa'] ?? '');
    $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? '');
    $inscricao_estadual = trim($_POST['inscricao_estadual'] ?? '');
    $inscricao_municipal = trim($_POST['inscricao_municipal'] ?? '');
    $telefone = preg_replace('/[^0-9]/', '', $_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $site = trim($_POST['site'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = $_POST['estado'] ?? '';
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
    
    // Formatar CNPJ
    if (strlen($cnpj) == 14) {
        $cnpj = substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }
    
    // Formatar telefone
    if (strlen($telefone) == 10) {
        $telefone = '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
    } elseif (strlen($telefone) == 11) {
        $telefone = '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
    }
    
    // Processar upload do logo
    $logo_path = $config['logo'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/empresas/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $extensao = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $nomeArquivo = 'logo_' . $empresa_id . '_' . time() . '.' . $extensao;
        $caminho = $uploadDir . $nomeArquivo;
        
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $caminho)) {
            // Remover logo antigo
            if ($logo_path && file_exists('../../' . $logo_path)) {
                unlink('../../' . $logo_path);
            }
            $logo_path = 'uploads/empresas/' . $nomeArquivo;
        }
    }
    
    // Remover logo
    if (isset($_POST['remover_logo']) && $_POST['remover_logo'] == '1') {
        if ($logo_path && file_exists('../../' . $logo_path)) {
            unlink('../../' . $logo_path);
        }
        $logo_path = null;
    }
    
    try {
        // Atualizar empresa principal
        $stmt = $db->prepare("UPDATE empresa SET nome_empresa = :nome WHERE id = :id");
        $stmt->execute([':nome' => $nome_empresa, ':id' => $empresa_id]);
        
        // Atualizar configurações
        $stmt = $db->prepare("UPDATE config_empresa SET 
            nome_empresa = :nome_empresa,
            cnpj = :cnpj,
            inscricao_estadual = :inscricao_estadual,
            inscricao_municipal = :inscricao_municipal,
            telefone = :telefone,
            email = :email,
            site = :site,
            endereco = :endereco,
            numero = :numero,
            complemento = :complemento,
            bairro = :bairro,
            cidade = :cidade,
            estado = :estado,
            cep = :cep,
            logo = :logo
            WHERE empresa_id = :empresa_id");
        
        $stmt->execute([
            ':nome_empresa' => $nome_empresa,
            ':cnpj' => $cnpj,
            ':inscricao_estadual' => $inscricao_estadual,
            ':inscricao_municipal' => $inscricao_municipal,
            ':telefone' => $telefone,
            ':email' => $email,
            ':site' => $site,
            ':endereco' => $endereco,
            ':numero' => $numero,
            ':complemento' => $complemento,
            ':bairro' => $bairro,
            ':cidade' => $cidade,
            ':estado' => $estado,
            ':cep' => $cep,
            ':logo' => $logo_path,
            ':empresa_id' => $empresa_id
        ]);
        
        // Atualizar sessão
        $_SESSION['empresa_nome'] = $nome_empresa;
        
        $success = 'Dados da empresa atualizados com sucesso!';
        
        // Recarregar dados
        $stmt = $db->prepare("SELECT * FROM config_empresa WHERE empresa_id = :empresa_id");
        $stmt->execute([':empresa_id' => $empresa_id]);
        $config = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = 'Erro ao salvar: ' . $e->getMessage();
    }
}
?>

<style>
.form-container {
    max-width: 800px;
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
    font-size: 20px;
}

.form-header p {
    margin: 8px 0 0;
    opacity: 0.9;
    font-size: 14px;
}

.form-body {
    padding: 24px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
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

.form-actions {
    display: flex;
    gap: 16px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
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

.logo-container {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.logo-preview {
    width: 100px;
    height: 100px;
    border-radius: 12px;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.logo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.logo-preview i {
    font-size: 40px;
    color: #9ca3af;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 16px;
    }
}
</style>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-building"></i> Dados da Empresa</h3>
            <p>Configure as informações cadastrais da sua empresa</p>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success" style="margin: 20px 24px 0 24px;"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin: 20px 24px 0 24px;"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="form-body" enctype="multipart/form-data">
            <!-- Logo -->
            <div class="form-group">
                <label>Logo da Empresa</label>
                <div class="logo-container">
                    <div class="logo-preview">
                        <?php if ($config['logo'] && file_exists('../../' . $config['logo'])): ?>
                            <img src="../../<?php echo $config['logo']; ?>?t=<?php echo time(); ?>" alt="Logo">
                        <?php else: ?>
                            <i class="fas fa-building"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <input type="file" name="logo" accept="image/*" class="form-control">
                        <small>Formatos: JPG, PNG (Max 2MB)</small>
                        <?php if ($config['logo']): ?>
                            <div class="checkbox-group">
                                <input type="checkbox" name="remover_logo" value="1" id="remover_logo">
                                <label for="remover_logo">Remover logo atual</label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nome da Empresa *</label>
                    <input type="text" name="nome_empresa" required value="<?php echo htmlspecialchars($config['nome_empresa'] ?? $empresa['nome_empresa'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>CNPJ</label>
                    <input type="text" name="cnpj" id="cnpj" value="<?php echo htmlspecialchars($config['cnpj'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Inscrição Estadual</label>
                    <input type="text" name="inscricao_estadual" value="<?php echo htmlspecialchars($config['inscricao_estadual'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Inscrição Municipal</label>
                    <input type="text" name="inscricao_municipal" value="<?php echo htmlspecialchars($config['inscricao_municipal'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="telefone" id="telefone" value="<?php echo htmlspecialchars($config['telefone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($config['email'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Site</label>
                    <input type="url" name="site" value="<?php echo htmlspecialchars($config['site'] ?? ''); ?>" placeholder="https://">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>CEP</label>
                    <input type="text" name="cep" id="cep" value="<?php echo htmlspecialchars($config['cep'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Endereço</label>
                    <input type="text" name="endereco" id="endereco" value="<?php echo htmlspecialchars($config['endereco'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Número</label>
                    <input type="text" name="numero" value="<?php echo htmlspecialchars($config['numero'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Complemento</label>
                    <input type="text" name="complemento" value="<?php echo htmlspecialchars($config['complemento'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Bairro</label>
                    <input type="text" name="bairro" id="bairro" value="<?php echo htmlspecialchars($config['bairro'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Cidade</label>
                    <input type="text" name="cidade" id="cidade" value="<?php echo htmlspecialchars($config['cidade'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" id="estado">
                        <option value="">Selecione</option>
                        <?php
                        $estados = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                        foreach ($estados as $uf) {
                            $selected = ($config['estado'] == $uf) ? 'selected' : '';
                            echo "<option value=\"$uf\" $selected>$uf</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Máscaras
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

document.getElementById('telefone')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 10) {
        value = value.replace(/(\d{2})(\d)/, '($1) $2');
        value = value.replace(/(\d{4})(\d)/, '$1-$2');
    } else {
        value = value.replace(/(\d{2})(\d)/, '($1) $2');
        value = value.replace(/(\d{5})(\d)/, '$1-$2');
    }
    e.target.value = value;
});

document.getElementById('cep')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 8) {
        value = value.replace(/^(\d{5})(\d)/, '$1-$2');
        e.target.value = value;
    }
});

// Buscar endereço por CEP
document.getElementById('cep')?.addEventListener('blur', function() {
    let cep = this.value.replace(/\D/g, '');
    if (cep.length === 8) {
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(response => response.json())
            .then(data => {
                if (!data.erro) {
                    document.getElementById('endereco').value = data.logradouro || '';
                    document.getElementById('bairro').value = data.bairro || '';
                    document.getElementById('cidade').value = data.localidade || '';
                    document.getElementById('estado').value = data.uf || '';
                }
            })
            .catch(error => console.log('Erro ao buscar CEP:', error));
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>