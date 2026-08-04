<?php
// modules/usuarios/salvar.php - Salvar Usuário (CORRIGIDO)
require_once '../../config/database.php';
require_once '../../includes/auth.php';

session_start();
if ($_SESSION['usuario_tipo'] !== 'super_admin') {
    header('Location: /index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$id = $_POST['id'] ?? 0;
$nome = $_POST['nome'] ?? '';
$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';
$tipo = $_POST['tipo'] ?? 'gestor';
$empresa_id = $_POST['empresa_id'] ?? null;
$status = $_POST['status'] ?? 'ativo';

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
