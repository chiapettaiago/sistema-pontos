<?php
// modules/funcionarios/editar.php - Editar Funcionário (COM PERMISSÕES)
$pageTitle = 'Editar Funcionário';
$activePage = 'funcionarios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

checkModuleAccess('funcionarios');

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;

// Buscar dados do funcionário
$query = "SELECT f.*, fj.jornada_id 
          FROM funcionarios f
          LEFT JOIN funcionario_jornada fj ON f.id = fj.funcionario_id AND (fj.data_fim IS NULL OR fj.data_fim >= CURDATE())
          WHERE f.id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    header('Location: index.php');
    exit;
}

// ============================================
// VERIFICAR PERMISSÃO PARA EDITAR
// ============================================
if (!canEditFuncionario($id)) {
    header('Location: index.php');
    exit;
}

// Buscar filiais (se admin, mostra todas; se não, só a sua)
if ($_SESSION['usuario_tipo'] === 'super_admin' || $_SESSION['usuario_tipo'] === 'admin_empresa') {
    $empresa_id = getCurrentEmpresaId() ?: 1;
    $stmt = $db->prepare("SELECT id, nome_fantasia FROM filiais WHERE empresa_id = :empresa_id AND ativo = 1 ORDER BY nome_fantasia");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $filiais = $stmt->fetchAll();
} else {
    // Para gestor, mostrar apenas sua filial
    $usuario_filial_id = $_SESSION['usuario_filial_id'] ?? null;
    if ($usuario_filial_id) {
        $stmt = $db->prepare("SELECT id, nome_fantasia FROM filiais WHERE id = :id AND ativo = 1");
        $stmt->execute([':id' => $usuario_filial_id]);
        $filiais = $stmt->fetchAll();
    } else {
        $filiais = [];
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
function formatarCPFEdit($cpf) {
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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empresa_id = getCurrentEmpresaId() ?: 1;
    $filial_id = $_POST['filial_id'] ?? $funcionario['filial_id'];
    $departamento_id = $_POST['departamento_id'] ?? null;
    $cargo_id = $_POST['cargo_id'] ?? null;
    $jornada_id = $_POST['jornada_id'] ?? null;
    $matricula = strtoupper(trim($_POST['matricula'] ?? ''));
    $nome = trim($_POST['nome'] ?? '');
    $nome_social = trim($_POST['nome_social'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $email_pessoal = trim($_POST['email_pessoal'] ?? '');
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
    
    $data_admissao = $_POST['data_admissao'] ?? $funcionario['data_admissao'];
    $tipo_contrato = $_POST['tipo_contrato'] ?? 'clt';
    $tipo_usuario = $_POST['tipo_usuario'] ?? 'funcionario';
    
    $pode_gerenciar_filiais = isset($_POST['pode_gerenciar_filiais']) ? 1 : 0;
    $pode_gerenciar_funcionarios = isset($_POST['pode_gerenciar_funcionarios']) ? 1 : 0;
    $pode_ver_relatorios = isset($_POST['pode_ver_relatorios']) ? 1 : 0;
    $status = $_POST['status'] ?? 'ativo';
    
    $errors = [];
    
    if (empty($matricula)) $errors[] = 'Matrícula é obrigatória';
    if (empty($nome)) $errors[] = 'Nome é obrigatório';
    if (empty($email)) $errors[] = 'E-mail é obrigatório';
    
    // Validação de CPF
    if (empty($cpf)) {
        $errors[] = 'CPF é obrigatório';
    } elseif (!validarCPF($cpf)) {
        $errors[] = 'CPF inválido';
    } else {
        $cpf_formatado = formatarCPFEdit($cpf);
        
        $check = $db->prepare("SELECT id FROM funcionarios WHERE cpf = :cpf AND empresa_id = :empresa_id AND id != :id");
        $check->execute([':cpf' => $cpf_formatado, ':empresa_id' => $empresa_id, ':id' => $id]);
        if ($check->fetch()) {
            $errors[] = 'CPF já cadastrado para outro funcionário desta empresa';
        }
    }
    
    // Validar email único
    $check = $db->prepare("SELECT id FROM funcionarios WHERE email = :email AND empresa_id = :empresa_id AND id != :id");
    $check->execute([':email' => $email, ':empresa_id' => $empresa_id, ':id' => $id]);
    if ($check->fetch()) {
        $errors[] = 'E-mail já cadastrado para outro funcionário desta empresa';
    }
    
    // Validar matrícula única
    $check = $db->prepare("SELECT id FROM funcionarios WHERE matricula = :matricula AND empresa_id = :empresa_id AND id != :id");
    $check->execute([':matricula' => $matricula, ':empresa_id' => $empresa_id, ':id' => $id]);
    if ($check->fetch()) {
        $errors[] = 'Matrícula já cadastrada para outro funcionário desta empresa';
    }
    
    // Processar upload da nova foto
    $foto_path = $funcionario['foto'];
    
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        if ($funcionario['foto'] && file_exists('../../' . $funcionario['foto'])) {
            unlink('../../' . $funcionario['foto']);
        }
        
        $uploadResult = uploadFoto($_FILES['foto'], $matricula);
        if (isset($uploadResult['error'])) {
            $errors[] = $uploadResult['error'];
        } else {
            $foto_path = $uploadResult['success'];
        }
    }
    
    // Remover foto se solicitado
    if (isset($_POST['remover_foto']) && $_POST['remover_foto'] == '1') {
        if ($funcionario['foto'] && file_exists('../../' . $funcionario['foto'])) {
            unlink('../../' . $funcionario['foto']);
        }
        $foto_path = null;
    }
    
    if (empty($errors)) {
        try {
            $telefone_formatado = $telefone ? '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4) : null;
            $celular_formatado = $celular ? '(' . substr($celular, 0, 2) . ') ' . substr($celular, 2, 5) . '-' . substr($celular, 7, 4) : null;
            
            $query = "UPDATE funcionarios SET 
                filial_id = :filial_id,
                departamento_id = :departamento_id,
                cargo_id = :cargo_id,
                matricula = :matricula,
                nome = :nome,
                nome_social = :nome_social,
                email = :email,
                email_pessoal = :email_pessoal,
                cpf = :cpf,
                rg = :rg,
                data_nascimento = :data_nascimento,
                telefone = :telefone,
                celular = :celular,
                endereco = :endereco,
                numero = :numero,
                complemento = :complemento,
                bairro = :bairro,
                cidade = :cidade,
                estado = :estado,
                cep = :cep,
                data_admissao = :data_admissao,
                tipo_contrato = :tipo_contrato,
                tipo_usuario = :tipo_usuario,
                pode_gerenciar_filiais = :pode_gerenciar_filiais,
                pode_gerenciar_funcionarios = :pode_gerenciar_funcionarios,
                pode_ver_relatorios = :pode_ver_relatorios,
                status = :status,
                foto = :foto
            WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':filial_id' => $filial_id,
                ':departamento_id' => $departamento_id ?: null,
                ':cargo_id' => $cargo_id ?: null,
                ':matricula' => $matricula,
                ':nome' => $nome,
                ':nome_social' => $nome_social ?: null,
                ':email' => $email,
                ':email_pessoal' => $email_pessoal ?: null,
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
                ':id' => $id
            ]);
            
            // Atualizar jornada
            $db->prepare("DELETE FROM funcionario_jornada WHERE funcionario_id = :id AND (data_fim IS NULL OR data_fim >= CURDATE())")
               ->execute([':id' => $id]);
            
            if ($jornada_id) {
                $stmt = $db->prepare("INSERT INTO funcionario_jornada (funcionario_id, jornada_id, data_inicio) VALUES (:funcionario_id, :jornada_id, :data_inicio)");
                $stmt->execute([
                    ':funcionario_id' => $id,
                    ':jornada_id' => $jornada_id,
                    ':data_inicio' => $data_admissao
                ]);
            }
            
            logAcao($db, 'UPDATE', 'funcionarios', $id, "Editou funcionário: $nome");
            
            $success = 'Funcionário atualizado com sucesso!';
            
            // Recarregar dados
            $stmt = $db->prepare("SELECT * FROM funcionarios WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $funcionario = $stmt->fetch();
            
        } catch (Exception $e) {
            $error = 'Erro ao atualizar: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-edit"></i> Editar Funcionário: <?php echo htmlspecialchars($funcionario['nome']); ?></h3>
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
                        <?php if ($funcionario['foto'] && file_exists('../../' . $funcionario['foto'])): ?>
                            <img src="../../<?php echo $funcionario['foto']; ?>" alt="Foto">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                    </div>
                    <div class="foto-upload">
                        <label class="btn btn-outline">
                            <i class="fas fa-upload"></i> Alterar Foto
                            <input type="file" name="foto" id="fotoInput" accept="image/*" style="display: none;">
                        </label>
                        <?php if ($funcionario['foto']): ?>
                            <label class="btn btn-outline btn-danger" style="margin-left: 8px;">
                                <i class="fas fa-trash"></i> Remover Foto
                                <input type="checkbox" name="remover_foto" value="1" style="display: none;" id="removerFotoCheck">
                            </label>
                        <?php endif; ?>
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
                        <input type="text" name="matricula" required value="<?php echo htmlspecialchars($funcionario['matricula']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Nome Completo *</label>
                        <input type="text" name="nome" required value="<?php echo htmlspecialchars($funcionario['nome']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome Social</label>
                        <input type="text" name="nome_social" value="<?php echo htmlspecialchars($funcionario['nome_social'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>CPF *</label>
                        <input type="text" name="cpf" id="cpf" required value="<?php echo htmlspecialchars($funcionario['cpf'] ?? ''); ?>">
                        <small>Digite um CPF válido (não pode ser duplicado)</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>RG</label>
                        <input type="text" name="rg" value="<?php echo htmlspecialchars($funcionario['rg'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Data de Nascimento</label>
                        <input type="date" name="data_nascimento" value="<?php echo htmlspecialchars($funcionario['data_nascimento'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Contato -->
            <div class="form-section">
                <h4><i class="fas fa-envelope"></i> Contato</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>E-mail Corporativo *</label>
                        <input type="email" name="email" required value="<?php echo htmlspecialchars($funcionario['email']); ?>">
                        <small>E-mail único na empresa</small>
                    </div>
                    <div class="form-group">
                        <label>E-mail Pessoal</label>
                        <input type="email" name="email_pessoal" value="<?php echo htmlspecialchars($funcionario['email_pessoal'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" id="telefone" value="<?php echo htmlspecialchars($funcionario['telefone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Celular</label>
                        <input type="text" name="celular" id="celular" value="<?php echo htmlspecialchars($funcionario['celular'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Endereço -->
            <div class="form-section">
                <h4><i class="fas fa-map-marker-alt"></i> Endereço</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>CEP</label>
                        <input type="text" name="cep" id="cep" value="<?php echo htmlspecialchars($funcionario['cep'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Endereço</label>
                        <input type="text" name="endereco" value="<?php echo htmlspecialchars($funcionario['endereco'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" name="numero" value="<?php echo htmlspecialchars($funcionario['numero'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text" name="complemento" value="<?php echo htmlspecialchars($funcionario['complemento'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Bairro</label>
                        <input type="text" name="bairro" value="<?php echo htmlspecialchars($funcionario['bairro'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Cidade</label>
                        <input type="text" name="cidade" value="<?php echo htmlspecialchars($funcionario['cidade'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado">
                            <option value="">Selecione</option>
                            <?php
                            $estados = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                            foreach ($estados as $uf) {
                                $selected = ($funcionario['estado'] == $uf) ? 'selected' : '';
                                echo "<option value=\"$uf\" $selected>$uf</option>";
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
                            <?php foreach ($filiais as $filial): ?>
                            <option value="<?php echo $filial['id']; ?>" <?php echo $funcionario['filial_id'] == $filial['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($filial['nome_fantasia']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Departamento</label>
                        <select name="departamento_id" id="departamento_id">
                            <option value="">Selecione</option>
                            <?php foreach ($departamentos as $depto): ?>
                            <option value="<?php echo $depto['id']; ?>" data-filial="<?php echo $depto['filial_id']; ?>" <?php echo $funcionario['departamento_id'] == $depto['id'] ? 'selected' : ''; ?>>
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
                            <option value="<?php echo $cargo['id']; ?>" <?php echo $funcionario['cargo_id'] == $cargo['id'] ? 'selected' : ''; ?>>
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
                            <option value="<?php echo $jornada['id']; ?>" <?php echo ($funcionario['jornada_id'] ?? '') == $jornada['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($jornada['nome']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Data de Admissão *</label>
                        <input type="date" name="data_admissao" required value="<?php echo htmlspecialchars($funcionario['data_admissao']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Tipo de Contrato</label>
                        <select name="tipo_contrato">
                            <option value="clt" <?php echo $funcionario['tipo_contrato'] == 'clt' ? 'selected' : ''; ?>>CLT</option>
                            <option value="pj" <?php echo $funcionario['tipo_contrato'] == 'pj' ? 'selected' : ''; ?>>PJ</option>
                            <option value="estagio" <?php echo $funcionario['tipo_contrato'] == 'estagio' ? 'selected' : ''; ?>>Estágio</option>
                            <option value="temporario" <?php echo $funcionario['tipo_contrato'] == 'temporario' ? 'selected' : ''; ?>>Temporário</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="ativo" <?php echo $funcionario['status'] == 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="ferias" <?php echo $funcionario['status'] == 'ferias' ? 'selected' : ''; ?>>Férias</option>
                            <option value="licenca" <?php echo $funcionario['status'] == 'licenca' ? 'selected' : ''; ?>>Licença</option>
                            <option value="afastado" <?php echo $funcionario['status'] == 'afastado' ? 'selected' : ''; ?>>Afastado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tipo de Usuário</label>
                        <select name="tipo_usuario">
                            <option value="funcionario" <?php echo $funcionario['tipo_usuario'] == 'funcionario' ? 'selected' : ''; ?>>Funcionário</option>
                            <option value="supervisor" <?php echo $funcionario['tipo_usuario'] == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                            <option value="gestor" <?php echo $funcionario['tipo_usuario'] == 'gestor' ? 'selected' : ''; ?>>Gestor</option>
                            <?php if ($_SESSION['usuario_tipo'] === 'super_admin' || $_SESSION['usuario_tipo'] === 'admin_empresa'): ?>
                            <option value="admin_empresa" <?php echo $funcionario['tipo_usuario'] == 'admin_empresa' ? 'selected' : ''; ?>>Administrador</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Permissões Especiais</label>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="pode_gerenciar_filiais" value="1" <?php echo $funcionario['pode_gerenciar_filiais'] ? 'checked' : ''; ?>>
                                Pode gerenciar filiais
                            </label>
                            <label>
                                <input type="checkbox" name="pode_gerenciar_funcionarios" value="1" <?php echo $funcionario['pode_gerenciar_funcionarios'] ? 'checked' : ''; ?>>
                                Pode gerenciar funcionários
                            </label>
                            <label>
                                <input type="checkbox" name="pode_ver_relatorios" value="1" <?php echo $funcionario['pode_ver_relatorios'] ? 'checked' : ''; ?>>
                                Pode ver relatórios
                            </label>
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

.btn-danger {
    background: #fee2e2;
    color: #dc2626;
    border-color: #dc2626;
}

.btn-danger:hover {
    background: #dc2626;
    color: white;
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

// Remover foto
document.getElementById('removerFotoCheck')?.addEventListener('change', function(e) {
    if (e.target.checked) {
        const preview = document.getElementById('fotoPreview');
        preview.innerHTML = '<i class="fas fa-user-circle"></i>';
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