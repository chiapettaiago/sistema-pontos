<?php
// modules/filiais/visualizar.php - Visualizar Filial (CORRIGIDO - SEM CARGO)
$pageTitle = 'Visualizar Filial';
$activePage = 'filiais';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

// Verificar permissão
redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header('Location: index.php');
    exit;
}

// Buscar dados da filial
$query = "SELECT f.*, 
          (SELECT COUNT(*) FROM funcionarios WHERE filial_id = f.id AND status = 'ativo') as total_funcionarios
          FROM filiais f
          WHERE f.id = :id";

$stmt = $db->prepare($query);
$stmt->execute([':id' => $id]);
$filial = $stmt->fetch();

if (!$filial) {
    echo "<div class='alert alert-error'>Filial não encontrada</div>";
    require_once '../../includes/footer.php';
    exit;
}

// Buscar funcionários da filial (SEM a coluna cargo)
$query_func = "SELECT id, nome, matricula, status, email 
               FROM funcionarios 
               WHERE filial_id = :id 
               ORDER BY nome ASC";
$stmt_func = $db->prepare($query_func);
$stmt_func->execute([':id' => $id]);
$funcionarios = $stmt_func->fetchAll();

// Buscar pontos de hoje dos funcionários da filial
$query_pontos = "SELECT funcionario_id, tipo, data_hora 
                 FROM pontos 
                 WHERE filial_id = :id AND DATE(data_hora) = CURDATE()";
$stmt_pontos = $db->prepare($query_pontos);
$stmt_pontos->execute([':id' => $id]);
$pontos_hoje = $stmt_pontos->fetchAll();

// Contar pontos por funcionário
$pontos_count = [];
foreach ($pontos_hoje as $ponto) {
    if (!isset($pontos_count[$ponto['funcionario_id']])) {
        $pontos_count[$ponto['funcionario_id']] = 0;
    }
    $pontos_count[$ponto['funcionario_id']]++;
}

$status_texto = $filial['ativo'] == 1 ? 'Ativa' : 'Inativa';
$status_classe = $filial['ativo'] == 1 ? 'success' : 'danger';

$tipo_texto = '';
$tipo_classe = '';
switch ($filial['tipo_ramo']) {
    case 'matriz':
        $tipo_texto = '🏢 Matriz';
        $tipo_classe = 'primary';
        break;
    case 'filial':
        $tipo_texto = '📌 Filial';
        $tipo_classe = 'info';
        break;
    case 'loja':
        $tipo_texto = '🛍️ Loja';
        $tipo_classe = 'warning';
        break;
    default:
        $tipo_texto = $filial['tipo_ramo'];
        $tipo_classe = 'secondary';
}
?>

