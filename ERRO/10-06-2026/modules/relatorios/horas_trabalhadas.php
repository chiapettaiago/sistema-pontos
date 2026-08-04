<?php
/**
 * RELATÓRIO DE HORAS TRABALHADAS
 * Corrigido - Versão estável
 */

// Forçar autenticação
require_once __DIR__ . '/../../includes/auth_check.php';
forceAuthentication();

// Verificar permissão de administrador
requireAdmin();

// Carregar cabeçalho
require_once __DIR__ . '/../../includes/header.php';

// Conexão com banco
global $pdo;

// Função para formatar data de forma segura
function formatarDataSegura($data, $formato = 'd/m/Y') {
    if (empty($data) || $data === '0000-00-00' || $data === '0000-00-00 00:00:00') {
        return '-';
    }
    
    $timestamp = is_numeric($data) ? $data : strtotime($data);
    if ($timestamp === false || $timestamp <= 0) {
        return '-';
    }
    
    return date($formato, $timestamp);
}

// Função para calcular horas de forma segura
function calcularHorasSeguras($hora_inicio, $hora_fim) {
    if (empty($hora_inicio) || empty($hora_fim)) {
        return '00:00';
    }
    
    try {
        $inicio = new DateTime($hora_inicio);
        $fim = new DateTime($hora_fim);
        $intervalo = $inicio->diff($fim);
        return $intervalo->format('%H:%I');
    } catch (Exception $e) {
        return '00:00';
    }
}

// Pegar parâmetros do formulário com validação segura
$data_inicio = isset($_GET['data_inicio']) && !empty($_GET['data_inicio']) 
    ? $_GET['data_inicio'] 
    : date('Y-m-01');

$data_fim = isset($_GET['data_fim']) && !empty($_GET['data_fim']) 
    ? $_GET['data_fim'] 
    : date('Y-m-t');

$funcionario_id = isset($_GET['funcionario_id']) && !empty($_GET['funcionario_id']) 
    ? (int)$_GET['funcionario_id'] 
    : 0;

// Validar datas
if (strtotime($data_inicio) === false) {
    $data_inicio = date('Y-m-01');
}
if (strtotime($data_fim) === false) {
    $data_fim = date('Y-m-t');
}

// Buscar lista de funcionários para o filtro
$funcionarios = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM funcionarios WHERE status = 'ativo' ORDER BY nome");
    $funcionarios = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar funcionários: " . $e->getMessage());
}

// Buscar relatório de horas
$relatorio = [];
$total_horas = 0;
$total_minutos = 0;

if ($funcionario_id > 0) {
    try {
        $sql = "
            SELECT 
                DATE(p.data_hora) as data,
                p.funcionario_id,
                f.nome as funcionario_nome,
                MIN(CASE WHEN p.tipo IN ('entrada', 'inicio_expediente') THEN p.data_hora END) as primeira_entrada,
                MAX(CASE WHEN p.tipo IN ('saida', 'fim_expediente') THEN p.data_hora END) as ultima_saida,
                SUM(CASE 
                    WHEN p.tipo = 'intervalo_inicio' THEN TIME_TO_SEC(p.data_hora)
                    WHEN p.tipo = 'intervalo_fim' THEN -TIME_TO_SEC(p.data_hora)
                    ELSE 0 
                END) as tempo_intervalo,
                COUNT(*) as total_registros
            FROM pontos p
            INNER JOIN funcionarios f ON p.funcionario_id = f.id
            WHERE p.funcionario_id = :funcionario_id
                AND DATE(p.data_hora) BETWEEN :data_inicio AND :data_fim
            GROUP BY DATE(p.data_hora), p.funcionario_id, f.nome
            ORDER BY data DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':funcionario_id' => $funcionario_id,
            ':data_inicio' => $data_inicio,
            ':data_fim' => $data_fim
        ]);
        $relatorio = $stmt->fetchAll();
        
        // Calcular total de horas
        foreach ($relatorio as $row) {
            $entrada = $row['primeira_entrada'] ?? null;
            $saida = $row['ultima_saida'] ?? null;
            
            if (!empty($entrada) && !empty($saida)) {
                $entrada_time = strtotime($entrada);
                $saida_time = strtotime($saida);
                
                if ($entrada_time !== false && $saida_time !== false && $entrada_time > 0 && $saida_time > 0) {
                    $diferenca = $saida_time - $entrada_time;
                    
                    // Subtrair intervalo se existir
                    $intervalo_segundos = (int)($row['tempo_intervalo'] ?? 0);
                    $diferenca -= $intervalo_segundos;
                    
                    $total_segundos = ($total_horas * 3600) + ($total_minutos * 60);
                    $total_segundos += max(0, $diferenca);
                    
                    $total_horas = floor($total_segundos / 3600);
                    $total_minutos = floor(($total_segundos % 3600) / 60);
                }
            }
        }
        
    } catch (PDOException $e) {
        error_log("Erro no relatório: " . $e->getMessage());
        $erro_msg = "Erro ao gerar relatório: " . $e->getMessage();
    }
}

// Formatar total
$total_formatado = sprintf("%02d:%02d", $total_horas, $total_minutos);
?>

