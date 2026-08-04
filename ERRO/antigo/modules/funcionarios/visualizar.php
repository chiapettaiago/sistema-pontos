<?php
// modules/funcionarios/visualizar.php - Visualizar Funcionário (CORRIGIDO)
$pageTitle = 'Visualizar Funcionário';
$activePage = 'funcionarios';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

// Verificar permissão
checkModuleAccess('funcionarios');

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header('Location: index.php');
    exit;
}

// Buscar dados do funcionário (SEM jornada_id)
$query = "SELECT f.*, 
          e.nome_empresa as empresa_nome,
          fil.nome_fantasia as filial_nome,
          c.nome as cargo_nome,
          d.nome as departamento_nome
          FROM funcionarios f
          LEFT JOIN empresa e ON f.empresa_id = e.id
          LEFT JOIN filiais fil ON f.filial_id = fil.id
          LEFT JOIN cargos c ON f.cargo_id = c.id
          LEFT JOIN departamentos d ON f.departamento_id = d.id
          WHERE f.id = :id";

$stmt = $db->prepare($query);
$stmt->execute([':id' => $id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    echo "<div class='alert alert-error'>Funcionário não encontrado</div>";
    require_once '../../includes/footer.php';
    exit;
}

// Verificar permissão para editar (usando a função do auth.php)
$pode_editar = function_exists('canEditFuncionario') ? canEditFuncionario($id) : true;

// Formatar dados
$cpf_formatado = '';
if (!empty($funcionario['cpf'])) {
    $cpf_limpo = preg_replace('/[^0-9]/', '', $funcionario['cpf']);
    if (strlen($cpf_limpo) == 11) {
        $cpf_formatado = substr($cpf_limpo, 0, 3) . '.' . 
                         substr($cpf_limpo, 3, 3) . '.' . 
                         substr($cpf_limpo, 6, 3) . '-' . 
                         substr($cpf_limpo, 9, 2);
    } else {
        $cpf_formatado = $funcionario['cpf'];
    }
}

$data_admissao = !empty($funcionario['data_admissao']) && $funcionario['data_admissao'] != '0000-00-00' 
    ? date('d/m/Y', strtotime($funcionario['data_admissao'])) 
    : 'Não informada';

$data_nascimento = !empty($funcionario['data_nascimento']) && $funcionario['data_nascimento'] != '0000-00-00' 
    ? date('d/m/Y', strtotime($funcionario['data_nascimento'])) 
    : 'Não informada';

$telefone_formatado = !empty($funcionario['telefone']) ? $funcionario['telefone'] : 'Não informado';
$celular_formatado = !empty($funcionario['celular']) ? $funcionario['celular'] : 'Não informado';

$status_texto = $funcionario['status'] == 'ativo' ? 'Ativo' : 'Inativo';
$status_classe = $funcionario['status'] == 'ativo' ? 'success' : 'danger';

// Buscar pontos recentes
$query_pontos = "SELECT tipo, data_hora, origem 
                 FROM pontos 
                 WHERE funcionario_id = :id 
                 ORDER BY data_hora DESC 
                 LIMIT 10";
$stmt_pontos = $db->prepare($query_pontos);
$stmt_pontos->execute([':id' => $id]);
$pontos_recentes = $stmt_pontos->fetchAll();
?>

<style>
.profile-header {
    display: flex;
    align-items: center;
    gap: 30px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.profile-photo {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.profile-photo i {
    font-size: 80px;
    color: white;
}

.profile-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-info h2 {
    margin: 0 0 8px 0;
    font-size: 28px;
}

.profile-info .matricula {
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

.pontos-table {
    width: 100%;
    border-collapse: collapse;
}

.pontos-table th,
.pontos-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.pontos-table th {
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

.actions-bar {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

@media (max-width: 768px) {
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-user"></i> Visualizar Funcionário</h2>
        <p>Detalhes do funcionário</p>
    </div>
    <div class="module-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
        <?php if ($pode_editar): ?>
        <a href="editar.php?id=<?php echo $id; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Editar
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="profile-header">
    <div class="profile-photo">
        <?php if (!empty($funcionario['foto']) && file_exists('../../' . $funcionario['foto'])): ?>
            <img src="../../<?php echo $funcionario['foto']; ?>" alt="Foto">
        <?php else: ?>
            <i class="fas fa-user-circle"></i>
        <?php endif; ?>
    </div>
    <div class="profile-info">
        <h2><?php echo htmlspecialchars($funcionario['nome']); ?></h2>
        <div class="matricula">
            <i class="fas fa-hashtag"></i> Matrícula: <?php echo htmlspecialchars($funcionario['matricula']); ?>
        </div>
        <div class="status-badge status-<?php echo $status_classe; ?>">
            <?php echo $status_texto; ?>
        </div>
    </div>
</div>

<!-- Dados Pessoais -->
<div class="info-section">
    <h3><i class="fas fa-user-circle"></i> Dados Pessoais</h3>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Nome Completo</span>
            <span class="info-value"><?php echo htmlspecialchars($funcionario['nome']); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Nome Social</span>
            <span class="info-value"><?php echo htmlspecialchars($funcionario['nome_social'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">CPF</span>
            <span class="info-value"><?php echo $cpf_formatado ?: 'Não informado'; ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">RG</span>
            <span class="info-value"><?php echo htmlspecialchars($funcionario['rg'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Data de Nascimento</span>
            <span class="info-value"><?php echo $data_nascimento; ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">E-mail Corporativo</span>
            <span class="info-value"><?php echo htmlspecialchars($funcionario['email']); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">E-mail Pessoal</span>
            <span class="info-value"><?php echo htmlspecialchars($funcionario['email_pessoal'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Telefone</span>
            <span class="info-value"><?php echo $telefone_formatado; ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Celular</span>
            <span class="info-value"><?php echo $celular_formatado; ?></span>
        </div>
    </div>
</div>

<!-- Dados Profissionais -->
<div class="info-section">
    <h3><i class="fas fa-briefcase"></i> Dados Profissionais</h3>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Empresa</span>
            <span class="info-value"><?php echo htmlspecialchars($funcionario['empresa_nome']); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Filial</span>
            <span class="info-value"><?php echo htmlspecialchars($funcionario['filial_nome'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Departamento</span>
            <span class="info-value"><?php echo htmlspecialchars($funcionario['departamento_nome'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Cargo</span>
            <span class="info-value"><?php echo htmlspecialchars($funcionario['cargo_nome'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Data de Admissão</span>
            <span class="info-value"><?php echo $data_admissao; ?></span>
        </div>
    </div>
</div>

<!-- Endereço -->
<div class="info-section">
    <h3><i class="fas fa-map-marker-alt"></i> Endereço</h3>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">CEP</span>
            <span class="info-value"><?php echo htmlspecialchars($funcionario['cep'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Endereço</span>
            <span class="info-value"><?php echo htmlspecialchars($funcionario['endereco'] ?: 'Não informado'); ?>, <?php echo htmlspecialchars($funcionario['numero'] ?: 's/n'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Complemento</span>
            <span class="info-value"><?php echo htmlspecialchars($funcionario['complemento'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Bairro</span>
            <span class="info-value"><?php echo htmlspecialchars($funcionario['bairro'] ?: 'Não informado'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Cidade/UF</span>
            <span class="info-value"><?php echo htmlspecialchars($funcionario['cidade'] ?: 'Não informado'); ?>/<?php echo htmlspecialchars($funcionario['estado'] ?: '--'); ?></span>
        </div>
    </div>
</div>

<!-- Pontos Recentes -->
<div class="info-section">
    <h3><i class="fas fa-clock"></i> Últimos Registros de Ponto</h3>
    <?php if (empty($pontos_recentes)): ?>
        <p class="text-center" style="padding: 20px; color: var(--text-secondary);">Nenhum ponto registrado</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="pontos-table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Tipo</th>
                        <th>Origem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pontos_recentes as $ponto): ?>
                    <tr>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($ponto['data_hora'])); ?></td>
                        <td>
                            <?php 
                            $tipos = [
                                'entrada' => '✅ Entrada',
                                'saida_almoco' => '🍽️ Saída Almoço',
                                'volta_almoco' => '🔄 Volta Almoço',
                                'saida' => '🏁 Saída'
                            ];
                            echo $tipos[$ponto['tipo']] ?? $ponto['tipo'];
                            ?>
                        </td>
                        <td>
                            <?php 
                            $origens = [
                                'web' => '🌐 Web',
                                'mobile' => '📱 App',
                                'biometrico' => '🖐️ Biometria'
                            ];
                            echo $origens[$ponto['origem']] ?? $ponto['origem'];
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