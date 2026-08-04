<?php
// modules/filiais/editar.php - Editar Filial (COMPLETO)
$pageTitle = 'Editar Filial';
$activePage = 'filiais';
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/multi_empresa.php';

redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

// Obter empresa do usuário logado
$empresa_id = getCurrentEmpresaId() ?: 1;

$id = $_GET['id'] ?? 0;

// Buscar dados da filial e verificar se pertence à empresa
$query = "SELECT * FROM filiais WHERE id = :id AND empresa_id = :empresa_id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $id, ':empresa_id' => $empresa_id]);
$filial = $stmt->fetch();

if (!$filial) {
    header('Location: index.php');
    exit;
}

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
    
    // Verificar se código já existe (excluindo a própria filial)
    $check = $db->prepare("SELECT id FROM filialis WHERE codigo = :codigo AND empresa_id = :empresa_id AND id != :id");
    $check->execute([':codigo' => $codigo, ':empresa_id' => $empresa_id, ':id' => $id]);
    if ($check->fetch()) $errors[] = 'Código já existe nesta empresa';
    
    // Verificar CNPJ
    $cnpj_formatado = $cnpj ? substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2) : null;
    if ($cnpj) {
        $check = $db->prepare("SELECT id FROM filiais WHERE cnpj = :cnpj AND empresa_id = :empresa_id AND id != :id");
        $check->execute([':cnpj' => $cnpj_formatado, ':empresa_id' => $empresa_id, ':id' => $id]);
        if ($check->fetch()) $errors[] = 'CNPJ já cadastrado nesta empresa';
    }
    
    $cep_formatado = $cep ? substr($cep, 0, 5) . '-' . substr($cep, 5, 3) : null;
    
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
                ':cidade' => $cidade ?: null,
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

<!-- O HTML do formulário de edição é similar ao de cadastro, com os valores preenchidos -->
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
                        <input type="text" name="codigo" required value="<?php echo htmlspecialchars($filial['codigo']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Nome Fantasia *</label>
                        <input type="text" name="nome_fantasia" required value="<?php echo htmlspecialchars($filial['nome_fantasia']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Razão Social</label>
                        <input type="text" name="razao_social" value="<?php echo htmlspecialchars($filial['razao_social'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>CNPJ</label>
                        <input type="text" name="cnpj" id="cnpj" value="<?php echo htmlspecialchars($filial['cnpj'] ?? ''); ?>">
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
                        <input type="text" name="segmento" value="<?php echo htmlspecialchars($filial['segmento'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Responsável -->
            <div class="form-section">
                <h4><i class="fas fa-user-tie"></i> Responsável</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome do Responsável</label>
                        <input type="text" name="responsavel_nome" value="<?php echo htmlspecialchars($filial['responsavel_nome'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="responsavel_email" value="<?php echo htmlspecialchars($filial['responsavel_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="responsavel_telefone" id="telefone_resp" value="<?php echo htmlspecialchars($filial['responsavel_telefone'] ?? ''); ?>">
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
                            <input type="text" name="cep" id="cep" value="<?php echo htmlspecialchars($filial['cep'] ?? ''); ?>">
                            <button type="button" id="buscarCep" class="btn btn-secondary">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Endereço</label>
                        <input type="text" name="endereco" id="endereco" value="<?php echo htmlspecialchars($filial['endereco'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" name="numero" id="numero" value="<?php echo htmlspecialchars($filial['numero'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text" name="complemento" id="complemento" value="<?php echo htmlspecialchars($filial['complemento'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Bairro</label>
                        <input type="text" name="bairro" id="bairro" value="<?php echo htmlspecialchars($filial['bairro'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Cidade *</label>
                        <input type="text" name="cidade" id="cidade" required value="<?php echo htmlspecialchars($filial['cidade'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Estado *</label>
                        <select name="estado" id="estado" required>
                            <option value="">Selecione</option>
                            <?php
                            $estados = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                            foreach ($estados as $uf) {
                                $selected = ($filial['estado'] ?? '') == $uf ? 'selected' : '';
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
                        <input type="time" name="horario_abertura" value="<?php echo substr($filial['horario_abertura'] ?? '08:00', 0, 5); ?>">
                    </div>
                    <div class="form-group">
                        <label>Horário de Fechamento</label>
                        <input type="time" name="horario_fechamento" value="<?php echo substr($filial['horario_fechamento'] ?? '18:00', 0, 5); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Raio Permitido (metros)</label>
                        <input type="number" name="raio_permitido" value="<?php echo $filial['raio_permitido']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Tolerância (minutos)</label>
                        <input type="number" name="tolerancia_minutos" value="<?php echo $filial['tolerancia_minutos']; ?>">
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
                showNotification('Endereço encontrado com sucesso!', 'success');
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

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.maxWidth = '300px';
    notification.innerHTML = message;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}
</script>

<?php require_once '../../includes/footer.php'; ?>