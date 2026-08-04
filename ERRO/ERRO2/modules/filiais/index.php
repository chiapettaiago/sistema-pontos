<?php
// modules/filiais/index.php - Lista de Filiais (VERSÃO DEFINITIVA)
$pageTitle = 'Filiais';
$activePage = 'filiais';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

// Verificar permissão (apenas admin)
redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

// ============================================
// OBTER EMPRESA DO USUÁRIO LOGADO
// ============================================
$empresa_id = getCurrentEmpresaId();

if (!$empresa_id && isset($_SESSION['empresa_id'])) {
    $empresa_id = $_SESSION['empresa_id'];
}

if (!$empresa_id && isset($_SESSION['usuario_id'])) {
    $stmt = $db->prepare("SELECT empresa_id FROM funcionarios WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['usuario_id']]);
    $func = $stmt->fetch();
    if ($func && $func['empresa_id']) {
        $empresa_id = $func['empresa_id'];
    }
}

if ($empresa_id === null || $empresa_id === '') {
    header('Location: /index.php');
    exit;
}

// ============================================
// PROCESSAR CORREÇÃO (se solicitado)
// ============================================
if (isset($_GET['corrigir']) && $_GET['corrigir'] == '1') {
    $stmt = $db->prepare("UPDATE filiais SET empresa_id = :empresa_id");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $atualizadas = $stmt->rowCount();
    echo "<script>alert('{$atualizadas} filiais foram atualizadas para empresa_id = {$empresa_id}. Recarregando...'); window.location.href = 'index.php';</script>";
    exit;
}

// ============================================
// GARANTIR QUE A EMPRESA EXISTE
// ============================================
try {
    $check = $db->prepare("SELECT id FROM empresa WHERE id = :id");
    $check->execute([':id' => $empresa_id]);
    if (!$check->fetch()) {
        $db->exec("INSERT INTO empresa (id, nome_empresa) VALUES ({$empresa_id}, 'Empresa {$empresa_id}')");
    }
} catch (Exception $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS empresa (
        id INT PRIMARY KEY,
        nome_empresa VARCHAR(100)
    )");
    $db->exec("INSERT INTO empresa (id, nome_empresa) VALUES ({$empresa_id}, 'Empresa {$empresa_id}')");
}

// ============================================
// BUSCAR TODAS AS FILIAIS DA EMPRESA
// ============================================
$query = "SELECT f.*, 
          (SELECT COUNT(*) FROM funcionarios WHERE filial_id = f.id AND status = 'ativo') as total_funcionarios,
          (SELECT COUNT(*) FROM pontos WHERE filial_id = f.id AND DATE(data_hora) = CURDATE()) as pontos_hoje
          FROM filiais f
          WHERE f.empresa_id = :empresa_id
          ORDER BY f.nome_fantasia ASC";

$stmt = $db->prepare($query);
$stmt->execute([':empresa_id' => $empresa_id]);
$filiais = $stmt->fetchAll();

// ============================================
// DIAGNÓSTICO - Mostrar filiais de outras empresas
// ============================================
$diagnostico = '';
if (empty($filiais)) {
    $stmt = $db->query("SELECT id, codigo, nome_fantasia, empresa_id FROM filiais");
    $todasFiliais = $stmt->fetchAll();
    if (!empty($todasFiliais)) {
        $diagnostico = '<div class="diagnostico-box">
            <strong><i class="fas fa-info-circle"></i> Diagnóstico:</strong> Existem ' . count($todasFiliais) . ' filiais no banco de dados.<br><br>
            <strong>Filiais encontradas:</strong>
            <table style="width: 100%; margin-top: 10px; border-collapse: collapse;">
                <thead>
                    <tr style="background: #fef3c7;">
                        <th style="padding: 5px; text-align: left;">ID</th>
                        <th style="padding: 5px; text-align: left;">Nome</th>
                        <th style="padding: 5px; text-align: left;">Empresa ID</th>
                    </tr>
                </thead>
                <tbody>';
        foreach ($todasFiliais as $fil) {
            $diagnostico .= "<tr>
                <td style=\"padding: 5px; border-bottom: 1px solid #fde68a;\">{$fil['id']}</td>
                <td style=\"padding: 5px; border-bottom: 1px solid #fde68a;\">{$fil['nome_fantasia']}</td>
                <td style=\"padding: 5px; border-bottom: 1px solid #fde68a; font-weight: bold;\">{$fil['empresa_id']}</td>
            </tr>";
        }
        $diagnostico .= '</tbody>
            </table>
            <br>
            <strong>Sua empresa atual tem ID = ' . $empresa_id . '</strong>. Para ver as filiais, clique no botão abaixo:<br><br>
            <a href="?corrigir=1" class="btn-corrigir" onclick="return confirm(\'ATENÇÃO: Isso vai atualizar TODAS as ' . count($todasFiliais) . ' filiais para empresa_id = ' . $empresa_id . '. Deseja continuar?\')">
                <i class="fas fa-sync-alt"></i> Corrigir: Associar todas as filiais à empresa atual
            </a>
            <p style="font-size: 12px; margin-top: 10px; color: #92400e;"><i class="fas fa-exclamation-triangle"></i> Esta ação moverá todas as filiais para sua empresa atual.</p>
        </div>';
    }
}

// Estatísticas
$total_filiais = count($filiais);
$total_ativas = 0;
$total_matriz = 0;

foreach ($filiais as $filial) {
    if ($filial['ativo'] == 1) $total_ativas++;
    if ($filial['tipo_ramo'] == 'matriz') $total_matriz++;
}

// Buscar nome da empresa
$empresa_nome = 'Empresa ' . $empresa_id;
try {
    $stmt = $db->prepare("SELECT nome_empresa FROM empresa WHERE id = :id");
    $stmt->execute([':id' => $empresa_id]);
    $emp = $stmt->fetch();
    if ($emp && $emp['nome_empresa']) {
        $empresa_nome = $emp['nome_empresa'];
    }
} catch (Exception $e) {
    $empresa_nome = 'Empresa ' . $empresa_id;
}
?>

<style>
.empresa-box {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    color: white;
}
.empresa-box h3 {
    margin: 0 0 5px 0;
    font-size: 18px;
}
.empresa-box p {
    margin: 0;
    opacity: 0.8;
    font-size: 13px;
}
.diagnostico-box {
    background: #fef3c7;
    border: 1px solid #f59e0b;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
    font-size: 13px;
    color: #92400e;
}
.diagnostico-box table {
    background: white;
}
.diagnostico-box th,
.diagnostico-box td {
    padding: 8px 12px;
}
.btn-corrigir {
    display: inline-block;
    background: #f59e0b;
    color: #fff;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    margin-top: 10px;
}
.btn-corrigir:hover {
    background: #d97706;
}
.stats-box {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}
.stat-item {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    border: 1px solid var(--border-color);
}
.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: #667eea;
}
.stat-label {
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 5px;
}
.table-wrapper {
    background: var(--bg-primary);
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}
.table-title {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-secondary);
}
.table-title h3 {
    margin: 0;
    font-size: 16px;
}
.table-responsive {
    overflow-x: auto;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table th,
.data-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}
.data-table th {
    background: var(--bg-secondary);
    font-weight: 600;
    font-size: 13px;
}
.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    color: var(--text-secondary);
    text-decoration: none;
}
.btn-icon:hover {
    background: var(--bg-secondary);
    color: #667eea;
}
.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-primary:hover {
    opacity: 0.9;
}
.text-center {
    text-align: center;
}
.status-ativo {
    background: #d1fae5;
    color: #059669;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    display: inline-block;
}
.status-inativo {
    background: #fee2e2;
    color: #dc2626;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    display: inline-block;
}
.badge-matriz {
    background: #e0e7ff;
    color: #4338ca;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    display: inline-block;
}
.badge-filial {
    background: #d1fae5;
    color: #059669;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    display: inline-block;
}
.badge-loja {
    background: #fed7aa;
    color: #c2410c;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    display: inline-block;
}
.empty-state {
    text-align: center;
    padding: 60px;
}
.empty-state i {
    font-size: 48px;
    color: #ccc;
    margin-bottom: 16px;
}
.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.module-title h2 {
    margin: 0 0 5px 0;
    font-size: 24px;
}
.module-title p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 14px;
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-store"></i> Filiais / Lojas</h2>
        <p>Gerencie as filiais da empresa</p>
    </div>
    <div class="module-actions">
        <a href="cadastrar.php" class="btn-primary">
            <i class="fas fa-plus"></i> Nova Filial
        </a>
    </div>
