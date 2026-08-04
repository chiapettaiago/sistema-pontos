<?php
/**
 * SOLICITAÇÕES - NOVA SOLICITAÇÃO
 * Adaptado para sua estrutura de tabela
 */

// Forçar autenticação
require_once __DIR__ . '/../../includes/auth_check.php';
forceAuthentication();

// Pega o ID do funcionário logado
$funcionario_id = $_SESSION['funcionario_id'] ?? 0;
$empresa_id = $_SESSION['funcionario_empresa_id'] ?? 5;
$filial_id = $_SESSION['funcionario_filial_id'] ?? null;

// Verificar se é admin
$is_admin = isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'admin';

if (!$funcionario_id && !$is_admin) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/config.php';

$erro = '';
$sucesso = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pegar dados do formulário
    $tipo = $_POST['tipo'] ?? '';
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $data_inicio = $_POST['data_inicio'] ?? null;
    $data_fim = $_POST['data_fim'] ?? null;
    
    // Se for admin, pode escolher o funcionário
    if ($is_admin && isset($_POST['funcionario_id']) && !empty($_POST['funcionario_id'])) {
        $funcionario_id = (int)$_POST['funcionario_id'];
        
        // Buscar empresa e filial do funcionário
        $stmt = $pdo->prepare("SELECT empresa_id, filial_id FROM funcionarios WHERE id = ?");
        $stmt->execute([$funcionario_id]);
        $func_data = $stmt->fetch();
        if ($func_data) {
            $empresa_id = $func_data['empresa_id'];
            $filial_id = $func_data['filial_id'];
        }
    }
    
    // Validações
    if (empty($tipo)) {
        $erro = 'Selecione o tipo de solicitação.';
    } elseif (empty($titulo)) {
        $erro = 'Informe um título para a solicitação.';
    } elseif (empty($descricao)) {
        $erro = 'Descreva o motivo da solicitação.';
    } elseif ($funcionario_id <= 0) {
        $erro = 'Funcionário não identificado.';
    } else {
        try {
            // Upload de anexo (se houver)
            $anexo_nome = null;
            if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../../uploads/solicitacoes/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $extensao = pathinfo($_FILES['anexo']['name'], PATHINFO_EXTENSION);
                $anexo_nome = 'solicitacao_' . time() . '_' . $funcionario_id . '.' . $extensao;
                $caminho_anexo = $upload_dir . $anexo_nome;
                
                if (move_uploaded_file($_FILES['anexo']['tmp_name'], $caminho_anexo)) {
                    // Sucesso no upload
                } else {
                    $anexo_nome = null;
                }
            }
            
            // Inserir solicitação usando as colunas CORRETAS da sua tabela
            $sql = "INSERT INTO solicitacoes 
                    (funcionario_id, empresa_id, filial_id, tipo, titulo, descricao, 
                     data_inicio, data_fim, anexo, status, created_at) 
                    VALUES 
                    (:funcionario_id, :empresa_id, :filial_id, :tipo, :titulo, :descricao, 
                     :data_inicio, :data_fim, :anexo, 'pendente', NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':funcionario_id' => $funcionario_id,
                ':empresa_id' => $empresa_id,
                ':filial_id' => $filial_id,
                ':tipo' => $tipo,
                ':titulo' => $titulo,
                ':descricao' => $descricao,
                ':data_inicio' => !empty($data_inicio) ? $data_inicio : null,
                ':data_fim' => !empty($data_fim) ? $data_fim : null,
                ':anexo' => $anexo_nome
            ]);
            
            $sucesso = 'Solicitação enviada com sucesso! Aguarde a análise do RH.';
            
            // Limpar formulário
            $_POST = [];
            
        } catch (PDOException $e) {
            error_log("Erro ao salvar solicitação: " . $e->getMessage());
            $erro = 'Erro ao enviar solicitação: ' . $e->getMessage();
        }
    }
}

// Buscar funcionários (para admin)
$funcionarios = [];
if ($is_admin) {
    try {
        $stmt = $pdo->query("SELECT id, nome FROM funcionarios WHERE status = 'ativo' ORDER BY nome");
        $funcionarios = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erro ao buscar funcionários: " . $e->getMessage());
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-paper-plane"></i> Nova Solicitação
                    </h4>
                </div>
                <div class="card-body">
                    
                    <?php if ($sucesso): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?= $sucesso ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($erro): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?= $erro ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <?php if ($is_admin && count($funcionarios) > 0): ?>
                        <div class="mb-3">
                            <label class="form-label">Funcionário</label>
                            <select name="funcionario_id" class="form-select">
                                <option value="">Selecione o funcionário</option>
                                <?php foreach ($funcionarios as $func): ?>
                                <option value="<?= $func['id'] ?>">
                                    <?= htmlspecialchars($func['nome']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Deixe em branco para solicitar para você mesmo</small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Solicitação</label>
                            <select name="tipo" class="form-select" required>
                                <option value="">Selecione...</option>
                                <option value="ajuste_ponto">Ajuste de Ponto</option>
                                <option value="abono_falta">Abono de Falta</option>
                                <option value="ferias">Solicitação de Férias</option>
                                <option value="atestado">Atestado Médico</option>
                                <option value="documentacao">Documentação</option>
                                <option value="outros">Outros</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Título</label>
                            <input type="text" name="titulo" class="form-control" 
                                   placeholder="Ex: Solicitação de ajuste de ponto do dia 10/06"
                                   value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrição / Justificativa</label>
                            <textarea name="descricao" rows="5" class="form-control" 
                                      placeholder="Descreva detalhadamente o motivo da solicitação..." required><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Data Início (se aplicável)</label>
                                    <input type="date" name="data_inicio" class="form-control" 
                                           value="<?= $_POST['data_inicio'] ?? '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Data Fim (se aplicável)</label>
                                    <input type="date" name="data_fim" class="form-control" 
                                           value="<?= $_POST['data_fim'] ?? '' ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Anexo (comprovante, atestado, etc.)</label>
                            <input type="file" name="anexo" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">Formatos aceitos: PDF, JPG, PNG (max. 5MB)</small>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-send"></i> Enviar Solicitação
                            </button>
                            <a href="/dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Voltar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-body">
                    <h6><i class="fas fa-info-circle"></i> Informações importantes:</h6>
                    <ul class="small text-muted">
                        <li>Solicitações de ajuste de ponto devem ser feitas em até 48 horas após o ocorrido.</li>
                        <li>Para atestados médicos, anexar documento (PDF ou imagem).</li>
                        <li>O prazo de resposta é de até 5 dias úteis.</li>
                        <li>Acompanhe o status das suas solicitações no menu "Minhas Solicitações".</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>