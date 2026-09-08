// Adicionar ao final do arquivo app.js

// ============================================
// ANIMAÇÕES E TRANSIÇÕES
// ============================================

// Adicionar classe fade-in aos elementos ao carregar
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stat-card, .table-card, .chart-card, .info-card');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.add('fade-in');
        }, index * 50);
    });
});

// ============================================
// TOOLTIPS PERSONALIZADOS
// ============================================

document.querySelectorAll('[title]').forEach(el => {
    el.addEventListener('mouseenter', function(e) {
        const title = this.getAttribute('title');
        if (!title) return;
        
        const tooltip = document.createElement('div');
        tooltip.className = 'custom-tooltip';
        tooltip.textContent = title;
        tooltip.style.cssText = `
            position: fixed;
            background: var(--text-primary);
            color: var(--bg-primary);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            z-index: 9999;
            pointer-events: none;
            white-space: nowrap;
            box-shadow: var(--shadow-md);
        `;
        
        document.body.appendChild(tooltip);
        
        const updatePosition = (e) => {
            tooltip.style.left = (e.clientX + 15) + 'px';
            tooltip.style.top = (e.clientY - 30) + 'px';
        };
        
        updatePosition(e);
        
        this.addEventListener('mousemove', updatePosition);
        
        this.addEventListener('mouseleave', () => {
            tooltip.remove();
            this.removeEventListener('mousemove', updatePosition);
        }, { once: true });
    });
});

// ============================================
// CONFIRMAÇÃO DE AÇÕES
// ============================================

document.querySelectorAll('.confirm-action, .btn-danger').forEach(btn => {
    btn.addEventListener('click', function(e) {
        const message = this.getAttribute('data-confirm') || 'Tem certeza que deseja realizar esta ação?';
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
});

// ============================================
// NOTIFICAÇÕES MODERNAS
// ============================================

function showNotification(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
        <div class="notification-progress"></div>
    `;
    
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: var(--bg-primary);
        color: var(--text-primary);
        border-radius: 12px;
        box-shadow: var(--shadow-lg);
        min-width: 280px;
        max-width: 400px;
        z-index: 9999;
        overflow: hidden;
        animation: slideInRight 0.3s ease;
    `;
    
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };
    
    notification.querySelector('.notification-progress').style.cssText = `
        height: 3px;
        background: ${colors[type] || colors.info};
        width: 100%;
        animation: progress ${duration}ms linear forwards;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, duration);
}

// Estilos para notificações
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    @keyframes progress {
        from {
            width: 100%;
        }
        to {
            width: 0%;
        }
    }
    
    .notification-content {
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .notification-content i {
        font-size: 20px;
    }
`;
document.head.appendChild(notificationStyles);

// Sobrescrever mostrarMensagem existente
if (typeof window.mostrarMensagem !== 'undefined') {
    window.mostrarMensagem = showNotification;
}

// ============================================
// CARREGAMENTO DE DADOS COM Skeleton
// ============================================

function showSkeleton(elementId, count = 3) {
    const container = document.getElementById(elementId);
    if (!container) return;
    
    const skeletonHtml = Array(count).fill(0).map(() => `
        <div class="skeleton-row">
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
        </div>
    `).join('');
    
    container.innerHTML = skeletonHtml;
}

const skeletonStyles = document.createElement('style');
skeletonStyles.textContent = `
    .skeleton-row {
        display: flex;
        gap: 16px;
        margin-bottom: 12px;
    }
    
    .skeleton-cell {
        height: 40px;
        background: linear-gradient(90deg, var(--border-color) 25%, var(--bg-tertiary) 50%, var(--border-color) 75%);
        background-size: 200% 100%;
        animation: skeletonLoading 1.5s infinite;
        border-radius: 8px;
        flex: 1;
    }
    
    @keyframes skeletonLoading {
        0% {
            background-position: 200% 0;
        }
        100% {
            background-position: -200% 0;
        }
    }
`;
document.head.appendChild(skeletonStyles);

// ============================================
// FORMULARIOS DE DADOS EM MODAIS
// ============================================
document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return;

    const content = document.querySelector('.pf-content');
    if (!content) return;

    const forms = Array.from(content.querySelectorAll('form')).filter(function (form) {
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        const style = (form.getAttribute('style') || '').replace(/\s/g, '').toLowerCase();
        const quickAction = form.matches('.inline-form, .d-inline, .filters-form, .row') ||
            form.closest('.modal, table, .table, .dropdown-menu, .btn-group');
        const disabled = form.dataset.pfModal === 'false' || form.hidden || style.includes('display:none');
        if (method !== 'post' || quickAction || disabled) return false;

        const fields = form.querySelectorAll('input:not([type="hidden"]), select, textarea').length;
        return fields >= 2 || Boolean(form.closest('.form-card, .form-container'));
    });

    if (!forms.length) return;

    const entries = forms.map(function (form, index) {
        const id = 'pfFormModal' + index;
        const shell = form.closest('.form-card');
        const movable = shell && shell.querySelectorAll('form').length === 1 ? shell : form;
        const heading = movable.querySelector('.form-header h1, .form-header h2, .form-header h3, h1, h2, h3') ||
            content.querySelector('.pf-topbar-title, .pf-page-header h1, .dashboard-header h1');
        const title = (heading && heading.textContent.trim()) || form.getAttribute('aria-label') || 'Preencher dados';
        const launcher = document.createElement('div');
        launcher.className = 'pf-form-modal-launcher';
        launcher.innerHTML = '<div><span class="pf-form-modal-kicker">Formulário</span><strong></strong></div>' +
            '<button type="button" class="btn btn-primary"><i class="fas fa-pen-to-square"></i> Abrir formulário</button>';
        launcher.querySelector('strong').textContent = title;

        const modal = document.createElement('div');
        modal.className = 'modal fade pf-form-modal';
        modal.id = id;
        modal.tabIndex = -1;
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = '<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">' +
            '<div class="modal-content"><div class="modal-header">' +
            '<div><span class="pf-form-modal-kicker">Formulário</span><h2 class="modal-title fs-5"></h2></div>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>' +
            '</div><div class="modal-body"></div></div></div>';
        modal.querySelector('.modal-title').textContent = title;

        movable.parentNode.insertBefore(launcher, movable);
        modal.querySelector('.modal-body').appendChild(movable);
        document.body.appendChild(modal);

        const instance = bootstrap.Modal.getOrCreateInstance(modal, {
            backdrop: 'static',
            keyboard: true
        });
        launcher.querySelector('button').addEventListener('click', function () { instance.show(); });
        form.addEventListener('submit', function () {
            modal.classList.add('pf-form-modal--submitting');
        });

        return { modal: modal, instance: instance };
    });

    // Páginas exclusivamente de cadastro/edição já apresentam o formulário aberto.
    if (entries.length === 1) entries[0].instance.show();
});
