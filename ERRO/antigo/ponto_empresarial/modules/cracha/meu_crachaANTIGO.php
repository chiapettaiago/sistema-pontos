<?php
// modules/cracha/meu_cracha.php - Crachá Digital do Funcionário (VERSÃO MELHORADA)
$pageTitle = 'Meu Crachá';
$activePage = 'cracha';

// ============================================
// VERIFICAÇÕES ANTES DO HEADER
// ============================================
session_start();

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Pega o ID do funcionário da sessão
$funcionario_id = $_SESSION['funcionario_id'] ?? null;

// Se não tiver funcionario_id na sessão, tenta buscar pelo usuário logado
if (!$funcionario_id && isset($_SESSION['usuario_id'])) {
    $stmt = $db->prepare("SELECT id FROM funcionarios WHERE email = :email");
    $stmt->execute([':email' => $_SESSION['usuario_email']]);
    $func = $stmt->fetch();
    if ($func) {
        $funcionario_id = $func['id'];
        $_SESSION['funcionario_id'] = $funcionario_id;
    }
}

// Se ainda não tem, redireciona
if (!$funcionario_id) {
    $_SESSION['mensagem'] = 'Perfil de funcionário não encontrado.';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: ../../index.php');
    exit;
}

// Buscar dados completos do funcionário
$query = "SELECT f.*, 
          e.nome_empresa as empresa_nome,
          fil.nome_fantasia as filial_nome,
          c.nome as cargo_nome,
          d.nome as departamento_nome
          FROM funcionarios f
          LEFT JOIN empresa e ON f.empresa_id = e.id
          LEFT JOIN filiais fil ON f.filial_id = fil.id
          LEFT JOIN cargos c ON f.cargo_id = c.id
          LEFT JOIN departamentos d ON f.departamento_id = d.id
          WHERE f.id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $funcionario_id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    $_SESSION['mensagem'] = 'Funcionário não encontrado';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: ../../index.php');
    exit;
}

// Gerar código QR Code com dados completos
$qr_data = json_encode([
    'id' => $funcionario['matricula'],
    'nome' => $funcionario['nome'],
    'matricula' => $funcionario['matricula'],
    'cpf' => $funcionario['cpf'],
    'empresa' => $funcionario['empresa_nome'],
    'filial' => $funcionario['filial_nome'],
    'valido_ate' => date('Y-m-d', strtotime('+1 year'))
]);

// QR Code com validação (API mais confiável)
$qr_url = "https://quickchart.io/qr?text=" . urlencode($qr_data) . "&size=200&margin=2";

// URL para validação do QR Code (API)
$validacao_url = "https://" . $_SERVER['HTTP_HOST'] . "/ponto_empresarial/api/validar_cracha.php";

// Formatar CPF
$cpf_formatado = '';
if (!empty($funcionario['cpf'])) {
    $cpf_limpo = preg_replace('/[^0-9]/', '', $funcionario['cpf']);
    if (strlen($cpf_limpo) == 11) {
        $cpf_formatado = substr($cpf_limpo, 0, 3) . '.' . 
                         substr($cpf_limpo, 3, 3) . '.' . 
                         substr($cpf_limpo, 6, 3) . '-' . 
                         substr($cpf_limpo, 9, 2);
    } else {
        $cpf_formatado = $funcionario['cpf'];
    }
}

// Formatar data de admissão
$data_admissao = 'Não informada';
if (!empty($funcionario['data_admissao']) && $funcionario['data_admissao'] != '0000-00-00') {
    $data_admissao = date('d/m/Y', strtotime($funcionario['data_admissao']));
}

// Data de validade do crachá (1 ano após admissão ou hoje + 1 ano)
$data_validade = date('d/m/Y', strtotime('+1 year'));

// Calcular tempo de empresa
$tempo_empresa = 'Não informado';
if (!empty($funcionario['data_admissao']) && $funcionario['data_admissao'] != '0000-00-00') {
    $data_admissao_ts = strtotime($funcionario['data_admissao']);
    $hoje = time();
    $diferenca = $hoje - $data_admissao_ts;
    $anos = floor($diferenca / (365 * 24 * 60 * 60));
    $meses = floor(($diferenca % (365 * 24 * 60 * 60)) / (30 * 24 * 60 * 60));
    
    $tempo_empresa = '';
    if ($anos > 0) {
        $tempo_empresa .= $anos . ' ano' . ($anos > 1 ? 's' : '');
    }
    if ($meses > 0) {
        $tempo_empresa .= ($anos > 0 ? ' e ' : '') . $meses . ' mês' . ($meses > 1 ? 'es' : '');
    }
    if ($tempo_empresa == '') {
        $tempo_empresa = 'Recém contratado';
    }
}