</div>

<!-- Informações da Empresa -->
<div class="empresa-box">
    <h3><i class="fas fa-building"></i> <?php echo htmlspecialchars($empresa_nome); ?></h3>
    <p>ID da Empresa: <?php echo $empresa_id; ?> | Total de filiais: <?php echo $total_filiais; ?></p>
</div>

<!-- Diagnóstico -->
<?php echo $diagnostico; ?>

<!-- Cards de Estatísticas -->
<?php if ($total_filiais > 0): ?>
<div class="stats-box">
    <div class="stat-item">
        <div class="stat-number"><?php echo $total_filiais; ?></div>
        <div class="stat-label">Total de Filiais</div>
    </div>
    <div class="stat-item">
        <div class="stat-number"><?php echo $total_ativas; ?></div>
        <div class="stat-label">Filiais Ativas</div>
    </div>
    <div class="stat-item">
        <div class="stat-number"><?php echo $total_matriz; ?></div>
        <div class="stat-label">Matriz</div>
    </div>
</div>

<!-- Lista de Filiais -->
<div class="table-wrapper">
    <div class="table-title">
        <h3><i class="fas fa-list"></i> Filiais Cadastradas</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nome Fantasia</th>
                    <th>Tipo</th>
                    <th>CNPJ</th>
                    <th>Cidade/UF</th>
                    <th>Funcionários</th>
                    <th>Pontos Hoje</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filiais as $filial): ?>
                <tr class="fade-in">
                    <td><strong><?php echo htmlspecialchars($filial['codigo']); ?></strong></td>
                    <td><?php echo htmlspecialchars($filial['nome_fantasia']); ?></td>
                    <td>
                        <span class="badge-<?php echo $filial['tipo_ramo']; ?>">
                            <?php 
                            $tipos = [
                                'matriz' => '🏢 Matriz',
                                'filial' => '📌 Filial',
                                'loja' => '🛍️ Loja'
                            ];
                            echo $tipos[$filial['tipo_ramo']] ?? $filial['tipo_ramo'];
                            ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($filial['cnpj'] ?: '--'); ?></td>
                    <td><?php echo htmlspecialchars($filial['cidade'] . '/' . $filial['estado']); ?></td>
                    <td class="text-center"><?php echo $filial['total_funcionarios']; ?></td>
                    <td class="text-center"><?php echo $filial['pontos_hoje']; ?></td>
                    <td>
                        <?php if ($filial['ativo']): ?>
                            <span class="status-ativo">Ativa</span>
                        <?php else: ?>
                            <span class="status-inativo">Inativa</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a href="visualizar.php?id=<?php echo $filial['id']; ?>" class="btn-icon" title="Visualizar">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="editar.php?id=<?php echo $filial['id']; ?>" class="btn-icon" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php if ($filial['ativo']): ?>
                            <a href="excluir.php?id=<?php echo $filial['id']; ?>" class="btn-icon" style="color: #dc2626;" 
                               onclick="return confirm('Tem certeza que deseja desativar esta filial?')" title="Desativar">
                                <i class="fas fa-ban"></i>
                            </a>
                        <?php else: ?>
                            <a href="excluir.php?id=<?php echo $filial['id']; ?>&reativar=1" class="btn-icon" style="color: #10b981;" 
                               onclick="return confirm('Tem certeza que deseja reativar esta filial?')" title="Reativar">
                                <i class="fas fa-check-circle"></i>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>


