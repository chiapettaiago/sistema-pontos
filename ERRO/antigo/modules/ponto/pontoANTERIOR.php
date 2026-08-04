<?php
// modules/ponto/ponto.php - Área do Funcionário (VERSÃO SIMPLIFICADA)
session_start();
require_once '../../config/database.php';

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$funcionario_id = $_SESSION['funcionario_id'];
$empresa_id = $_SESSION['empresa_id'];
$filial_id = $_SESSION['filial_id'];

// Buscar dados do funcionário
$stmt = $db->prepare("SELECT * FROM funcionarios WHERE id = :id");
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

// Buscar pontos de hoje
$stmt = $db->prepare("SELECT * FROM pontos 
                      WHERE funcionario_id = :funcionario_id 
                      AND DATE(data_hora) = CURDATE() 
                      ORDER BY data_hora ASC");
$stmt->execute([':funcionario_id' => $funcionario_id]);
$pontos_hoje = $stmt->fetchAll();

// Verificar qual o próximo tipo de ponto
$tipos = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
$proximo_tipo = 'entrada';

if (count($pontos_hoje) > 0) {
    $ultimo = $pontos_hoje[count($pontos_hoje) - 1]['tipo'];
    $indice = array_search($ultimo, $tipos);
    if ($indice !== false && $indice < 3) {
        $proximo_tipo = $tipos[$indice + 1];
    } else {
        $proximo_tipo = 'finalizado';
    }
}

// Mensagem de sucesso/erro
$mensagem = '';
$tipo_mensagem = '';

if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    $tipo_mensagem = $_SESSION['tipo_mensagem'];
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bater Ponto - <?php echo htmlspecialchars($funcionario['nome']); ?></title>
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
            background: linear-gradient(135deg, #667eea, #764ba2);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 28px;
            color: #333;
        }
        .header p {
            color: #666;
            margin-top: 8px;
        }
        .info-funcionario {
            background: #f3f4f6;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        .info-funcionario h3 {
            margin-bottom: 5px;
            color: #333;
        }
        .info-funcionario p {
            color: #666;
            font-size: 14px;
        }
        .btn-ponto {
            width: 100%;
            padding: 20px;
            font-size: 22px;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        .btn-ponto:hover:not(:disabled) {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-ponto:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        .btn-justificativa {
            width: 100%;
            padding: 12px;
            background: #f59e0b;
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-justificativa:hover {
            background: #d97706;
        }
        .pontos-list {
            margin-top: 25px;
        }
        .pontos-list h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 18px;
        }
        .ponto-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #e5e5e5;
        }
        .ponto-hora {
            font-weight: bold;
            color: #667eea;
            font-size: 18px;
        }
        .ponto-tipo {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        .tipo-entrada { background: #d1fae5; color: #059669; }
        .tipo-saida_almoco { background: #fed7aa; color: #c2410c; }
        .tipo-volta_almoco { background: #bfdbfe; color: #1e40af; }
        .tipo-saida { background: #fee2e2; color: #dc2626; }
        .logout {
            text-align: center;
            margin-top: 20px;
        }
        .logout a {
            color: #666;
            text-decoration: none;
        }
        .logout a:hover {
            text-decoration: underline;
        }
        .alert {
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
        }
        .alert-success {
            background: #d1fae5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            border-radius: 24px;
            padding: 25px;
            max-width: 500px;
            width: 90%;
        }
        .modal-content h3 {
            margin-bottom: 20px;
        }
        .modal-content textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            font-family: inherit;
            resize: vertical;
            margin-bottom: 15px;
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
        }
        .modal-buttons button {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }
        .btn-salvar {
            background: #667eea;
            color: white;
        }
        .btn-cancelar {
            background: #e5e5e5;
            color: #333;
        }
        .relogio {
            font-size: 48px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>⏰ Bater Ponto</h1>
                <p>Sistema de Ponto Eletrônico</p>
            </div>
            
            <div class="relogio" id="relogio"></div>
            
            <?php if ($mensagem): ?>
                <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                    <?php echo $mensagem; ?>
                </div>
            <?php endif; ?>
            
            <div class="info-funcionario">
                <h3><i class="fas fa-user"></i> <?php echo htmlspecialchars($funcionario['nome']); ?></h3>
                <p>Matrícula: <?php echo htmlspecialchars($funcionario['matricula']); ?> | Filial: <?php echo htmlspecialchars($funcionario['filial_id']); ?></p>
                <p>Data: <?php echo date('d/m/Y'); ?></p>
            </div>
            
            <?php if ($proximo_tipo !== 'finalizado'): ?>
                <form method="POST" action="processar.php">
                    <input type="hidden" name="tipo" value="<?php echo $proximo_tipo; ?>">
                    <button type="submit" class="btn-ponto">
                        <i class="fas fa-hand-paper"></i> 
                        <?php 
                        switch($proximo_tipo) {
                            case 'entrada': echo 'REGISTRAR ENTRADA'; break;
                            case 'saida_almoco': echo 'REGISTRAR SAÍDA PARA ALMOÇO'; break;
                            case 'volta_almoco': echo 'REGISTRAR VOLTA DO ALMOÇO'; break;
                            case 'saida': echo 'REGISTRAR SAÍDA'; break;
                        }
                        ?>
                    </button>
                </form>
                
                <button class="btn-justificativa" onclick="abrirJustificativa()">
                    <i class="fas fa-pencil-alt"></i> Justificar Atraso / Falta
                </button>
            <?php else: ?>
                <button class="btn-ponto" disabled>
                    <i class="fas fa-check-circle"></i> PONTO FINALIZADO HOJE
                </button>
            <?php endif; ?>
            
            <div class="pontos-list">
                <h3><i class="fas fa-history"></i> Registros de hoje:</h3>
                <?php if (count($pontos_hoje) > 0): ?>
                    <?php foreach ($pontos_hoje as $ponto): ?>
                    <div class="ponto-item">
                        <span class="ponto-tipo tipo-<?php echo $ponto['tipo']; ?>">
                            <?php 
                            $labels = [
                                'entrada' => '✅ ENTRADA',
                                'saida_almoco' => '🍽️ SAÍDA ALMOÇO',
                                'volta_almoco' => '🔄 VOLTA ALMOÇO',
                                'saida' => '🏁 SAÍDA'
                            ];
                            echo $labels[$ponto['tipo']];
                            ?>
                        </span>
                        <span class="ponto-hora"><?php echo date('H:i:s', strtotime($ponto['data_hora'])); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #999; padding: 20px;">
                        <i class="fas fa-info-circle"></i> Nenhum ponto registrado hoje
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="logout">
                <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Sair do Sistema</a>
            </div>
        </div>
    </div>
    
    <!-- Modal Justificativa -->
    <div id="modalJustificativa" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-pencil-alt"></i> Justificativa</h3>
            <form method="POST" action="processar.php">
                <input type="hidden" name="tipo" value="justificativa">
                <textarea name="justificativa" rows="4" placeholder="Digite sua justificativa (atraso, falta, problema técnico, etc.)" required></textarea>
                <div class="modal-buttons">
                    <button type="submit" class="btn-salvar">Enviar Justificativa</button>
                    <button type="button" class="btn-cancelar" onclick="fecharJustificativa()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Relógio
        function atualizarRelogio() {
            const agora = new Date();
            const horas = String(agora.getHours()).padStart(2, '0');
            const minutos = String(agora.getMinutes()).padStart(2, '0');
            const segundos = String(agora.getSeconds()).padStart(2, '0');
            document.getElementById('relogio').innerHTML = `${horas}:${minutos}:${segundos}`;
        }
        setInterval(atualizarRelogio, 1000);
        atualizarRelogio();
        
        // Modal Justificativa
        function abrirJustificativa() {
            document.getElementById('modalJustificativa').style.display = 'flex';
        }
        
        function fecharJustificativa() {
            document.getElementById('modalJustificativa').style.display = 'none';
        }
        
        // Fechar modal clicando fora
        window.onclick = function(event) {
            const modal = document.getElementById('modalJustificativa');
            if (event.target === modal) {
                fecharJustificativa();
            }
        }
    </script>
</body>
</html>