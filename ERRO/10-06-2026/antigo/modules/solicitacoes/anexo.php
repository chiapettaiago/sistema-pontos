<?php
// modules/solicitacoes/anexo.php - Upload de anexos para solicitações
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$solicitacao_id = $_GET['id'] ?? 0;
$funcionario_id = $_SESSION['funcionario_id'] ?? null;

if (!$solicitacao_id) {
    header('Location: index.php');
    exit;
}

// Processar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['anexo'])) {
    $file = $_FILES['anexo'];
    $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    $extensao = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extensao, $extensoes_permitidas)) {
        $_SESSION['mensagem'] = 'Tipo de arquivo não permitido';
        $_SESSION['tipo_mensagem'] = 'error';
        header("Location: visualizar.php?id=$solicitacao_id");
        exit;
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        $_SESSION['mensagem'] = 'Arquivo muito grande. Máximo 5MB';
        $_SESSION['tipo_mensagem'] = 'error';
        header("Location: visualizar.php?id=$solicitacao_id");
        exit;
    }
    
    $uploadDir = '../../uploads/solicitacoes/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $nomeArquivo = $solicitacao_id . '_' . time() . '.' . $extensao;
    $caminho = $uploadDir . $nomeArquivo;
    
    if (move_uploaded_file($file['tmp_name'], $caminho)) {
        $stmt = $db->prepare("UPDATE solicitacoes SET anexo = :anexo WHERE id = :id");
        $stmt->execute([':anexo' => 'uploads/solicitacoes/' . $nomeArquivo, ':id' => $solicitacao_id]);
        
        $_SESSION['mensagem'] = 'Anexo enviado com sucesso!';
        $_SESSION['tipo_mensagem'] = 'success';
    } else {
        $_SESSION['mensagem'] = 'Erro ao fazer upload';
        $_SESSION['tipo_mensagem'] = 'error';
    }
    
    header("Location: visualizar.php?id=$solicitacao_id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Anexar Arquivo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea, #764ba2);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .upload-container {
            background: white;
            border-radius: 24px;
            padding: 32px;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        .upload-container h2 {
            margin-bottom: 20px;
        }
        .drop-area {
            border: 2px dashed #ccc;
            border-radius: 20px;
            padding: 40px;
            margin: 20px 0;
            cursor: pointer;
            transition: all 0.3s;
        }
        .drop-area:hover {
            border-color: #667eea;
            background: #f3f4f6;
        }
        .drop-area i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 16px;
        }
        .btn-upload {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-voltar {
            background: #e5e5e5;
            color: #333;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 12px;
            display: inline-block;
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <div class="upload-container">
        <h2><i class="fas fa-paperclip"></i> Anexar Arquivo</h2>
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="drop-area" id="dropArea">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Arraste ou clique para selecionar um arquivo</p>
                <small>Formatos: JPG, PNG, PDF, DOC (Max 5MB)</small>
                <input type="file" name="anexo" id="fileInput" style="display: none;">
            </div>
            <button type="submit" class="btn-upload">Enviar Anexo</button>
        </form>
        <a href="visualizar.php?id=<?php echo $solicitacao_id; ?>" class="btn-voltar">Voltar</a>
    </div>
    
    <script>
        const dropArea = document.getElementById('dropArea');
        const fileInput = document.getElementById('fileInput');
        
        dropArea.addEventListener('click', () => fileInput.click());
        dropArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropArea.style.borderColor = '#667eea';
        });
        dropArea.addEventListener('dragleave', () => {
            dropArea.style.borderColor = '#ccc';
        });
        dropArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileInput.files = e.dataTransfer.files;
            dropArea.style.borderColor = '#ccc';
        });
        
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                document.getElementById('uploadForm').submit();
            }
        });
    </script>
</body>
</html>