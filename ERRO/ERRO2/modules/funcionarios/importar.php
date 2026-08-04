<?php
// modules/funcionarios/importar.php - Importar Funcionários via CSV (COM VALIDAÇÃO DE CPF)
$pageTitle = 'Importar Funcionários';
$activePage = 'funcionarios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

checkModuleAccess('funcionarios');

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$importados = 0;
$erros = [];

// Função para validar CPF
function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) return false;
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;
    
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += $cpf[$i] * (10 - $i);
    }
    $resto = $soma % 11;
    $dv1 = $resto < 2 ? 0 : 11 - $resto;
    
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += $cpf[$i] * (11 - $i);
    }
    $resto = $soma % 11;
    $dv2 = $resto < 2 ? 0 : 11 - $resto;
    
    return $cpf[9] == $dv1 && $cpf[10] == $dv2;
}

// Função para formatar CPF
function formatarCPFImport($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) == 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

// Buscar filiais para o select
if ($_SESSION['usuario_tipo'] === 'super_admin' || $_SESSION['usuario_tipo'] === 'admin') {
    $stmt = $db->query("SELECT id, nome_fantasia FROM filiais WHERE ativo = 1 ORDER BY nome_fantasia");
    $filiais = $stmt->fetchAll();
} else {
    $filiais = [['id' => $_SESSION['usuario_filial_id'], 'nome_fantasia' => $_SESSION['usuario_filial_nome']]];
}

