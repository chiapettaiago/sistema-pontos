<?php
// modules/ponto/autorizar_gerente.php - Gerente autoriza ponto escaneando QR Code do funcionário
session_start();

// Verificar se é gerente/admin
$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa', 'gestor'])) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Autorizar Ponto';
$activePage = 'ponto';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$mensagem = '';
$tipo_mensagem = '';
$funcionario = null;
$solicitacao = null;

// Processar dados do QR Code
$dados_qr = $_GET['data'] ?? '';
if ($dados_qr) {
    // Decodificar dados do QR Code (do crachá do funcionário)
    $funcionario_data = json_decode(urldecode($dados_qr), true);
    
    if ($funcionario_data && isset($funcionario_data['matricula'])) {
        // Buscar funcionário pela matrícula
        $stmt = $db->prepare("SELECT f.*, fi.nome_fantasia as filial_nome 
                              FROM funcionarios f
                              LEFT JOIN filiais fi ON f.filial_id = fi.id
                              WHERE f.matricula = :matricula AND f.status = 'ativo'");
        $stmt->execute([':matricula' => $funcionario_data['matricula']]);
        $funcionario = $stmt->fetch();
        
        // Buscar solicitação pendente
        if ($funcionario) {
            $stmt = $db->prepare("SELECT * FROM autorizacoes_ponto 
                                  WHERE funcionario_id = :funcionario_id AND status = 'pendente' 
                                  ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([':funcionario_id' => $funcionario['id']]);
            $solicitacao = $stmt->fetch();
        }
    }
}

// Processar autorização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $solicitacao_id = $_POST['solicitacao_id'] ?? 0;
    $acao = $_POST['acao'] ?? '';
    $senha_gerente = $_POST['senha_gerente'] ?? '';
    
    // Verificar senha do gerente
    $stmt = $db->prepare("SELECT id FROM usuarios_sistema 
                          WHERE id = :id AND senha = :senha AND tipo IN ('super_admin', 'admin_empresa', 'gestor')");
    $stmt->execute([
        ':id' => $_SESSION['usuario_id'],
        ':senha' => md5($senha_gerente)
    ]);
    
    if (!$stmt->fetch()) {
        $mensagem = 'Senha incorreta!';
        $tipo_mensagem = 'error';
    } elseif ($acao === 'autorizar') {
        // Buscar solicitação
        $stmt = $db->prepare("SELECT * FROM autorizacoes_ponto WHERE id = :id");
        $stmt->execute([':id' => $solicitacao_id]);
        $sol = $stmt->fetch();
        
        if ($sol) {
            // Buscar filial do funcionário
            $stmt = $db->prepare("SELECT filial_id FROM funcionarios WHERE id = :id");
            $stmt->execute([':id' => $sol['funcionario_id']]);
            $filial_id = $stmt->fetchColumn();
            
            // Registrar ponto autorizado
            $stmt = $db->prepare("INSERT INTO pontos 
                                  (funcionario_id, filial_id, tipo, data_hora, latitude, longitude, origem, autorizado_por) 
                                  VALUES 
                                  (:funcionario_id, :filial_id, :tipo, NOW(), :latitude, :longitude, 'autorizado_gerente', :autorizado_por)");
            
            $stmt->execute([
                ':funcionario_id' => $sol['funcionario_id'],
                ':filial_id' => $filial_id,
                ':tipo' => $sol['tipo'],
                ':latitude' => $sol['latitude'],
                ':longitude' => $sol['longitude'],
                ':autorizado_por' => $_SESSION['usuario_id']
            ]);
            
            // Atualizar status da solicitação
            $stmt = $db->prepare("UPDATE autorizacoes_ponto SET status = 'aprovado', autorizado_por = :autorizado_por WHERE id = :id");
            $stmt->execute([
                ':autorizado_por' => $_SESSION['usuario_id'],
                ':id' => $solicitacao_id
            ]);
            
            $mensagem = '✅ Ponto autorizado com sucesso!';
            $tipo_mensagem = 'success';
            $solicitacao = null;
        }
    } else {
        $stmt = $db->prepare("UPDATE autorizacoes_ponto SET status = 'rejeitado' WHERE id = :id");
        $stmt->execute([':id' => $solicitacao_id]);
        $mensagem = '❌ Autorização negada!';
        $tipo_mensagem = 'error';
        $solicitacao = null;
    }
}

$tipos = [
    'entrada' => 'Entrada',
    'saida_almoco' => 'Saída para Almoço',
    'volta_almoco' => 'Volta do Almoço',
    'saida' => 'Saída'
];
?>

<style>
.autorizar-container {
    max-width: 600px;
    margin: 0 auto;
}

.card {
    background: var(--bg-primary);
    border-radius: 24px;
    padding: 32px;
    border: 1px solid var(--border-color);
    text-align: center;
}

.scanner-area {
    background: #f8f9fa;
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 24px;
    text-align: center;
    border: 2px dashed var(--border-color);
}

.scanner-area i {
    font-size: 64px;
    color: #667eea;
    margin-bottom: 16px;
}

.btn-scanner {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 14px 28px;
    border-radius: 40px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 16px;
}

.funcionario-info {
    background: var(--bg-secondary);
    border-radius: 20px;
    padding: 20px;
    margin: 20px 0;
    text-align: left;
}

.funcionario-foto {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    margin-right: 16px;
}

.funcionario-foto img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--bg-primary);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
}

.btn-danger {
    background: #ef4444;
    color: white;
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 12px;
}

.alert {
    padding: 12px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    color: #059669;
}

.alert-error {
    background: #fee2e2;
    color: #dc2626;
}

.modal-scanner {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.95);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: #000;
    border-radius: 24px;
    overflow: hidden;
    max-width: 500px;
    width: 90%;
}

