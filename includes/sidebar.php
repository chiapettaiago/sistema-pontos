<?php
// includes/sidebar — Bootstrap 5 (links via $baseUrl)
$activePage   = $activePage ?? '';
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'funcionario';
$podeVerFuncionarios = function_exists('hasPermission') && hasPermission('ver_funcionarios');
$podeGerenciarFiliais = function_exists('hasPermission') && hasPermission('gerenciar_filiais');
$podeVerRelatorios = function_exists('hasPermission') && hasPermission('ver_relatorios');
$podeGerenciarFuncionarios = function_exists('hasPermission') && hasPermission('gerenciar_funcionarios');

// $baseUrl e calculado no header antes de incluir este arquivo
// Fallback para garantir que nunca sera undefined
if (!isset($baseUrl)) {
    $baseUrl = function_exists('appBasePath') ? appBasePath() : '';
}

function pfNav(string $href, string $icon, string $label, bool $active, string $badge = ''): string {
    $cls    = $active ? ' active' : '';
    $bdg    = $badge !== '' ? "<span class=\"pf-nav-badge\">$badge</span>" : '';
    return "<a href=\"$href\" class=\"pf-nav-item$cls\" aria-label=\"$label\" title=\"$label\"><i class=\"fas $icon\"></i><span>$label</span>$bdg</a>";
}

$homeUrl = function_exists('appUrl')
    ? appUrl(appHomeRouteFor($usuario_tipo))
    : $baseUrl . '/index';
?>

