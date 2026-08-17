<?php
// modules/escala/index.php - Dashboard de Escalas
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa', 'gestor'])) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Escala de Trabalho';
$activePage = 'escala';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
$ano_atual = date('Y');
$mes_atual = date('m');

// Parâmetros
$ano = $_GET['ano'] ?? $ano_atual;
$mes = $_GET['mes'] ?? $mes_atual;
$funcionario_id = $_GET['funcionario_id'] ?? '';
$tipo_escala = $_GET['tipo'] ?? '';

// Buscar funcionários
$stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios 
                      WHERE empresa_id = :empresa_id AND status = 'ativo' 
                      ORDER BY nome");
$stmt->execute([':empresa_id' => $empresa_id]);
$funcionarios = $stmt->fetchAll();

// Buscar tipos de escala
$stmt = $db->prepare("SELECT * FROM escala_tipos WHERE empresa_id = :empresa_id AND ativo = 1");
$stmt->execute([':empresa_id' => $empresa_id]);
$tipos_escala = $stmt->fetchAll();

// Buscar escalas dos funcionários
$query = "SELECT fe.*, f.nome as funcionario_nome, f.matricula,
          et.nome as escala_nome, et.tipo as escala_tipo
          FROM funcionario_escala fe
          JOIN funcionarios f ON fe.funcionario_id = f.id
          JOIN escala_tipos et ON fe.escala_tipo_id = et.id
          WHERE f.empresa_id = :empresa_id AND fe.ativo = 1";

$params = [':empresa_id' => $empresa_id];

if ($funcionario_id) {
    $query .= " AND fe.funcionario_id = :funcionario_id";
    $params[':funcionario_id'] = $funcionario_id;
}

if ($tipo_escala) {
    $query .= " AND et.id = :tipo_escala";
    $params[':tipo_escala'] = $tipo_escala;
}

$query .= " ORDER BY f.nome";

$stmt = $db->prepare($query);
$stmt->execute($params);
$escalas = $stmt->fetchAll();

// Estatísticas
$stmt = $db->prepare("SELECT et.tipo, COUNT(*) as total
                      FROM funcionario_escala fe
                      JOIN escala_tipos et ON fe.escala_tipo_id = et.id
                      WHERE fe.ativo = 1
                      GROUP BY et.tipo");
$stmt->execute();
$stats = $stmt->fetchAll();
?>
<div class="pf-page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-calendar-week me-2 text-primary"></i>Escalas de Trabalho</h1>
        <p class="text-muted">Gerencie as escalas e horários dos funcionários</p>
    </div>
    <div class="d-flex gap-2">
        <a href="configurar.php" class="btn btn-outline-secondary"><i class="fas fa-cog me-1"></i>Configurar</a>
        <a href="configurar.php?nova=1" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Nova Escala</a>
    </div>
</div>

<div class="card pf-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th class="d-none d-md-table-cell">Horário</th>
                        <th class="d-none d-lg-table-cell">Dias</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($escalas as $escala): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($escala['nome']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($escala['descricao'] ?? ''); ?></div>
                        </td>
                        <td class="d-none d-md-table-cell text-muted">
                            <?php echo htmlspecialchars(($escala['hora_entrada'] ?? '') . ' - ' . ($escala['hora_saida'] ?? '')); ?>
                        </td>
                        <td class="d-none d-lg-table-cell text-muted small">
                            <?php
                            $dias = ['seg','ter','qua','qui','sex','sab','dom'];
                            $diasAtivos = [];
                            foreach ($dias as $d) {
                                if (!empty($escala[$d])) $diasAtivos[] = strtoupper($d);
                            }
                            echo implode(', ', $diasAtivos) ?: '-';
                            ?>
                        </td>
                        <td>
                            <span class="badge <?php echo !empty($escala['ativo']) ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo !empty($escala['ativo']) ? 'Ativa' : 'Inativa'; ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="editar.php?id=<?php echo $escala['id']; ?>" class="btn btn-outline-secondary"><i class="fas fa-edit"></i></a>
                                <a href="configurar.php?escala_id=<?php echo $escala['id']; ?>" class="btn btn-outline-primary"><i class="fas fa-cog"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($escalas)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">
                        <i class="fas fa-calendar-times fa-2x d-block mb-2 opacity-25"></i>Nenhuma escala cadastrada
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