video {
    width: 100%;
    height: auto;
}

.modal-buttons {
    padding: 16px;
    display: flex;
    gap: 12px;
}

.modal-buttons button {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 30px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
}

.btn-fechar {
    background: #ef4444;
    color: white;
}
</style>

<div class="autorizar-container">
    <div class="card">
        <h2><i class="fas fa-user-check"></i> Autorizar Ponto</h2>
        <p>Escaneie o QR Code do crachá do funcionário para autorizar o ponto</p>
        
        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($solicitacao && $funcionario): ?>
            <!-- Funcionário encontrado via QR Code -->
            <div class="funcionario-info" style="display: flex; align-items: center;">
                <div class="funcionario-foto">
                    <?php if (!empty($funcionario['foto']) && file_exists('../../' . $funcionario['foto'])): ?>
                        <img src="../../<?php echo $funcionario['foto']; ?>" alt="Foto">
                    <?php else: ?>
                        <i class="fas fa-user-circle" style="font-size: 48px;"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <h3><?php echo htmlspecialchars($funcionario['nome']); ?></h3>
                    <p>Matrícula: <?php echo htmlspecialchars($funcionario['matricula']); ?></p>
                    <p>Filial: <?php echo htmlspecialchars($funcionario['filial_nome'] ?? 'Matriz'); ?></p>
                    <p><strong>Tipo de Ponto:</strong> <?php echo $tipos[$solicitacao['tipo']]; ?></p>
                </div>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="solicitacao_id" value="<?php echo $solicitacao['id']; ?>">
                <div class="form-group">
                    <label>Digite sua senha para confirmar</label>
                    <input type="password" name="senha_gerente" required placeholder="Sua senha de gestor">
                </div>
                <button type="submit" name="acao" value="autorizar" class="btn-primary">
                    <i class="fas fa-check"></i> Autorizar Ponto
                </button>
                <button type="submit" name="acao" value="negar" class="btn-danger">
                    <i class="fas fa-times"></i> Negar
                </button>
            </form>
            
        <?php else: ?>
            <!-- Scanner de QR Code -->
            <div class="scanner-area">
                <i class="fas fa-qrcode"></i>
                <p>Aponte a câmera para o QR Code do crachá do funcionário</p>
                <button class="btn-scanner" id="btnAbrirScanner">
                    <i class="fas fa-camera"></i> Abrir Câmera
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal do Scanner -->
<div id="scannerModal" class="modal-scanner">
    <div class="modal-content">
        <video id="video" autoplay playsinline></video>
        <div class="modal-buttons">
            <button id="btnFecharScanner" class="btn-fechar">Fechar</button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let html5QrCode = null;

document.getElementById('btnAbrirScanner').addEventListener('click', function() {
    const modal = document.getElementById('scannerModal');
    const video = document.getElementById('video');
    
    modal.style.display = 'flex';
    
    html5QrCode = new Html5Qrcode("video");
    html5QrCode.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        (decodedText) => {
            // QR Code lido com sucesso
            html5QrCode.stop();
            modal.style.display = 'none';
            window.location.href = 'autorizar_gerente.php?data=' + encodeURIComponent(decodedText);
        }
    ).catch(err => {
        console.error("Erro:", err);
        modal.style.display = 'none';
        alert('Erro ao acessar a câmera');
    });
});

document.getElementById('btnFecharScanner').addEventListener('click', function() {
    if (html5QrCode) {
        html5QrCode.stop();
    }
    document.getElementById('scannerModal').style.display = 'none';
});
</script>

<?php require_once '../../includes/footer.php'; ?>