// Se não houver filial definida, criar filial padrão para não quebrar o banco
if (empty($filiais) || empty($filiais[0]['id'])) {
    $empresa_id_tmp = getCurrentEmpresaId();
    if ($empresa_id_tmp === null || $empresa_id_tmp === '') {
        $empresa_id_tmp = $_SESSION['empresa_id'] ?? null;
    }
    if ($empresa_id_tmp !== null && $empresa_id_tmp !== '') {
        try {
            $stmt = $db->prepare("INSERT INTO filiais (empresa_id, codigo, nome_fantasia, tipo_ramo, ativo)
                                  VALUES (:empresa_id, 'MATRIZ', 'Matriz', 'matriz', 1)");
            $stmt->execute([':empresa_id' => $empresa_id_tmp]);
            $filiais = [['id' => $db->lastInsertId(), 'nome_fantasia' => 'Matriz']];
        } catch (Exception $e) {
            // Se já existir ou falhar, seguimos com um placeholder para evitar NULL no insert
            $stmt = $db->prepare("SELECT id, nome_fantasia FROM filiais WHERE empresa_id = :empresa_id AND ativo = 1 ORDER BY id ASC LIMIT 1");
            $stmt->execute([':empresa_id' => $empresa_id_tmp]);
            $filialExistente = $stmt->fetch();
            if ($filialExistente) {
                $filiais = [$filialExistente];
            }
        }
    }
}

// Buscar cargos
$stmt = $db->query("SELECT id, nome FROM cargos WHERE ativo = 1 ORDER BY nome");
$cargos = $stmt->fetchAll();

// Processar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
    $empresa_id = getCurrentEmpresaId();
    if ($empresa_id === null || $empresa_id === '') {
        $empresa_id = $_SESSION['empresa_id'] ?? null;
    }
    if ($empresa_id === null || $empresa_id === '') {
        header('Location: ../../index.php');
        exit;
    }
    $filial_id = $_POST['filial_id'] ?? $_SESSION['usuario_filial_id'];
    if (($filial_id === null || $filial_id === '') && !empty($filiais) && isset($filiais[0]['id'])) {
        $filial_id = $filiais[0]['id'];
    }
    $cargo_id = $_POST['cargo_id'] ?? null;
    $senha_padrao = $_POST['senha_padrao'] ?? '123456';
    
    $arquivo = $_FILES['arquivo'];
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $error = 'Erro no upload do arquivo';
    } elseif (!in_array($extensao, ['csv', 'xls', 'xlsx'])) {
        $error = 'Formato não suportado. Use CSV, XLS ou XLSX';
    } else {
        // Processar CSV
        if ($extensao == 'csv') {
            // Detectar delimitador
            $handle = fopen($arquivo['tmp_name'], 'r');
            $primeiraLinha = fgets($handle);
            rewind($handle);
            
            $delimitador = ';';
            if (strpos($primeiraLinha, ',') !== false && strpos($primeiraLinha, ';') === false) {
                $delimitador = ',';
            }
            
            $cabecalho = fgetcsv($handle, 0, $delimitador);
            
            // Normalizar cabeçalho (remover BOM, espaços, converter para minúsculo)
            $cabecalho = array_map(function($col) {
                $col = trim($col);
                $col = str_replace("\xEF\xBB\xBF", '', $col);
                return strtolower($col);
            }, $cabecalho);
            
            // Mapear colunas esperadas
            $colunas = [
                'nome' => array_search('nome', $cabecalho),
                'email' => array_search('email', $cabecalho),
                'matricula' => array_search('matricula', $cabecalho),
                'cpf' => array_search('cpf', $cabecalho),
                'telefone' => array_search('telefone', $cabecalho),
                'data_admissao' => array_search('data_admissao', $cabecalho),
                'cargo' => array_search('cargo', $cabecalho)
            ];
            
            // Verificar colunas obrigatórias
            $colunasObrigatorias = ['nome', 'email', 'matricula', 'cpf'];
            foreach ($colunasObrigatorias as $col) {
                if ($colunas[$col] === false) {
                    $error = "Coluna '$col' não encontrada no arquivo CSV";
                    break;
                }
            }
            
            if (!$error) {
                $linha_num = 1;
                while (($dados = fgetcsv($handle, 0, $delimitador)) !== false) {
                    $linha_num++;
                    
                    $nome = trim($dados[$colunas['nome']] ?? '');
                    $email = trim($dados[$colunas['email']] ?? '');
                    $matricula = strtoupper(trim($dados[$colunas['matricula']] ?? ''));
                    $cpf = preg_replace('/[^0-9]/', '', $dados[$colunas['cpf']] ?? '');
                    $telefone = trim($dados[$colunas['telefone']] ?? '');
                    $data_admissao = trim($dados[$colunas['data_admissao']] ?? date('Y-m-d'));
                    $cargo_nome = trim($dados[$colunas['cargo']] ?? '');
                    
                    $errosLinha = [];
                    
                    // Validações
                    if (empty($nome)) $errosLinha[] = 'Nome é obrigatório';
                    if (empty($email)) $errosLinha[] = 'E-mail é obrigatório';
                    if (empty($matricula)) $errosLinha[] = 'Matrícula é obrigatória';
                    
                    // Validação de CPF
                    if (empty($cpf)) {
                        $errosLinha[] = 'CPF é obrigatório';
                    } elseif (!validarCPF($cpf)) {
                        $errosLinha[] = 'CPF inválido';
                    } else {
                        $cpf_formatado = formatarCPFImport($cpf);
                        
                        // Verificar CPF duplicado na empresa
                        $check = $db->prepare("SELECT id FROM funcionarios WHERE cpf = :cpf AND empresa_id = :empresa_id");
                        $check->execute([':cpf' => $cpf_formatado, ':empresa_id' => $empresa_id]);
                        if ($check->fetch()) {
                            $errosLinha[] = "CPF já cadastrado nesta empresa";
                        }
                    }
                    
                    // Verificar email único
                    $check = $db->prepare("SELECT id FROM funcionarios WHERE email = :email AND empresa_id = :empresa_id");
                    $check->execute([':email' => $email, ':empresa_id' => $empresa_id]);
                    if ($check->fetch()) {
                        $errosLinha[] = "E-mail já cadastrado nesta empresa";
                    }
                    
                    // Verificar matrícula única
                    $check = $db->prepare("SELECT id FROM funcionarios WHERE matricula = :matricula AND empresa_id = :empresa_id");
                    $check->execute([':matricula' => $matricula, ':empresa_id' => $empresa_id]);
                    if ($check->fetch()) {
                        $errosLinha[] = "Matrícula já cadastrada nesta empresa";
                    }
                    
                    // Buscar cargo pelo nome
                    $cargo_id_final = $cargo_id;
                    if (!empty($cargo_nome) && !$cargo_id) {
                        $checkCargo = $db->prepare("SELECT id FROM cargos WHERE nome = :nome");
                        $checkCargo->execute([':nome' => $cargo_nome]);
                        $cargoEncontrado = $checkCargo->fetch();
                        if ($cargoEncontrado) {
                            $cargo_id_final = $cargoEncontrado['id'];
                        } else {
                            // Criar novo cargo
                            $stmtCargo = $db->prepare("INSERT INTO cargos (nome) VALUES (:nome)");
                            $stmtCargo->execute([':nome' => $cargo_nome]);
                            $cargo_id_final = $db->lastInsertId();
                        }
                    }
                    
                    // Validar data de admissão
                    $data_admissao_validada = $data_admissao;
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_admissao)) {
                        $data_admissao_validada = date('Y-m-d');
                    }
                    
                    if (empty($errosLinha)) {
                        try {
                            $senha_hash = password_hash($senha_padrao, PASSWORD_DEFAULT);
                            
                            // Formatar telefone
                            $telefone_limpo = preg_replace('/[^0-9]/', '', $telefone);
                            $telefone_formatado = $telefone_limpo ? '(' . substr($telefone_limpo, 0, 2) . ') ' . substr($telefone_limpo, 2, 4) . '-' . substr($telefone_limpo, 6, 4) : null;
                            
                            $query = "INSERT INTO funcionarios (
                                empresa_id, filial_id, cargo_id, matricula, nome, email, senha, cpf, telefone, 
                                data_admissao, status, created_by
                            ) VALUES (
                                :empresa_id, :filial_id, :cargo_id, :matricula, :nome, :email, :senha, :cpf, :telefone,
                                :data_admissao, 'ativo', :created_by
                            )";
                            
                            $stmt = $db->prepare($query);
                            $stmt->execute([
                                ':empresa_id' => $empresa_id,
                                ':filial_id' => $filial_id,
                                ':cargo_id' => $cargo_id_final,
                                ':matricula' => $matricula,
                                ':nome' => $nome,
                                ':email' => $email,
                                ':senha' => $senha_hash,
                                ':cpf' => $cpf_formatado,
                                ':telefone' => $telefone_formatado,
                                ':data_admissao' => $data_admissao_validada,
                                ':created_by' => getCurrentUserId()
                            ]);
                            
                            $importados++;
                            
                        } catch (Exception $e) {
                            $errosLinha[] = 'Erro no banco: ' . $e->getMessage();
                            $erros[] = "Linha $linha_num: " . implode(', ', $errosLinha);
                        }
                    } else {
                        $erros[] = "Linha $linha_num: " . implode(', ', $errosLinha);
                    }
                }
                fclose($handle);
            }
        }
        
        if ($importados > 0) {
            $success = "Importação concluída! ✅ $importados funcionários importados com sucesso.";
            if (!empty($erros)) {
                $success .= " ⚠️ " . count($erros) . " erros encontrados (verifique abaixo).";
            }
        } elseif (empty($error)) {
            $error = "Nenhum funcionário foi importado. Verifique os erros abaixo.";
        }
    }
}
?>

