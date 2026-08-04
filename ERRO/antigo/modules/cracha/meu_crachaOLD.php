<?php
// modules/cracha/meu_cracha.php - Crachá Digital do Funcionário (Apenas Visualização)
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

// Gerar código QR Code com os dados do funcionário (usando API local)
$qr_data = http_build_query([
    'nome' => $funcionario['nome'],
    'matricula' => $funcionario['matricula'],
    'cpf' => $funcionario['cpf'],
    'empresa' => $funcionario['empresa_nome'],
    'filial' => $funcionario['filial_nome']
]);

// Usando API gratuita do QR Code (mais confiável)
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_data);

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
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

.cracha-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 30px 20px;
    text-align: center;
    color: white;
}

.cracha-header .empresa-nome {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 5px;
}

.cracha-header .cracha-titulo {
    font-size: 12px;
    opacity: 0.9;
}

.cracha-body {
    padding: 30px;
}

.foto-area {
    text-align: center;
    margin-top: -60px;
    margin-bottom: 20px;
}

.foto-funcionario {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: #f3f4f6;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 4px solid white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.foto-funcionario i {
    font-size: 60px;
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
    border-bottom: 1px solid #e5e5e5;
}

.dados-funcionario h2 {
    font-size: 22px;
    color: #333;
    margin-bottom: 8px;
}

.dados-funcionario .matricula {
    display: inline-block;
    background: #f3f4f6;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    color: #666;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.info-item {
    text-align: center;
    padding: 12px;
    background: #f9fafb;
    border-radius: 12px;
}

.info-item i {
    font-size: 20px;
    color: #667eea;
    margin-bottom: 8px;
    display: block;
}

.info-item .label {
    font-size: 10px;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-item .value {
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin-top: 4px;
    word-break: break-word;
}

.qr-code {
    text-align: center;
    padding: 20px;
    background: #f9fafb;
    border-radius: 16px;
    margin-bottom: 24px;
}

.qr-code img {
    width: 150px;
    height: 150px;
    margin-bottom: 10px;
}

.qr-code p {
    font-size: 11px;
    color: #999;
}

.tempo-empresa {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px;
    border-radius: 12px;
    text-align: center;
}

.tempo-empresa i {
    margin-right: 8px;
}

.tempo-empresa span {
    font-weight: bold;
}

.btn-voltar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    margin-top: 20px;
    padding: 12px;
    background: #6b7280;
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}

.btn-voltar:hover {
    background: #4b5563;
}

@media (max-width: 480px) {
    .cracha-body {
        padding: 20px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .foto-funcionario {
        width: 100px;
        height: 100px;
    }
    
    .dados-funcionario h2 {
        font-size: 18px;
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
                <i class="fas fa-id-card"></i> Carteira de Identificação
            </div>
        </div>
        
        <div class="cracha-body">
            <div class="foto-area">
                <div class="foto-funcionario">
                    <?php if (!empty($funcionario['foto']) && file_exists('../../' . $funcionario['foto'])): ?>
                        <img src="../../<?php echo $funcionario['foto']; ?>" alt="Foto do Funcionário">
                    <?php else: ?>
                        <i class="fas fa-user-circle"></i>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dados-funcionario">
                <h2><?php echo htmlspecialchars($funcionario['nome'] ?? 'Não informado'); ?></h2>
                <div class="matricula">
                    <i class="fas fa-hashtag"></i> Matrícula: <?php echo htmlspecialchars($funcionario['matricula'] ?? 'Não informada'); ?>
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
                Tempo de empresa: <span><?php echo $tempo_empresa; ?></span>
            </div>
            
            <div class="qr-code">
                <img src="<?php echo $qr_url; ?>" alt="QR Code" onerror="this.src='https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=<?php echo urlencode($qr_data); ?>'">
                <p><i class="fas fa-qrcode"></i> Código de validação</p>
            </div>
            
            <a href="../../index.php" class="btn-voltar">
                <i class="fas fa-arrow-left"></i> Voltar ao Início
            </a>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>