<?php
// includes/sidebar.php - Menu Lateral Completo (COM TODOS OS MENUS E PERMISSÕES)
$activePage = $activePage ?? '';
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-clock"></i>
            <span>PontoFácil</span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <!-- ============================================ -->
        <!-- DASHBOARD - visível para todos EXCETO funcionário comum -->
        <!-- ============================================ -->
        <?php if ($usuario_tipo !== 'funcionario'): ?>
        <a href="/ponto_empresarial/index.php" class="nav-item <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- ADMINISTRAÇÃO (apenas Super Admin) -->
        <!-- ============================================ -->
        <?php if ($usuario_tipo === 'super_admin'): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">
            <i class="fas fa-crown"></i> <span>Administração</span>
        </div>
        
        <a href="/ponto_empresarial/modules/admin/empresas/index.php" class="nav-item <?php echo $activePage === 'admin_empresas' ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Empresas</span>
        </a>
        
        <a href="/ponto_empresarial/modules/admin/planos/index.php" class="nav-item <?php echo $activePage === 'admin_planos' ? 'active' : ''; ?>">
            <i class="fas fa-crown"></i>
            <span>Planos</span>
        </a>
        
        <a href="/ponto_empresarial/modules/admin/assinaturas/index.php" class="nav-item <?php echo $activePage === 'admin_assinaturas' ? 'active' : ''; ?>">
            <i class="fas fa-receipt"></i>
            <span>Assinaturas</span>
        </a>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- DASHBOARD EMPRESA (Admin Empresa e Gestor - NÃO para funcionário) -->
        <!-- ============================================ -->
        <?php if ($usuario_tipo === 'admin_empresa' || $usuario_tipo === 'gestor'): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">
            <i class="fas fa-chart-pie"></i> <span>Visão Geral</span>
        </div>
        
        <a href="/ponto_empresarial/modules/dashboard_empresa/index.php" class="nav-item <?php echo $activePage === 'dashboard_empresa' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard Empresa</span>
        </a>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- EMPRESA (Apenas Admin e Gestor - NÃO para funcionário) -->
        <!-- ============================================ -->
        <?php if ($usuario_tipo !== 'funcionario'): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">
            <i class="fas fa-building"></i> <span>Empresa</span>
        </div>
        <?php endif; ?>
        
        <!-- Usuários do Sistema (apenas admin) -->
        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
        <a href="/ponto_empresarial/modules/usuarios/index.php" class="nav-item <?php echo $activePage === 'usuarios' ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i>
            <span>Usuários</span>
        </a>
        <?php endif; ?>
        
        <!-- Funcionários (admin, gestor - NÃO para funcionário) -->
        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa' || $usuario_tipo === 'gestor'): ?>
        <a href="/ponto_empresarial/modules/funcionarios/index.php" class="nav-item <?php echo $activePage === 'funcionarios' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Funcionários</span>
        </a>
        <?php endif; ?>
        
        <!-- Filiais (apenas admin - NÃO para funcionário) -->
        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
        <a href="/ponto_empresarial/modules/filiais/index.php" class="nav-item <?php echo $activePage === 'filiais' ? 'active' : ''; ?>">
            <i class="fas fa-store"></i>
            <span>Filiais</span>
        </a>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- BIOMETRIA (APENAS Admin, Gestor e Supervisor - NÃO para funcionário) -->
        <!-- ============================================ -->
        <?php if ($usuario_tipo !== 'funcionario'): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">
            <i class="fas fa-fingerprint"></i> <span>Biometria</span>
        </div>
        <?php endif; ?>
        
        <?php if ($usuario_tipo === 'super_admin' || 
                  $usuario_tipo === 'admin_empresa' || 
                  $usuario_tipo === 'gestor' || 
                  $usuario_tipo === 'supervisor'): ?>
        <a href="/ponto_empresarial/modules/biometrico/index.php" class="nav-item <?php echo $activePage === 'biometrico' ? 'active' : ''; ?>">
            <i class="fas fa-fingerprint"></i>
            <span>Gestão Biométrica</span>
        </a>
        
        <a href="/ponto_empresarial/modules/biometrico/cadastrar.php" class="nav-item <?php echo $activePage === 'biometrico_cadastro' ? 'active' : ''; ?>">
            <i class="fas fa-user-plus"></i>
            <span>Cadastrar Biometria</span>
        </a>
        
        <a href="/ponto_empresarial/modules/ponto/biometrico.php" class="nav-item <?php echo $activePage === 'ponto_biometrico' ? 'active' : ''; ?>">
            <i class="fas fa-camera"></i>
            <span>Ponto por Biometria</span>
        </a>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- PONTO (VISÍVEL PARA TODOS - inclusive funcionário) -->
        <!-- ============================================ -->
        <div class="nav-divider"></div>
        <div class="nav-section-title">
            <i class="fas fa-clock"></i> <span>Ponto</span>
        </div>
        
        <a href="/ponto_empresarial/modules/ponto/registrar.php" class="nav-item <?php echo $activePage === 'ponto' ? 'active' : ''; ?>">
            <i class="fas fa-fingerprint"></i>
            <span>Registrar Ponto</span>
        </a>
        
        <a href="/ponto_empresarial/modules/ponto/extrato.php" class="nav-item <?php echo $activePage === 'extrato' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Meu Extrato</span>
        </a>

        <!-- ============================================ -->
        <!-- ESCALA DE TRABALHO (NOVO - Admin, Gestor) -->
        <!-- ============================================ -->
        <?php if ($usuario_tipo !== 'funcionario'): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">
            <i class="fas fa-calendar-alt"></i> <span>Escala</span>
        </div>
        
        <a href="/ponto_empresarial/modules/escala/index.php" class="nav-item <?php echo $activePage === 'escala' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Escala de Trabalho</span>
        </a>
        
        <a href="/ponto_empresarial/modules/escala/calendario.php" class="nav-item <?php echo $activePage === 'calendario_escala' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-week"></i>
            <span>Calendário</span>
        </a>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- GESTÃO (VISÍVEL PARA TODOS) -->
        <!-- ============================================ -->
        <div class="nav-divider"></div>
        <div class="nav-section-title">
            <i class="fas fa-chart-line"></i> <span>Gestão</span>
        </div>
        
        <!-- Relatórios (apenas admin e gestor - NÃO para funcionário) -->
        <?php if ($usuario_tipo !== 'funcionario'): ?>
        <a href="/ponto_empresarial/modules/relatorios/index.php" class="nav-item <?php echo $activePage === 'relatorios' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Relatórios</span>
        </a>
        
        <a href="/ponto_empresarial/modules/relatorios/extrato_funcionario.php" class="nav-item <?php echo $activePage === 'extrato_funcionario' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span>Extrato Funcionários</span>
        </a>
        <?php endif; ?>
        
        <!-- ============================================ -->
        <!-- CRACHÁ (DIFERENTE PARA CADA PERFIL) -->
        <!-- ============================================ -->
        
        <!-- Para ADMIN/GESTOR: Gerar crachá de funcionários -->
        <?php if ($usuario_tipo !== 'funcionario'): ?>
        <a href="/ponto_empresarial/modules/cracha/index.php" class="nav-item <?php echo $activePage === 'cracha_admin' ? 'active' : ''; ?>">
            <i class="fas fa-id-card"></i>
            <span>Gerar Crachás</span>
        </a>
        <?php endif; ?>
        
        <!-- Para FUNCIONÁRIO: Ver seu próprio crachá -->
        <?php if ($usuario_tipo === 'funcionario'): ?>
        <a href="/ponto_empresarial/modules/cracha/meu_cracha.php" class="nav-item <?php echo $activePage === 'cracha' ? 'active' : ''; ?>">
            <i class="fas fa-id-card"></i>
            <span>Meu Crachá</span>
        </a>
        <?php endif; ?>
        
        <!-- ============================================ -->
        <!-- SOLICITAÇÕES (VISÍVEL PARA TODOS) -->
        <!-- ============================================ -->
        
        <!-- Minhas Solicitações - VISÍVEL PARA TODOS -->
        <a href="/ponto_empresarial/modules/solicitacoes/index.php" class="nav-item <?php echo $activePage === 'solicitacoes' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list"></i>
            <span>Solicitações</span>
        </a>
        
        <!-- Gerenciar Solicitações - APENAS ADMIN, GESTOR E SUPER_ADMIN -->
        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa' || $usuario_tipo === 'gestor'): ?>
        <a href="/ponto_empresarial/modules/solicitacoes/admin.php" class="nav-item <?php echo $activePage === 'solicitacoes_admin' ? 'active' : ''; ?>">
            <i class="fas fa-user-check"></i>
            <span>Gerenciar Solicitações</span>
        </a>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- CONFIGURAÇÕES (APENAS ADMIN E SUPER_ADMIN) -->
        <!-- ============================================ -->
        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">
            <i class="fas fa-cogs"></i> <span>Configurações</span>
        </div>
        
        <a href="/ponto_empresarial/modules/configuracoes/index.php" class="nav-item <?php echo $activePage === 'configuracoes' ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Empresa</span>
        </a>
        
        <a href="/ponto_empresarial/modules/configuracoes/horarios.php" class="nav-item <?php echo $activePage === 'config_horarios' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i>
            <span>Horários</span>
        </a>
        
        <a href="/ponto_empresarial/modules/configuracoes/regras.php" class="nav-item <?php echo $activePage === 'config_regras' ? 'active' : ''; ?>">
            <i class="fas fa-gavel"></i>
            <span>Regras de Ponto</span>
        </a>
        
        <a href="/ponto_empresarial/modules/configuracoes/feriados.php" class="nav-item <?php echo $activePage === 'config_feriados' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-times"></i>
            <span>Feriados</span>
        </a>
        
        <a href="/ponto_empresarial/modules/configuracoes/email.php" class="nav-item <?php echo $activePage === 'config_email' ? 'active' : ''; ?>">
            <i class="fas fa-envelope"></i>
            <span>E-mail</span>
        </a>
        
        <a href="/ponto_empresarial/modules/configuracoes/facial.php" class="nav-item <?php echo $activePage === 'config_facial' ? 'active' : ''; ?>">
            <i class="fas fa-camera"></i>
            <span>Reconhecimento Facial</span>
        </a>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- BACKUP (APENAS ADMIN E SUPER_ADMIN) -->
        <!-- ============================================ -->
        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">
            <i class="fas fa-database"></i> <span>Backup</span>
        </div>
        
        <a href="/ponto_empresarial/modules/backup/index.php" class="nav-item <?php echo $activePage === 'backup' ? 'active' : ''; ?>">
            <i class="fas fa-database"></i>
            <span>Backup e Restauração</span>
        </a>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- SEGURANÇA / AUDITORIA (APENAS SUPER_ADMIN E ADMIN_EMPRESA) -->
        <!-- ============================================ -->
        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">
            <i class="fas fa-shield-alt"></i> <span>Segurança</span>
        </div>
        
        <a href="/ponto_empresarial/modules/auditoria/index.php" class="nav-item <?php echo $activePage === 'auditoria' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span>Auditoria</span>
        </a>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- NOTIFICAÇÕES (APENAS ADMIN E SUPER_ADMIN) -->
        <!-- ============================================ -->
        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">
            <i class="fas fa-bell"></i> <span>Notificações</span>
        </div>
        
        <a href="/ponto_empresarial/modules/notificacoes/index.php" class="nav-item <?php echo $activePage === 'notificacoes' ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i>
            <span>Notificações</span>
            <span class="notificacao-badge" style="display: none;"></span>
        </a>
        
        <a href="/ponto_empresarial/modules/notificacoes/config.php" class="nav-item <?php echo $activePage === 'notificacoes_config' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Configurar Notificações</span>
        </a>
        <?php endif; ?>
    </nav>
    
    <!-- ============================================ -->
    <!-- FOOTER DO SIDEBAR -->
    <!-- ============================================ -->
    <div class="sidebar-footer">
        <?php if (function_exists('getCurrentEmpresaNome') && getCurrentEmpresaNome() && $usuario_tipo !== 'super_admin'): ?>
        <div class="empresa-info">
            <i class="fas fa-building"></i> 
            <span title="<?php echo htmlspecialchars(getCurrentEmpresaNome()); ?>">
                <?php 
                $empresa_nome = function_exists('getCurrentEmpresaNome') ? getCurrentEmpresaNome() : 'Empresa';
                echo strlen($empresa_nome) > 25 ? substr($empresa_nome, 0, 22) . '...' : $empresa_nome;
                ?>
            </span>
        </div>
        <?php endif; ?>
        
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-details">
                <span class="user-name" title="<?php echo $_SESSION['usuario_nome'] ?? 'Usuário'; ?>">
                    <?php 
                    $nome = $_SESSION['usuario_nome'] ?? 'Usuário';
                    echo strlen($nome) > 20 ? substr($nome, 0, 18) . '...' : $nome;
                    ?>
                </span>
                <span class="user-role">
                    <?php 
                    $roles = [
                        'super_admin' => '👑 Super Admin',
                        'admin_empresa' => '🏢 Administrador',
                        'gestor' => '📊 Gestor',
                        'supervisor' => '👁️ Supervisor',
                        'funcionario' => '👤 Funcionário'
                    ];
                    echo $roles[$usuario_tipo] ?? ucfirst($usuario_tipo);
                    ?>
                </span>
            </div>
        </div>
        
        <a href="/ponto_empresarial/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sair</span>
        </a>
    </div>
