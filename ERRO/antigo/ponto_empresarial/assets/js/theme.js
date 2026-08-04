// assets/js/theme.js - Controle de Tema Simplificado

(function() {
    // Inicializar tema
    function initTheme() {
        const savedTheme = localStorage.getItem('ponto_facil_theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
            setTheme('dark');
        } else {
            setTheme('light');
        }
        
        criarBotaoTema();
    }
    
    function setTheme(theme) {
        const body = document.body;
        
        if (theme === 'dark') {
            body.classList.add('dark-theme');
            body.classList.remove('light-theme');
            localStorage.setItem('ponto_facil_theme', 'dark');
        } else {
            body.classList.add('light-theme');
            body.classList.remove('dark-theme');
            localStorage.setItem('ponto_facil_theme', 'light');
        }
        
        atualizarBotoes(theme);
    }
    
    function toggleTheme() {
        const isDark = document.body.classList.contains('dark-theme');
        setTheme(isDark ? 'light' : 'dark');
    }
    
    function criarBotaoTema() {
        // Aguardar o DOM carregar
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', criarBotaoTema);
            return;
        }
        
        // Verificar se o botão já existe
        let toggleBtn = document.getElementById('themeToggleBtn');
        
        if (!toggleBtn) {
            // Tentar encontrar o container
            const topBarActions = document.querySelector('.top-bar-actions');
            if (topBarActions && !document.querySelector('.theme-toggle-btn')) {
                const btnHtml = `
                    <div class="theme-toggle-btn" id="themeToggleBtn">
                        <div class="theme-option light-option" data-theme="light">
                            <i class="fas fa-sun"></i>
                        </div>
                        <div class="theme-option dark-option" data-theme="dark">
                            <i class="fas fa-moon"></i>
                        </div>
                    </div>
                `;
                topBarActions.insertAdjacentHTML('beforeend', btnHtml);
                toggleBtn = document.getElementById('themeToggleBtn');
            }
        }
        
        if (toggleBtn) {
            // Remover event listeners antigos
            const newBtn = toggleBtn.cloneNode(true);
            toggleBtn.parentNode.replaceChild(newBtn, toggleBtn);
            toggleBtn = newBtn;
            
            // Adicionar event listeners
            const lightOption = toggleBtn.querySelector('.light-option');
            const darkOption = toggleBtn.querySelector('.dark-option');
            
            if (lightOption) {
                lightOption.addEventListener('click', function(e) {
                    e.stopPropagation();
                    setTheme('light');
                });
            }
            
            if (darkOption) {
                darkOption.addEventListener('click', function(e) {
                    e.stopPropagation();
                    setTheme('dark');
                });
            }
            
            toggleBtn.addEventListener('click', function(e) {
                // Se clicou no botão mas não nos ícones específicos
                if (e.target === toggleBtn || e.target.parentElement === toggleBtn) {
                    toggleTheme();
                }
            });
        }
        
        atualizarBotoes(localStorage.getItem('ponto_facil_theme') || 
            (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
    }
    
    function atualizarBotoes(theme) {
        const lightOption = document.querySelector('.light-option');
        const darkOption = document.querySelector('.dark-option');
        
        if (lightOption && darkOption) {
            if (theme === 'dark') {
                lightOption.classList.remove('active');
                darkOption.classList.add('active');
            } else {
                lightOption.classList.add('active');
                darkOption.classList.remove('active');
            }
        }
    }
    
    // Iniciar quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
})();