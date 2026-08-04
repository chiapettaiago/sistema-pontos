<?php
// modules/admin/empresas/visualizar.php - Visualizar Empresa (REFATORADO)
// Iniciar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se está logado e é super admin
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_tipo'] ?? '') !== 'super_admin') {
    header('Location: /login.php');
    exit;
}

$pageTitle = 'Detalhes da Empresa';
$activePage = 'admin_empresas';
require_once '../../../includes/header.php';
require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;

// Buscar dados da empresa
$query = "SELECT e.*, 
          (SELECT COUNT(*) FROM funcionarios WHERE empresa_id = e.id) as total_funcionarios,
          (SELECT COUNT(*) FROM filiais WHERE empresa_id = e.id) as total_filiais,
          (SELECT COUNT(*) FROM pontos WHERE empresa_id = e.id) as total_pontos,
          (SELECT COUNT(*) FROM solicitacoes WHERE empresa_id = e.id) as total_solicitacoes
          FROM empresas e
          WHERE e.id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    header('Location: index.php');
    exit;
}

// Buscar assinatura atual
$query = "SELECT a.*, p.nome as plano_nome, p.preco_mensal, p.recurso_funcionarios, 
          p.recurso_horas_extras, p.recurso_relatorios_avancados, p.recurso_multi_gestores
          FROM assinaturas a
          JOIN planos p ON a.plano_id = p.id
          WHERE a.empresa_id = :id AND a.status = 'ativa'
          ORDER BY a.id DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $id]);
$assinatura = $stmt->fetch();

// Buscar histórico de assinaturas
$query = "SELECT a.*, p.nome as plano_nome 
          FROM assinaturas a
          JOIN planos p ON a.plano_id = p.id
          WHERE a.empresa_id = :id
          ORDER BY a.created_at DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $id]);
$historico_assinaturas = $stmt->fetchAll();

// Buscar administradores da empresa
$query = "SELECT * FROM usuarios_sistema WHERE empresa_id = :id AND tipo = 'admin_empresa'";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $id]);
$administradores = $stmt->fetchAll();
?>

<style>
.empresa-detalhes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
    gap: 24px;
    margin-bottom: 24px;
}

.detalhe-card {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 24px;
    border: 1px solid var(--border-color);
    transition: var(--transition);
}

.detalhe-card:hover {
    box-shadow: var(--shadow-md);
}

.detalhe-card h3 {
    font-size: 18px;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

.detalhe-card h3 i {
    color: var(--primary);
    font-size: 20px;
}

.detalhe-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 10px 0;
    border-bottom: 1px dashed var(--border-color);
}

.detalhe-row:last-child {
    border-bottom: none;
}

.detalhe-label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 13px;
    min-width: 120px;
}

.detalhe-value {
    font-weight: 500;
    color: var(--text-primary);
    font-size: 14px;
    text-align: right;
    word-break: break-word;
    max-width: 60%;
}

.stat-number {
    font-size: 28px;
    font-weight: 700;
    color: var(--primary);
}

.endereco-completo {
    background: var(--bg-secondary);
    padding: 12px;
    border-radius: 12px;
    margin-top: 8px;
    font-size: 13px;
    line-height: 1.5;
}