<aside class="pf-sidebar" id="pfSidebar">
    <!-- Logo -->
    <div class="pf-sidebar-header">
        <a href="<?php echo htmlspecialchars($homeUrl); ?>" class="pf-sidebar-logo">
            <div class="logo-icon"><i class="fas fa-clock"></i></div>
            <span>PontoFácil</span>
        </a>
    </div>

    <!-- Nav -->
    <nav class="pf-sidebar-nav">

        <!-- DASHBOARD (todos exceto funcionario comum) -->
        <?php if ($usuario_tipo !== 'funcionario'): ?>
        <?php echo pfNav($homeUrl, 'fa-gauge', 'Dashboard', in_array($activePage, ['dashboard', 'admin_dashboard'], true)); ?>
        <?php endif; ?>

        <!-- SUPER ADMIN -->
        <?php if ($usuario_tipo === 'super_admin'): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-crown"></i><span>Administração</span></div>
        <?php echo pfNav($baseUrl.'/modules/admin/empresas/index', 'fa-building', 'Empresas', $activePage === 'admin_empresas'); ?>
        <?php echo pfNav($baseUrl.'/modules/admin/planos/index', 'fa-crown', 'Planos', $activePage === 'admin_planos'); ?>
        <?php echo pfNav($baseUrl.'/modules/admin/assinaturas/index', 'fa-receipt', 'Assinaturas', $activePage === 'admin_assinaturas'); ?>
        <?php endif; ?>

        <!-- DASHBOARD EMPRESA -->
        <?php if ($usuario_tipo === 'admin_empresa' || $usuario_tipo === 'gestor'): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-chart-pie"></i><span>Visão Geral</span></div>
        <?php echo pfNav($baseUrl.'/modules/dashboard_empresa/index', 'fa-chart-line', 'Dashboard Empresa', $activePage === 'dashboard_empresa'); ?>
        <?php endif; ?>

        <!-- EMPRESA -->
        <?php if ($usuario_tipo !== 'funcionario' && $usuario_tipo !== 'super_admin'): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-building"></i><span>Empresa</span></div>
        <?php endif; ?>

        <?php if ($usuario_tipo === 'admin_empresa'): ?>
        <?php echo pfNav($baseUrl.'/modules/usuarios/index', 'fa-user-shield', 'Usuários', $activePage === 'usuarios'); ?>
        <?php endif; ?>

        <?php if ($podeVerFuncionarios): ?>
        <?php echo pfNav($baseUrl.'/modules/funcionarios/index', 'fa-users', 'Funcionários', $activePage === 'funcionarios'); ?>
        <?php endif; ?>

        <?php if ($podeGerenciarFiliais): ?>
        <?php echo pfNav($baseUrl.'/modules/filiais/index', 'fa-store', 'Filiais', $activePage === 'filiais'); ?>
        <?php endif; ?>

        <!-- PONTO -->
        <?php if ($usuario_tipo !== 'super_admin'): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-fingerprint"></i><span>Ponto</span></div>
        <?php echo pfNav($baseUrl.'/modules/ponto/ponto', 'fa-clock', 'Registrar Ponto', $activePage === 'ponto'); ?>
        <?php echo pfNav($baseUrl.'/modules/ponto/extrato', 'fa-rectangle-list', 'Meu Extrato', $activePage === 'extrato'); ?>
        <?php if ($usuario_tipo === 'admin_empresa'): ?>
        <?php echo pfNav($baseUrl.'/modules/ponto/link_publico', 'fa-link', 'Link público', $activePage === 'link_publico'); ?>
        <?php endif; ?>

        <?php if (in_array($usuario_tipo, ['admin_empresa','gestor','supervisor'])): ?>
        <?php echo pfNav($baseUrl.'/modules/ponto/autorizar_gerente', 'fa-user-check', 'Autorizar Ponto', $activePage === 'autorizar'); ?>
        <?php endif; ?>
        <?php endif; ?>

        <!-- BIOMETRIA -->
        <?php if (in_array($usuario_tipo, ['super_admin','admin_empresa'], true)): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-fingerprint"></i><span>Biometria</span></div>
        <?php echo pfNav($baseUrl.'/modules/biometrico/index', 'fa-id-card', 'Visão geral', $activePage === 'biometrico'); ?>
        <?php echo pfNav($baseUrl.'/modules/biometrico/cadastrar?tipo=digital', 'fa-fingerprint', 'Cadastrar digital', $activePage === 'biometrico_digital'); ?>
        <?php echo pfNav($baseUrl.'/modules/biometrico/cadastrar?tipo=facial', 'fa-face-smile', 'Cadastrar facial', $activePage === 'biometrico_facial'); ?>
        <?php echo pfNav($baseUrl.'/modules/biometrico/manual', 'fa-book-open', 'Manual facial', $activePage === 'biometrico_manual'); ?>
        <?php endif; ?>

        <!-- ESCALAS -->
        <?php if ($podeVerRelatorios): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-calendar"></i><span>Escalas</span></div>
        <?php echo pfNav($baseUrl.'/modules/escala/index', 'fa-calendar-week', 'Escalas', $activePage === 'escala'); ?>
        <?php echo pfNav($baseUrl.'/modules/escala/configurar', 'fa-gear', 'Configurar Escala', $activePage === 'escala_config'); ?>
        <?php endif; ?>

        <!-- SOLICITAÇÕES -->
        <?php if (!empty($_SESSION['funcionario_id'])): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-paper-plane"></i><span>Solicitações</span></div>
        <?php echo pfNav($baseUrl.'/modules/solicitacoes/index', 'fa-envelope-open-text', 'Minhas Solicitações', $activePage === 'solicitacoes'); ?>
        <?php echo pfNav($baseUrl.'/modules/solicitacoes/nova', 'fa-plus-circle', 'Nova Solicitação', $activePage === 'nova_solicitacao'); ?>
        <?php endif; ?>

        <?php if (in_array($usuario_tipo, ['admin_empresa','gestor'], true)): ?>
        <?php if (empty($_SESSION['funcionario_id'])): ?><div class="pf-nav-divider"></div><?php endif; ?>
        <?php echo pfNav($baseUrl.'/modules/solicitacoes/admin', 'fa-clipboard-check', 'Gerenciar Solicitações', $activePage === 'solicitacoes_admin'); ?>
        <?php endif; ?>

        <!-- RELATÓRIOS -->
        <?php if (in_array($usuario_tipo, ['admin_empresa','gestor'])): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-chart-bar"></i><span>Relatórios</span></div>
        <?php echo pfNav($baseUrl.'/modules/relatorios/index', 'fa-file-lines', 'Relatórios', $activePage === 'relatorios'); ?>
        <?php echo pfNav($baseUrl.'/modules/ponto/gerenciar_batidas', 'fa-pen-to-square', 'Gerenciar Batidas', $activePage === 'gerenciar_batidas'); ?>
        <?php echo pfNav($baseUrl.'/modules/relatorios/horas_trabalhadas', 'fa-hourglass-half', 'Horas Trabalhadas', $activePage === 'rel_horas'); ?>
        <?php echo pfNav($baseUrl.'/modules/relatorios/banco_horas', 'fa-piggy-bank', 'Banco de Horas', $activePage === 'rel_banco'); ?>
        <?php echo pfNav($baseUrl.'/modules/relatorios/atrasos_faltas', 'fa-triangle-exclamation', 'Atrasos e Faltas', $activePage === 'rel_atrasos'); ?>
        <?php echo pfNav($baseUrl.'/modules/relatorios/horas_extras', 'fa-plus-square', 'Horas Extras', $activePage === 'rel_extras'); ?>
        <?php endif; ?>

        <!-- SEGURANÇA -->
        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-shield-halved"></i><span>Segurança</span></div>
        <?php echo pfNav($baseUrl.'/modules/auditoria/index', 'fa-history', 'Auditoria', $activePage === 'auditoria'); ?>
        <?php endif; ?>

        <!-- NOTIFICAÇÕES -->
        <?php if ($usuario_tipo === 'super_admin' || $usuario_tipo === 'admin_empresa'): ?>
        <div class="pf-nav-divider"></div>
        <div class="pf-nav-section"><i class="fas fa-bell"></i><span>Notificações</span></div>
        <?php echo pfNav($baseUrl.'/modules/notificacoes/index', 'fa-bell', 'Notificações', $activePage === 'notificacoes', ''); ?>
        <?php echo pfNav($baseUrl.'/modules/notificacoes/config', 'fa-gear', 'Configurar Notif.', $activePage === 'notificacoes_config'); ?>
        <?php endif; ?>

    </nav>

</aside>

<script>
// Badge de notificacoes nao lidas
(function () {
    var base = '<?php echo $baseUrl; ?>';
    function buscar() {
        fetch(base + '/modules/notificacoes/buscar')
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
