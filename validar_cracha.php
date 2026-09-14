<?php
// validar_cracha.php - Página pública de validação do QR Code assinado
require_once __DIR__ . '/config/database.php';
$dados = $_GET['data'] ?? '';

if (empty($dados)) {
    die('QR Code inválido');
}

// Decodificar os dados
$funcionario = json_decode(urldecode($dados), true);

if (!$funcionario || !isset($funcionario['matricula'], $funcionario['empresa_id'], $funcionario['exp'], $funcionario['assinatura'])) {
    die('Dados do QR Code inválidos');
}

$payloadAssinado = $funcionario['matricula'] . '|' . (int) $funcionario['empresa_id'] . '|' . (int) $funcionario['exp'];
if ((int) $funcionario['exp'] < time() || !hash_equals(hash_hmac('sha256', $payloadAssinado, APP_SIGNING_KEY), (string) $funcionario['assinatura'])) {
    http_response_code(403);
    die('Crachá inválido ou expirado');
}

$stmt = $pdo->prepare('SELECT f.matricula, f.nome, f.cpf, f.foto, fi.nome_fantasia AS filial, c.nome AS cargo, e.nome_empresa AS empresa FROM funcionarios f LEFT JOIN filiais fi ON fi.id=f.filial_id LEFT JOIN cargos c ON c.id=f.cargo_id LEFT JOIN empresa e ON e.id=f.empresa_id WHERE f.matricula=:matricula AND f.empresa_id=:empresa_id AND f.status=:status LIMIT 1');
$stmt->execute([':matricula' => $funcionario['matricula'], ':empresa_id' => (int) $funcionario['empresa_id'], ':status' => 'ativo']);
$registro = $stmt->fetch();
if (!$registro) { http_response_code(404); die('Crachá não encontrado ou inativo'); }
$funcionario = array_merge($funcionario, $registro);
$funcionario['valido_ate'] = date('Y-m-d', (int) $funcionario['exp']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validação de Crachá - PontoFácil</title>
    <link rel="stylesheet" href="assets/css/public-responsive.css">
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 32px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .valid-icon {
            width: 80px;
            height: 80px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        
        .valid-icon i {
            font-size: 40px;
            color: white;
        }
        
        h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 8px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 32px;
        }
        
        .info-card {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            text-align: left;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            font-weight: 700;
            color: #333;
        }
        
        .status-valid {
            background: #d1fae5;
            color: #059669;
            padding: 12px;
            border-radius: 12px;
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .status-invalid {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 12px;
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .footer {
            margin-top: 24px;
            font-size: 12px;
            color: #999;
        }
        
        @media (max-width: 480px) {
            .card {
                padding: 24px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .info-row {
                flex-direction: column;
                gap: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="valid-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1>Crachá Válido</h1>
        <p class="subtitle">Documento de identificação funcional</p>
        
        <div class="info-card">
            <div class="info-row">
                <span class="info-label">Funcionário:</span>
                <span class="info-value"><?php echo htmlspecialchars($funcionario['nome']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Matrícula:</span>
                <span class="info-value"><?php echo htmlspecialchars($funcionario['matricula']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">CPF:</span>
                <span class="info-value"><?php echo htmlspecialchars($funcionario['cpf']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Empresa:</span>
                <span class="info-value"><?php echo htmlspecialchars($funcionario['empresa']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Filial:</span>
                <span class="info-value"><?php echo htmlspecialchars($funcionario['filial']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Válido até:</span>
                <span class="info-value"><?php echo date('d/m/Y', strtotime($funcionario['valido_ate'])); ?></span>
            </div>
        </div>
        
        <?php
        $valido_ate = strtotime($funcionario['valido_ate']);
        $hoje = time();
        if ($valido_ate < $hoje):
        ?>
        <div class="status-invalid">
            <i class="fas fa-exclamation-triangle"></i>
            Este crachá está EXPIRADO! Emitir novo crachá.
        </div>
        <?php else: ?>
        <div class="status-valid">
            <i class="fas fa-check-circle"></i>
            Documento válido até <?php echo date('d/m/Y', strtotime($funcionario['valido_ate'])); ?>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <i class="fas fa-clock"></i> Validação em tempo real<br>
            Em caso de dúvidas, contactar o RH
        </div>
    </div>
</body>
</html>
