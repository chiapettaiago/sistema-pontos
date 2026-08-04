<?php
// modules/cracha/gerar.php - Geração do PDF do Crachá (CORRIGIDO - SESSÃO)
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Iniciar sessão APENAS se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar permissão
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;

// Buscar dados do funcionário
$query = "SELECT f.*, fil.nome_fantasia as filial_nome, c.nome as cargo_nome
          FROM funcionarios f
          LEFT JOIN filiais fil ON f.filial_id = fil.id
          LEFT JOIN cargos c ON f.cargo_id = c.id
          WHERE f.id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    die('Funcionário não encontrado');
}

// Função para gerar QR Code
function gerarQRCode($texto) {
    $tamanho = 150;
    $url = "https://quickchart.io/qr?text=" . urlencode($texto) . "&size={$tamanho}";
    return $url;
}

// Dados para o QR Code
$qrData = "ID: " . $funcionario['matricula'] . "\n";
$qrData .= "Nome: " . $funcionario['nome'] . "\n";
$qrData .= "Filial: " . $funcionario['filial_nome'] . "\n";
$qrData .= "Valido ate: " . date('d/m/Y', strtotime('+1 year'));

$qrCodeUrl = gerarQRCode($qrData);

// Função para converter imagem para base64 COM VERIFICAÇÃO
function imagemBase64($caminho) {
    if (empty($caminho)) {
        return null;
    }
    
    $caminhoCompleto = '../../' . $caminho;
    
    if (file_exists($caminhoCompleto)) {
        $tipo = pathinfo($caminhoCompleto, PATHINFO_EXTENSION);
        $dados = file_get_contents($caminhoCompleto);
        return 'data:image/' . $tipo . ';base64,' . base64_encode($dados);
    }
    return null;
}

// Foto do funcionário
$fotoBase64 = null;
if (!empty($funcionario['foto']) && file_exists('../../' . $funcionario['foto'])) {
    $fotoBase64 = imagemBase64($funcionario['foto']);
}

$noPhotoText = strtoupper(substr($funcionario['nome'], 0, 1));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Crachá - <?php echo htmlspecialchars($funcionario['nome']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            background: #e5e7eb;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .cracha-container {
            width: 400px;
            margin: 0 auto;
        }
        
        .cracha {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.3);
            position: relative;
        }
        
        .cracha-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            text-align: center;
            color: white;
        }
        
        .cracha-logo {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 16px;
        }
        
        .cracha-logo i {
            font-size: 32px;
            margin-right: 8px;
        }
        
        .cracha-titulo {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .cracha-foto-area {
            text-align: center;
            margin-top: -40px;
            margin-bottom: 16px;
        }
        
        .cracha-foto {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto;
            overflow: hidden;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .cracha-foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cracha-foto .no-photo {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: bold;
        }
        
        .cracha-body {
            padding: 20px;
        }
        
        .cracha-nome {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .cracha-nome h2 {
            font-size: 18px;
            color: #1f2937;
            margin-bottom: 4px;
        }
        
        .cracha-nome p {
            font-size: 12px;
            color: #6b7280;
        }
        
        .cracha-info {
            margin-bottom: 20px;
        }
        
        .cracha-info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .cracha-info-label {
            font-size: 11px;
            color: #6b7280;
        }
        
        .cracha-info-value {
            font-size: 12px;
            font-weight: 600;
            color: #1f2937;
        }
        
        .cracha-qrcode {
            text-align: center;
            padding: 16px;
            background: #f9fafb;
            border-radius: 12px;
            margin-top: 16px;
        }
        
        .cracha-qrcode img {
            width: 100px;
            height: 100px;
            margin-bottom: 8px;
        }
        
        .cracha-qrcode p {
            font-size: 10px;
            color: #6b7280;
        }
        
        .cracha-footer {
            background: #f3f4f6;
            padding: 12px;
            text-align: center;
            font-size: 9px;
            color: #6b7280;
        }
        
        .action-buttons {
            margin-top: 20px;
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        
        .btn-print, .btn-back {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-back {
            background: #e5e7eb;
            color: #374151;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            
            .action-buttons {
                display: none;
            }
            
            .cracha-container {
                width: 100%;
                margin: 0;
            }
            
            .cracha {
                box-shadow: none;
                border: 1px solid #e5e7eb;
            }
        }
    </style>
</head>
<body>
    <div class="cracha-container">
        <div class="cracha">
            <div class="cracha-header">
                <div class="cracha-logo">
                    <i class="fas fa-clock"></i> PontoFácil
                </div>
                <div class="cracha-titulo">IDENTIFICAÇÃO FUNCIONAL</div>
            </div>
            
            <div class="cracha-foto-area">
                <div class="cracha-foto">
                    <?php if ($fotoBase64): ?>
                        <img src="<?php echo $fotoBase64; ?>" alt="Foto de <?php echo htmlspecialchars($funcionario['nome']); ?>">
                    <?php else: ?>
                        <div class="no-photo">
                            <?php echo $noPhotoText; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="cracha-body">
                <div class="cracha-nome">
                    <h2><?php echo htmlspecialchars($funcionario['nome']); ?></h2>
                    <p><?php echo htmlspecialchars($funcionario['cargo_nome'] ?? 'Colaborador'); ?></p>
                </div>
                
                <div class="cracha-info">
                    <div class="cracha-info-item">
                        <span class="cracha-info-label">MATRÍCULA</span>
                        <span class="cracha-info-value"><?php echo htmlspecialchars($funcionario['matricula']); ?></span>
                    </div>
                    <div class="cracha-info-item">
                        <span class="cracha-info-label">FILIAL</span>
                        <span class="cracha-info-value"><?php echo htmlspecialchars($funcionario['filial_nome']); ?></span>
                    </div>
                    <div class="cracha-info-item">
                        <span class="cracha-info-label">E-MAIL</span>
                        <span class="cracha-info-value"><?php echo htmlspecialchars($funcionario['email']); ?></span>
                    </div>
                    <div class="cracha-info-item">
                        <span class="cracha-info-label">DATA ADMISSÃO</span>
                        <span class="cracha-info-value"><?php echo date('d/m/Y', strtotime($funcionario['data_admissao'])); ?></span>
                    </div>
                </div>
                
                <div class="cracha-qrcode">
                    <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code">
                    <p>Escanear para validar identificação</p>
                </div>
            </div>
            
            <div class="cracha-footer">
                Este documento é de uso exclusivo do funcionário<br>
                Em caso de perda, comunicar imediatamente o RH
            </div>
        </div>
        
        <div class="action-buttons">
            <button onclick="window.print()" class="btn-print">
                <i class="fas fa-print"></i> Imprimir / Salvar PDF
            </button>
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>