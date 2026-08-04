<?php
// modules/funcionarios/cadastrar.php - Cadastrar Novo Funcionário (CORRIGIDO)
$pageTitle = 'Novo Funcionário';
$activePage = 'funcionarios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

checkModuleAccess('funcionarios');

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Buscar filiais (se admin, mostra todas; se não, só a sua)
if ($_SESSION['usuario_tipo'] === 'super_admin' || $_SESSION['usuario_tipo'] === 'admin_empresa') {
    $stmt = $db->query("SELECT id, nome_fantasia FROM filiais WHERE ativo = 1 ORDER BY nome_fantasia");
    $filiais = $stmt->fetchAll();
} else {
    // Para gestor ou supervisor, usar valores padrão ou buscar da sessão
    $filial_id = $_SESSION['usuario_filial_id'] ?? null;
    $filial_nome = $_SESSION['usuario_filial_nome'] ?? null;
    
    if ($filial_id && $filial_nome) {
        $filiais = [['id' => $filial_id, 'nome_fantasia' => $filial_nome]];
    } else {
        // Fallback: buscar a primeira filial da empresa
        $empresa_id = getCurrentEmpresaId() ?: 1;
        $stmt = $db->prepare("SELECT id, nome_fantasia FROM filiais WHERE empresa_id = :empresa_id AND ativo = 1 LIMIT 1");
        $stmt->execute([':empresa_id' => $empresa_id]);
        $filiais = $stmt->fetchAll();
        
        if (empty($filiais)) {
            // Se ainda não houver filial, criar uma padrão
            $filiais = [['id' => null, 'nome_fantasia' => 'Nenhuma filial disponível']];
        }
    }
}

// Buscar cargos
$stmt = $db->query("SELECT id, nome FROM cargos WHERE ativo = 1 ORDER BY nome");
$cargos = $stmt->fetchAll();

// Buscar departamentos
$stmt = $db->query("SELECT id, nome, filial_id FROM departamentos WHERE ativo = 1 ORDER BY nome");
$departamentos = $stmt->fetchAll();

// Buscar jornadas
$stmt = $db->query("SELECT id, nome FROM jornadas ORDER BY nome");
$jornadas = $stmt->fetchAll();

// Função para validar CPF
function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) return false;
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;
    
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += $cpf[$i] * (10 - $i);
    }
    $resto = $soma % 11;
    $dv1 = $resto < 2 ? 0 : 11 - $resto;
    
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += $cpf[$i] * (11 - $i);
    }
    $resto = $soma % 11;
    $dv2 = $resto < 2 ? 0 : 11 - $resto;
    
    return $cpf[9] == $dv1 && $cpf[10] == $dv2;
}

// Função para formatar CPF
function formatarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) == 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