.assinatura-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.assinatura-ativa { background: #d1fae5; color: #059669; }
.assinatura-expirada { background: #fee2e2; color: #dc2626; }
.assinatura-cancelada { background: #fef3c7; color: #d97706; }

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-ativa { background: #d1fae5; color: #059669; }
.status-inativa { background: #fee2e2; color: #dc2626; }
.status-suspensa { background: #fef3c7; color: #d97706; }
.status-teste { background: #bfdbfe; color: #1e40af; }

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table th {
    background: var(--bg-secondary);
    font-weight: 600;
    font-size: 13px;
}

.data-table tr:hover {
    background: var(--bg-secondary);
}

.fade-in {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
    .empresa-detalhes {
        grid-template-columns: 1fr;
    }
    
    .detalhe-row {
        flex-direction: column;
        gap: 4px;
    }
    
    .detalhe-label {
        min-width: auto;
    }
    
    .detalhe-value {
        text-align: left;
        max-width: 100%;
    }
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-building"></i> <?php echo htmlspecialchars($empresa['nome']); ?></h2>
        <p>Detalhes e informações da empresa</p>
    </div>
    <div class="module-actions">
        <a href="editar.php?id=<?php echo $id; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Editar
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="empresa-detalhes">
    <!-- Informações Básicas -->
    <div class="detalhe-card fade-in">
        <h3><i class="fas fa-info-circle"></i> Informações Básicas</h3>
        <div class="detalhe-row">
            <span class="detalhe-label">Nome:</span>
            <span class="detalhe-value"><?php echo htmlspecialchars($empresa['nome']); ?></span>
        </div>
        <div class="detalhe-row">
            <span class="detalhe-label">CNPJ:</span>
            <span class="detalhe-value"><?php echo htmlspecialchars($empresa['cnpj'] ?? '--'); ?></span>
        </div>
        <div class="detalhe-row">
            <span class="detalhe-label">E-mail:</span>
            <span class="detalhe-value"><?php echo htmlspecialchars($empresa['email']); ?></span>
        </div>
        <div class="detalhe-row">
            <span class="detalhe-label">Telefone:</span>
            <span class="detalhe-value"><?php echo htmlspecialchars($empresa['telefone'] ?? '--'); ?></span>
        </div>
        <div class="detalhe-row">
            <span class="detalhe-label">Status:</span>
            <span class="detalhe-value">
                <span class="status-badge status-<?php echo $empresa['status']; ?>">
                    <?php echo ucfirst($empresa['status']); ?>
                </span>
            </span>
        </div>
        <div class="detalhe-row">
            <span class="detalhe-label">Cadastro:</span>
            <span class="detalhe-value"><?php echo date('d/m/Y H:i', strtotime($empresa['created_at'])); ?></span>
        </div>
        <div class="detalhe-row">
            <span class="detalhe-label">Domínio:</span>
            <span class="detalhe-value">
                <code><?php echo htmlspecialchars($empresa['dominio'] ?? ''); ?>.pontofacil.com</code>
            </span>
        </div>
    </div>
    
    <!-- Endereço Completo -->
    <div class="detalhe-card fade-in">
        <h3><i class="fas fa-map-marker-alt"></i> Endereço</h3>
        <div class="detalhe-row">
            <span class="detalhe-label">CEP:</span>
            <span class="detalhe-value"><?php echo htmlspecialchars($empresa['cep'] ?? '--'); ?></span>
        </div>
        <div class="detalhe-row">
            <span class="detalhe-label">Endereço:</span>
            <span class="detalhe-value"><?php echo htmlspecialchars($empresa['endereco'] ?? '--'); ?>, <?php echo htmlspecialchars($empresa['numero'] ?? 'S/N'); ?></span>
        </div>
        <?php if (!empty($empresa['complemento'])): ?>
        <div class="detalhe-row">
            <span class="detalhe-label">Complemento:</span>
            <span class="detalhe-value"><?php echo htmlspecialchars($empresa['complemento']); ?></span>
        </div>
        <?php endif; ?>
        <div class="detalhe-row">
            <span class="detalhe-label">Bairro:</span>
            <span class="detalhe-value"><?php echo htmlspecialchars($empresa['bairro'] ?? '--'); ?></span>
        </div>
        <div class="detalhe-row">
            <span class="detalhe-label">Cidade/UF:</span>
            <span class="detalhe-value"><?php echo htmlspecialchars($empresa['cidade'] ?? '--'); ?> / <?php echo htmlspecialchars($empresa['estado'] ?? '--'); ?></span>
        </div>
        <?php if ($empresa['endereco'] || $empresa['cidade']): ?>
        <div class="endereco-completo">
            <i class="fas fa-location-dot"></i> 
            <?php 
            $endereco_completo = '';
            if ($empresa['endereco']) $endereco_completo .= $empresa['endereco'];
            if ($empresa['numero']) $endereco_completo .= ', ' . $empresa['numero'];
            if ($empresa['bairro']) $endereco_completo .= ' - ' . $empresa['bairro'];
            if ($empresa['cidade']) $endereco_completo .= '<br>' . $empresa['cidade'];
            if ($empresa['estado']) $endereco_completo .= '/' . $empresa['estado'];
            if ($empresa['cep']) $endereco_completo .= '<br>CEP: ' . $empresa['cep'];
            echo $endereco_completo;
            ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Estatísticas -->
    <div class="detalhe-card fade-in">
        <h3><i class="fas fa-chart-line"></i> Estatísticas</h3>
        <div class="detalhe-row">
            <span class="detalhe-label">Funcionários:</span>
            <span class="detalhe-value stat-number"><?php echo $empresa['total_funcionarios']; ?></span>
        </div>
        <div class="detalhe-row">
            <span class="detalhe-label">Filiais:</span>
            <span class="detalhe-value stat-number"><?php echo $empresa['total_filiais']; ?></span>
        </div>
        <div class="detalhe-row">
            <span class="detalhe-label">Registros de Ponto:</span>
            <span class="detalhe-value stat-number"><?php echo number_format($empresa['total_pontos']); ?></span>
        </div>
        <div class="detalhe-row">
            <span class="detalhe-label">Solicitações:</span>
            <span class="detalhe-value stat-number"><?php echo $empresa['total_solicitacoes']; ?></span>
        </div>
    </div>
</div>

<!-- Assinatura Atual -->
<div class="detalhe-card fade-in" style="margin-bottom: 24px;">
    <h3><i class="fas fa-crown"></i> Assinatura Atual</h3>
    <?php if ($assinatura): ?>
    <div class="detalhe-row">
        <span class="detalhe-label">Plano:</span>
        <span class="detalhe-value"><strong><?php echo htmlspecialchars($assinatura['plano_nome']); ?></strong></span>
    </div>
    <div class="detalhe-row">
        <span class="detalhe-label">Valor:</span>
        <span class="detalhe-value">R$ <?php echo number_format($assinatura['valor'], 2, ',', '.'); ?> / <?php echo $assinatura['ciclo']; ?></span>
    </div>
    <div class="detalhe-row">
        <span class="detalhe-label">Início:</span>
        <span class="detalhe-value"><?php echo date('d/m/Y', strtotime($assinatura['data_inicio'])); ?></span>
    </div>
    <div class="detalhe-row">
        <span class="detalhe-label">Fim:</span>
        <span class="detalhe-value">
            <?php echo $assinatura['data_fim'] ? date('d/m/Y', strtotime($assinatura['data_fim'])) : '--'; ?>
            <?php if ($assinatura['data_fim'] && strtotime($assinatura['data_fim']) < time()): ?>
                <span class="assinatura-status assinatura-expirada">Expirada</span>
            <?php elseif ($assinatura['data_fim'] && strtotime($assinatura['data_fim']) < strtotime('+30 days')): ?>
                <span class="assinatura-status assinatura-ativa">Vence em breve</span>
            <?php else: ?>
                <span class="assinatura-status assinatura-ativa">Ativa</span>
            <?php endif; ?>
        </span>
    </div>
    <div class="detalhe-row">
        <span class="detalhe-label">Limite Funcionários:</span>
        <span class="detalhe-value">
            <?php echo $assinatura['recurso_funcionarios'] == 0 ? 'Ilimitado' : $assinatura['recurso_funcionarios']; ?>
        </span>
    </div>
    <?php else: ?>
    <div class="detalhe-row">
        <span class="detalhe-value" style="color: var(--warning);">⚠️ Nenhuma assinatura ativa</span>
    </div>
    <?php endif; ?>
</div>

<!-- Histórico de Assinaturas -->
<?php if (!empty($historico_assinaturas)): ?>
<div class="detalhe-card fade-in" style="margin-bottom: 24px;">
    <h3><i class="fas fa-history"></i> Histórico de Assinaturas</h3>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Plano</th>
                    <th>Valor</th>
                    <th>Ciclo</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historico_assinaturas as $hist): ?>
                <tr>
                    <td><?php echo htmlspecialchars($hist['plano_nome']); ?></td>
                    <td>R$ <?php echo number_format($hist['valor'], 2, ',', '.'); ?></td>
                    <td><?php echo $hist['ciclo']; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($hist['data_inicio'])); ?></td>
                    <td><?php echo $hist['data_fim'] ? date('d/m/Y', strtotime($hist['data_fim'])) : '--'; ?></td>
                    <td>
                        <span class="assinatura-status assinatura-<?php echo $hist['status']; ?>">
                            <?php echo ucfirst($hist['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Administradores -->
<div class="detalhe-card fade-in" style="margin-bottom: 24px;">
    <h3><i class="fas fa-user-shield"></i> Administradores</h3>
    <?php if (!empty($administradores)): ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Status</th>
                    <th>Último Acesso</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($administradores as $admin): ?>
                <tr>
                    <td><?php echo htmlspecialchars($admin['nome']); ?></td>
                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $admin['status']; ?>">
                            <?php echo ucfirst($admin['status']); ?>
                        </span>
                    </td>
                    <td><?php echo $admin['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($admin['ultimo_acesso'])) : 'Nunca'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p style="padding: 20px; text-align: center; color: var(--text-secondary);">
        <i class="fas fa-user-slash"></i> Nenhum administrador cadastrado
    </p>
    <?php endif; ?>
</div>

<?php require_once '../../../includes/footer.php'; ?>
