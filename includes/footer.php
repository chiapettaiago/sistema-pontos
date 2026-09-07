<?php // includes/footer.php ?>
            </div><!-- /.pf-content -->
        </div><!-- /.pf-main-content -->
    </div><!-- /.pf-app-wrapper -->

    <!-- Bootstrap 5 Bundle (Popper included) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Theme JS (Bootstrap data-bs-theme) -->
    <script src="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/assets/js/theme.js"></script>

    <!-- App JS -->
    <script src="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/assets/js/app.js"></script>

    <script>
    // Sidebar toggle mobile
    (function () {
        var sidebar  = document.querySelector('.pf-sidebar');
        var backdrop = document.getElementById('pfSidebarBackdrop');
        var toggle   = document.getElementById('pfMenuToggle');

        function openSidebar()  { sidebar && sidebar.classList.add('show'); backdrop && backdrop.classList.add('show'); }
        function closeSidebar() { sidebar && sidebar.classList.remove('show'); backdrop && backdrop.classList.remove('show'); }

        if (toggle)   toggle.addEventListener('click', openSidebar);
        if (backdrop) backdrop.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSidebar(); });
    })();

    // Relógio
    (function () {
        function tick() {
            var now  = new Date();
            var dateEl = document.getElementById('pfDate');
            var timeEl = document.getElementById('pfTime');
            if (dateEl) dateEl.textContent = now.toLocaleDateString('pt-BR', { weekday:'short', day:'2-digit', month:'short' });
            if (timeEl) timeEl.textContent = now.toLocaleTimeString('pt-BR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        tick();
        setInterval(tick, 1000);
    })();

    // Inicializar tooltips Bootstrap
    document.addEventListener('DOMContentLoaded', function () {
        var tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(function (el) { new bootstrap.Tooltip(el); });
    });

    // Evita repetir no conteúdo o mesmo título que já aparece na navbar.
    (function () {
        var navbarTitle = document.querySelector('.pf-topbar-title');
        var content = document.querySelector('.pf-content');
        if (!navbarTitle || !content) return;

        function normalizeTitle(value) {
            return (value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/\b(da|das|de|do|dos)\b/g, ' ')
                .replace(/[^a-z0-9]+/g, ' ')
                .trim()
                .replace(/\s+/g, ' ');
        }

        var expected = normalizeTitle(navbarTitle.textContent);
        var candidates = content.querySelectorAll([
            '.pf-page-header h1',
            '.pf-page-header h2',
            '.module-header .module-title > h1',
            '.module-header .module-title > h2',
            '.module-header > h1',
            '.module-header > h2',
            '.dashboard-header h1',
            '.dashboard-header h2'
        ].join(','));

        candidates.forEach(function (title) {
            var isStandardPageHeader = title.closest('.pf-page-header') !== null;
            if (isStandardPageHeader || normalizeTitle(title.textContent) === expected) {
                title.classList.add('pf-redundant-page-title');
                title.setAttribute('aria-hidden', 'true');
            }
        });
    })();
    </script>
</body>
</html>
