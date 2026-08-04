<?php
/**
 * PÁGINA INICIAL - DASHBOARD PRINCIPAL
 * Força autenticação antes de qualquer conteúdo
 */
require_once __DIR__ . '/includes/auth_check.php';

// Força login obrigatório
forceAuthentication();

// Log de acesso
logAccess('acessou_dashboard', 'Página inicial');

// Carrega header DEPOIS da verificação
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h1 class="card-title">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </h1>
                    <hr>
                    
                    <?php if (isset($_SESSION['user_nome'])): ?>
                        <p>Bem-vindo, <strong><?= htmlspecialchars($_SESSION['user_nome']) ?></strong>!</p>
                        <p>Você está logado como <span class="badge bg-primary">Administrador do Sistema</span></p>
                    <?php elseif (isset($_SESSION['funcionario_nome'])): ?>
                        <p>Bem-vindo, <strong><?= htmlspecialchars($_SESSION['funcionario_nome']) ?></strong>!</p>
                        <p>Você está logado como <span class="badge bg-success">Funcionário</span></p>
                        
                        <!-- Botão de bater ponto para mobile -->
                        <div class="mt-4">
                            <a href="/ponto/registrar.php" class="btn btn-success btn-lg">
                                <i class="fas fa-fingerprint"></i> Bater Ponto
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cards de informações - Responsivos -->
    <div class="row mt-4">
        <div class="col-12 col-md-6 col-lg-4 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Meus Pontos Hoje</h5>
                    <h2 class="display-4" id="pontos-hoje">--</h2>
                    <a href="/ponto/historico.php" class="text-white">Ver histórico →</a>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-6 col-lg-4 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Horas Trabalhadas</h5>
                    <h2 class="display-4" id="horas-hoje">--h</h2>
                    <small>Hoje</small>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-6 col-lg-4 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Saldo do Mês</h5>
                    <h2 class="display-4" id="saldo-mes">--h</h2>
                    <small>Positivo/Negativo</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Carregar dados via AJAX (exemplo)
document.addEventListener('DOMContentLoaded', function() {
    fetch('/api/dashboard.php')
        .then(response => response.json())
        .then(data => {
            if (data.pontos_hoje) document.getElementById('pontos-hoje').innerText = data.pontos_hoje;
            if (data.horas_hoje) document.getElementById('horas-hoje').innerText = data.horas_hoje + 'h';
            if (data.saldo_mes) document.getElementById('saldo-mes').innerText = data.saldo_mes + 'h';
        })
        .catch(error => console.error('Erro:', error));
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>