// Função para fazer upload e redimensionar imagem
function uploadFoto($file, $matricula) {
    $uploadDir = '../../uploads/funcionarios/';
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $extensao = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $nomeArquivo = $matricula . '_' . time() . '.' . $extensao;
    $caminhoCompleto = $uploadDir . $nomeArquivo;
    
    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extensao, $extensoesPermitidas)) {
        return ['error' => 'Formato de imagem não permitido. Use JPG, PNG, GIF ou WEBP.'];
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['error' => 'Imagem muito grande. Máximo 5MB.'];
    }
    
    list($larguraOriginal, $alturaOriginal, $tipo) = getimagesize($file['tmp_name']);
    $larguraDesejada = 300;
    $alturaDesejada = 300;
    
    $imagemOriginal = null;
    switch ($extensao) {
        case 'jpg':
        case 'jpeg':
            $imagemOriginal = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'png':
            $imagemOriginal = imagecreatefrompng($file['tmp_name']);
            break;
        case 'gif':
            $imagemOriginal = imagecreatefromgif($file['tmp_name']);
            break;
        case 'webp':
            $imagemOriginal = imagecreatefromwebp($file['tmp_name']);
            break;
    }
    
    if ($imagemOriginal) {
        $imagemRedimensionada = imagecreatetruecolor($larguraDesejada, $alturaDesejada);
        
        if ($extensao == 'png') {
            imagealphablending($imagemRedimensionada, false);
            imagesavealpha($imagemRedimensionada, true);
            $transparente = imagecolorallocatealpha($imagemRedimensionada, 255, 255, 255, 127);
            imagefilledrectangle($imagemRedimensionada, 0, 0, $larguraDesejada, $alturaDesejada, $transparente);
        }
        
        imagecopyresampled($imagemRedimensionada, $imagemOriginal, 0, 0, 0, 0, 
                          $larguraDesejada, $alturaDesejada, $larguraOriginal, $alturaOriginal);
        
        switch ($extensao) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($imagemRedimensionada, $caminhoCompleto, 90);
                break;
            case 'png':
                imagepng($imagemRedimensionada, $caminhoCompleto, 9);
                break;
            case 'gif':
                imagegif($imagemRedimensionada, $caminhoCompleto);
                break;
            case 'webp':
                imagewebp($imagemRedimensionada, $caminhoCompleto, 90);
                break;
        }
        
        imagedestroy($imagemOriginal);
        imagedestroy($imagemRedimensionada);
        
        return ['success' => 'uploads/funcionarios/' . $nomeArquivo];
    }
    
    return ['error' => 'Erro ao processar imagem'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empresa_id = getCurrentEmpresaId() ?: 1;
    $filial_id = $_POST['filial_id'] ?? null;
    $departamento_id = $_POST['departamento_id'] ?? null;
    $cargo_id = $_POST['cargo_id'] ?? null;
    $jornada_id = $_POST['jornada_id'] ?? null;
    $matricula = strtoupper(trim($_POST['matricula'] ?? ''));
    $nome = trim($_POST['nome'] ?? '');
    $nome_social = trim($_POST['nome_social'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $email_pessoal = trim($_POST['email_pessoal'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
    $rg = trim($_POST['rg'] ?? '');
    $data_nascimento = $_POST['data_nascimento'] ?? null;
    $telefone = preg_replace('/[^0-9]/', '', $_POST['telefone'] ?? '');
    $celular = preg_replace('/[^0-9]/', '', $_POST['celular'] ?? '');
    
    $endereco = trim($_POST['endereco'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
    
    $data_admissao = $_POST['data_admissao'] ?? date('Y-m-d');
    $tipo_contrato = $_POST['tipo_contrato'] ?? 'clt';
    $tipo_usuario = $_POST['tipo_usuario'] ?? 'funcionario';
    
    $pode_gerenciar_filiais = isset($_POST['pode_gerenciar_filiais']) ? 1 : 0;
    $pode_gerenciar_funcionarios = isset($_POST['pode_gerenciar_funcionarios']) ? 1 : 0;
    $pode_ver_relatorios = isset($_POST['pode_ver_relatorios']) ? 1 : 0;
    $status = $_POST['status'] ?? 'ativo';
    
    $errors = [];
    
    // Validações
    if (empty($matricula)) $errors[] = 'Matrícula é obrigatória';
    if (empty($nome)) $errors[] = 'Nome é obrigatório';
    if (empty($email)) $errors[] = 'E-mail é obrigatório';
    if (empty($senha)) $errors[] = 'Senha é obrigatória';
    if (empty($data_admissao)) $errors[] = 'Data de admissão é obrigatória';
    
    // Validação de filial
    if (!$filial_id) {
        $errors[] = 'Filial é obrigatória';
    }
    
    // Validação de CPF
    if (empty($cpf)) {
        $errors[] = 'CPF é obrigatório';
    } elseif (!validarCPF($cpf)) {
        $errors[] = 'CPF inválido';
    } else {
        $cpf_formatado = formatarCPF($cpf);
        
        $check = $db->prepare("SELECT id FROM funcionarios WHERE cpf = :cpf AND empresa_id = :empresa_id");
        $check->execute([':cpf' => $cpf_formatado, ':empresa_id' => $empresa_id]);
        if ($check->fetch()) {
            $errors[] = 'CPF já cadastrado nesta empresa';
        }
    }
    
    // Validar email único
    $check = $db->prepare("SELECT id FROM funcionarios WHERE email = :email AND empresa_id = :empresa_id");
    $check->execute([':email' => $email, ':empresa_id' => $empresa_id]);
    if ($check->fetch()) {
        $errors[] = 'E-mail já cadastrado nesta empresa';
    }
    
    // Validar matrícula única
    $check = $db->prepare("SELECT id FROM funcionarios WHERE matricula = :matricula AND empresa_id = :empresa_id");
    $check->execute([':matricula' => $matricula, ':empresa_id' => $empresa_id]);
    if ($check->fetch()) {
        $errors[] = 'Matrícula já cadastrada nesta empresa';
    }
    
    // Processar upload da foto
    $foto_path = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadFoto($_FILES['foto'], $matricula);
        if (isset($uploadResult['error'])) {
            $errors[] = $uploadResult['error'];
        } else {
            $foto_path = $uploadResult['success'];
        }
    }
    
    if (empty($errors)) {
        try {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            
            $telefone_formatado = $telefone ? '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4) : null;
            $celular_formatado = $celular ? '(' . substr($celular, 0, 2) . ') ' . substr($celular, 2, 5) . '-' . substr($celular, 7, 4) : null;
            
            $query = "INSERT INTO funcionarios (
                empresa_id, filial_id, departamento_id, cargo_id, matricula, nome, nome_social, 
                email, email_pessoal, senha, cpf, rg, data_nascimento, 
                telefone, celular, endereco, numero, complemento, bairro, 
                cidade, estado, cep, data_admissao, tipo_contrato, 
                tipo_usuario, pode_gerenciar_filiais, pode_gerenciar_funcionarios, 
                pode_ver_relatorios, status, foto, created_by
            ) VALUES (
                :empresa_id, :filial_id, :departamento_id, :cargo_id, :matricula, :nome, :nome_social,
                :email, :email_pessoal, :senha, :cpf, :rg, :data_nascimento,
                :telefone, :celular, :endereco, :numero, :complemento, :bairro,
                :cidade, :estado, :cep, :data_admissao, :tipo_contrato,
                :tipo_usuario, :pode_gerenciar_filiais, :pode_gerenciar_funcionarios,
                :pode_ver_relatorios, :status, :foto, :created_by
            )";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':empresa_id' => $empresa_id,
                ':filial_id' => $filial_id,
                ':departamento_id' => $departamento_id ?: null,
                ':cargo_id' => $cargo_id ?: null,
                ':matricula' => $matricula,
                ':nome' => $nome,
                ':nome_social' => $nome_social ?: null,
                ':email' => $email,
                ':email_pessoal' => $email_pessoal ?: null,
                ':senha' => $senha_hash,
                ':cpf' => $cpf_formatado,
                ':rg' => $rg ?: null,
                ':data_nascimento' => $data_nascimento ?: null,
                ':telefone' => $telefone_formatado,
                ':celular' => $celular_formatado,
                ':endereco' => $endereco ?: null,
                ':numero' => $numero ?: null,
                ':complemento' => $complemento ?: null,
                ':bairro' => $bairro ?: null,
                ':cidade' => $cidade ?: null,
                ':estado' => $estado ?: null,
                ':cep' => $cep ?: null,
                ':data_admissao' => $data_admissao,
                ':tipo_contrato' => $tipo_contrato,
                ':tipo_usuario' => $tipo_usuario,
                ':pode_gerenciar_filiais' => $pode_gerenciar_filiais,
                ':pode_gerenciar_funcionarios' => $pode_gerenciar_funcionarios,
                ':pode_ver_relatorios' => $pode_ver_relatorios,
                ':status' => $status,
                ':foto' => $foto_path,
                ':created_by' => getCurrentUserId()
            ]);
            
            $funcionario_id = $db->lastInsertId();
            
            if ($jornada_id) {
                $stmt = $db->prepare("INSERT INTO funcionario_jornada (funcionario_id, jornada_id, data_inicio) VALUES (:funcionario_id, :jornada_id, :data_inicio)");
                $stmt->execute([
                    ':funcionario_id' => $funcionario_id,
                    ':jornada_id' => $jornada_id,
                    ':data_inicio' => $data_admissao
                ]);
            }
            
            logAcao($db, 'INSERT', 'funcionarios', $funcionario_id, "Cadastrou funcionário: $nome");
            
            $success = 'Funcionário cadastrado com sucesso!';
            $_POST = [];
            
        } catch (Exception $e) {
            $error = 'Erro ao cadastrar: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-user-plus"></i> Cadastrar Novo Funcionário</h3>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="form-main" enctype="multipart/form-data">
            <!-- Foto -->
            <div class="form-section">
                <h4><i class="fas fa-camera"></i> Foto do Funcionário</h4>
                <div class="foto-container">
                    <div class="foto-preview" id="fotoPreview">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="foto-upload">
                        <label class="btn btn-outline">
                            <i class="fas fa-upload"></i> Selecionar Foto
                            <input type="file" name="foto" id="fotoInput" accept="image/*" style="display: none;">
                        </label>
                        <small>Formatos: JPG, PNG, GIF, WEBP (Max 5MB)</small>
                    </div>
                </div>
            </div>
            
            <!-- Dados Pessoais -->
            <div class="form-section">
                <h4><i class="fas fa-user"></i> Dados Pessoais</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Matrícula *</label>
                        <input type="text" name="matricula" required value="<?php echo htmlspecialchars($_POST['matricula'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Nome Completo *</label>
                        <input type="text" name="nome" required value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome Social</label>
                        <input type="text" name="nome_social" value="<?php echo htmlspecialchars($_POST['nome_social'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>CPF *</label>
                        <input type="text" name="cpf" id="cpf" required value="<?php echo htmlspecialchars($_POST['cpf'] ?? ''); ?>">
                        <small>Digite um CPF válido (não pode ser duplicado)</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>RG</label>
                        <input type="text" name="rg" value="<?php echo htmlspecialchars($_POST['rg'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Data de Nascimento</label>
                        <input type="date" name="data_nascimento" value="<?php echo htmlspecialchars($_POST['data_nascimento'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Contato -->
            <div class="form-section">
                <h4><i class="fas fa-envelope"></i> Contato</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>E-mail Corporativo *</label>
                        <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        <small>E-mail único na empresa</small>
                    </div>
                    <div class="form-group">
                        <label>E-mail Pessoal</label>
                        <input type="email" name="email_pessoal" value="<?php echo htmlspecialchars($_POST['email_pessoal'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" id="telefone" value="<?php echo htmlspecialchars($_POST['telefone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Celular</label>
                        <input type="text" name="celular" id="celular" value="<?php echo htmlspecialchars($_POST['celular'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Senha *</label>
                        <input type="password" name="senha" required>
                        <small>Mínimo 6 caracteres</small>
                    </div>
                </div>
            </div>
            
            <!-- Endereço -->
            <div class="form-section">
                <h4><i class="fas fa-map-marker-alt"></i> Endereço</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>CEP</label>
                        <input type="text" name="cep" id="cep" value="<?php echo htmlspecialchars($_POST['cep'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Endereço</label>
                        <input type="text" name="endereco" value="<?php echo htmlspecialchars($_POST['endereco'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" name="numero" value="<?php echo htmlspecialchars($_POST['numero'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text" name="complemento" value="<?php echo htmlspecialchars($_POST['complemento'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Bairro</label>
                        <input type="text" name="bairro" value="<?php echo htmlspecialchars($_POST['bairro'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Cidade</label>
                        <input type="text" name="cidade" value="<?php echo htmlspecialchars($_POST['cidade'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado">
                            <option value="">Selecione</option>
                            <?php
                            $estados = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                            foreach ($estados as $uf) {
                                echo "<option value=\"$uf\">$uf</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Dados Profissionais -->
            <div class="form-section">
                <h4><i class="fas fa-briefcase"></i> Dados Profissionais</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Filial *</label>
                        <select name="filial_id" required>
                            <option value="">Selecione uma filial</option>
                            <?php foreach ($filiais as $filial): ?>
                            <option value="<?php echo $filial['id']; ?>">
                                <?php echo htmlspecialchars($filial['nome_fantasia']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Departamento</label>
                        <select name="departamento_id">
                            <option value="">Selecione</option>
                            <?php foreach ($departamentos as $depto): ?>
                            <option value="<?php echo $depto['id']; ?>" data-filial="<?php echo $depto['filial_id']; ?>">
                                <?php echo htmlspecialchars($depto['nome']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Cargo</label>
                        <select name="cargo_id">
                            <option value="">Selecione</option>
                            <?php foreach ($cargos as $cargo): ?>
                            <option value="<?php echo $cargo['id']; ?>">
                                <?php echo htmlspecialchars($cargo['nome']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jornada de Trabalho</label>
                        <select name="jornada_id">
                            <option value="">Selecione</option>
                            <?php foreach ($jornadas as $jornada): ?>
                            <option value="<?php echo $jornada['id']; ?>">
                                <?php echo htmlspecialchars($jornada['nome']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Data de Admissão *</label>
                        <input type="date" name="data_admissao" required value="<?php echo htmlspecialchars($_POST['data_admissao'] ?? date('Y-m-d')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Tipo de Contrato</label>
                        <select name="tipo_contrato">
                            <option value="clt">CLT</option>
                            <option value="pj">PJ</option>
                            <option value="estagio">Estágio</option>
                            <option value="temporario">Temporário</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="ativo">Ativo</option>
                            <option value="ferias">Férias</option>
                            <option value="licenca">Licença</option>
                            <option value="afastado">Afastado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tipo de Usuário</label>
                        <select name="tipo_usuario">
                            <option value="funcionario">Funcionário</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="gestor">Gestor</option>
                            <?php if ($_SESSION['usuario_tipo'] === 'super_admin' || $_SESSION['usuario_tipo'] === 'admin_empresa'): ?>
                            <option value="admin_empresa">Administrador</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Permissões Especiais</label>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="pode_gerenciar_filiais" value="1">
                                Pode gerenciar filiais
                            </label>
                            <label>
                                <input type="checkbox" name="pode_gerenciar_funcionarios" value="1">
                                Pode gerenciar funcionários
                            </label>
                            <label>
                                <input type="checkbox" name="pode_ver_relatorios" value="1">
                                Pode ver relatórios
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Funcionário
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<style>
.foto-container {
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
}

.foto-preview {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 3px solid #e5e5e5;
}

.foto-preview i {
    font-size: 80px;
    color: #9ca3af;
}

.foto-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.form-section {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid #e5e5e5;
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

.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 8px;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: normal;
    cursor: pointer;
}

small {
    font-size: 11px;
    color: #999;
    margin-top: 4px;
    display: block;
}
</style>

<script>
// Preview da foto
document.getElementById('fotoInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            const preview = document.getElementById('fotoPreview');
            preview.innerHTML = `<img src="${event.target.result}" alt="Preview">`;
        };
        reader.readAsDataURL(file);
    }
});

// Máscara para CPF
document.getElementById('cpf')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        e.target.value = value;
    }
});

// Máscara para Telefone
document.getElementById('telefone')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 10) {
        value = value.replace(/(\d{2})(\d)/, '($1) $2');
        value = value.replace(/(\d{4})(\d)/, '$1-$2');
        e.target.value = value;
    }
});

// Máscara para Celular
document.getElementById('celular')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        value = value.replace(/(\d{2})(\d)/, '($1) $2');
        value = value.replace(/(\d{5})(\d)/, '$1-$2');
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
document.getElementById('cep')?.addEventListener('blur', function() {
    let cep = this.value.replace(/\D/g, '');
    if (cep.length === 8) {
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(response => response.json())
            .then(data => {
                if (!data.erro) {
                    document.querySelector('[name="endereco"]').value = data.logradouro;
                    document.querySelector('[name="bairro"]').value = data.bairro;
                    document.querySelector('[name="cidade"]').value = data.localidade;
                    document.querySelector('[name="estado"]').value = data.uf;
                }
            })
            .catch(error => console.log('Erro ao buscar CEP:', error));
    }
});

// Filtrar departamentos por filial
document.querySelector('select[name="filial_id"]')?.addEventListener('change', function() {
    const filialId = this.value;
    const departamentoSelect = document.querySelector('select[name="departamento_id"]');
    const options = departamentoSelect.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value === '') return;
        const optionFilial = option.getAttribute('data-filial');
        if (optionFilial === filialId || !optionFilial) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    });
    
    departamentoSelect.value = '';
});
</script>

<?php require_once '../../includes/footer.php'; ?>