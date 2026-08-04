<?php
// modules/funcionarios/cadastrar.php - Cadastrar Novo Funcionário (COM DETECÇÃO FACIAL AVANÇADA)
$pageTitle = 'Novo Funcionário';
$activePage = 'funcionarios';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

checkModuleAccess('funcionarios');

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$info_filial = '';

// ============================================
// OBTER EMPRESA DO USUÁRIO LOGADO
// ============================================
$empresa_id = getCurrentEmpresaId() ?: 1;
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';
$usuario_filial_id = $_SESSION['usuario_filial_id'] ?? null;

// ============================================
// BUSCAR FILIAIS APENAS DA EMPRESA DO USUÁRIO
// ============================================
$filiais = [];

if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa') {
    $stmt = $db->prepare("SELECT id, nome_fantasia FROM filiais WHERE empresa_id = :empresa_id AND ativo = 1 ORDER BY nome_fantasia");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $filiais = $stmt->fetchAll();
} elseif ($usuario_tipo === 'gestor' && $usuario_filial_id) {
    $stmt = $db->prepare("SELECT id, nome_fantasia FROM filiais WHERE id = :id AND ativo = 1 AND empresa_id = :empresa_id");
    $stmt->execute([':id' => $usuario_filial_id, ':empresa_id' => $empresa_id]);
    $filiais = $stmt->fetchAll();
}

// ============================================
// SE NÃO HOUVER FILIAIS, CRIAR UMA PADRÃO
// ============================================
if (empty($filiais)) {
    try {
        $stmt = $db->prepare("INSERT INTO filiais (empresa_id, codigo, nome_fantasia, tipo_ramo, ativo) 
                              VALUES (:empresa_id, 'MATRIZ', 'Matriz', 'matriz', 1)");
        $stmt->execute([':empresa_id' => $empresa_id]);
        
        $stmt = $db->prepare("SELECT id, nome_fantasia FROM filiais WHERE empresa_id = :empresa_id AND ativo = 1 LIMIT 1");
        $stmt->execute([':empresa_id' => $empresa_id]);
        $filiais = $stmt->fetchAll();
        
        if (!empty($filiais)) {
            $info_filial = '<div class="alert alert-info" style="margin-bottom: 20px;">
                            <i class="fas fa-info-circle"></i> 
                            Nenhuma filial encontrada. Uma filial padrão "Matriz" foi criada automaticamente.
                            </div>';
        }
    } catch (Exception $e) {
        $filiais = [['id' => null, 'nome_fantasia' => 'Selecione uma filial']];
        $info_filial = '<div class="alert alert-warning" style="margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Nenhuma filial disponível. Contacte o administrador.
                        </div>';
    }
}

// ============================================
// BUSCAR CARGOS, DEPARTAMENTOS E JORNADAS
// ============================================
$stmt = $db->query("SELECT id, nome FROM cargos WHERE ativo = 1 ORDER BY nome");
$cargos = $stmt->fetchAll();

$stmt = $db->query("SELECT id, nome, filial_id FROM departamentos WHERE ativo = 1 ORDER BY nome");
$departamentos = $stmt->fetchAll();

$stmt = $db->query("SELECT id, nome FROM jornadas ORDER BY nome");
$jornadas = $stmt->fetchAll();

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

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

function formatarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) == 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

// Função para salvar foto base64 da câmera
function salvarFotoBase64($base64, $matricula) {
    $uploadDir = '../../uploads/funcionarios/';
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $base64 = str_replace('data:image/jpeg;base64,', '', $base64);
    $base64 = str_replace('data:image/png;base64,', '', $base64);
    $base64 = str_replace(' ', '+', $base64);
    
    $foto_nome = preg_replace('/[^a-zA-Z0-9]/', '_', $matricula) . '_' . time() . '.jpg';
    $caminhoCompleto = $uploadDir . $foto_nome;
    
    file_put_contents($caminhoCompleto, base64_decode($base64));
    
    // Redimensionar e centralizar para 400x400
    $img = @imagecreatefromjpeg($caminhoCompleto);
    if (!$img) {
        $img = @imagecreatefrompng($caminhoCompleto);
    }
    if (!$img) {
        $img = @imagecreatefromgif($caminhoCompleto);
    }
    
    if ($img) {
        $new_width = 400;
        $new_height = 400;
        $resized = imagecreatetruecolor($new_width, $new_height);
        
        $orig_width = imagesx($img);
        $orig_height = imagesy($img);
        $ratio = max($new_width / $orig_width, $new_height / $orig_height);
        $crop_width = $new_width / $ratio;
        $crop_height = $new_height / $ratio;
        $crop_x = ($orig_width - $crop_width) / 2;
        $crop_y = ($orig_height - $crop_height) / 2;
        
        imagecopyresampled($resized, $img, 0, 0, $crop_x, $crop_y, $new_width, $new_height, $crop_width, $crop_height);
        imagejpeg($resized, $caminhoCompleto, 95);
        imagedestroy($img);
        imagedestroy($resized);
    }
    
    return 'uploads/funcionarios/' . $foto_nome;
}

