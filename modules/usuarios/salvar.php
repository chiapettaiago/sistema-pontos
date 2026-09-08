<?php
// modules/usuarios/salvar.php - Salvar Usuário (CORRIGIDO)
require_once '../../includes/config.php';
require_once '../../config/database.php';

$usuarioTipoAtual = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuarioTipoAtual, ['super_admin', 'admin_empresa'], true)) {
    header('Location: ' . appUrl(appHomeRouteFor($usuarioTipoAtual)));
    exit;
}

$database = new Database();
$db = $database->getConnection();

$id = $_POST['id'] ?? 0;
$nome = $_POST['nome'] ?? '';
$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';
$tipo = $_POST['tipo'] ?? 'gestor';
$empresa_id = $usuarioTipoAtual === 'admin_empresa'
    ? ($_SESSION['empresa_id'] ?? null)
    : ($_POST['empresa_id'] ?? null);
$status = $_POST['status'] ?? 'ativo';

if (!$empresa_id || !in_array($tipo, ['admin_empresa', 'gestor', 'supervisor'], true)) {
    header('Location: index.php?error=' . urlencode('Empresa ou tipo de usuário inválido'));
    exit;
}

if (empty($nome) || empty($email)) {
    header('Location: index.php?error=Campos obrigatórios');
    exit;
}

// Validar e-mail único
$check = $db->prepare("SELECT id FROM usuarios_sistema WHERE email = :email AND id != :id");
$check->execute([':email' => $email, ':id' => $id]);
if ($check->fetch()) {
    header('Location: index.php?error=E-mail já cadastrado');
    exit;
}

if ($id && $usuarioTipoAtual === 'admin_empresa') {
    $checkScope = $db->prepare('SELECT id FROM usuarios_sistema WHERE id = :id AND empresa_id = :empresa_id');
    $checkScope->execute([':id' => $id, ':empresa_id' => $empresa_id]);
    if (!$checkScope->fetch()) {
        header('Location: index.php?error=' . urlencode('Usuário não pertence à sua empresa'));
        exit;
    }
}

try {
    if ($id) {
        // Atualizar usuário existente
        if (!empty($senha)) {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $query = "UPDATE usuarios_sistema SET 
                      nome = :nome, 
                      email = :email, 
                      senha = :senha, 
                      tipo = :tipo,
                      empresa_id = :empresa_id, 
                      status = :status
                      WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':nome' => $nome, 
                ':email' => $email, 
                ':senha' => $senha_hash,
                ':tipo' => $tipo, 
                ':empresa_id' => $empresa_id, 
                ':status' => $status, 
                ':id' => $id
            ]);
        } else {
            $query = "UPDATE usuarios_sistema SET 
                      nome = :nome, 
                      email = :email, 
                      tipo = :tipo,
                      empresa_id = :empresa_id, 
                      status = :status
                      WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':nome' => $nome, 
                ':email' => $email,
                ':tipo' => $tipo, 
                ':empresa_id' => $empresa_id, 
                ':status' => $status, 
                ':id' => $id
            ]);
        }
        header('Location: index.php?success=Usuário atualizado com sucesso');
    } else {
        // Criar novo usuário
        if (empty($senha)) {
            header('Location: index.php?error=Senha é obrigatória para novo usuário');
            exit;
        }
        
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $query = "INSERT INTO usuarios_sistema (nome, email, senha, tipo, empresa_id, status) 
                  VALUES (:nome, :email, :senha, :tipo, :empresa_id, :status)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':nome' => $nome, 
            ':email' => $email, 
            ':senha' => $senha_hash,
            ':tipo' => $tipo, 
            ':empresa_id' => $empresa_id, 
            ':status' => $status
        ]);
        header('Location: index.php?success=Usuário criado com sucesso');
    }
} catch (Exception $e) {
    header('Location: index.php?error=' . urlencode($e->getMessage()));
}
exit;
?>
