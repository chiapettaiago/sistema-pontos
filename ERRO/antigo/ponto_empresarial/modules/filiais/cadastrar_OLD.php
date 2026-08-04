<?php
// modules/filiais/cadastrar.php - Cadastrar Nova Filial
$pageTitle = 'Nova Filial';
$activePage = 'filiais';
require_once '../../includes/header.php';
require_once '../../config/database.php';

redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Buscar empresa para o select
$stmt = $db->query("SELECT id, nome_empresa FROM empresa ORDER BY nome_empresa");
$empresas = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Dados do formulário
    $empresa_id = $_POST['empresa_id'] ?? 1;
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $nome_fantasia = trim($_POST['nome_fantasia'] ?? '');
    $razao_social = trim($_POST['razao_social'] ?? '');
    $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? '');
    $inscricao_estadual = trim($_POST['inscricao_estadual'] ?? '');
    $tipo_ramo = $_POST['tipo_ramo'] ?? 'filial';
    $segmento = trim($_POST['segmento'] ?? '');
    
    // Responsável
    $responsavel_nome = trim($_POST['responsavel_nome'] ?? '');
    $responsavel_email = trim($_POST['responsavel_email'] ?? '');
    $responsavel_telefone = trim($_POST['responsavel_telefone'] ?? '');
    
    // Endereço
    $endereco = trim($_POST['endereco'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
    
    // Geolocalização
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $raio_permitido = $_POST['raio_permitido'] ?? 100;
    $tolerancia_minutos = $_POST['tolerancia_minutos'] ?? 5;
    
    // Horários
    $horario_abertura = $_POST['horario_abertura'] ?? null;
    $horario_fechamento = $_POST['horario_fechamento'] ?? null;
    $dias_funcionamento = $_POST['dias_funcionamento'] ?? 'segunda_sexta';
    
    $observacao = trim($_POST['observacao'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    // Validações
    $errors = [];
    
    if (empty($codigo)) $errors[] = 'Código é obrigatório';
    if (empty($nome_fantasia)) $errors[] = 'Nome fantasia é obrigatório';
    if (empty($cidade)) $errors[] = 'Cidade é obrigatória';
    if (empty($estado)) $errors[] = 'Estado é obrigatório';
    
    // Verificar se código já existe
    $check = $db->prepare("SELECT id FROM filiais WHERE codigo = :codigo");
    $check->execute([':codigo' => $codigo]);
    if ($check->fetch()) $errors[] = 'Código já existe';
    
    // Verificar se CNPJ já existe
    if ($cnpj) {
        $check = $db->prepare("SELECT id FROM filiais WHERE cnpj = :cnpj");
        $check->execute([':cnpj' => $cnpj]);
        if ($check->fetch()) $errors[] = 'CNPJ já cadastrado';
    }
    
    // Formatar CNPJ para exibição
    $cnpj_formatado = $cnpj ? 
        substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . 
        substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2) : null;
    
    if (empty($errors)) {
        try {
            $query = "INSERT INTO filiais (
                empresa_id, codigo, nome_fantasia, razao_social, cnpj, inscricao_estadual,
                tipo_ramo, segmento, responsavel_nome, responsavel_email, responsavel_telefone,
                endereco, numero, complemento, bairro, cidade, estado, cep,
                latitude, longitude, raio_permitido, tolerancia_minutos,
                horario_abertura, horario_fechamento, dias_funcionamento,
                observacao, ativo
            ) VALUES (
                :empresa_id, :codigo, :nome_fantasia, :razao_social, :cnpj, :inscricao_estadual,
                :tipo_ramo, :segmento, :responsavel_nome, :responsavel_email, :responsavel_telefone,
                :endereco, :numero, :complemento, :bairro, :cidade, :estado, :cep,
                :latitude, :longitude, :raio_permitido, :tolerancia_minutos,
                :horario_abertura, :horario_fechamento, :dias_funcionamento,
                :observacao, :ativo
            )";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':empresa_id' => $empresa_id,
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
                ':ativo' => $ativo
            ]);
            
            $filial_id = $db->lastInsertId();
            logAcao($db, 'INSERT', 'filiais', $filial_id, "Cadastrou filial: $nome_fantasia");
            
            $success = 'Filial cadastrada com sucesso!';
            
            // Limpar formulário
            $_POST = [];
            
        } catch (Exception $e) {
            $error = 'Erro ao cadastrar: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-plus-circle"></i> Nova Filial / Loja</h3>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="form-main" id="formFilial">
            <!-- Dados Básicos -->
            <div class="form-section">
                <h4><i class="fas fa-building"></i> Dados Básicos</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Código *</label>
                        <input type="text" name="codigo" required 
                               value="<?php echo htmlspecialchars($_POST['codigo'] ?? ''); ?>"
                               placeholder="Ex: MATRIZ, LOJA001">
                        <small>Código único para identificação</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Nome Fantasia *</label>
                        <input type="text" name="nome_fantasia" required 
                               value="<?php echo htmlspecialchars($_POST['nome_fantasia'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Razão Social</label>
                        <input type="text" name="razao_social" 
                               value="<?php echo htmlspecialchars($_POST['razao_social'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>CNPJ</label>
                        <input type="text" name="cnpj" id="cnpj" 
                               value="<?php echo htmlspecialchars($_POST['cnpj'] ?? ''); ?>"
                               placeholder="00.000.000/0000-00">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo de Estabelecimento</label>
                        <select name="tipo_ramo">
                            <option value="matriz" <?php echo ($_POST['tipo_ramo'] ?? '') == 'matriz' ? 'selected' : ''; ?>>🏢 Matriz</option>
                            <option value="filial" <?php echo ($_POST['tipo_ramo'] ?? '') == 'filial' ? 'selected' : ''; ?>>📌 Filial</option>
                            <option value="loja" <?php echo ($_POST['tipo_ramo'] ?? '') == 'loja' ? 'selected' : ''; ?>>🛍️ Loja</option>
                            <option value="deposito" <?php echo ($_POST['tipo_ramo'] ?? '') == 'deposito' ? 'selected' : ''; ?>>📦 Depósito</option>
                            <option value="escritorio" <?php echo ($_POST['tipo_ramo'] ?? '') == 'escritorio' ? 'selected' : ''; ?>>💼 Escritório</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Segmento</label>
                        <input type="text" name="segmento" 
                               value="<?php echo htmlspecialchars($_POST['segmento'] ?? ''); ?>"
                               placeholder="Ex: Varejo, Tecnologia, Serviços">
                    </div>
                </div>
            </div>
            
            <!-- Responsável -->
            <div class="form-section">
                <h4><i class="fas fa-user-tie"></i> Responsável</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome do Responsável</label>
                        <input type="text" name="responsavel_nome" 
                               value="<?php echo htmlspecialchars($_POST['responsavel_nome'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="responsavel_email" 
                               value="<?php echo htmlspecialchars($_POST['responsavel_email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="responsavel_telefone" 
                               value="<?php echo htmlspecialchars($_POST['responsavel_telefone'] ?? ''); ?>"
                               placeholder="(00) 00000-0000">
                    </div>
                </div>
            </div>
            
            <!-- Endereço -->
            <div class="form-section">
                <h4><i class="fas fa-map-marker-alt"></i> Endereço</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>CEP</label>
                        <input type="text" name="cep" id="cep" 
                               value="<?php echo htmlspecialchars($_POST['cep'] ?? ''); ?>"
                               placeholder="00000-000">
                    </div>
                    
                    <div class="form-group">
                        <label>Endereço</label>
                        <input type="text" name="endereco" 
                               value="<?php echo htmlspecialchars($_POST['endereco'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" name="numero" 
                               value="<?php echo htmlspecialchars($_POST['numero'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text" name="complemento" 
                               value="<?php echo htmlspecialchars($_POST['complemento'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Bairro</label>
                        <input type="text" name="bairro" 
                               value="<?php echo htmlspecialchars($_POST['bairro'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Cidade *</label>
                        <input type="text" name="cidade" required 
                               value="<?php echo htmlspecialchars($_POST['cidade'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Estado *</label>
                        <select name="estado" required>
                            <option value="">Selecione</option>
                            <option value="AC" <?php echo ($_POST['estado'] ?? '') == 'AC' ? 'selected' : ''; ?>>AC</option>
                            <option value="AL" <?php echo ($_POST['estado'] ?? '') == 'AL' ? 'selected' : ''; ?>>AL</option>
                            <option value="AP" <?php echo ($_POST['estado'] ?? '') == 'AP' ? 'selected' : ''; ?>>AP</option>
                            <option value="AM" <?php echo ($_POST['estado'] ?? '') == 'AM' ? 'selected' : ''; ?>>AM</option>
                            <option value="BA" <?php echo ($_POST['estado'] ?? '') == 'BA' ? 'selected' : ''; ?>>BA</option>
                            <option value="CE" <?php echo ($_POST['estado'] ?? '') == 'CE' ? 'selected' : ''; ?>>CE</option>
                            <option value="DF" <?php echo ($_POST['estado'] ?? '') == 'DF' ? 'selected' : ''; ?>>DF</option>
                            <option value="ES" <?php echo ($_POST['estado'] ?? '') == 'ES' ? 'selected' : ''; ?>>ES</option>
                            <option value="GO" <?php echo ($_POST['estado'] ?? '') == 'GO' ? 'selected' : ''; ?>>GO</option>
                            <option value="MA" <?php echo ($_POST['estado'] ?? '') == 'MA' ? 'selected' : ''; ?>>MA</option>
                            <option value="MT" <?php echo ($_POST['estado'] ?? '') == 'MT' ? 'selected' : ''; ?>>MT</option>
                            <option value="MS" <?php echo ($_POST['estado'] ?? '') == 'MS' ? 'selected' : ''; ?>>MS</option>
                            <option value="MG" <?php echo ($_POST['estado'] ?? '') == 'MG' ? 'selected' : ''; ?>>MG</option>
                            <option value="PA" <?php echo ($_POST['estado'] ?? '') == 'PA' ? 'selected' : ''; ?>>PA</option>
                            <option value="PB" <?php echo ($_POST['estado'] ?? '') == 'PB' ? 'selected' : ''; ?>>PB</option>
                            <option value="PR" <?php echo ($_POST['estado'] ?? '') == 'PR' ? 'selected' : ''; ?>>PR</option>
                            <option value="PE" <?php echo ($_POST['estado'] ?? '') == 'PE' ? 'selected' : ''; ?>>PE</option>
                            <option value="PI" <?php echo ($_POST['estado'] ?? '') == 'PI' ? 'selected' : ''; ?>>PI</option>
                            <option value="RJ" <?php echo ($_POST['estado'] ?? '') == 'RJ' ? 'selected' : ''; ?>>RJ</option>
                            <option value="RN" <?php echo ($_POST['estado'] ?? '') == 'RN' ? 'selected' : ''; ?>>RN</option>
                            <option value="RS" <?php echo ($_POST['estado'] ?? '') == 'RS' ? 'selected' : ''; ?>>RS</option>
                            <option value="RO" <?php echo ($_POST['estado'] ?? '') == 'RO' ? 'selected' : ''; ?>>RO</option>
                            <option value="RR" <?php echo ($_POST['estado'] ?? '') == 'RR' ? 'selected' : ''; ?>>RR</option>
                            <option value="SC" <?php echo ($_POST['estado'] ?? '') == 'SC' ? 'selected' : ''; ?>>SC</option>
                            <option value="SP" <?php echo ($_POST['estado'] ?? '') == 'SP' ? 'selected' : ''; ?>>SP</option>
                            <option value="SE" <?php echo ($_POST['estado'] ?? '') == 'SE' ? 'selected' : ''; ?>>SE</option>
                            <option value="TO" <?php echo ($_POST['estado'] ?? '') == 'TO' ? 'selected' : ''; ?>>TO</option>
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
                               value="<?php echo htmlspecialchars($_POST['horario_abertura'] ?? '08:00'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Horário de Fechamento</label>
                        <input type="time" name="horario_fechamento" 
                               value="<?php echo htmlspecialchars($_POST['horario_fechamento'] ?? '18:00'); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Raio Permitido (metros)</label>
                        <input type="number" name="raio_permitido" 
                               value="<?php echo htmlspecialchars($_POST['raio_permitido'] ?? '100'); ?>">
                        <small>Distância máxima do GPS para bater ponto</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Tolerância (minutos)</label>
                        <input type="number" name="tolerancia_minutos" 
                               value="<?php echo htmlspecialchars($_POST['tolerancia_minutos'] ?? '5'); ?>">
                        <small>Minutos de tolerância para atraso</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Dias de Funcionamento</label>
                        <select name="dias_funcionamento">
                            <option value="segunda_sexta" <?php echo ($_POST['dias_funcionamento'] ?? '') == 'segunda_sexta' ? 'selected' : ''; ?>>Segunda a Sexta</option>
                            <option value="segunda_sabado" <?php echo ($_POST['dias_funcionamento'] ?? '') == 'segunda_sabado' ? 'selected' : ''; ?>>Segunda a Sábado</option>
                            <option value="segunda_domingo" <?php echo ($_POST['dias_funcionamento'] ?? '') == 'segunda_domingo' ? 'selected' : ''; ?>>Segunda a Domingo</option>
                            <option value="todos_dias" <?php echo ($_POST['dias_funcionamento'] ?? '') == 'todos_dias' ? 'selected' : ''; ?>>Todos os dias</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="ativo" value="1" checked> Filial ativa
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Observação -->
            <div class="form-section">
                <h4><i class="fas fa-comment"></i> Observações</h4>
                <div class="form-group">
                    <textarea name="observacao" rows="3" placeholder="Informações adicionais sobre a filial..."><?php echo htmlspecialchars($_POST['observacao'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Filial
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<style>
.form-section {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid #e5e5e5;
}

.form-section h4 {
    margin-bottom: 20px;
    color: #333;
    font-size: 18px;
}

.form-section h4 i {
    color: #667eea;
    margin-right: 8px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.checkbox-label input {
    width: auto;
    margin: 0;
}

small {
    font-size: 11px;
    color: #999;
    margin-top: 4px;
    display: block;
}
</style>

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

// Buscar endereço por CEP
document.getElementById('cep')?.addEventListener('blur', function() {
    let cep = this.value.replace(/\D/g, '');
    if (cep.length === 8) {
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(response => response.json())
            .then(data => {
                if (!data.erro) {
                    document.querySelector('[name="endereco"]').value = data.logradouro;
                    document.querySelector('[name="bairro"]').value = data.bairro;
                    document.querySelector('[name="cidade"]').value = data.localidade;
                    document.querySelector('[name="estado"]').value = data.uf;
                }
            })
            .catch(error => console.log('Erro ao buscar CEP:', error));
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>