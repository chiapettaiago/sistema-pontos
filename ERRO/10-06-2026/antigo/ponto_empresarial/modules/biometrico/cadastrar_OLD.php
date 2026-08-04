<?php
// modules/biometrico/cadastrar.php - Cadastro Biométrico
$pageTitle = 'Cadastro Biométrico';
$activePage = 'biometrico';
require_once '../../includes/header.php';
require_once '../../config/database.php';

checkModuleAccess('gerenciar_funcionarios');

$database = new Database();
$db = $database->getConnection();

$empresa_id = getCurrentEmpresaId() ?: 1;

// Buscar funcionários
$query = "SELECT f.id, f.nome, f.matricula, f.foto,
          (SELECT COUNT(*) FROM biometricos_faciais bf WHERE bf.funcionario_id = f.id AND bf.ativo = 1) as tem_facial,
          (SELECT COUNT(*) FROM biometricos_digitais bd WHERE bd.funcionario_id = f.id AND bd.ativo = 1) as tem_digital
          FROM funcionarios f
          WHERE f.empresa_id = :empresa_id AND f.status = 'ativo'
          ORDER BY f.nome ASC";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$funcionarios = $stmt->fetchAll();

// Buscar configurações
$query = "SELECT valor FROM configuracoes WHERE chave = 'biometrico_tipo'";
$stmt = $db->query($query);
$biometrico_tipo = $stmt->fetch()['valor'] ?? 'ambos';
?>

<style>
.funcionario-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 16px;
    border: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    transition: all 0.3s;
}

.funcionario-card:hover {
    box-shadow: var(--shadow-md);
}

.funcionario-info {
    display: flex;
    align-items: center;
    gap: 16px;
}

.funcionario-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    font-weight: bold;
}

.funcionario-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-cadastrado { background: #d1fae5; color: #059669; }
.status-pendente { background: #fee2e2; color: #dc2626; }

.biometrico-buttons {
    display: flex;
    gap: 10px;
}

.btn-biometrico {
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s;
}

.btn-biometrico:hover {
    transform: translateY(-2px);
}

.btn-digital {
    background: #667eea;
    color: white;
}

.btn-facial {
    background: #10b981;
    color: white;
}

.btn-disabled {
    background: #9ca3af;
    color: white;
    cursor: not-allowed;
}

.btn-disabled:hover {
    transform: none;
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-fingerprint"></i> Cadastro Biométrico</h2>
        <p>Registre digital e/ou rosto dos funcionários</p>
    </div>
    <div class="module-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="filters-card">
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <strong>Instruções para cadastro:</strong>
        <ul style="margin-top: 8px; margin-left: 20px;">
            <li><strong>Cadastro Facial:</strong> Posicione o rosto em frente à webcam. O sistema capturará <?php 
                $stmt = $db->query("SELECT valor FROM configuracoes WHERE chave = 'biometrico_amostras_faciais'");
                $amostras = $stmt->fetch()['valor'] ?? 5;
                echo $amostras; 
            ?> amostras do rosto para maior precisão.</li>
            <li><strong>Cadastro Digital:</strong> Utilize o leitor biométrico conectado ao computador. Serão necessárias 3 leituras do mesmo dedo.</li>
            <li>O funcionário deve estar presente durante o cadastro.</li>
        </ul>
    </div>
</div>

<div class="funcionarios-list">
    <?php foreach ($funcionarios as $func): ?>
    <div class="funcionario-card">
        <div class="funcionario-info">
            <div class="funcionario-avatar">
                <?php if ($func['foto'] && file_exists('../../' . $func['foto'])): ?>
                    <img src="../../<?php echo $func['foto']; ?>" alt="Foto">
                <?php else: ?>
                    <?php echo strtoupper(substr($func['nome'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div>
                <strong><?php echo htmlspecialchars($func['nome']); ?></strong>
                <div style="font-size: 12px; color: var(--text-secondary);">
                    Matrícula: <?php echo htmlspecialchars($func['matricula']); ?>
                </div>
            </div>
        </div>
        
        <div class="biometrico-buttons">
            <?php if ($biometrico_tipo == 'digital' || $biometrico_tipo == 'ambos'): ?>
                <?php if ($func['tem_digital'] > 0): ?>
                    <span class="status-badge status-cadastrado">
                        <i class="fas fa-check-circle"></i> Digital OK
                    </span>
                <?php else: ?>
                    <a href="digital.php?id=<?php echo $func['id']; ?>" class="btn-biometrico btn-digital" onclick="return confirm('Prepare o leitor biométrico. Serão necessárias 3 leituras do dedo do funcionário. Clique OK para continuar.')">
                        <i class="fas fa-fingerprint"></i> Cadastrar Digital
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($biometrico_tipo == 'facial' || $biometrico_tipo == 'ambos'): ?>
                <?php if ($func['tem_facial'] > 0): ?>
                    <span class="status-badge status-cadastrado">
                        <i class="fas fa-check-circle"></i> Facial OK
                    </span>
                <?php else: ?>
                    <a href="facial.php?id=<?php echo $func['id']; ?>" class="btn-biometrico btn-facial">
                        <i class="fas fa-camera"></i> Cadastrar Rosto
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>