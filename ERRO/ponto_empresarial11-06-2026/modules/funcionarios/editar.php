<?php
// modules/funcionarios/editar.php - Editar Funcionário (COM CAPTURA FACIAL)
$pageTitle = 'Editar Funcionário';
$activePage = 'funcionarios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

checkModuleAccess('funcionarios');

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;
$redirectAfterSave = null;

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

// Buscar filiais
if ($_SESSION['usuario_tipo'] === 'super_admin' || $_SESSION['usuario_tipo'] === 'admin_empresa') {
    $empresa_id = getCurrentEmpresaId() ?: 1;
    $stmt = $db->prepare("SELECT id, nome_fantasia FROM filiais WHERE empresa_id = :empresa_id AND ativo = 1 ORDER BY nome_fantasia");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $filiais = $stmt->fetchAll();
} else {
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

// Funções auxiliares
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

function formatarCPFEdit($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) == 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

// Função para salvar foto base64
function salvarFotoBase64Edit($base64, $matricula) {
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
    
    // Redimensionar para 400x400 (centralizando o rosto)
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

// Função para upload tradicional
function uploadFotoEdit($file, $matricula) {
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
    
    move_uploaded_file($file['tmp_name'], $caminhoCompleto);
    
    // Redimensionar
    $img = null;
    switch ($extensao) {
        case 'jpg': case 'jpeg': $img = imagecreatefromjpeg($caminhoCompleto); break;
        case 'png': $img = imagecreatefrompng($caminhoCompleto); break;
        case 'gif': $img = imagecreatefromgif($caminhoCompleto); break;
        case 'webp': $img = imagecreatefromwebp($caminhoCompleto); break;
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
    
    return ['success' => 'uploads/funcionarios/' . $nomeArquivo];
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
    
    $foto_base64 = $_POST['foto_base64'] ?? '';
    
    $errors = [];
    
    if (empty($matricula)) $errors[] = 'Matrícula é obrigatória';
    if (empty($nome)) $errors[] = 'Nome é obrigatório';
    if (empty($email)) $errors[] = 'E-mail é obrigatório';
    
    if (empty($cpf)) {
        $errors[] = 'CPF é obrigatório';
    } elseif (!validarCPF($cpf)) {
        $errors[] = 'CPF inválido';
    } else {
        $cpf_formatado = formatarCPFEdit($cpf);
        $check = $db->prepare("SELECT id FROM funcionarios WHERE cpf = :cpf AND empresa_id = :empresa_id AND id != :id");
        $check->execute([':cpf' => $cpf_formatado, ':empresa_id' => $empresa_id, ':id' => $id]);
        if ($check->fetch()) {
            $errors[] = 'CPF já cadastrado para outro funcionário';
        }
    }
    
    $check = $db->prepare("SELECT id FROM funcionarios WHERE email = :email AND empresa_id = :empresa_id AND id != :id");
    $check->execute([':email' => $email, ':empresa_id' => $empresa_id, ':id' => $id]);
    if ($check->fetch()) {
        $errors[] = 'E-mail já cadastrado para outro funcionário';
    }
    
    $check = $db->prepare("SELECT id FROM funcionarios WHERE matricula = :matricula AND empresa_id = :empresa_id AND id != :id");
    $check->execute([':matricula' => $matricula, ':empresa_id' => $empresa_id, ':id' => $id]);
    if ($check->fetch()) {
        $errors[] = 'Matrícula já cadastrada para outro funcionário';
    }
    
    // Processar foto
    $foto_path = $funcionario['foto'];
    $abrir_facial_pos_salvar = isset($_POST['abrir_facial_pos_salvar']) && $_POST['abrir_facial_pos_salvar'] == '1';
    
    if ($foto_base64) {
        $foto_path = salvarFotoBase64Edit($foto_base64, $matricula);
        if ($funcionario['foto'] && file_exists('../../' . $funcionario['foto'])) {
            unlink('../../' . $funcionario['foto']);
        }
    } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        if ($funcionario['foto'] && file_exists('../../' . $funcionario['foto'])) {
            unlink('../../' . $funcionario['foto']);
        }
        $uploadResult = uploadFotoEdit($_FILES['foto'], $matricula);
        if (isset($uploadResult['error'])) {
            $errors[] = $uploadResult['error'];
        } else {
            $foto_path = $uploadResult['success'];
        }
    }
    
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
                ':cep' => $cep,
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
            
            $db->prepare("DELETE FROM funcionario_jornada WHERE funcionario_id = :id AND (data_fim IS NULL OR data_fim >= CURDATE())")->execute([':id' => $id]);
            
            if ($jornada_id) {
                $stmt = $db->prepare("INSERT INTO funcionario_jornada (funcionario_id, jornada_id, data_inicio) VALUES (:funcionario_id, :jornada_id, :data_inicio)");
                $stmt->execute([
                    ':funcionario_id' => $id,
                    ':jornada_id' => $jornada_id,
                    ':data_inicio' => $data_admissao
                ]);
            }
            
            logAcao($db, 'UPDATE', 'funcionarios', $id, "Editou funcionário: $nome");

            if ($abrir_facial_pos_salvar && !empty($foto_path)) {
                $_SESSION['mensagem_biometria'] = 'Foto atualizada com sucesso. Agora vamos cadastrar/atualizar a biometria facial.';
                $redirectAfterSave = '../biometrico/facial.php?id=' . $id;
            }
            
            $success = 'Funcionário atualizado com sucesso!';
            
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
.form-group input, .form-group select {
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    font-size: 14px;
    background: var(--bg-primary);
    color: var(--text-primary);
    transition: all 0.3s;
}
.form-group input:focus, .form-group select:focus {
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

/* Estilos da Foto */
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
            <h3><i class="fas fa-edit"></i> Editar Funcionário: <?php echo htmlspecialchars($funcionario['nome']); ?></h3>
            <p>Edite os dados e atualize a foto do rosto para reconhecimento facial</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="form-main" enctype="multipart/form-data" id="editarForm">
            <!-- Foto com Câmera -->
            <div class="form-section">
                <h4><i class="fas fa-camera"></i> Foto do Funcionário (para reconhecimento facial)</h4>
                <div class="foto-container">
                    <div class="foto-preview" id="fotoPreview">
                        <?php if ($funcionario['foto'] && file_exists('../../' . $funcionario['foto'])): ?>
                            <img src="../../<?php echo $funcionario['foto']; ?>?t=<?php echo time(); ?>" alt="Foto do Rosto" style="object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                    </div>
                    <div class="foto-buttons">
                        <button type="button" class="btn-foto btn-camera" id="abrirCameraBtn">
                            <i class="fas fa-camera"></i> Tirar Foto do Rosto
                        </button>
                        <label class="btn-foto btn-upload">
                            <i class="fas fa-upload"></i> Upload de Arquivo
                            <input type="file" name="foto" id="fotoInput" accept="image/*" class="file-input">
                        </label>
                        <?php if ($funcionario['foto']): ?>
                            <button type="button" class="btn-foto btn-remover" id="removerFotoBtn">
                                <i class="fas fa-trash-alt"></i> Remover Foto
                            </button>
                        <?php endif; ?>
                    </div>
                    <label style="display:flex;align-items:center;gap:10px;margin-top:12px;font-size:13px;color:var(--text-secondary);">
                        <input type="checkbox" name="abrir_facial_pos_salvar" value="1" style="width:auto;min-height:auto;">
                        Salvar foto e abrir o cadastro facial em seguida
                    </label>
                    <input type="hidden" name="foto_base64" id="foto_base64">
                    <input type="hidden" name="remover_foto" id="remover_foto_input" value="0">
                    <small><i class="fas fa-info-circle"></i> Centralize o rosto no círculo. O sistema focará nos olhos para melhor reconhecimento.</small>
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
                        <div class="cep-row">
                            <input type="text" name="cep" id="cep" value="<?php echo htmlspecialchars($funcionario['cep'] ?? ''); ?>">
                            <button type="button" id="buscarCep" class="btn btn-secondary">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Endereço</label>
                        <input type="text" name="endereco" id="endereco" value="<?php echo htmlspecialchars($funcionario['endereco'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" name="numero" id="numero" value="<?php echo htmlspecialchars($funcionario['numero'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text" name="complemento" id="complemento" value="<?php echo htmlspecialchars($funcionario['complemento'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Bairro</label>
                        <input type="text" name="bairro" id="bairro" value="<?php echo htmlspecialchars($funcionario['bairro'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Cidade</label>
                        <input type="text" name="cidade" id="cidade" value="<?php echo htmlspecialchars($funcionario['cidade'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado" id="estado">
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
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Atenção:</strong> A foto do rosto será usada para validação no reconhecimento facial.
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
        <div id="statusDetect" class="status-detect">🔄 Aguardando câmera...</div>
    </div>
</div>

<script>
// ============================================
// SISTEMA DE CAPTURA FACIAL - EDITAR
// ============================================

let stream = null;
let fotoCapturada = false;

// Elementos
const fotoPreview = document.getElementById('fotoPreview');
const fotoBase64Input = document.getElementById('foto_base64');
const removerFotoBtn = document.getElementById('removerFotoBtn');
const removerFotoInput = document.getElementById('remover_foto_input');
const abrirCameraBtn = document.getElementById('abrirCameraBtn');
const cameraModal = document.getElementById('cameraModal');
const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const capturarBtn = document.getElementById('capturarFoto');
const fecharBtn = document.getElementById('fecharCamera');
const statusDetect = document.getElementById('statusDetect');

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

// Desenhar guia facial
function desenharGuia(overlay, videoElement) {
    if (!overlay || !videoElement) return;
    
    const rect = videoElement.getBoundingClientRect();
    if (rect.width === 0) return;
    
    overlay.width = rect.width;
    overlay.height = rect.height;
    const ctx = overlay.getContext('2d');
    ctx.clearRect(0, 0, overlay.width, overlay.height);
    
    const centerX = overlay.width / 2;
    const centerY = overlay.height / 2;
    const radius = Math.min(overlay.width, overlay.height) * 0.35;
    
    // Círculo externo
    ctx.beginPath();
    ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
    ctx.strokeStyle = '#10b981';
    ctx.lineWidth = 3;
    ctx.stroke();
    
    // Círculo interno
    ctx.beginPath();
    ctx.arc(centerX, centerY, radius * 0.85, 0, 2 * Math.PI);
    ctx.strokeStyle = 'rgba(16, 185, 129, 0.5)';
    ctx.lineWidth = 1.5;
    ctx.stroke();
    
    // Pontos dos olhos
    const olhoY = centerY - radius * 0.2;
    ctx.fillStyle = '#ef4444';
    ctx.beginPath();
    ctx.arc(centerX - radius * 0.35, olhoY, 5, 0, 2 * Math.PI);
    ctx.fill();
    ctx.beginPath();
    ctx.arc(centerX + radius * 0.35, olhoY, 5, 0, 2 * Math.PI);
    ctx.fill();
    
    // Íris
    ctx.fillStyle = '#fbbf24';
    ctx.beginPath();
    ctx.arc(centerX - radius * 0.35, olhoY, 3, 0, 2 * Math.PI);
    ctx.fill();
    ctx.beginPath();
    ctx.arc(centerX + radius * 0.35, olhoY, 3, 0, 2 * Math.PI);
    ctx.fill();
    
    // Texto
    ctx.font = '14px Arial';
    ctx.fillStyle = 'white';
    ctx.shadowBlur = 4;
    ctx.fillText('Centralize seu rosto', centerX - 85, centerY - radius - 10);
    ctx.fillText('Mantenha os olhos nos pontos', centerX - 105, centerY + radius + 25);
    ctx.shadowBlur = 0;
}

// Cortar rosto da imagem capturada
function cortarRosto(videoElement) {
    return new Promise((resolve) => {
        const videoWidth = videoElement.videoWidth;
        const videoHeight = videoElement.videoHeight;
        
        // Centralizar 50% da imagem (foco no rosto)
        const cropSize = Math.min(videoWidth, videoHeight) * 0.6;
        const cropX = (videoWidth - cropSize) / 2;
        const cropY = (videoHeight - cropSize) / 2;
        
        const finalCanvas = document.createElement('canvas');
        finalCanvas.width = 400;
        finalCanvas.height = 400;
        const ctx = finalCanvas.getContext('2d');
        
        ctx.drawImage(videoElement, cropX, cropY, cropSize, cropSize, 0, 0, 400, 400);
        resolve(finalCanvas.toDataURL('image/jpeg', 0.95));
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
            video.onloadedmetadata = () => {
                video.play();
                resolve();
            };
        });
        
        if (statusDetect) {
            statusDetect.innerHTML = '✅ Câmera ativa - Centralize o rosto';
            statusDetect.style.backgroundColor = 'rgba(16, 185, 129, 0.8)';
        }
        
        const overlay = criarOverlay();
        
        function atualizarGuia() {
            if (cameraModal.style.display === 'flex' && overlay) {
                desenharGuia(overlay, video);
                requestAnimationFrame(atualizarGuia);
            }
        }
        atualizarGuia();
        
    } catch (err) {
        alert('Erro ao acessar a câmera: ' + err.message);
        if (statusDetect) {
            statusDetect.innerHTML = '❌ Erro na câmera';
            statusDetect.style.backgroundColor = 'rgba(239, 68, 68, 0.8)';
        }
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
    
    if (statusDetect) {
        statusDetect.innerHTML = '📸 Capturando foto...';
    }
    
    try {
        const fotoBase64 = await cortarRosto(video);
        
        fotoPreview.innerHTML = `<img src="${fotoBase64}" alt="Foto do Rosto" style="object-fit: cover;">`;
        fotoBase64Input.value = fotoBase64;
        fotoCapturada = true;
        if (removerFotoInput) removerFotoInput.value = '0';
        if (removerFotoBtn) removerFotoBtn.style.display = 'inline-flex';
        
        if (statusDetect) {
            statusDetect.innerHTML = '✅ Foto capturada com sucesso!';
            statusDetect.style.backgroundColor = 'rgba(16, 185, 129, 0.8)';
            setTimeout(() => {
                if (cameraModal.style.display === 'flex') {
                    statusDetect.innerHTML = 'Câmera ativa';
                }
            }, 2000);
        }
        
        fecharCamera();
        
    } catch (err) {
        console.error('Erro:', err);
        alert('Erro ao processar a foto. Tente novamente.');
        if (statusDetect) {
            statusDetect.innerHTML = '❌ Falha na captura';
            statusDetect.style.backgroundColor = 'rgba(239, 68, 68, 0.8)';
        }
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
    capturarBtn.disabled = false;
    capturarBtn.innerHTML = '📸 Capturar Foto do Rosto';
}

fecharBtn.addEventListener('click', fecharCamera);

// Remover foto
if (removerFotoBtn) {
    removerFotoBtn.addEventListener('click', function() {
        fotoPreview.innerHTML = '<i class="fas fa-user-circle"></i>';
        fotoBase64Input.value = '';
        removerFotoInput.value = '1';
        fotoCapturada = false;
        document.getElementById('fotoInput').value = '';
        removerFotoBtn.style.display = 'none';
    });
}

// Upload de arquivo
document.getElementById('fotoInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            fotoPreview.innerHTML = `<img src="${event.target.result}" alt="Preview">`;
            fotoBase64Input.value = event.target.result;
            fotoCapturada = true;
            removerFotoInput.value = '0';
            if (removerFotoBtn) removerFotoBtn.style.display = 'inline-flex';
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
        option.style.display = (optionFilial === filialId || !optionFilial) ? '' : 'none';
    });
    
    departamentoSelect.value = '';
});
</script>

<?php if (!empty($redirectAfterSave)): ?>
<script>
    setTimeout(function() {
        window.location.href = <?php echo json_encode($redirectAfterSave); ?>;
    }, 600);
</script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
