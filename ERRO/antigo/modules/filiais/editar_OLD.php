<?php
// modules/filiais/editar.php - Editar Filial
$pageTitle = 'Editar Filial';
$activePage = 'filiais';
require_once '../../includes/header.php';
require_once '../../config/database.php';

redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;

// Buscar dados da filial
$query = "SELECT * FROM filiais WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $id]);
$filial = $stmt->fetch();

if (!$filial) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Dados do formulário (mesmo do cadastro)
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $nome_fantasia = trim($_POST['nome_fantasia'] ?? '');
    $razao_social = trim($_POST['razao_social'] ?? '');
    $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? '');
    $inscricao_estadual = trim($_POST['inscricao_estadual'] ?? '');
    $tipo_ramo = $_POST['tipo_ramo'] ?? 'filial';
    $segmento = trim($_POST['segmento'] ?? '');
    
    $responsavel_nome = trim($_POST['responsavel_nome'] ?? '');
    $responsavel_email = trim($_POST['responsavel_email'] ?? '');
    $responsavel_telefone = trim($_POST['responsavel_telefone'] ?? '');
    
    $endereco = trim($_POST['endereco'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
    
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $raio_permitido = $_POST['raio_permitido'] ?? 100;
    $tolerancia_minutos = $_POST['tolerancia_minutos'] ?? 5;
    
    $horario_abertura = $_POST['horario_abertura'] ?? null;
    $horario_fechamento = $_POST['horario_fechamento'] ?? null;
    $dias_funcionamento = $_POST['dias_funcionamento'] ?? 'segunda_sexta';
    
    $observacao = trim($_POST['observacao'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    $errors = [];
    
    if (empty($codigo)) $errors[] = 'Código é obrigatório';
    if (empty($nome_fantasia)) $errors[] = 'Nome fantasia é obrigatório';
    
    // Verificar se código já existe (excluindo a própria filial)
    $check = $db->prepare("SELECT id FROM filiais WHERE codigo = :codigo AND id != :id");
    $check->execute([':codigo' => $codigo, ':id' => $id]);
    if ($check->fetch()) $errors[] = 'Código já existe';
    
    // Verificar se CNPJ já existe
    if ($cnpj) {
        $check = $db->prepare("SELECT id FROM filiais WHERE cnpj = :cnpj AND id != :id");
        $check->execute([':cnpj' => $cnpj, ':id' => $id]);
        if ($check->fetch()) $errors[] = 'CNPJ já cadastrado';
    }
    
    if (empty($errors)) {
        try {
            $query = "UPDATE filiais SET 
                codigo = :codigo,
                nome_fantasia = :nome_fantasia,
                razao_social = :razao_social,
                cnpj = :cnpj,
                inscricao_estadual = :inscricao_estadual,
                tipo_ramo = :tipo_ramo,
                segmento = :segmento,
                responsavel_nome = :responsavel_nome,
                responsavel_email = :responsavel_email,
                responsavel_telefone = :responsavel_telefone,
                endereco = :endereco,
                numero = :numero,
                complemento = :complemento,
                bairro = :bairro,
                cidade = :cidade,
                estado = :estado,
                cep = :cep,
                latitude = :latitude,
                longitude = :longitude,
                raio_permitido = :raio_permitido,
                tolerancia_minutos = :tolerancia_minutos,
                horario_abertura = :horario_abertura,
                horario_fechamento = :horario_fechamento,
                dias_funcionamento = :dias_funcionamento,
                observacao = :observacao,
                ativo = :ativo
            WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':codigo' => $codigo,
                ':nome_fantasia' => $nome_fantasia,
                ':razao_social' => $razao_social ?: null,
                ':cnpj' => $cnpj ?: null,
                ':inscricao_estadual' => $inscricao_estadual ?: null,
                ':tipo_ramo' => $tipo_ramo,
                ':segmento' => $segmento ?: null,
                ':responsavel_nome' => $responsavel_nome ?: null,
                ':responsavel_email' => $responsavel_email ?: null,
                ':responsavel_telefone' => $responsavel_telefone ?: null,
                ':endereco' => $endereco ?: null,
                ':numero' => $numero ?: null,
                ':complemento' => $complemento ?: null,
                ':bairro' => $bairro ?: null,
                ':cidade' => $cidade ?: null,
                ':estado' => $estado ?: null,
                ':cep' => $cep ?: null,
                ':latitude' => $latitude ?: null,
                ':longitude' => $longitude ?: null,
                ':raio_permitido' => $raio_permitido,
                ':tolerancia_minutos' => $tolerancia_minutos,
                ':horario_abertura' => $horario_abertura ?: null,
                ':horario_fechamento' => $horario_fechamento ?: null,
                ':dias_funcionamento' => $dias_funcionamento,
                ':observacao' => $observacao ?: null,
                ':ativo' => $ativo,
                ':id' => $id
            ]);
            
            logAcao($db, 'UPDATE', 'filiais', $id, "Editou filial: $nome_fantasia");
            
            $success = 'Filial atualizada com sucesso!';
            
            // Recarregar dados
            $stmt = $db->prepare("SELECT * FROM filiais WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $filial = $stmt->fetch();
            
        } catch (Exception $e) {
            $error = 'Erro ao atualizar: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-edit"></i> Editar Filial: <?php echo htmlspecialchars($filial['nome_fantasia']); ?></h3>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="form-main">
            <!-- Dados Básicos -->
            <div class="form-section">
                <h4><i class="fas fa-building"></i> Dados Básicos</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Código *</label>
                        <input type="text" name="codigo" required 
                               value="<?php echo htmlspecialchars($filial['codigo']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Nome Fantasia *</label>
                        <input type="text" name="nome_fantasia" required 
                               value="<?php echo htmlspecialchars($filial['nome_fantasia']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Razão Social</label>
                        <input type="text" name="razao_social" 
                               value="<?php echo htmlspecialchars($filial['razao_social'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>CNPJ</label>
                        <input type="text" name="cnpj" id="cnpj" 
                               value="<?php echo htmlspecialchars($filial['cnpj'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo de Estabelecimento</label>
                        <select name="tipo_ramo">
                            <option value="matriz" <?php echo $filial['tipo_ramo'] == 'matriz' ? 'selected' : ''; ?>>🏢 Matriz</option>
                            <option value="filial" <?php echo $filial['tipo_ramo'] == 'filial' ? 'selected' : ''; ?>>📌 Filial</option>
                            <option value="loja" <?php echo $filial['tipo_ramo'] == 'loja' ? 'selected' : ''; ?>>🛍️ Loja</option>
                            <option value="deposito" <?php echo $filial['tipo_ramo'] == 'deposito' ? 'selected' : ''; ?>>📦 Depósito</option>
                            <option value="escritorio" <?php echo $filial['tipo_ramo'] == 'escritorio' ? 'selected' : ''; ?>>💼 Escritório</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Segmento</label>
                        <input type="text" name="segmento" 
                               value="<?php echo htmlspecialchars($filial['segmento'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Responsável (mesmo do cadastro) -->
            <div class="form-section">
                <h4><i class="fas fa-user-tie"></i> Responsável</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome do Responsável</label>
                        <input type="text" name="responsavel_nome" 
                               value="<?php echo htmlspecialchars($filial['responsavel_nome'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="responsavel_email" 
                               value="<?php echo htmlspecialchars($filial['responsavel_email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="responsavel_telefone" 
                               value="<?php echo htmlspecialchars($filial['responsavel_telefone'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Endereço (mesmo do cadastro) -->
            <div class="form-section">
                <h4><i class="fas fa-map-marker-alt"></i> Endereço</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>CEP</label>
                        <input type="text" name="cep" id="cep" 
                               value="<?php echo htmlspecialchars($filial['cep'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Endereço</label>
                        <input type="text" name="endereco" 
                               value="<?php echo htmlspecialchars($filial['endereco'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" name="numero" 
                               value="<?php echo htmlspecialchars($filial['numero'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text" name="complemento" 
                               value="<?php echo htmlspecialchars($filial['complemento'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Bairro</label>
                        <input type="text" name="bairro" 
                               value="<?php echo htmlspecialchars($filial['bairro'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Cidade</label>
                        <input type="text" name="cidade" 
                               value="<?php echo htmlspecialchars($filial['cidade'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado">
                            <option value="">Selecione</option>
                            <option value="AC" <?php echo ($filial['estado'] ?? '') == 'AC' ? 'selected' : ''; ?>>AC</option>
                            <option value="AL" <?php echo ($filial['estado'] ?? '') == 'AL' ? 'selected' : ''; ?>>AL</option>
                            <option value="AP" <?php echo ($filial['estado'] ?? '') == 'AP' ? 'selected' : ''; ?>>AP</option>
                            <option value="AM" <?php echo ($filial['estado'] ?? '') == 'AM' ? 'selected' : ''; ?>>AM</option>
                            <option value="BA" <?php echo ($filial['estado'] ?? '') == 'BA' ? 'selected' : ''; ?>>BA</option>
                            <option value="CE" <?php echo ($filial['estado'] ?? '') == 'CE' ? 'selected' : ''; ?>>CE</option>
                            <option value="DF" <?php echo ($filial['estado'] ?? '') == 'DF' ? 'selected' : ''; ?>>DF</option>
                            <option value="ES" <?php echo ($filial['estado'] ?? '') == 'ES' ? 'selected' : ''; ?>>ES</option>
                            <option value="GO" <?php echo ($filial['estado'] ?? '') == 'GO' ? 'selected' : ''; ?>>GO</option>
                            <option value="MA" <?php echo ($filial['estado'] ?? '') == 'MA' ? 'selected' : ''; ?>>MA</option>
                            <option value="MT" <?php echo ($filial['estado'] ?? '') == 'MT' ? 'selected' : ''; ?>>MT</option>
                            <option value="MS" <?php echo ($filial['estado'] ?? '') == 'MS' ? 'selected' : ''; ?>>MS</option>
                            <option value="MG" <?php echo ($filial['estado'] ?? '') == 'MG' ? 'selected' : ''; ?>>MG</option>
                            <option value="PA" <?php echo ($filial['estado'] ?? '') == 'PA' ? 'selected' : ''; ?>>PA</option>
                            <option value="PB" <?php echo ($filial['estado'] ?? '') == 'PB' ? 'selected' : ''; ?>>PB</option>
                            <option value="PR" <?php echo ($filial['estado'] ?? '') == 'PR' ? 'selected' : ''; ?>>PR</option>
                            <option value="PE" <?php echo ($filial['estado'] ?? '') == 'PE' ? 'selected' : ''; ?>>PE</option>
                            <option value="PI" <?php echo ($filial['estado'] ?? '') == 'PI' ? 'selected' : ''; ?>>PI</option>
                            <option value="RJ" <?php echo ($filial['estado'] ?? '') == 'RJ' ? 'selected' : ''; ?>>RJ</option>
                            <option value="RN" <?php echo ($filial['estado'] ?? '') == 'RN' ? 'selected' : ''; ?>>RN</option>
                            <option value="RS" <?php echo ($filial['estado'] ?? '') == 'RS' ? 'selected' : ''; ?>>RS</option>
                            <option value="RO" <?php echo ($filial['estado'] ?? '') == 'RO' ? 'selected' : ''; ?>>RO</option>
                            <option value="RR" <?php echo ($filial['estado'] ?? '') == 'RR' ? 'selected' : ''; ?>>RR</option>
                            <option value="SC" <?php echo ($filial['estado'] ?? '') == 'SC' ? 'selected' : ''; ?>>SC</option>
                            <option value="SP" <?php echo ($filial['estado'] ?? '') == 'SP' ? 'selected' : ''; ?>>SP</option>
                            <option value="SE" <?php echo ($filial['estado'] ?? '') == 'SE' ? 'selected' : ''; ?>>SE</option>
                            <option value="TO" <?php echo ($filial['estado'] ?? '') == 'TO' ? 'selected' : ''; ?>>TO</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Configurações de Ponto -->
            <div class="form-section">
                <h4><i class="fas fa-clock"></i> Configurações de Ponto</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Horário de Abertura</label>
                        <input type="time" name="horario_abertura" 
                               value="<?php echo htmlspecialchars(substr($filial['horario_abertura'] ?? '08:00', 0, 5)); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Horário de Fechamento</label>
                        <input type="time" name="horario_fechamento" 
                               value="<?php echo htmlspecialchars(substr($filial['horario_fechamento'] ?? '18:00', 0, 5)); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Raio Permitido (metros)</label>
                        <input type="number" name="raio_permitido" 
                               value="<?php echo $filial['raio_permitido']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Tolerância (minutos)</label>
                        <input type="number" name="tolerancia_minutos" 
                               value="<?php echo $filial['tolerancia_minutos']; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Dias de Funcionamento</label>
                        <select name="dias_funcionamento">
                            <option value="segunda_sexta" <?php echo ($filial['dias_funcionamento'] ?? '') == 'segunda_sexta' ? 'selected' : ''; ?>>Segunda a Sexta</option>
                            <option value="segunda_sabado" <?php echo ($filial['dias_funcionamento'] ?? '') == 'segunda_sabado' ? 'selected' : ''; ?>>Segunda a Sábado</option>
                            <option value="segunda_domingo" <?php echo ($filial['dias_funcionamento'] ?? '') == 'segunda_domingo' ? 'selected' : ''; ?>>Segunda a Domingo</option>
                            <option value="todos_dias" <?php echo ($filial['dias_funcionamento'] ?? '') == 'todos_dias' ? 'selected' : ''; ?>>Todos os dias</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="ativo" value="1" <?php echo $filial['ativo'] ? 'checked' : ''; ?>> Filial ativa
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Observação -->
            <div class="form-section">
                <h4><i class="fas fa-comment"></i> Observações</h4>
                <div class="form-group">
                    <textarea name="observacao" rows="3"><?php echo htmlspecialchars($filial['observacao'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
                <a href="visualizar.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Máscara para CNPJ
document.getElementById('cnpj')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 14) {
        value = value.replace(/^(\d{2})(\d)/, '$1.$2');
        value = value.replace(/^(\d{2}\.\d{3})(\d)/, '$1.$2');
        value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
        value = value.replace(/(\d{4})(\d)/, '$1-$2');
        e.target.value = value;
    }
});

// Máscara para CEP
document.getElementById('cep')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 8) {
        value = value.replace(/^(\d{5})(\d)/, '$1-$2');
        e.target.value = value;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>