// Incluir header DEPOIS de todas as verificações
require_once '../../includes/header.php';
?>

<style>
.cracha-container {
    max-width: 500px;
    margin: 0 auto;
    padding: 20px;
}

.cracha-card {
    background: white;
    border-radius: 28px;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    transition: transform 0.3s;
}

.cracha-card:hover {
    transform: translateY(-5px);
}

.cracha-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 30px 20px;
    text-align: center;
    color: white;
    position: relative;
    overflow: hidden;
}

.cracha-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    pointer-events: none;
}

.cracha-header .empresa-nome {
    font-size: 22px;
    font-weight: 800;
    margin-bottom: 5px;
    letter-spacing: 1px;
}

.cracha-header .cracha-titulo {
    font-size: 11px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.cracha-corner {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 60px;
    height: 60px;
    background: rgba(255,255,255,0.1);
    border-radius: 30px 0 0 0;
}

.cracha-body {
    padding: 30px;
}

.foto-area {
    text-align: center;
    margin-top: -70px;
    margin-bottom: 20px;
}

.foto-funcionario {
    width: 130px;
    height: 130px;
    border-radius: 50%;
    background: linear-gradient(135deg, #fff, #f3f4f6);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 4px solid white;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    transition: transform 0.3s;
}

.foto-funcionario:hover {
    transform: scale(1.02);
}

.foto-funcionario i {
    font-size: 65px;
    color: #9ca3af;
}

.foto-funcionario img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.dados-funcionario {
    text-align: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid #f3f4f6;
}

.dados-funcionario h2 {
    font-size: 22px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 8px;
}

.dados-funcionario .matricula {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f3f4f6;
    padding: 6px 14px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 500;
    color: #4b5563;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 24px;
}

.info-item {
    text-align: center;
    padding: 12px 8px;
    background: linear-gradient(135deg, #f9fafb, #f3f4f6);
    border-radius: 16px;
    transition: all 0.3s;
}

.info-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.info-item i {
    font-size: 20px;
    color: #667eea;
    margin-bottom: 8px;
    display: block;
}

.info-item .label {
    font-size: 10px;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-item .value {
    font-size: 13px;
    font-weight: 700;
    color: #1f2937;
    margin-top: 4px;
    word-break: break-word;
}

.tempo-empresa {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    padding: 14px;
    border-radius: 16px;
    text-align: center;
    margin-bottom: 20px;
    font-size: 13px;
}

.tempo-empresa i {
    margin-right: 8px;
}

.tempo-empresa span {
    font-weight: 800;
}

.qr-code {
    text-align: center;
    padding: 20px;
    background: white;
    border-radius: 20px;
    border: 1px solid #f3f4f6;
    margin-bottom: 20px;
}

.qr-code img {
    width: 140px;
    height: 140px;
    margin-bottom: 10px;
    border-radius: 12px;
}

.qr-code p {
    font-size: 11px;
    color: #9ca3af;
}

.validade {
    text-align: center;
    font-size: 11px;
    color: #6b7280;
    margin-bottom: 20px;
    padding: 8px;
    background: #fef3c7;
    border-radius: 10px;
}

.validade i {
    color: #f59e0b;
    margin-right: 5px;
}

.acoes {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 20px;
}

.btn-acao {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    border-radius: 14px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
}

.btn-pdf {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.btn-pdf:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.btn-wallet {
    background: linear-gradient(135deg, #1f2937, #111827);
    color: white;
}

.btn-wallet:hover {
    transform: translateY(-2px);
}

.btn-voltar {
    background: #f3f4f6;
    color: #374151;
}

.btn-voltar:hover {
    background: #e5e7eb;
}

@media (max-width: 480px) {
    .cracha-body {
        padding: 20px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .foto-funcionario {
        width: 100px;
        height: 100px;
    }
    
    .dados-funcionario h2 {
        font-size: 18px;
    }
    
    .acoes {
        flex-direction: column;
    }
}
</style>

<div class="cracha-container">
    <div class="cracha-card">
        <div class="cracha-header">
            <div class="empresa-nome">
                <?php echo htmlspecialchars($funcionario['empresa_nome'] ?? 'PontoFácil'); ?>
            </div>
            <div class="cracha-titulo">
                CARTEIRA DE IDENTIFICAÇÃO DIGITAL
            </div>
            <div class="cracha-corner"></div>
        </div>
        
        <div class="cracha-body">
            <div class="foto-area">
                <div class="foto-funcionario">
                    <?php if (!empty($funcionario['foto']) && file_exists('../../' . $funcionario['foto'])): ?>
                        <img src="../../<?php echo $funcionario['foto']; ?>?t=<?php echo time(); ?>" alt="Foto do Funcionário">
                    <?php else: ?>
                        <i class="fas fa-user-circle"></i>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dados-funcionario">
                <h2><?php echo htmlspecialchars($funcionario['nome'] ?? 'Não informado'); ?></h2>
                <div class="matricula">
                    <i class="fas fa-hashtag"></i>
                    Matrícula: <?php echo htmlspecialchars($funcionario['matricula'] ?? 'Não informada'); ?>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <i class="fas fa-briefcase"></i>
                    <div class="label">Cargo</div>
                    <div class="value"><?php echo htmlspecialchars($funcionario['cargo_nome'] ?? 'Não definido'); ?></div>
                </div>
                <div class="info-item">
                    <i class="fas fa-building"></i>
                    <div class="label">Departamento</div>
                    <div class="value"><?php echo htmlspecialchars($funcionario['departamento_nome'] ?? 'Não definido'); ?></div>
                </div>
                <div class="info-item">
                    <i class="fas fa-store"></i>
                    <div class="label">Filial</div>
                    <div class="value"><?php echo htmlspecialchars($funcionario['filial_nome'] ?? 'Matriz'); ?></div>
                </div>
                <div class="info-item">
                    <i class="fas fa-id-card"></i>
                    <div class="label">CPF</div>
                    <div class="value"><?php echo $cpf_formatado ?: 'Não informado'; ?></div>
                </div>
                <div class="info-item">
                    <i class="fas fa-calendar-alt"></i>
                    <div class="label">Admissão</div>
                    <div class="value"><?php echo $data_admissao; ?></div>
                </div>
                <div class="info-item">
                    <i class="fas fa-envelope"></i>
                    <div class="label">E-mail</div>
                    <div class="value"><?php echo htmlspecialchars($funcionario['email'] ?? 'Não informado'); ?></div>
                </div>
            </div>
            
            <div class="tempo-empresa">
                <i class="fas fa-clock"></i>
                ⏳ Tempo de empresa: <span><?php echo $tempo_empresa; ?></span>
            </div>
            
            <div class="qr-code">
                <img src="<?php echo $qr_url; ?>" alt="QR Code de Validação">
                <p><i class="fas fa-qrcode"></i> Escaneie para validar a identificação</p>
            </div>
            
            <div class="validade">
                <i class="fas fa-calendar-check"></i>
                Válido até: <strong><?php echo $data_validade; ?></strong>
            </div>
            
            <div class="acoes">
                <a href="gerar.php?id=<?php echo $funcionario_id; ?>" class="btn-acao btn-pdf" target="_blank">
                    <i class="fas fa-file-pdf"></i> Baixar PDF
                </a>
                <button class="btn-acao btn-wallet" onclick="adicionarWallet()" id="btnWallet">
                    <i class="fab fa-apple"></i> + Wallet
                </button>
                <a href="../../modules/ponto/ponto.php" class="btn-acao btn-voltar">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Função para adicionar à carteira digital (Apple Wallet / Google Wallet)
function adicionarWallet() {
    // Verificar se é dispositivo iOS
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    
    // Verificar se é Android
    const isAndroid = /Android/.test(navigator.userAgent);
    
    if (isIOS) {
        // Para iOS - Apple Wallet (requer certificado)
        alert('Para adicionar à Apple Wallet, use o aplicativo PontoFácil para iOS.');
    } else if (isAndroid) {
        // Para Android - Google Pay
        alert('Para adicionar ao Google Wallet, use o aplicativo PontoFácil para Android.');
    } else {
        // Desktop - mostrar QR Code novamente
        alert('Escaneie o QR Code acima com seu celular para adicionar à carteira digital.');
    }
}

// Animação de entrada
document.querySelector('.cracha-card').style.opacity = '0';
document.querySelector('.cracha-card').style.transform = 'translateY(20px)';
setTimeout(() => {
    document.querySelector('.cracha-card').style.transition = 'all 0.5s ease';
    document.querySelector('.cracha-card').style.opacity = '1';
    document.querySelector('.cracha-card').style.transform = 'translateY(0)';
}, 100);
</script>

<?php require_once '../../includes/footer.php'; ?>