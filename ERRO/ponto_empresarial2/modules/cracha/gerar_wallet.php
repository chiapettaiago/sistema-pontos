<?php
// modules/cracha/gerar_wallet.php - Adicionar crachá à carteira digital
session_start();

if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$funcionario_id = $_SESSION['funcionario_id'] ?? $_GET['id'] ?? 0;

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Buscar dados do funcionário
$stmt = $db->prepare("SELECT f.*, e.nome_empresa, fi.nome_fantasia as filial_nome 
                      FROM funcionarios f
                      LEFT JOIN empresa e ON f.empresa_id = e.id
                      LEFT JOIN filiais fi ON f.filial_id = fi.id
                      WHERE f.id = :id");
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    die('Funcionário não encontrado');
}

// Gerar URL para validação
$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocolo . '://' . $host . '/ponto_empresarial';

$dados_qr = [
    'id' => $funcionario['matricula'],
    'nome' => $funcionario['nome'],
    'matricula' => $funcionario['matricula'],
    'cpf' => $funcionario['cpf'],
    'empresa' => $funcionario['empresa_nome'],
    'filial' => $funcionario['filial_nome'],
    'foto' => $funcionario['foto'],
    'valido_ate' => date('Y-m-d', strtotime('+1 year'))
];

$url_validacao = $base_url . '/validar_cracha.php?data=' . urlencode(json_encode($dados_qr));

// CPF formatado
$cpf_formatado = '';
if (!empty($funcionario['cpf'])) {
    $cpf_limpo = preg_replace('/[^0-9]/', '', $funcionario['cpf']);
    if (strlen($cpf_limpo) == 11) {
        $cpf_formatado = substr($cpf_limpo, 0, 3) . '.' . substr($cpf_limpo, 3, 3) . '.' . substr($cpf_limpo, 6, 3) . '-' . substr($cpf_limpo, 9, 2);
    }
}