</aside>

<style>
.sidebar {
    width: 280px;
    background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
    color: white;
    position: fixed;
    height: 100vh;
    display: flex;
    flex-direction: column;
    z-index: 100;
    transition: transform 0.3s ease;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
}

.sidebar-header {
    padding: 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header .logo {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 20px;
    font-weight: 700;
}

.sidebar-nav {
    flex: 1;
    padding: 16px;
    overflow-y: auto;
}

.sidebar-nav::-webkit-scrollbar {
    width: 4px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 4px;
}

.nav-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.1);
    margin: 12px 0;
}

.nav-section-title {
    padding: 8px 16px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgba(255, 255, 255, 0.5);
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    border-radius: 12px;
    margin-bottom: 4px;
    transition: all 0.3s ease;
    font-size: 14px;
}

.nav-item:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    transform: translateX(4px);
}

.nav-item.active {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.nav-item i {
    width: 24px;
    font-size: 18px;
    text-align: center;
}

.sidebar-footer {
    padding: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.empresa-info {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 8px 12px;
    margin-bottom: 16px;
    text-align: center;
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.user-avatar i {
    font-size: 40px;
    opacity: 0.9;
}

.user-details {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    flex: 1;
}

.user-name {
    font-weight: 600;
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    font-size: 10px;
    opacity: 0.7;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.logout-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 13px;
}

.logout-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateX(4px);
}

.notificacao-badge {
    background: #ef4444;
    color: white;
    border-radius: 20px;
    padding: 2px 8px;
    font-size: 10px;
    font-weight: bold;
    margin-left: auto;
    min-width: 18px;
    text-align: center;
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        box-shadow: none;
    }
    
    .sidebar.open {
        transform: translateX(0);
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
    }
}
</style>

<script>
// Fun��o para buscar notifica��es n�o lidas
function buscarNotificacoesNaoLidas() {
    fetch('/ponto_empresarial/modules/notificacoes/buscar.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.notificacao-badge');
            if (!badge) return;
            if (data.success && data.total > 0) {
                badge.textContent = data.total;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(error => console.log('Erro ao buscar notifica��es:', error));
}

setInterval(buscarNotificacoesNaoLidas, 30000);
buscarNotificacoesNaoLidas();
</script>