// Função para upload de arquivo tradicional
function uploadFoto($file, $matricula) {
    $uploadDir = '../../uploads/funcionarios/';
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $extensao = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $nomeArquivo = preg_replace('/[^a-zA-Z0-9]/', '_', $matricula) . '_' . time() . '.' . $extensao;
    $caminhoCompleto = $uploadDir . $nomeArquivo;
    
    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extensao, $extensoesPermitidas)) {
        return ['error' => 'Formato de imagem não permitido.'];
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['error' => 'Imagem muito grande. Máximo 5MB.'];
    }
    
    $img = null;
    switch ($extensao) {
        case 'jpg': case 'jpeg': $img = imagecreatefromjpeg($file['tmp_name']); break;
        case 'png': $img = imagecreatefrompng($file['tmp_name']); break;
        case 'gif': $img = imagecreatefromgif($file['tmp_name']); break;
        case 'webp': $img = imagecreatefromwebp($file['tmp_name']); break;
    }
    
    if ($img) {
        $new_width = 400;
        $new_height = 400;
        $resized = imagecreatetruecolor($new_width, $new_height);
        
        $orig_width = imagesx($img);
        $orig_height = imagesy($img);
        $ratio = max($new_width / $orig_width, $new_height / $orig_height);
        $crop_width = $new_width / $ratio;
        $crop_height = $new_height / $ratio;
        $crop_x = ($orig_width - $crop_width) / 2;
        $crop_y = ($orig_height - $crop_height) / 2;
        
        imagecopyresampled($resized, $img, 0, 0, $crop_x, $crop_y, $new_width, $new_height, $crop_width, $crop_height);
        imagejpeg($resized, $caminhoCompleto, 95);
        imagedestroy($img);
        imagedestroy($resized);
        
        return ['success' => 'uploads/funcionarios/' . $nomeArquivo];
    }
    
    return ['error' => 'Erro ao processar imagem'];
}

// ============================================
// PROCESSAR FORMULÁRIO
// ============================================
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
    
    $foto_base64 = $_POST['foto_base64'] ?? '';
    
    $errors = [];
    
    if (empty($matricula)) $errors[] = 'Matrícula é obrigatória';
    if (empty($nome)) $errors[] = 'Nome é obrigatório';
    if (empty($email)) $errors[] = 'E-mail é obrigatório';
    if (empty($senha)) $errors[] = 'Senha é obrigatória';
    if (empty($data_admissao)) $errors[] = 'Data de admissão é obrigatória';
    
    if (!$filial_id) {
        $errors[] = 'Filial é obrigatória';
    }
    
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
    
    $check = $db->prepare("SELECT id FROM funcionarios WHERE email = :email AND empresa_id = :empresa_id");
    $check->execute([':email' => $email, ':empresa_id' => $empresa_id]);
    if ($check->fetch()) {
        $errors[] = "O e-mail '{$email}' já está cadastrado para outro funcionário desta empresa.";
    }
    
    $check = $db->prepare("SELECT id FROM funcionarios WHERE matricula = :matricula AND empresa_id = :empresa_id");
    $check->execute([':matricula' => $matricula, ':empresa_id' => $empresa_id]);
    if ($check->fetch()) {
        $errors[] = 'Matrícula já cadastrada nesta empresa';
    }
    
    $foto_path = null;
    
    if ($foto_base64) {
        $foto_path = salvarFotoBase64($foto_base64, $matricula);
    } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
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

<style>
.form-container {
    max-width: 900px;
    margin: 0 auto;
}
.form-card {
    background: var(--bg-primary);
    border-radius: 24px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}