<style>
.import-template {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
}

.import-template h4 {
    margin-bottom: 12px;
}

.import-template code {
    background: var(--bg-primary);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
}

.download-template {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #10b981;
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    margin-top: 12px;
}

.erros-list {
    max-height: 300px;
    overflow-y: auto;
    background: rgba(239, 68, 68, 0.1);
    padding: 12px;
    border-radius: 8px;
    margin-top: 16px;
}

.erros-list ul {
    margin-left: 20px;
    color: #dc2626;
    font-size: 12px;
}

.erros-list ul li {
    margin: 4px 0;
}

.success-badge {
    color: #10b981;
    font-weight: bold;
}
</style>

<div class="form-container">
    <div class="form-card">
        <div class="form-header">
            <h3><i class="fas fa-file-import"></i> Importar Funcionários</h3>
            <p>Importe múltiplos funcionários usando um arquivo CSV</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($erros)): ?>
            <div class="erros-list">
                <strong><i class="fas fa-exclamation-triangle"></i> Detalhes dos erros (<?php echo count($erros); ?>):</strong>
                <ul>
                    <?php foreach ($erros as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Template do CSV -->
        <div class="import-template">
            <h4><i class="fas fa-file-csv"></i> Template do Arquivo CSV</h4>
            <p>Seu arquivo deve ter as seguintes colunas (primeira linha = cabeçalho):</p>
            <code>nome;email;matricula;cpf;telefone;data_admissao;cargo</code>
            <br><br>
            <strong>Exemplo:</strong>
            <code>João Silva;joao@email.com;FUNC001;123.456.789-00;(11) 99999-9999;2024-01-01;Analista</code>
            <br><br>
            <strong>Regras de validação:</strong>
            <ul style="margin-top: 8px; margin-left: 20px; font-size: 12px;">
                <li>CPF deve ser válido (11 dígitos, dígitos verificadores corretos)</li>
                <li>CPF não pode ser duplicado na mesma empresa</li>
                <li>E-mail deve ser único na empresa</li>
                <li>Matrícula deve ser única na empresa</li>
                <li>Data de admissão no formato YYYY-MM-DD</li>
            </ul>
            
            <a href="#" id="downloadTemplate" class="download-template">
                <i class="fas fa-download"></i> Baixar Template CSV
            </a>
        </div>
        
        <form method="POST" action="" enctype="multipart/form-data" class="form-main">
            <div class="form-row">
                <div class="form-group">
                    <label>Filial *</label>
                    <select name="filial_id" required>
                        <option value="">Selecione uma filial</option>
                        <?php foreach ($filiais as $filial): ?>
                        <option value="<?php echo $filial['id']; ?>">
                            <?php echo htmlspecialchars($filial['nome_fantasia']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Cargo Padrão</label>
                    <select name="cargo_id">
                        <option value="">-- Selecione um cargo (ou use a coluna cargo) --</option>
                        <?php foreach ($cargos as $cargo): ?>
                        <option value="<?php echo $cargo['id']; ?>">
                            <?php echo htmlspecialchars($cargo['nome']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Se não preenchido, usará a coluna "cargo" do CSV</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Senha Padrão</label>
                    <input type="text" name="senha_padrao" value="123456">
                    <small>Senha inicial para os novos funcionários</small>
                </div>
                
                <div class="form-group">
                    <label>Arquivo CSV *</label>
                    <input type="file" name="arquivo" accept=".csv" required>
                    <small>Use ponto e vírgula (;) como separador</small>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Importar Funcionários
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Gerar template CSV
document.getElementById('downloadTemplate').addEventListener('click', function(e) {
    e.preventDefault();
    
    const headers = ['nome', 'email', 'matricula', 'cpf', 'telefone', 'data_admissao', 'cargo'];
    const exemplo = ['João Silva', 'joao@exemplo.com', 'FUNC001', '123.456.789-00', '(11) 99999-9999', '2024-01-01', 'Analista'];
    
    let csvContent = headers.join(';') + '\n';
    csvContent += exemplo.join(';') + '\n';
    csvContent += 'Maria Santos;maria@exemplo.com;FUNC002;987.654.321-00;(11) 98888-8888;2024-02-01;Vendedor\n';
    csvContent += 'Pedro Costa;pedro@exemplo.com;FUNC003;111.222.333-44;(21) 97777-7777;2024-03-01;Auxiliar\n';
    
    const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.href = url;
    link.setAttribute('download', 'template_funcionarios.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
});
</script>

<?php require_once '../../includes/footer.php'; ?>


