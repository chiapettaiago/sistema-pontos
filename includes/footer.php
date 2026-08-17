<?php // includes/footer.php ?>
            </div><!-- /.pf-content -->
        </div><!-- /.pf-main-content -->
    </div><!-- /.pf-app-wrapper -->

    <!-- Bootstrap 5 Bundle (Popper included) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Theme JS (Bootstrap data-bs-theme) -->
    <script src="/assets/js/theme.js"></script>

    <!-- App JS -->
    <script src="/assets/js/app.js"></script>

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
            if (dateEl) dateEl.textContent = now.toLocaleDateString('pt-BR', { weekday:'long', day:'2-digit', month:'long' });
            if (timeEl) timeEl.textContent = now.toLocaleTimeString('pt-BR');
        }
        tick();
        setInterval(tick, 1000);
    })();

    // Inicializar tooltips Bootstrap
    document.addEventListener('DOMContentLoaded', function () {
        var tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(function (el) { new bootstrap.Tooltip(el); });
    });
    </script>
</body>
</html>