.form-header {
    padding: 20px 24px;
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
.alert-warning {
    background: #fed7aa;
    color: #c2410c;
    border: 1px solid #fed7aa;
}

/* Estilos da Foto com Câmera */
.foto-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}
.foto-preview {
    width: 180px;
    height: 180px;
    border-radius: 50%;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 4px solid #667eea;
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
.foto-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: center;
}
.btn-foto {
    padding: 10px 20px;
    border: none;
    border-radius: 40px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-camera {
    background: #667eea;
    color: white;
}
.btn-camera:hover {
    background: #5a67d8;
    transform: scale(1.05);
}
.btn-upload {
    background: #10b981;
    color: white;
}
.btn-upload:hover {
    background: #059669;
}
.btn-remover {
    background: #ef4444;
    color: white;
}
.btn-remover:hover {
    background: #dc2626;
}
.file-input {
    display: none;
}

/* Modal da Câmera */
.camera-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.95);
    z-index: 2000;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}
.camera-container {
    position: relative;
    background: #000;
    border-radius: 24px;
    overflow: hidden;
    max-width: 500px;
    width: 90%;
}
video {
    width: 100%;
    height: auto;
    display: block;
}
#faceOverlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 10;
}
.camera-buttons {
    display: flex;
    gap: 12px;
    padding: 20px;
    background: #1e293b;
}
.btn-capturar-foto {
    flex: 1;
    background: #667eea;
    color: white;
    border: none;
    padding: 14px;
    border-radius: 40px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
}
.btn-fechar-camera {
    flex: 1;
    background: #ef4444;
    color: white;
    border: none;
    padding: 14px;
    border-radius: 40px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
}
.instrucao-rosto {
    text-align: center;
    padding: 12px;
    background: rgba(0,0,0,0.7);
    color: white;
    font-size: 13px;
    position: absolute;
    bottom: 80px;
    left: 0;
    right: 0;
    z-index: 20;
    border-radius: 0 0 24px 24px;
}
.status-detect {
    position: absolute;
    top: 10px;
    left: 10px;
    background: rgba(0,0,0,0.6);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    z-index: 20;
}
.btn-capturar-foto:disabled {
    opacity: 0.7;
    cursor: not-allowed;
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

@media (max-width: 480px) {
    .form-main {
        padding: 16px;
    }
    .foto-preview {
        width: 140px;
        height: 140px;
    }
    .btn-foto {
        padding: 8px 16px;
        font-size: 12px;
    }
}
</style>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-user-plus"></i> Cadastrar Novo Funcionário</h3>
            <p>Preencha os dados e tire uma foto do rosto para reconhecimento facial</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($info_filial): ?>
            <?php echo $info_filial; ?>
        <?php endif; ?>
        
        <form method="POST" action="" class="form-main" enctype="multipart/form-data" id="cadastroForm">
            <!-- Foto com Câmera -->
            <div class="form-section">
                <h4><i class="fas fa-camera"></i> Foto do Funcionário (para reconhecimento facial)</h4>
                <div class="foto-container">
                    <div class="foto-preview" id="fotoPreview">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="foto-buttons">
                        <button type="button" class="btn-foto btn-camera" id="abrirCameraBtn">
                            <i class="fas fa-camera"></i> Tirar Foto do Rosto
                        </button>
                        <label class="btn-foto btn-upload">
                            <i class="fas fa-upload"></i> Upload de Arquivo
                            <input type="file" name="foto" id="fotoInput" accept="image/*" class="file-input">
                        </label>
                        <button type="button" class="btn-foto btn-remover" id="removerFotoBtn" style="display: none;">
                            <i class="fas fa-trash-alt"></i> Remover Foto
                        </button>
                    </div>
                    <input type="hidden" name="foto_base64" id="foto_base64">
                    <small><i class="fas fa-info-circle"></i> A foto será usada para reconhecimento facial. Centralize o rosto e mantenha os olhos nos pontos.</small>
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
                        <div class="cep-row">
                            <input type="text" name="cep" id="cep" value="<?php echo htmlspecialchars($_POST['cep'] ?? ''); ?>">
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
            
            <!-- Dados Profissionais -->
            <div class="form-section">
                <h4><i class="fas fa-briefcase"></i> Dados Profissionais</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Filial *</label>
                        <select name="filial_id" required>
                            <option value="">Selecione uma filial</option>
                            <?php foreach ($filiais as $filial): ?>
                            <option value="<?php echo $filial['id']; ?>" <?php echo ($_POST['filial_id'] ?? '') == $filial['id'] ? 'selected' : ''; ?>>
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
                            <option value="desligado">Desligado</option>
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
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Atenção:</strong> A foto do rosto será usada para validação no reconhecimento facial.
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

<!-- Modal da Câmera -->
<div id="cameraModal" class="camera-modal">
    <div class="camera-container">
        <video id="video" autoplay playsinline></video>
        <canvas id="canvas" style="display: none;"></canvas>
        <div class="camera-buttons">
            <button class="btn-capturar-foto" id="capturarFoto">📸 Capturar Foto do Rosto</button>
            <button class="btn-fechar-camera" id="fecharCamera">❌ Fechar</button>
        </div>
        <div class="instrucao-rosto">
            <i class="fas fa-face-smile"></i> Centralize seu rosto no círculo e mantenha os olhos nos pontos
        </div>
        <div id="statusDetect" class="status-detect">🔄 Carregando detecção facial...</div>
    </div>
</div>

<script>
// ============================================
// DETECÇÃO FACIAL AVANÇADA (ROSTO + OLHOS + ÍRIS)
// ============================================

let stream = null;
let fotoCapturada = false;
let faceDetectionModel = null;
let detectionInterval = null;

// Elementos
const fotoPreview = document.getElementById('fotoPreview');
const fotoBase64Input = document.getElementById('foto_base64');
const removerFotoBtn = document.getElementById('removerFotoBtn');
const abrirCameraBtn = document.getElementById('abrirCameraBtn');
const cameraModal = document.getElementById('cameraModal');
const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const capturarBtn = document.getElementById('capturarFoto');
const fecharBtn = document.getElementById('fecharCamera');
const statusDetect = document.getElementById('statusDetect');

// Carregar modelo Face API
async function carregarModeloFacial() {
    if (statusDetect) statusDetect.innerHTML = '🔄 Carregando modelo facial...';
    
    try {
        await faceapi.nets.tinyFaceDetector.loadFromUri('../../models');
        await faceapi.nets.faceLandmark68Net.loadFromUri('../../models');
        await faceapi.nets.faceRecognitionNet.loadFromUri('../../models');
        
        if (statusDetect) {
            statusDetect.innerHTML = '✅ Detecção facial ativa';
            statusDetect.style.backgroundColor = 'rgba(16, 185, 129, 0.8)';
        }
        console.log('✅ Modelo facial carregado!');
        return true;
    } catch (err) {
        console.error('Erro ao carregar modelo:', err);
        if (statusDetect) {
            statusDetect.innerHTML = '⚠️ Modo manual (sem detecção)';
            statusDetect.style.backgroundColor = 'rgba(245, 158, 11, 0.8)';
        }
        return false;
    }
}

// Criar overlay
function criarOverlay() {
    const videoContainer = document.querySelector('.camera-container');
    if (!videoContainer) return null;
    
    let overlay = document.getElementById('faceOverlay');
    if (overlay) overlay.remove();
    
    overlay = document.createElement('canvas');
    overlay.id = 'faceOverlay';
    overlay.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:10;';
    videoContainer.style.position = 'relative';
    videoContainer.appendChild(overlay);
    
    return overlay;
}

// Desenhar detecção facial
function desenharDeteccao(overlay, videoElement, deteccoes) {
    if (!overlay || !videoElement) return;
    
    const rect = videoElement.getBoundingClientRect();
    if (rect.width === 0) return;
    
    overlay.width = rect.width;
    overlay.height = rect.height;
    const ctx = overlay.getContext('2d');
    ctx.clearRect(0, 0, overlay.width, overlay.height);
    
    const centerX = overlay.width / 2;
    const centerY = overlay.height / 2;
    
    if (deteccoes && deteccoes.length > 0) {
        const deteccao = deteccoes[0];
        const caixa = deteccao.box;
        const scaleX = overlay.width / videoElement.videoWidth;
        const scaleY = overlay.height / videoElement.videoHeight;
        
        const x = caixa.x * scaleX;
        const y = caixa.y * scaleY;
        const width = caixa.width * scaleX;
        const height = caixa.height * scaleY;
        
        // Retângulo verde ao redor do rosto
        ctx.strokeStyle = '#10b981';
        ctx.lineWidth = 3;
        ctx.strokeRect(x, y, width, height);
        
        // Pontos faciais
        if (deteccao.landmarks) {
            const pontos = deteccao.landmarks.positions;
            
            // Olhos (vermelho)
            ctx.fillStyle = '#ef4444';
            for (let i = 36; i <= 41 && i < pontos.length; i++) {
                ctx.beginPath();
                ctx.arc(pontos[i].x * scaleX, pontos[i].y * scaleY, 4, 0, 2 * Math.PI);
                ctx.fill();
            }
            for (let i = 42; i <= 47 && i < pontos.length; i++) {
                ctx.beginPath();
                ctx.arc(pontos[i].x * scaleX, pontos[i].y * scaleY, 4, 0, 2 * Math.PI);
                ctx.fill();
            }
            
            // Íris (amarelo)
            ctx.fillStyle = '#fbbf24';
            if (pontos.length > 40) {
                const olhoEsqX = (pontos[36].x + pontos[39].x) / 2 * scaleX;
                const olhoEsqY = (pontos[36].y + pontos[39].y) / 2 * scaleY;
                ctx.beginPath();
                ctx.arc(olhoEsqX, olhoEsqY, 5, 0, 2 * Math.PI);
                ctx.fill();
                
                const olhoDirX = (pontos[42].x + pontos[45].x) / 2 * scaleX;
                const olhoDirY = (pontos[42].y + pontos[45].y) / 2 * scaleY;
                ctx.beginPath();
                ctx.arc(olhoDirX, olhoDirY, 5, 0, 2 * Math.PI);
                ctx.fill();
            }
        }
        
        ctx.font = '14px Arial';
        ctx.fillStyle = '#10b981';
        ctx.fillText('✓ Rosto detectado', x, y - 10);
        
    } else {
        // Desenhar guia
        const radius = Math.min(overlay.width, overlay.height) * 0.3;
        
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
        ctx.strokeStyle = 'white';
        ctx.lineWidth = 3;
        ctx.stroke();
        
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius * 0.85, 0, 2 * Math.PI);
        ctx.strokeStyle = 'rgba(255,255,255,0.5)';
        ctx.lineWidth = 1.5;
        ctx.stroke();
        
        // Pontos dos olhos
        const olhoY = centerY - radius * 0.2;
        ctx.fillStyle = 'rgba(255,255,255,0.7)';
        ctx.beginPath();
        ctx.arc(centerX - radius * 0.35, olhoY, 6, 0, 2 * Math.PI);
        ctx.fill();
        ctx.beginPath();
        ctx.arc(centerX + radius * 0.35, olhoY, 6, 0, 2 * Math.PI);
        ctx.fill();
    }
}

