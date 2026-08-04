<?php
// modules/biometrico/cadastrar.php - Cadastro Biométrico
$pageTitle = 'Cadastro Biométrico';
$activePage = 'biometrico_cadastro';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

checkModuleAccess('funcionarios');

$database = new Database();
$db = $database->getConnection();

$empresa_id = getCurrentEmpresaId() ?: 1;

// Buscar funcionários sem biometria facial
$query = "SELECT f.id, f.nome, f.matricula, f.foto,
          (SELECT COUNT(*) FROM biometricos_faciais bf WHERE bf.funcionario_id = f.id AND bf.ativo = 1) as tem_facial,
          (SELECT COUNT(*) FROM biometricos_digitais bd WHERE bd.funcionario_id = f.id AND bd.ativo = 1) as tem_digital
          FROM funcionarios f
          WHERE f.empresa_id = :empresa_id AND f.status = 'ativo'
          ORDER BY f.nome ASC";
$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$funcionarios = $stmt->fetchAll();
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

.btn-facial {
    background: #10b981;
    color: white;
}

.btn-digital {
    background: #667eea;
    color: white;
}

.alert-info {
    background: #bfdbfe;
    color: #1e40af;
    border: 1px solid #bfdbfe;
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-fingerprint"></i> Cadastro Biométrico</h2>
        <p>Registre a face dos funcionários para reconhecimento facial</p>
    </div>
    <div class="module-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="alert-info">
    <i class="fas fa-info-circle"></i>
    <strong>Instruções para cadastro facial:</strong>
    <ul style="margin-top: 8px; margin-left: 20px;">
        <li><strong>Cadastro Facial:</strong> Posicione o rosto do funcionário em frente à webcam.</li>
        <li>Serão capturadas <strong>5 amostras</strong> do rosto para maior precisão.</li>
        <li>O funcionário deve estar presente durante o cadastro.</li>
        <li>Após o cadastro, o funcionário poderá registrar o ponto usando o reconhecimento facial.</li>
    </ul>
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
            <?php if ($func['tem_facial'] > 0): ?>
                <span class="status-badge status-cadastrado">
                    <i class="fas fa-check-circle"></i> Facial Cadastrado
                </span>
            <?php else: ?>
                <a href="facial.php?id=<?php echo $func['id']; ?>" class="btn-biometrico btn-facial">
                    <i class="fas fa-camera"></i> Cadastrar Rosto
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($funcionarios)): ?>
    <div style="text-align: center; padding: 60px;">
        <i class="fas fa-users" style="font-size: 48px; color: #ccc;"></i>
        <p style="margin-top: 16px;">Nenhum funcionário encontrado</p>
        <a href="../funcionarios/cadastrar.php" class="btn btn-primary" style="margin-top: 16px;">
            <i class="fas fa-plus"></i> Cadastrar Funcionário
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>