<div class="container-fluid mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4><i class="fas fa-chart-line"></i> Relatório de Horas Trabalhadas</h4>
        </div>
        <div class="card-body">
            <!-- Filtros -->
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Funcionário</label>
                    <select name="funcionario_id" class="form-select" required>
                        <option value="">Selecione um funcionário</option>
                        <?php foreach ($funcionarios as $func): ?>
                        <option value="<?= $func['id'] ?>" <?= $funcionario_id == $func['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($func['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Início</label>
                    <input type="date" name="data_inicio" class="form-control" 
                           value="<?= htmlspecialchars($data_inicio) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Fim</label>
                    <input type="date" name="data_fim" class="form-control" 
                           value="<?= htmlspecialchars($data_fim) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>
            </form>
            
            <?php if (isset($erro_msg)): ?>
                <div class="alert alert-danger"><?= $erro_msg ?></div>
            <?php endif; ?>
            
            <?php if ($funcionario_id > 0 && !empty($relatorio)): ?>
                <!-- Resumo -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5>Total de Dias</h5>
                                <h2><?= count($relatorio) ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5>Total de Horas</h5>
                                <h2><?= $total_formatado ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h5>Média Diária</h5>
                                <h2>
                                    <?php
                                    $media = '00:00';
                                    if (count($relatorio) > 0) {
                                        $media_segundos = ($total_horas * 3600 + $total_minutos * 60) / count($relatorio);
                                        $media_horas = floor($media_segundos / 3600);
                                        $media_minutos = floor(($media_segundos % 3600) / 60);
                                        $media = sprintf("%02d:%02d", $media_horas, $media_minutos);
                                    }
                                    echo $media;
                                    ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabela de detalhes -->
                <div class="table-responsive">
                    <table class="table table-striped table-bordered" id="tabelaRelatorio">
                        <thead class="table-dark">
                            <tr>
                                <th>Data</th>
                                <th>Funcionário</th>
                                <th>Entrada</th>
                                <th>Saída</th>
                                <th>Intervalo</th>
                                <th>Total Horas</th>
                                <th>Registros</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($relatorio as $row): 
                                $entrada = formatarDataSegura($row['primeira_entrada'] ?? null, 'H:i:s');
                                $saida = formatarDataSegura($row['ultima_saida'] ?? null, 'H:i:s');
                                $intervalo = '00:00';
                                $total_dia = '00:00';
                                
                                if (!empty($row['primeira_entrada']) && !empty($row['ultima_saida'])) {
                                    $entrada_time = strtotime($row['primeira_entrada']);
                                    $saida_time = strtotime($row['ultima_saida']);
                                    
                                    if ($entrada_time !== false && $saida_time !== false && $entrada_time > 0 && $saida_time > 0) {
                                        $diferenca = $saida_time - $entrada_time;
                                        $intervalo_segundos = (int)($row['tempo_intervalo'] ?? 0);
                                        $diferenca -= $intervalo_segundos;
                                        $diferenca = max(0, $diferenca);
                                        
                                        $horas = floor($diferenca / 3600);
                                        $minutos = floor(($diferenca % 3600) / 60);
                                        $total_dia = sprintf("%02d:%02d", $horas, $minutos);
                                        
                                        $int_horas = floor($intervalo_segundos / 3600);
                                        $int_minutos = floor(($intervalo_segundos % 3600) / 60);
                                        $intervalo = sprintf("%02d:%02d", $int_horas, $int_minutos);
                                    }
                                }
                            ?>
                            <tr>
                                <td><?= formatarDataSegura($row['data']) ?></td>
                                <td><?= htmlspecialchars($row['funcionario_nome']) ?></td>
                                <td><?= $entrada ?></td>
                                <td><?= $saida ?></td>
                                <td><?= $intervalo ?></td>
                                <td><strong><?= $total_dia ?></strong></td>
                                <td><?= $row['total_registros'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="5" class="text-end">TOTAL GERAL:</th>
                                <th><?= $total_formatado ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Botões de ação -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-success" onclick="exportarExcel()">
                                <i class="fas fa-file-excel"></i> Exportar Excel
                            </button>
                            <button type="button" class="btn btn-danger" onclick="exportarPDF()">
                                <i class="fas fa-file-pdf"></i> Exportar PDF
                            </button>
                            <button type="button" class="btn btn-info" onclick="window.print()">
                                <i class="fas fa-print"></i> Imprimir
                            </button>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($funcionario_id > 0 && empty($relatorio)): ?>
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                    <h5>Nenhum registro encontrado</h5>
                    <p>Não há registros de ponto para o período selecionado.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function exportarExcel() {
    const tabela = document.getElementById('tabelaRelatorio');
    const nomeFuncionario = document.querySelector('select[name="funcionario_id"] option:checked')?.text || 'Todos';
    const dataInicio = document.querySelector('input[name="data_inicio"]').value;
    const dataFim = document.querySelector('input[name="data_fim"]').value;
    
    let html = `
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Relatório de Horas Trabalhadas</title>
        </head>
        <body>
            <h2>Relatório de Horas Trabalhadas</h2>
            <p>Funcionário: ${nomeFuncionario}</p>
            <p>Período: ${dataInicio} a ${dataFim}</p>
            <p>Gerado em: ${new Date().toLocaleString('pt-BR')}</p>
            ${tabela.outerHTML}
            <p>Total de Horas: <?= $total_formatado ?></p>
        </body>
        </html>
    `;
    
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `relatorio_horas_${nomeFuncionario}_${dataInicio}_a_${dataFim}.xls`;
    link.click();
    URL.revokeObjectURL(link.href);
}

function exportarPDF() {
    window.print();
}
</script>

<style>
@media print {
    .btn-group, form, .card-header .btn, .btn-print {
        display: none !important;
    }
    .card {
        border: none !important;
    }
    .table {
        font-size: 12px;
    }
}

.table-responsive {
    overflow-x: auto;
}

.btn-group {
    gap: 10px;
}

@media (max-width: 768px) {
    .btn-group {
        flex-direction: column;
    }
    .btn-group .btn {
        margin-bottom: 5px;
    }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>