// Iniciar detecção contínua
let currentDeteccoes = [];
let modeloCarregado = false;

async function iniciarDeteccaoContínua(videoElement, overlay) {
    if (!modeloCarregado) return;
    
    const detectar = async () => {
        if (!videoElement.videoWidth || cameraModal.style.display !== 'flex') return;
        
        try {
            const deteccoes = await faceapi.detectAllFaces(videoElement, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks();
            currentDeteccoes = deteccoes;
            desenharDeteccao(overlay, videoElement, deteccoes);
        } catch (err) {
            desenharDeteccao(overlay, videoElement, null);
        }
        
        if (cameraModal.style.display === 'flex') {
            requestAnimationFrame(detectar);
        }
    };
    
    detectar();
}

// Cortar apenas o rosto
async function cortarApenasRosto(videoElement) {
    return new Promise((resolve) => {
        if (currentDeteccoes && currentDeteccoes.length > 0) {
            const caixa = currentDeteccoes[0].box;
            const margin = 0.25;
            let x = Math.max(0, caixa.x - caixa.width * margin / 2);
            let y = Math.max(0, caixa.y - caixa.height * margin / 2);
            let width = Math.min(videoElement.videoWidth - x, caixa.width * (1 + margin));
            let height = Math.min(videoElement.videoHeight - y, caixa.height * (1 + margin));
            
            const finalCanvas = document.createElement('canvas');
            finalCanvas.width = 400;
            finalCanvas.height = 400;
            const ctx = finalCanvas.getContext('2d');
            ctx.drawImage(videoElement, x, y, width, height, 0, 0, 400, 400);
            resolve(finalCanvas.toDataURL('image/jpeg', 0.95));
        } else {
            const videoWidth = videoElement.videoWidth;
            const videoHeight = videoElement.videoHeight;
            const size = Math.min(videoWidth, videoHeight) * 0.5;
            const x = (videoWidth - size) / 2;
            const y = (videoHeight - size) / 2;
            
            const finalCanvas = document.createElement('canvas');
            finalCanvas.width = 400;
            finalCanvas.height = 400;
            const ctx = finalCanvas.getContext('2d');
            ctx.drawImage(videoElement, x, y, size, size, 0, 0, 400, 400);
            resolve(finalCanvas.toDataURL('image/jpeg', 0.95));
        }
    });
}

// Abrir câmera
abrirCameraBtn.addEventListener('click', async function() {
    cameraModal.style.display = 'flex';
    
    try {
        const constraints = {
            video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' }
        };
        
        stream = await navigator.mediaDevices.getUserMedia(constraints);
        video.srcObject = stream;
        
        await new Promise((resolve) => {
            video.onloadedmetadata = () => { video.play(); resolve(); };
        });
        
        if (!modeloCarregado) {
            modeloCarregado = await carregarModeloFacial();
        }
        
        const overlay = criarOverlay();
        iniciarDeteccaoContínua(video, overlay);
        
    } catch (err) {
        alert('Erro ao acessar a câmera: ' + err.message);
        fecharCamera();
    }
});

// Capturar foto
capturarBtn.addEventListener('click', async function() {
    if (!video.videoWidth || !video.videoHeight) {
        alert('Aguarde a câmera carregar');
        return;
    }
    
    capturarBtn.disabled = true;
    capturarBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
    
    try {
        const fotoBase64 = await cortarApenasRosto(video);
        fotoPreview.innerHTML = `<img src="${fotoBase64}" alt="Foto do Rosto" style="object-fit: cover;">`;
        fotoBase64Input.value = fotoBase64;
        fotoCapturada = true;
        removerFotoBtn.style.display = 'inline-flex';
        
        alert('✓ Foto capturada com sucesso! O rosto foi centralizado para melhor reconhecimento.');
        
        fecharCamera();
    } catch (err) {
        console.error('Erro:', err);
        alert('Erro ao processar a foto. Tente novamente.');
    } finally {
        capturarBtn.disabled = false;
        capturarBtn.innerHTML = '📸 Capturar Foto do Rosto';
    }
});

// Fechar câmera
function fecharCamera() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    cameraModal.style.display = 'none';
    video.srcObject = null;
    const overlay = document.getElementById('faceOverlay');
    if (overlay) overlay.remove();
}

