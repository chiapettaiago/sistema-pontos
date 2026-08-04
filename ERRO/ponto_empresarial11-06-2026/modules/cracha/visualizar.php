<?php
// modules/cracha/visualizar.php - Visualizar Crachá na Tela (CORRIGIDO)
$pageTitle = 'Visualizar Crachá';
$activePage = 'cracha';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

checkModuleAccess('funcionarios');

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;

// Buscar dados do funcionário - CORRIGIDO
$query = "SELECT f.*, fil.nome_fantasia as filial_nome, c.nome as cargo_nome
          FROM funcionarios f
          LEFT JOIN filiais fil ON f.filial_id = fil.id
          LEFT JOIN cargos c ON f.cargo_id = c.id
          WHERE f.id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    header('Location: index.php');
    exit;
}

// Função para gerar QR Code
function gerarQRCode($texto) {
    $tamanho = 150;
    $url = "https://quickchart.io/qr?text=" . urlencode($texto) . "&size={$tamanho}";
    return $url;
}

$qrData = "ID: " . $funcionario['matricula'] . "\n";
$qrData .= "Nome: " . $funcionario['nome'] . "\n";
$qrData .= "Filial: " . $funcionario['filial_nome'];
$qrCodeUrl = gerarQRCode($qrData);
?>

<style>
.visualizar-container {
    max-width: 450px;
    margin: 0 auto;
}

.cracha-preview {
    background: white;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-bottom: 24px;
}

.cracha-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 24px;
    text-align: center;
    color: white;
}

.cracha-logo {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 8px;
}

.cracha-logo i {
    font-size: 32px;
    margin-right: 8px;
}

.cracha-foto-area {
    text-align: center;
    margin-top: -50px;
    margin-bottom: 16px;
}

.cracha-foto {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    margin: 0 auto;
    overflow: hidden;
    border: 4px solid white;
    box-shadow: var(--shadow-md);
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
    padding: 24px;
}

.cracha-nome {
    text-align: center;
    margin-bottom: 24px;
}

.cracha-nome h2 {
    font-size: 20px;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.cracha-info {
    margin-bottom: 24px;
}

.cracha-info-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
}

.cracha-qrcode {
    text-align: center;
    padding: 20px;
    background: var(--bg-secondary);
    border-radius: 16px;
}

.cracha-qrcode img {
    width: 120px;
    height: 120px;
    margin-bottom: 12px;
}

.cracha-footer {
    background: var(--bg-secondary);
    padding: 16px;
    text-align: center;
    font-size: 11px;
    color: var(--text-secondary);
}

.actions {
    display: flex;
    gap: 16px;
    justify-content: center;
}

.btn-download {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    transition: var(--transition);
}

.btn-download:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
}
</style>

<div class="visualizar-container">
    <div class="cracha-preview">
        <div class="cracha-header">
            <div class="cracha-logo">
                <i class="fas fa-clock"></i> PontoFácil
            </div>
            <div class="cracha-subtitle">IDENTIFICAÇÃO FUNCIONAL</div>
        </div>
        
        <div class="cracha-foto-area">
            <div class="cracha-foto">
                <?php if ($funcionario['foto'] && file_exists('../../' . $funcionario['foto'])): ?>
                    <img src="../../<?php echo $funcionario['foto']; ?>" alt="Foto">
                <?php else: ?>
                    <div class="no-photo">
                        <?php echo strtoupper(substr($funcionario['nome'], 0, 1)); ?>
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
                    <span>Matrícula</span>
                    <strong><?php echo htmlspecialchars($funcionario['matricula']); ?></strong>
                </div>
                <div class="cracha-info-item">
                    <span>Filial</span>
                    <strong><?php echo htmlspecialchars($funcionario['filial_nome']); ?></strong>
                </div>
                <div class="cracha-info-item">
                    <span>E-mail</span>
                    <strong><?php echo htmlspecialchars($funcionario['email']); ?></strong>
                </div>
                <div class="cracha-info-item">
                    <span>Admissão</span>
                    <strong><?php echo date('d/m/Y', strtotime($funcionario['data_admissao'])); ?></strong>
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
    
    <div class="actions">
        <a href="gerar.php?id=<?php echo $id; ?>" class="btn-download" target="_blank">
            <i class="fas fa-file-pdf"></i> Baixar PDF
        </a>
        <a href="index.php" class="btn-download" style="background: var(--bg-secondary); color: var(--text-primary);">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>