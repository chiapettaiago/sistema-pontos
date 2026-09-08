<?php
// modules/usuarios/index.php - Gerenciar Usuários do Sistema (CORRIGIDO)
require_once '../../includes/config.php';

// Super admin gerencia todos; admin da empresa gerencia apenas sua empresa.
$usuarioTipoAtual = $_SESSION['usuario_tipo'] ?? '';
if (!isset($_SESSION['usuario_id']) || !in_array($usuarioTipoAtual, ['super_admin', 'admin_empresa'], true)) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
$empresaAtualId = $_SESSION['empresa_id'] ?? null;
if ($usuarioTipoAtual === 'admin_empresa' && !$empresaAtualId) {
    header('Location: ' . appUrl(appHomeRouteFor($usuarioTipoAtual)));
    exit;
}

$pageTitle = 'Usuários do Sistema';
$activePage = 'usuarios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Buscar usuários
$search = $_GET['search'] ?? '';
$tipo = $_GET['tipo'] ?? '';

$query = "SELECT u.*, e.nome as empresa_nome
          FROM usuarios_sistema u
          LEFT JOIN empresas e ON u.empresa_id = e.id
          WHERE 1=1";

$params = [];

if ($usuarioTipoAtual === 'admin_empresa') {
    $query .= " AND u.empresa_id = :empresa_atual_id";
    $params[':empresa_atual_id'] = $empresaAtualId;
}

if ($search) {
    $query .= " AND (u.nome LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($tipo) {
    $query .= " AND u.tipo = :tipo";
    $params[':tipo'] = $tipo;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// Buscar empresas para o select
if ($usuarioTipoAtual === 'super_admin') {
    $empresas = $db->query("SELECT id, nome FROM empresas ORDER BY nome")->fetchAll();
} else {
    $stmtEmpresas = $db->prepare("SELECT id, nome FROM empresas WHERE id = :id");
    $stmtEmpresas->execute([':id' => $empresaAtualId]);
    $empresas = $stmtEmpresas->fetchAll();
}
?>
<div class="pf-page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><i class="fas fa-user-shield me-2 text-primary"></i>Usuários do Sistema</h1>
        <p class="text-muted">Gerencie usuários administradores</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario">
        <i class="fas fa-plus me-1"></i>Novo Usuário
    </button>
</div>

<div class="card pf-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th class="d-none d-md-table-cell">E-mail</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td class="fw-semibold"><?php echo htmlspecialchars($u['nome']); ?></td>
                        <td class="d-none d-md-table-cell text-muted"><?php echo htmlspecialchars($u['email']); ?></td>
                        <td>
                            <?php
                            $tipos = ['super_admin'=>['Super Admin','bg-danger'],'admin_empresa'=>['Admin','bg-primary'],'gestor'=>['Gestor','bg-info'],'supervisor'=>['Supervisor','bg-warning text-dark']];
                            $t = $tipos[$u['tipo']] ?? [ucfirst($u['tipo']),'bg-secondary'];
                            ?>
                            <span class="badge <?php echo $t[1]; ?>"><?php echo $t[0]; ?></span>
                        </td>
                        <td>
                            <span class="badge <?php echo $u['status'] === 'ativo' ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo ucfirst($u['status']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary btn-editar-usuario"
                                    data-id="<?php echo $u['id']; ?>"
                                    data-nome="<?php echo htmlspecialchars($u['nome']); ?>"
                                    data-email="<?php echo htmlspecialchars($u['email']); ?>"
                                    data-tipo="<?php echo $u['tipo']; ?>"
                                    data-bs-toggle="modal" data-bs-target="#modalUsuario">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($usuarios)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Nenhum usuário encontrado</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Usuário -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--pf-gradient);">
                <h5 class="modal-title text-white"><i class="fas fa-user-shield me-2"></i><span id="modalUsuarioTitulo">Novo Usuário</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="salvar.php">
                <div class="modal-body">
                    <input type="hidden" name="id" id="inputUserId">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" id="inputUserNome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" id="inputUserEmail" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" id="inputUserTipo" class="form-select">
                            <option value="admin_empresa">Administrador</option>
                            <option value="gestor">Gestor</option>
                            <option value="supervisor">Supervisor</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha <small class="text-muted">(deixe em branco para manter)</small></label>
                        <input type="password" name="senha" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-editar-usuario').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('modalUsuarioTitulo').textContent = 'Editar Usuário';
        document.getElementById('inputUserId').value   = this.dataset.id;
        document.getElementById('inputUserNome').value  = this.dataset.nome;
        document.getElementById('inputUserEmail').value = this.dataset.email;
        document.getElementById('inputUserTipo').value  = this.dataset.tipo;
    });
});
document.querySelector('[data-bs-target="#modalUsuario"]')?.addEventListener('click', function() {
    if (!this.classList.contains('btn-editar-usuario')) {
        document.getElementById('modalUsuarioTitulo').textContent = 'Novo Usuário';
        document.getElementById('inputUserId').value = '';
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>

