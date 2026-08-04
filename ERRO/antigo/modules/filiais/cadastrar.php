<?php
// modules/filiais/cadastrar.php - Cadastrar Nova Filial (COMPLETO E CORRIGIDO)
$pageTitle = 'Nova Filial';
$activePage = 'filiais';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

// Usar ID fixo 1 para a empresa principal (já que o sistema é multi-empresa)
$empresa_id = 1;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    
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
    if (empty($cidade)) $errors[] = 'Cidade é obrigatória';
    if (empty($estado)) $errors[] = 'Estado é obrigatório';
    
    // Verificar se código já existe
    $check = $db->prepare("SELECT id FROM filiais WHERE codigo = :codigo");
    $check->execute([':codigo' => $codigo]);
    if ($check->fetch()) $errors[] = 'Código já existe';
    
    // Verificar CNPJ
    $cnpj_formatado = $cnpj ? substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2) : null;
    if ($cnpj) {
        $check = $db->prepare("SELECT id FROM filiais WHERE cnpj = :cnpj");
        $check->execute([':cnpj' => $cnpj_formatado]);
        if ($check->fetch()) $errors[] = 'CNPJ já cadastrado';
    }
    
    $cep_formatado = $cep ? substr($cep, 0, 5) . '-' . substr($cep, 5, 3) : null;
    
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
                ':cnpj' => $cnpj_formatado,
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
                ':cidade' => $cidade,
                ':estado' => $estado,
                ':cep' => $cep_formatado,
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
            $_POST = [];
            
        } catch (Exception $e) {
            $error = 'Erro ao cadastrar: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>

<style>
.form-container {
    max-width: 900px;
    margin: 0 auto;
}
.form-card {
    background: var(--bg-primary);
    border-radius: 24px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}
.form-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-secondary);
}
.form-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}
.form-header p {
    margin: 8px 0 0 0;
    color: var(--text-secondary);
    font-size: 14px;
}
.form-main {
    padding: 24px;
}
.form-section {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border-color);
}
.form-section h4 {
    margin-bottom: 20px;
    color: var(--text-primary);
    font-size: 18px;
}
.form-section h4 i {
    color: var(--primary);
    margin-right: 8px;
}
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}
.form-group {
    display: flex;
    flex-direction: column;
}
.form-group label {
    margin-bottom: 8px;
    font-weight: 500;
    font-size: 14px;
    color: var(--text-primary);
}
.form-group input, .form-group select, .form-group textarea {
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    font-size: 14px;
    background: var(--bg-primary);
    color: var(--text-primary);
    transition: all 0.3s;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}
.form-actions {
    display: flex;
    gap: 16px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s;
    border: none;
}
.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}
.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}
.btn-secondary:hover {
    background: var(--bg-tertiary);
}
.alert {
    padding: 12px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
}
.alert-error {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.alert-success {
    background: #d1fae5;
    color: #059669;
    border: 1px solid #a7f3d0;
}
.alert-info {
    background: #bfdbfe;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}
small {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 4px;
    display: block;
}
.cep-row {
    display: flex;
    gap: 16px;
    align-items: flex-end;
}
.cep-row .form-group:first-child {
    flex: 1;
}
.cep-row .form-group:last-child {
    flex-shrink: 0;
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
</style>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-plus-circle"></i> Nova Filial / Loja</h3>
            <p>Preencha os dados da nova filial</p>
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
                        <input type="text" name="responsavel_telefone" id="telefone_resp"
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
                        <div class="cep-row">
                            <input type="text" name="cep" id="cep" 
                                   value="<?php echo htmlspecialchars($_POST['cep'] ?? ''); ?>"
                                   placeholder="00000-000">
                            <button type="button" id="buscarCep" class="btn btn-secondary">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Endereço</label>
                        <input type="text" name="endereco" id="endereco"
                               value="<?php echo htmlspecialchars($_POST['endereco'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" name="numero" id="numero"
                               value="<?php echo htmlspecialchars($_POST['numero'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text" name="complemento" id="complemento"
                               value="<?php echo htmlspecialchars($_POST['complemento'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Bairro</label>
                        <input type="text" name="bairro" id="bairro"
                               value="<?php echo htmlspecialchars($_POST['bairro'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Cidade *</label>
                        <input type="text" name="cidade" id="cidade" required
                               value="<?php echo htmlspecialchars($_POST['cidade'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Estado *</label>
                        <select name="estado" id="estado" required>
                            <option value="">Selecione</option>
                            <?php
                            $estados = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                            foreach ($estados as $uf) {
                                $selected = ($_POST['estado'] ?? '') == $uf ? 'selected' : '';
                                echo "<option value=\"$uf\" $selected>$uf</option>";
                            }
                            ?>
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

// Máscara para Telefone
document.getElementById('telefone_resp')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        if (value.length === 11) {
            value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        } else if (value.length === 10) {
            value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        }
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
document.getElementById('buscarCep')?.addEventListener('click', function() {
    let cep = document.getElementById('cep').value.replace(/\D/g, '');
    const btn = this;
    const btnText = btn.innerHTML;
    
    if (cep.length !== 8) {
        alert('Digite um CEP válido com 8 dígitos');
        return;
    }
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
    btn.disabled = true;
    
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(response => response.json())
        .then(data => {
            if (!data.erro) {
                document.getElementById('endereco').value = data.logradouro || '';
                document.getElementById('bairro').value = data.bairro || '';
                document.getElementById('cidade').value = data.localidade || '';
                document.getElementById('estado').value = data.uf || '';
                document.getElementById('numero').focus();
                alert('Endereço encontrado com sucesso!');
            } else {
                alert('CEP não encontrado');
            }
        })
        .catch(error => {
            console.error('Erro ao buscar CEP:', error);
            alert('Erro ao buscar CEP. Tente novamente.');
        })
        .finally(() => {
            btn.innerHTML = btnText;
            btn.disabled = false;
        });
});

// Remover erros ao digitar
document.querySelectorAll('.form-group input, .form-group select').forEach(field => {
    field.addEventListener('focus', function() {
        const errorDiv = document.querySelector('.alert-error');
        if (errorDiv) errorDiv.style.display = 'none';
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>