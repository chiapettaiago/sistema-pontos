<?php
// modules/usuarios/index.php - Gerenciar Usuários do Sistema (CORRIGIDO)
// Iniciar sessão e verificar permissões ANTES de qualquer saída HTML
session_start();

// Verificar se é super admin
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_tipo'] ?? '') !== 'super_admin') {
    header('Location: /login.php');
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
$empresas = $db->query("SELECT id, nome FROM empresas ORDER BY nome")->fetchAll();
?>

<style>
.usuario-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 16px;
    border: 1px solid var(--border-color);
    transition: var(--transition);
}

.usuario-card:hover {
    box-shadow: var(--shadow-md);
}

.tipo-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.tipo-super_admin { background: #e0e7ff; color: #4338ca; }
.tipo-admin_empresa { background: #d1fae5; color: #059669; }
.tipo-gestor { background: #fed7aa; color: #c2410c; }
.tipo-supervisor { background: #bfdbfe; color: #1e40af; }

.status-ativo { background: #d1fae5; color: #059669; }
.status-inativo { background: #fee2e2; color: #dc2626; }

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--bg-primary);
    border-radius: 24px;
    padding: 32px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    min-width: 150px;
    z-index: 100;
    box-shadow: var(--shadow-md);
}

.dropdown-item {
    display: block;
    padding: 8px 16px;
    text-decoration: none;
    color: var(--text-primary);
    font-size: 13px;
    transition: var(--transition);
}

.dropdown-item:hover {
    background: var(--bg-secondary);
}

.text-danger {
    color: #dc2626 !important;
}
</style>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-user-shield"></i> Usuários do Sistema</h2>
        <p>Gerencie os usuários que acessam o sistema administrativo</p>
    </div>
    <div class="module-actions">
        <button onclick="abrirModalUsuario()" class="btn btn-primary">
            <i class="fas fa-plus"></i> Novo Usuário
        </button>
        <a href="../funcionarios/index.php" class="btn btn-secondary">
            <i class="fas fa-users"></i> Gerenciar Funcionários
        </a>
    </div>
</div>

<!-- Filtros -->
<div class="filters-card">
    <form method="GET" action="" class="filters-form">
        <div class="filter-group">
            <label><i class="fas fa-search"></i> Buscar</label>
            <input type="text" name="search" placeholder="Nome ou e-mail" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-group">
            <label><i class="fas fa-filter"></i> Tipo</label>
            <select name="tipo">
                <option value="">Todos</option>
                <option value="super_admin" <?php echo $tipo == 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                <option value="admin_empresa" <?php echo $tipo == 'admin_empresa' ? 'selected' : ''; ?>>Admin Empresa</option>
                <option value="gestor" <?php echo $tipo == 'gestor' ? 'selected' : ''; ?>>Gestor</option>
                <option value="supervisor" <?php echo $tipo == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
            </select>
        </div>
        <div class="filter-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="index.php" class="btn btn-secondary">Limpar</a>
        </div>
    </form>
</div>

<!-- Lista de Usuários -->
<?php foreach ($usuarios as $usuario): ?>
<div class="usuario-card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
        <div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <div class="avatar" style="width: 50px; height: 50px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px;">
                    <?php echo strtoupper(substr($usuario['nome'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 18px;"><?php echo htmlspecialchars($usuario['nome']); ?></div>
                    <div style="font-size: 13px; color: var(--text-secondary);">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($usuario['email']); ?>
                    </div>
                </div>
            </div>
        </div>
        <div>
            <span class="tipo-badge tipo-<?php echo $usuario['tipo']; ?>">
                <?php 
                $tipos = [
                    'super_admin' => '👑 Super Admin',
                    'admin_empresa' => '🏢 Admin Empresa',
                    'gestor' => '📊 Gestor',
                    'supervisor' => '👁️ Supervisor'
                ];
                echo $tipos[$usuario['tipo']] ?? $usuario['tipo'];
                ?>
            </span>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--border-color);">
        <?php if ($usuario['empresa_nome']): ?>
        <div>
            <div style="font-size: 11px; color: var(--text-secondary);">Empresa</div>
            <div><?php echo htmlspecialchars($usuario['empresa_nome']); ?></div>
        </div>
        <?php endif; ?>
        <div>
            <div style="font-size: 11px; color: var(--text-secondary);">Status</div>
            <div>
                <span class="status-badge status-<?php echo $usuario['status']; ?>">
                    <?php echo ucfirst($usuario['status']); ?>
                </span>
            </div>
        </div>
        <div>
            <div style="font-size: 11px; color: var(--text-secondary);">Último Acesso</div>
            <div><?php echo $usuario['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acesso'])) : 'Nunca'; ?></div>
        </div>
        <div>
            <div style="font-size: 11px; color: var(--text-secondary);">Cadastro</div>
            <div><?php echo date('d/m/Y', strtotime($usuario['created_at'])); ?></div>
        </div>
    </div>
    
    <div style="margin-top: 16px; display: flex; gap: 12px;">
        <button onclick="editarUsuario(<?php echo $usuario['id']; ?>)" class="btn btn-secondary btn-sm">
            <i class="fas fa-edit"></i> Editar
        </button>
        <button onclick="excluirUsuario(<?php echo $usuario['id']; ?>)" class="btn btn-danger btn-sm">
            <i class="fas fa-trash"></i> Excluir
        </button>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($usuarios)): ?>
<div class="empty-state" style="text-align: center; padding: 60px;">
    <i class="fas fa-user-shield" style="font-size: 48px; color: #ccc;"></i>
    <p style="margin-top: 16px;">Nenhum usuário encontrado</p>
    <button onclick="abrirModalUsuario()" class="btn btn-primary" style="margin-top: 16px;">
        <i class="fas fa-plus"></i> Criar primeiro usuário
    </button>
</div>
<?php endif; ?>

<!-- Modal Novo/Editar Usuário -->
<div id="modalUsuario" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle">Novo Usuário</h3>
        <form method="POST" action="salvar.php" class="form-main">
            <input type="hidden" name="id" id="usuarioId">
            
            <div class="form-group">
                <label>Nome *</label>
                <input type="text" name="nome" id="usuarioNome" required>
            </div>
            
            <div class="form-group">
                <label>E-mail *</label>
                <input type="email" name="email" id="usuarioEmail" required>
            </div>
            
            <div class="form-group" id="senhaGroup">
                <label>Senha *</label>
                <input type="password" name="senha" id="usuarioSenha">
                <small>Mínimo 6 caracteres. Deixe em branco para manter a atual (edição)</small>
            </div>
            
            <div class="form-group">
                <label>Tipo *</label>
                <select name="tipo" id="usuarioTipo" required>
                    <option value="super_admin">Super Admin</option>
                    <option value="admin_empresa">Admin Empresa</option>
                    <option value="gestor">Gestor</option>
                    <option value="supervisor">Supervisor</option>
                </select>
            </div>
            
            <div class="form-group" id="empresaGroup">
                <label>Empresa</label>
                <select name="empresa_id" id="usuarioEmpresa">
                    <option value="">Selecione uma empresa</option>
                    <?php foreach ($empresas as $emp): ?>
                    <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Obrigatório para Admins de Empresa, Gestores e Supervisores</small>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="usuarioStatus">
                    <option value="ativo">Ativo</option>
                    <option value="inativo">Inativo</option>
                </select>
            </div>
            
            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <button type="button" onclick="fecharModal()" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalUsuario() {
    document.getElementById('modalTitle').textContent = 'Novo Usuário';
    document.getElementById('usuarioId').value = '';
    document.getElementById('usuarioNome').value = '';
    document.getElementById('usuarioEmail').value = '';
    document.getElementById('usuarioSenha').required = true;
    document.getElementById('senhaGroup').style.display = 'block';
    document.getElementById('usuarioTipo').value = 'gestor';
    document.getElementById('usuarioEmpresa').value = '';
    document.getElementById('usuarioStatus').value = 'ativo';
    document.getElementById('modalUsuario').style.display = 'flex';
    
    toggleTipoCampos();
}

function editarUsuario(id) {
    fetch(`/api/usuarios.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('modalTitle').textContent = 'Editar Usuário';
            document.getElementById('usuarioId').value = data.id;
            document.getElementById('usuarioNome').value = data.nome;
            document.getElementById('usuarioEmail').value = data.email;
            document.getElementById('usuarioSenha').required = false;
            document.getElementById('usuarioTipo').value = data.tipo;
            document.getElementById('usuarioEmpresa').value = data.empresa_id || '';
            document.getElementById('usuarioStatus').value = data.status;
            document.getElementById('modalUsuario').style.display = 'flex';
            
            toggleTipoCampos();
        })
        .catch(error => {
            alert('Erro ao carregar dados do usuário: ' + error);
        });
}

function excluirUsuario(id) {
    if (confirm('Tem certeza que deseja excluir este usuário?')) {
        window.location.href = `excluir.php?id=${id}`;
    }
}

function toggleTipoCampos() {
    const tipo = document.getElementById('usuarioTipo').value;
    const empresaGroup = document.getElementById('empresaGroup');
    
    if (tipo === 'super_admin') {
        empresaGroup.style.display = 'none';
    } else {
        empresaGroup.style.display = 'block';
    }
}

document.getElementById('usuarioTipo')?.addEventListener('change', toggleTipoCampos);

function fecharModal() {
    document.getElementById('modalUsuario').style.display = 'none';
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    const modal = document.getElementById('modalUsuario');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
