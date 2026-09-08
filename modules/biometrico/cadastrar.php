<?php
// modules/biometrico/cadastrar.php - Seleção de funcionário para cadastro biométrico
$tipoCadastro = ($_GET['tipo'] ?? 'facial') === 'digital' ? 'digital' : 'facial';
$pageTitle = $tipoCadastro === 'digital' ? 'Cadastrar digital' : 'Cadastrar facial';
$activePage = $tipoCadastro === 'digital' ? 'biometrico_digital' : 'biometrico_facial';
require_once '../../includes/auth_check.php';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

$empresa_id = getCurrentEmpresaId();
if ($empresa_id === null || $empresa_id === '') {
    $empresa_id = $_SESSION['empresa_id'] ?? null;
}
if ($empresa_id === null || $empresa_id === '') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

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
    background: linear-gradient(135deg, #0f172a, #10b981 55%, #059669);
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
        <h2><i class="fas <?= $tipoCadastro === 'digital' ? 'fa-fingerprint' : 'fa-face-smile' ?>"></i> <?= $tipoCadastro === 'digital' ? 'Cadastrar digital' : 'Cadastrar facial' ?></h2>
        <p>Escolha o funcionário que terá a <?= $tipoCadastro === 'digital' ? 'digital' : 'face' ?> cadastrada</p>
    </div>
    <div class="module-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="alert-info">
    <i class="fas fa-info-circle"></i>
    <strong><?= $tipoCadastro === 'digital' ? 'Cadastro de digital' : 'Cadastro facial' ?>:</strong>
    <ul style="margin-top: 8px; margin-left: 20px;">
        <?php if ($tipoCadastro === 'digital'): ?>
        <li>Escolha abaixo o funcionário que terá a digital cadastrada ou atualizada.</li>
        <li>Confirme a presença e a identidade do funcionário antes de continuar.</li>
        <?php else: ?>
        <li>Posicione o rosto do funcionário em frente à câmera.</li>
        <li>A tela orientará o enquadramento e fará uma captura segura.</li>
        <?php endif; ?>
        <li>O funcionário deve estar presente durante o cadastro.</li>
        <li>Um novo cadastro substitui o anterior.</li>
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
            <?php $temCadastro = $tipoCadastro === 'digital' ? $func['tem_digital'] > 0 : $func['tem_facial'] > 0; ?>
            <?php if ($temCadastro): ?>
                <span class="status-badge status-cadastrado">
                    <i class="fas fa-check-circle"></i> <?= $tipoCadastro === 'digital' ? 'Digital cadastrada' : 'Facial cadastrada' ?>
                </span>
            <?php endif; ?>
            <a href="<?= $tipoCadastro === 'digital' ? 'digital.php' : '../funcionarios/cadastro_facial.php' ?>?id=<?php echo $func['id']; ?>" class="btn-biometrico <?= $tipoCadastro === 'digital' ? 'btn-digital' : 'btn-facial' ?>">
                <i class="fas <?= $tipoCadastro === 'digital' ? 'fa-fingerprint' : 'fa-camera' ?>"></i> <?= $temCadastro ? 'Atualizar' : 'Cadastrar' ?>
            </a>
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