fecharBtn.addEventListener('click', fecharCamera);

// Remover foto
removerFotoBtn.addEventListener('click', function() {
    fotoPreview.innerHTML = '<i class="fas fa-user-circle"></i>';
    fotoBase64Input.value = '';
    fotoCapturada = false;
    removerFotoBtn.style.display = 'none';
    document.getElementById('fotoInput').value = '';
});

// Upload de arquivo
document.getElementById('fotoInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            fotoPreview.innerHTML = `<img src="${event.target.result}" alt="Preview">`;
            fotoBase64Input.value = event.target.result;
            fotoCapturada = true;
            removerFotoBtn.style.display = 'inline-flex';
        };
        reader.readAsDataURL(file);
    }
});

// Máscaras
document.getElementById('cpf')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        e.target.value = value;
    }
});

document.getElementById('telefone')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 10) {
        value = value.replace(/(\d{2})(\d)/, '($1) $2');
        value = value.replace(/(\d{4})(\d)/, '$1-$2');
        e.target.value = value;
    } else if (value.length <= 11) {
        value = value.replace(/(\d{2})(\d)/, '($1) $2');
        value = value.replace(/(\d{5})(\d)/, '$1-$2');
        e.target.value = value;
    }
});

document.getElementById('celular')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        value = value.replace(/(\d{2})(\d)/, '($1) $2');
        value = value.replace(/(\d{5})(\d)/, '$1-$2');
        e.target.value = value;
    }
});

document.getElementById('cep')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 8) {
        value = value.replace(/^(\d{5})(\d)/, '$1-$2');
        e.target.value = value;
    }
});

// Buscar CEP
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
            alert('Erro ao buscar CEP. Tente novamente.');
        })
        .finally(() => {
            btn.innerHTML = btnText;
            btn.disabled = false;
        });
});

// Filtrar departamentos
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