<style>
.profile-header {
    display: flex;
    align-items: center;
    gap: 30px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.profile-icon {
    width: 120px;
    height: 120px;
    border-radius: 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-icon i {
    font-size: 60px;
    color: white;
}

.profile-info h2 {
    margin: 0 0 8px 0;
    font-size: 28px;
}

.profile-info .codigo {
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.info-section {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
}

.info-section h3 {
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 4px;
}

.info-value {
    font-size: 16px;
    font-weight: 500;
    color: var(--text-primary);
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-success {
    background: #d1fae5;
    color: #059669;
}

.status-danger {
    background: #fee2e2;
    color: #dc2626;
}

.type-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.type-primary {
    background: #e0e7ff;
    color: #4338ca;
}

.type-info {
    background: #d1fae5;
    color: #059669;
}

.type-warning {
    background: #fed7aa;
    color: #c2410c;
}

.funcionarios-table {
    width: 100%;
    border-collapse: collapse;
}

.funcionarios-table th,
.funcionarios-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.funcionarios-table th {
    background: var(--bg-secondary);
    font-weight: 600;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    border: 1px solid var(--border-color);
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.stat-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 5px;
}

.text-center {
    text-align: center;
}

@media (max-width: 768px) {
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .funcionarios-table {
        font-size: 12px;
    }
    
    .funcionarios-table th,
    .funcionarios-table td {
        padding: 8px;
    }
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-store"></i> Visualizar Filial</h2>
        <p>Detalhes da filial</p>
    </div>
    <div class="module-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
        <a href="editar.php?id=<?php echo $id; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Editar
        </a>
    </div>
</div>

<div class="profile-header">
    <div class="profile-icon">
        <i class="fas fa-store"></i>
    </div>
    <div class="profile-info">
        <h2><?php echo htmlspecialchars($filial['nome_fantasia']); ?></h2>
        <div class="codigo">
            <i class="fas fa-hashtag"></i> Código: <?php echo htmlspecialchars($filial['codigo']); ?>
        </div>
        <div>
            <span class="status-badge status-<?php echo $status_classe; ?>"><?php echo $status_texto; ?></span>
            <span class="type-badge type-<?php echo $tipo_classe; ?>" style="margin-left: 8px;"><?php echo $tipo_texto; ?></span>
        </div>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?php echo $filial['total_funcionarios']; ?></div>
        <div class="stat-label">Funcionários</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo count($pontos_hoje); ?></div>
        <div class="stat-label">Pontos Hoje</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $filial['ativo'] == 1 ? 'Ativa' : 'Inativa'; ?></div>
        <div class="stat-label">Status</div>
    </div>
</div>

<!-- Informações da Filial -->
<div class="info-section">
    <h3><i class="fas fa-info-circle"></i> Informações da Filial</h3>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Código</span>
            <span class="info-value"><?php echo htmlspecialchars($filial['codigo']); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Nome Fantasia</span>
            <span class="info-value"><?php echo htmlspecialchars($filial['nome_fantasia']); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Razão Social</span>
            <span class="info-value"><?php echo htmlspecialchars($filial['razao_social'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">CNPJ</span>
            <span class="info-value"><?php echo htmlspecialchars($filial['cnpj'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Inscrição Estadual</span>
            <span class="info-value"><?php echo htmlspecialchars($filial['inscricao_estadual'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Tipo</span>
            <span class="info-value"><?php echo $tipo_texto; ?></span>
        </div>
    </div>
</div>

<!-- Endereço -->
<div class="info-section">
    <h3><i class="fas fa-map-marker-alt"></i> Endereço</h3>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">CEP</span>
            <span class="info-value"><?php echo htmlspecialchars($filial['cep'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Logradouro</span>
            <span class="info-value"><?php echo htmlspecialchars($filial['logradouro'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Número</span>
            <span class="info-value"><?php echo htmlspecialchars($filial['numero'] ?: 's/n'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Complemento</span>
            <span class="info-value"><?php echo htmlspecialchars($filial['complemento'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Bairro</span>
            <span class="info-value"><?php echo htmlspecialchars($filial['bairro'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Cidade/UF</span>
            <span class="info-value"><?php echo htmlspecialchars($filial['cidade'] ?: 'Não informado'); ?>/<?php echo htmlspecialchars($filial['estado'] ?: '--'); ?></span>
        </div>
    </div>
</div>

<!-- Contato -->
<?php if (!empty($filial['telefone']) || !empty($filial['email'])): ?>
<div class="info-section">
    <h3><i class="fas fa-phone-alt"></i> Contato</h3>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Telefone</span>
            <span class="info-value"><?php echo htmlspecialchars($filial['telefone'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">E-mail</span>
            <span class="info-value"><?php echo htmlspecialchars($filial['email'] ?: 'Não informado'); ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Funcionários -->
<div class="info-section">
    <h3><i class="fas fa-users"></i> Funcionários da Filial</h3>
    <?php if (empty($funcionarios)): ?>
        <p class="text-center" style="padding: 20px; color: var(--text-secondary);">Nenhum funcionário vinculado a esta filial</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="funcionarios-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Matrícula</th>
                        <th>E-mail</th>
                        <th>Status</th>
                        <th>Pontos Hoje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($funcionarios as $func): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($func['nome']); ?></td>
                        <td><?php echo htmlspecialchars($func['matricula']); ?></td>
                        <td><?php echo htmlspecialchars($func['email']); ?></td>
                        <td>
                            <?php if ($func['status'] == 'ativo'): ?>
                                <span class="status-badge status-success">Ativo</span>
                            <?php else: ?>
                                <span class="status-badge status-danger">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php 
                            $quantidade = $pontos_count[$func['id']] ?? 0;
                            if ($quantidade > 0) {
                                echo "<span style='color: #10b981; font-weight: 600;'>$quantidade</span>";
                            } else {
                                echo "<span style='color: #9ca3af;'>0</span>";
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>