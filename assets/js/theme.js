// assets/js/theme.js — Bootstrap 5 data-bs-theme integration
(function () {
    var KEY = 'pf_theme';

    function getPreferred() {
        var saved = localStorage.getItem(KEY);
        if (saved) return saved;
        return 'light';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem(KEY, theme);

        // Atualiza botoes visuais se existirem
        document.querySelectorAll('.pf-theme-opt').forEach(function (el) {
            el.classList.toggle('active', el.dataset.theme === theme);
        });
    }

    function toggleTheme() {
        var current = document.documentElement.getAttribute('data-bs-theme') || 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
    }

    // Aplica tema imediatamente (antes do DOMContentLoaded para evitar flash)
    applyTheme(getPreferred());

    document.addEventListener('DOMContentLoaded', function () {
        // Bind nos botoes de tema
        document.querySelectorAll('.pf-theme-opt').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.stopPropagation();
                applyTheme(el.dataset.theme);
            });
        });

        // Bind no container do toggle (clique geral fora dos icones)
        var toggleEl = document.getElementById('pfThemeToggle');
        if (toggleEl) {
            toggleEl.addEventListener('click', function (e) {
                if (!e.target.closest('.pf-theme-opt')) toggleTheme();
            });
        }
    });

    // Expoe globalmente para uso em outros scripts
    window.pfSetTheme = applyTheme;
    window.pfToggleTheme = toggleTheme;
})();
