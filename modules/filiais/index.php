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
    header('Location: ' . BASE_URL . '/index.php');
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
<div class="pf-page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-store me-2 text-primary"></i>Filiais</h1>
        <p class="text-muted">Gerencie as filiais da empresa</p>
    </div>
    <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
    <a href="cadastrar.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Nova Filial</a>
    <?php endif; ?>
</div>

<div class="card pf-table-card">
    <div class="card-header bg-transparent border-bottom fw-semibold">
        <i class="fas fa-list me-2 text-primary"></i>Lista de Filiais
        <span class="badge bg-primary ms-2"><?php echo count($filiais); ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th class="d-none d-md-table-cell">Cidade/UF</th>
                        <th class="d-none d-lg-table-cell">Responsável</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filiais as $filial): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($filial['nome']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($filial['endereco'] ?? ''); ?></div>
                        </td>
                        <td class="d-none d-md-table-cell text-muted">
                            <?php echo htmlspecialchars(trim(($filial['cidade'] ?? '') . '/' . ($filial['uf'] ?? ''), '/')); ?>
                        </td>
                        <td class="d-none d-lg-table-cell text-muted"><?php echo htmlspecialchars($filial['responsavel'] ?? '-'); ?></td>
                        <td>
                            <span class="badge <?php echo ($filial['ativo'] ?? 1) ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo ($filial['ativo'] ?? 1) ? 'Ativa' : 'Inativa'; ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="visualizar.php?id=<?php echo $filial['id']; ?>" class="btn btn-outline-primary" title="Ver"><i class="fas fa-eye"></i></a>
                                <?php if (in_array($usuario_tipo, ['super_admin','admin_empresa'])): ?>
                                <a href="editar.php?id=<?php echo $filial['id']; ?>" class="btn btn-outline-secondary" title="Editar"><i class="fas fa-edit"></i></a>
                                <a href="excluir.php?id=<?php echo $filial['id']; ?>" class="btn btn-outline-danger" title="Excluir"
                                   onclick="return confirm('Excluir esta filial?')"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($filiais)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">
                        <i class="fas fa-store fa-2x d-block mb-2 opacity-25"></i>Nenhuma filial cadastrada
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

