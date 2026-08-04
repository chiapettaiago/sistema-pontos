<?php
// modules/configuracoes/index.php - Dashboard de Configurações
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa'])) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Configurações da Empresa';
$activePage = 'configuracoes';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;
?>

<style>
.config-dashboard {
    max-width: 1200px;
    margin: 0 auto;
}

.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.module-title h2 {
    margin: 0;
    font-size: 24px;
}

.module-title p {
    margin: 8px 0 0 0;
    color: var(--text-secondary);
    font-size: 14px;
}

.config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.config-card {
    background: var(--bg-primary);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: all 0.3s;
}

.config-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.config-header {
    padding: 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.config-header i {
    font-size: 32px;
    margin-bottom: 12px;
    display: block;
}

.config-header h3 {
    margin: 0;
    font-size: 18px;
}

.config-body {
    padding: 20px;
}

.config-descricao {
    color: var(--text-secondary);
    font-size: 13px;
    margin-bottom: 20px;
    line-height: 1.5;
}

.config-status {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    font-size: 12px;
}

.status-ok {
    color: #10b981;
}

.status-pendente {
    color: #f59e0b;
}

.btn-config {
    width: 100%;
    padding: 12px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    text-align: center;
    text-decoration: none;
    color: var(--text-primary);
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-config:hover {
    background: var(--bg-tertiary);
    transform: translateY(-2px);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a67d8, #6b46a0);
}
</style>

<div class="config-dashboard">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-cogs"></i> Configurações da Empresa</h2>
            <p>Gerencie as configurações gerais do sistema</p>
        </div>
    </div>

    <div class="config-grid">
        <!-- Dados da Empresa -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-building"></i>
                <h3>Dados da Empresa</h3>
            </div>
            <div class="config-body">
                <div class="config-descricao">
                    Configure os dados cadastrais da sua empresa, como nome, CNPJ, endereço e logo.
                </div>
                <a href="empresa.php" class="btn-config">
                    <i class="fas fa-edit"></i> Configurar Dados
                </a>
            </div>
        </div>

        <!-- Horários de Funcionamento -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-clock"></i>
                <h3>Horários de Funcionamento</h3>
            </div>
            <div class="config-body">
                <div class="config-descricao">
                    Defina os horários padrão de entrada, almoço e saída, além de tolerâncias.
                </div>
                <a href="horarios.php" class="btn-config">
                    <i class="fas fa-calendar-alt"></i> Configurar Horários
                </a>
            </div>
        </div>

        <!-- Regras de Ponto -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-gavel"></i>
                <h3>Regras de Ponto</h3>
            </div>
            <div class="config-body">
                <div class="config-descricao">
                    Configure regras de horas extras, descontos por atraso e carga horária.
                </div>
                <a href="regras.php" class="btn-config">
                    <i class="fas fa-ruler"></i> Configurar Regras
                </a>
            </div>
        </div>

        <!-- Feriados e Exceções -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-calendar-times"></i>
                <h3>Feriados e Exceções</h3>
            </div>
            <div class="config-body">
                <div class="config-descricao">
                    Cadastre feriados nacionais, estaduais, municipais e dias com horário especial.
                </div>
                <a href="feriados.php" class="btn-config">
                    <i class="fas fa-plus-circle"></i> Gerenciar Feriados
                </a>
            </div>
        </div>

        <!-- Configurações de E-mail -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-envelope"></i>
                <h3>Configurações de E-mail</h3>
            </div>
            <div class="config-body">
                <div class="config-descricao">
                    Configure o servidor SMTP para envio de notificações por e-mail.
                </div>
                <a href="email.php" class="btn-config">
                    <i class="fas fa-server"></i> Configurar E-mail
                </a>
            </div>
        </div>

        <!-- Reconhecimento Facial -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-camera"></i>
                <h3>Reconhecimento Facial</h3>
            </div>
            <div class="config-body">
                <div class="config-descricao">
                    Configure as regras para o reconhecimento facial no registro de ponto.
                </div>
                <a href="facial.php" class="btn-config">
                    <i class="fas fa-fingerprint"></i> Configurar Facial
                </a>
            </div>
        </div>

        <!-- Backup -->
        <div class="config-card">
            <div class="config-header">
                <i class="fas fa-database"></i>
                <h3>Backup da Base</h3>
            </div>
            <div class="config-body">
                <div class="config-descricao">
                    Realize backup e restauração do banco de dados.
                </div>
                <a href="../backup/index.php" class="btn-config">
                    <i class="fas fa-download"></i> Gerenciar Backup
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>