// Data de validade
$data_validade = date('d/m/Y', strtotime('+1 year'));
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Adicionar à Carteira - PontoFácil</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 30px 20px;
            text-align: center;
            color: white;
        }
        
        .header i {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .body {
            padding: 24px;
        }
        
        .crachá-preview {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: center;
            border: 1px solid #e5e7eb;
        }
        
        .crachá-foto {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .crachá-foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .crachá-foto i {
            font-size: 35px;
            color: white;
        }
        
        .crachá-info {
            flex: 1;
        }
        
        .crachá-info h3 {
            font-size: 16px;
            margin-bottom: 4px;
        }
        
        .crachá-info p {
            font-size: 12px;
            color: #6b7280;
        }
        
        .wallet-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .btn-wallet {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 16px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
        }
        
        .btn-ios {
            background: #000;
            color: white;
        }
        
        .btn-ios:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .btn-android {
            background: #34a853;
            color: white;
        }
        
        .btn-android:hover {
            background: #2e8b57;
            transform: translateY(-2px);
        }
        
        .btn-google-pass {
            background: #4285f4;
            color: white;
        }
        
        .btn-google-pass:hover {
            background: #3367d6;
            transform: translateY(-2px);
        }
        
        .qr-section {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 20px;
            margin-bottom: 24px;
        }
        
        .qr-section img {
            width: 180px;
            height: 180px;
            margin-bottom: 12px;
            border-radius: 16px;
        }
        
        .qr-section p {
            font-size: 12px;
            color: #6b7280;
        }
        
        .instrucoes {
            background: #e0e7ff;
            border-radius: 16px;
            padding: 16px;
            margin-top: 16px;
        }
        
        .instrucoes h4 {
            font-size: 14px;
            margin-bottom: 12px;
            color: #1e40af;
        }
        
        .instrucoes ul {
            padding-left: 20px;
        }
        
        .instrucoes li {
            font-size: 13px;
            margin-bottom: 8px;
            color: #1e3a8a;
        }
        
        .btn-voltar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            background: #e5e7eb;
            color: #374151;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            margin-top: 16px;
        }
        
        .info {
            background: #fed7aa;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #c2410c;
        }
        
        @media (max-width: 480px) {
            .wallet-buttons {
                gap: 10px;
            }
            
            .btn-wallet {
                padding: 14px;
                font-size: 14px;
            }
            
            .crachá-preview {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <i class="fas fa-mobile-alt"></i>
                <h1>Carteira Digital</h1>
                <p>Adicione seu crachá à carteira do celular</p>
            </div>
            
            <div class="body">
                <div class="info">
                    <i class="fas fa-info-circle"></i>
                    <div>Tenha seu crachá sempre à mão, mesmo sem internet!</div>
                </div>
                
                <!-- Preview do Crachá -->
                <div class="crachá-preview">
                    <div class="crachá-foto">
                        <?php if (!empty($funcionario['foto']) && file_exists('../../' . $funcionario['foto'])): ?>
                            <img src="../../<?php echo $funcionario['foto']; ?>" alt="Foto">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                    </div>
                    <div class="crachá-info">
                        <h3><?php echo htmlspecialchars($funcionario['nome']); ?></h3>
                        <p>Matrícula: <?php echo htmlspecialchars($funcionario['matricula']); ?></p>
                        <p>CPF: <?php echo $cpf_formatado; ?></p>
                        <p>Válido até: <?php echo $data_validade; ?></p>
                    </div>
                </div>
                
                <!-- Botões para Carteira -->
                <div class="wallet-buttons">
                    <button class="btn-wallet btn-ios" id="btnIOS" onclick="mostrarInstrucoes('ios')">
                        <i class="fab fa-apple"></i>
                        Adicionar à Apple Wallet
                    </button>
                    <button class="btn-wallet btn-android" id="btnAndroid" onclick="mostrarInstrucoes('android')">
                        <i class="fab fa-android"></i>
                        Adicionar à Google Wallet
                    </button>
                </div>
                
                <!-- QR Code para escanear -->
                <div class="qr-section" id="qrSection">
                    <img id="qrCode" src="https://quickchart.io/qr?text=<?php echo urlencode($url_validacao); ?>&size=180&margin=2" alt="QR Code">
                    <p><i class="fas fa-qrcode"></i> Escaneie com o celular</p>
                </div>
                
                <!-- Instruções dinâmicas -->
                <div id="instrucoes" class="instrucoes">
                    <h4><i class="fas fa-mobile-alt"></i> Como adicionar:</h4>
                    <ul>
                        <li>1. Abra a câmera do seu celular</li>
                        <li>2. Aponte para o QR Code acima</li>
                        <li>3. Toque na notificação que aparecer</li>
                        <li>4. Siga as instruções para salvar</li>
                        <li>5. Acesse seu crachá direto da carteira!</li>
                    </ul>
                </div>
                
                <a href="meu_cracha.php" class="btn-voltar">
                    <i class="fas fa-arrow-left"></i> Voltar ao Crachá
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Detectar dispositivo automaticamente
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        const isAndroid = /Android/.test(navigator.userAgent);
        
        // Função para mostrar instruções específicas
        function mostrarInstrucoes(tipo) {
            const instrucoesDiv = document.getElementById('instrucoes');
            
            if (tipo === 'ios') {
                instrucoesDiv.innerHTML = `
                    <h4><i class="fab fa-apple"></i> Adicionar à Apple Wallet:</h4>
                    <ul>
                        <li>1. Abra a câmera do iPhone</li>
                        <li>2. Aponte para o QR Code acima</li>
                        <li>3. Toque na notificação "Adicionar à Wallet"</li>
                        <li>4. Confirme a adição</li>
                        <li>5. O crachá estará disponível na sua Apple Wallet!</li>
                    </ul>
                    <p style="margin-top: 12px; font-size: 12px;">
                        <i class="fas fa-check-circle"></i> Após adicionar, você pode acessar o crachá mesmo sem internet.
                    </p>
                `;
            } else if (tipo === 'android') {
                instrucoesDiv.innerHTML = `
                    <h4><i class="fab fa-android"></i> Adicionar ao Google Wallet:</h4>
                    <ul>
                        <li>1. Abra o Google Lens ou leitor de QR Code</li>
                        <li>2. Escaneie o QR Code acima</li>
                        <li>3. Toque no link que aparecer</li>
                        <li>4. Selecione "Salvar na carteira"</li>
                        <li>5. O crachá estará disponível no Google Wallet!</li>
                    </ul>
                    <p style="margin-top: 12px; font-size: 12px;">
                        <i class="fas fa-check-circle"></i> Após adicionar, você pode acessar o crachá rapidamente.
                    </p>
                `;
            }
            
            // Rolar para as instruções
            instrucoesDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        // Detectar dispositivo automaticamente e mostrar instrução específica
        if (isIOS) {
            document.getElementById('btnIOS').style.display = 'flex';
            document.getElementById('btnAndroid').style.display = 'flex';
            mostrarInstrucoes('ios');
        } else if (isAndroid) {
            document.getElementById('btnIOS').style.display = 'flex';
            document.getElementById('btnAndroid').style.display = 'flex';
            mostrarInstrucoes('android');
        }
    </script>
</body>
</html>