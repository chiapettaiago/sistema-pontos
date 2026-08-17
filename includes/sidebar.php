<?php
// includes/sidebar.php — Bootstrap 5 (links via $baseUrl)
$activePage   = $activePage ?? '';
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';

// $baseUrl e calculado no header.php antes de incluir este arquivo
// Fallback para garantir que nunca sera undefined
if (!isset($baseUrl)) {
    $_bd  = str_replace('\\', '/', dirname(__DIR__));
    $_dr  = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/\\'));
    $baseUrl = rtrim(str_replace($_dr, '', $_bd), '/');
}

function pfNav(string $href, string $icon, string $label, bool $active, string $badge = ''): string {
    $cls    = $active ? ' active' : '';
    $bdg    = $badge !== '' ? "<span class=\"pf-nav-badge\">$badge</span>" : '';
    return "<a href=\"$href\" class=\"pf-nav-item$cls\"><i class=\"fas $icon\"></i><span>$label</span>$bdg</a>";
}
?>

<aside class="pf-sidebar" id="pfSidebar">
    <!-- Logo -->
    <div class="pf-sidebar-header">
        <a href="<?php echo $baseUrl; ?>/" class="pf-sidebar-logo">
            <div class="logo-icon"><i class="fas fa-clock"></i></div>
            <span>PontoFácil</span>
        </a>
    </div>

    <!-- Nav -->
    <nav class="pf-sidebar-nav">

        <!-- DASHBOARD (todos exceto funcionario comum) -->
        <?php if ($usuario_tipo !== 'funcionario'): ?>
        <?php echo pfNav($baseUrl.'/index.php', 'fa-tachometer-alt', 'Dashboard', $activePage === 'dashboard'); ?>
        <?php endif; ?>

        <!-- SUPER ADMIN -->
        <?php if ($usuario_tipo === 'super_admin'): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-crown"></i><span>Administração</span></div>
        <?php echo pfNav($baseUrl.'/modules/admin/empresas/index.php', 'fa-building', 'Empresas', $activePage === 'admin_empresas'); ?>
        <?php echo pfNav($baseUrl.'/modules/admin/planos/index.php', 'fa-crown', 'Planos', $activePage === 'admin_planos'); ?>
        <?php echo pfNav($baseUrl.'/modules/admin/assinaturas/index.php', 'fa-receipt', 'Assinaturas', $activePage === 'admin_assinaturas'); ?>
        <?php endif; ?>

        <!-- DASHBOARD EMPRESA -->
        <?php if ($usuario_tipo === 'admin_empresa' || $usuario_tipo === 'gestor'): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-chart-pie"></i><span>Visão Geral</span></div>
        <?php echo pfNav($baseUrl.'/modules/dashboard_empresa/index.php', 'fa-chart-line', 'Dashboard Empresa', $activePage === 'dashboard_empresa'); ?>
        <?php endif; ?>

        <!-- EMPRESA -->
        <?php if ($usuario_tipo !== 'funcionario'): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-building"></i><span>Empresa</span></div>
        <?php endif; ?>

        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
        <?php echo pfNav($baseUrl.'/modules/usuarios/index.php', 'fa-user-shield', 'Usuários', $activePage === 'usuarios'); ?>
        <?php endif; ?>

        <?php if (in_array($usuario_tipo, ['super_admin','admin_empresa','gestor'])): ?>
        <?php echo pfNav($baseUrl.'/modules/funcionarios/index.php', 'fa-users', 'Funcionários', $activePage === 'funcionarios'); ?>
        <?php endif; ?>

        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
        <?php echo pfNav($baseUrl.'/modules/filiais/index.php', 'fa-store', 'Filiais', $activePage === 'filiais'); ?>
        <?php endif; ?>

        <!-- PONTO -->
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-fingerprint"></i><span>Ponto</span></div>
        <?php echo pfNav($baseUrl.'/modules/ponto/ponto.php', 'fa-clock', 'Registrar Ponto', $activePage === 'ponto'); ?>
        <?php echo pfNav($baseUrl.'/modules/ponto/extrato.php', 'fa-list-alt', 'Meu Extrato', $activePage === 'extrato'); ?>

        <?php if (in_array($usuario_tipo, ['super_admin','admin_empresa','gestor','supervisor'])): ?>
        <?php echo pfNav($baseUrl.'/modules/ponto/autorizar_gerente.php', 'fa-user-check', 'Autorizar Ponto', $activePage === 'autorizar'); ?>
        <?php endif; ?>

        <!-- BIOMETRIA -->
        <?php if (in_array($usuario_tipo, ['super_admin','admin_empresa','gestor','supervisor'])): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-fingerprint"></i><span>Biometria</span></div>
        <?php echo pfNav($baseUrl.'/modules/funcionarios/cadastro_facial.php', 'fa-camera', 'Cad. Facial', $activePage === 'cadastro_facial'); ?>
        <?php echo pfNav($baseUrl.'/modules/biometrico/index.php', 'fa-id-card', 'Biométrico', $activePage === 'biometrico'); ?>
        <?php endif; ?>

        <!-- ESCALAS -->
        <?php if (in_array($usuario_tipo, ['super_admin','admin_empresa','gestor'])): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-calendar-alt"></i><span>Escalas</span></div>
        <?php echo pfNav($baseUrl.'/modules/escala/index.php', 'fa-calendar-week', 'Escalas', $activePage === 'escala'); ?>
        <?php echo pfNav($baseUrl.'/modules/escala/configurar.php', 'fa-cog', 'Configurar Escala', $activePage === 'escala_config'); ?>
        <?php endif; ?>

        <!-- SOLICITAÇÕES -->
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-paper-plane"></i><span>Solicitações</span></div>
        <?php echo pfNav($baseUrl.'/modules/solicitacoes/index.php', 'fa-envelope-open-text', 'Minhas Solicitações', $activePage === 'solicitacoes'); ?>
        <?php echo pfNav($baseUrl.'/modules/solicitacoes/nova.php', 'fa-plus-circle', 'Nova Solicitação', $activePage === 'nova_solicitacao'); ?>

        <?php if (in_array($usuario_tipo, ['super_admin','admin_empresa','gestor','supervisor'])): ?>
        <?php echo pfNav($baseUrl.'/modules/solicitacoes/admin.php', 'fa-clipboard-check', 'Gerenciar Solicitações', $activePage === 'solicitacoes_admin'); ?>
        <?php endif; ?>

        <!-- RELATÓRIOS -->
        <?php if (in_array($usuario_tipo, ['super_admin','admin_empresa','gestor'])): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-chart-bar"></i><span>Relatórios</span></div>
        <?php echo pfNav($baseUrl.'/modules/relatorios/index.php', 'fa-file-alt', 'Relatórios', $activePage === 'relatorios'); ?>
        <?php echo pfNav($baseUrl.'/modules/relatorios/horas_trabalhadas.php', 'fa-hourglass-half', 'Horas Trabalhadas', $activePage === 'rel_horas'); ?>
        <?php echo pfNav($baseUrl.'/modules/relatorios/banco_horas.php', 'fa-piggy-bank', 'Banco de Horas', $activePage === 'rel_banco'); ?>
        <?php echo pfNav($baseUrl.'/modules/relatorios/atrasos_faltas.php', 'fa-exclamation-triangle', 'Atrasos e Faltas', $activePage === 'rel_atrasos'); ?>
        <?php echo pfNav($baseUrl.'/modules/relatorios/horas_extras.php', 'fa-plus-square', 'Horas Extras', $activePage === 'rel_extras'); ?>
        <?php endif; ?>

        <!-- SEGURANÇA -->
        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-shield-alt"></i><span>Segurança</span></div>
        <?php echo pfNav($baseUrl.'/modules/auditoria/index.php', 'fa-history', 'Auditoria', $activePage === 'auditoria'); ?>
        <?php endif; ?>

        <!-- NOTIFICAÇÕES -->
        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-bell"></i><span>Notificações</span></div>
        <?php echo pfNav($baseUrl.'/modules/notificacoes/index.php', 'fa-bell', 'Notificações', $activePage === 'notificacoes', ''); ?>
        <?php echo pfNav($baseUrl.'/modules/notificacoes/config.php', 'fa-cog', 'Configurar Notif.', $activePage === 'notificacoes_config'); ?>
        <?php endif; ?>

    </nav>

    <!-- Footer do Sidebar -->
    <div class="pf-sidebar-footer">
        <?php if (function_exists('getCurrentEmpresaNome') && getCurrentEmpresaNome() && $usuario_tipo !== 'super_admin'): ?>
        <div class="pf-empresa-badge">
            <i class="fas fa-building me-1"></i>
            <?php
            $en = getCurrentEmpresaNome();
            echo htmlspecialchars(strlen($en) > 28 ? substr($en, 0, 25).'...' : $en);
            ?>
        </div>
        <?php endif; ?>

        <div class="pf-user-info">
            <div class="pf-user-avatar"><i class="fas fa-user-circle text-white"></i></div>
            <div class="flex-grow-1 overflow-hidden">
                <div class="pf-user-name">
                    <?php
                    $nome = $_SESSION['usuario_nome'] ?? 'Usuário';
                    echo htmlspecialchars(strlen($nome) > 22 ? substr($nome, 0, 20).'...' : $nome);
                    ?>
                </div>
                <div class="pf-user-role">
                    <?php
                    $roles = [
                        'super_admin'   => '⭐ Super Admin',
                        'admin_empresa' => '🏢 Administrador',
                        'gestor'        => '📊 Gestor',
                        'supervisor'    => '👁️ Supervisor',
                        'funcionario'   => '👤 Funcionário',
                    ];
                    echo $roles[$usuario_tipo] ?? ucfirst($usuario_tipo);
                    ?>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-3 px-1">
            <span class="small opacity-75 text-white"><i class="fas fa-adjust me-1"></i>Tema</span>
            <div class="pf-theme-toggle" title="Alternar tema">
                <div class="pf-theme-opt" data-theme="light" title="Modo claro">
                    <i class="fas fa-sun"></i>
                </div>
                <div class="pf-theme-opt" data-theme="dark" title="Modo escuro">
                    <i class="fas fa-moon"></i>
                </div>
            </div>
        </div>

        <a href="<?php echo $baseUrl; ?>/logout.php" class="pf-logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sair</span>
        </a>
    </div>
</aside>

<script>
// Badge de notificacoes nao lidas
(function () {
    var base = '<?php echo $baseUrl; ?>';
    function buscar() {
        fetch(base + '/modules/notificacoes/buscar.php')
            .then(function(r){ return r.json(); })
            .then(function(data) {
                var badge = document.querySelector('.pf-nav-badge');
                if (!badge) return;
                if (data.success && data.total > 0) {
                    badge.textContent = data.total;
                    badge.style.display = 'inline-flex';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(function(){});
    }
    buscar();
    setInterval(buscar, 30000);
})();
</script>
