<?php
/**
 * RELATÓRIO DE EXTRATO DO FUNCIONÁRIO
 * Compatível com PHP 8.x - Sem strftime()
 */

// Forçar autenticação
require_once __DIR__ . '/../../includes/auth_check.php';
forceAuthentication();

// Verificar permissão
requireAdmin();

// Carregar cabeçalho
require_once __DIR__ . '/../../includes/header.php';

// Conexão com banco
global $pdo;

/**
 * Função segura para formatar data (substituta do strftime)
 */
function formatarDataLocalizada($data, $formato = 'completo') {
    if (empty($data) || $data === '0000-00-00' || $data === '0000-00-00 00:00:00') {
        return '-';
    }
    
    $timestamp = is_numeric($data) ? $data : strtotime($data);
    if ($timestamp === false || $timestamp <= 0) {
        return '-';
    }
    
    $diasSemana = [
        'Sunday' => 'Domingo',
        'Monday' => 'Segunda-feira',
        'Tuesday' => 'Terça-feira',
        'Wednesday' => 'Quarta-feira',
        'Thursday' => 'Quinta-feira',
        'Friday' => 'Sexta-feira',
        'Saturday' => 'Sábado'
    ];
    
    $meses = [
        'January' => 'Janeiro',
        'February' => 'Fevereiro',
        'March' => 'Março',
        'April' => 'Abril',
        'May' => 'Maio',
        'June' => 'Junho',
        'July' => 'Julho',
        'August' => 'Agosto',
        'September' => 'Setembro',
        'October' => 'Outubro',
        'November' => 'Novembro',
        'December' => 'Dezembro'
    ];
    
    $diaSemanaIngles = date('l', $timestamp);
    $mesIngles = date('F', $timestamp);
    
    switch ($formato) {
        case 'completo':
            // Exemplo: Segunda-feira, 15 de Janeiro de 2024
            return sprintf(
                '%s, %d de %s de %d',
                $diasSemana[$diaSemanaIngles],
                date('d', $timestamp),
                $meses[$mesIngles],
                date('Y', $timestamp)
            );
        case 'data_hora':
            // Exemplo: 15/01/2024 14:30:00
            return date('d/m/Y H:i:s', $timestamp);
        case 'hora':
            return date('H:i:s', $timestamp);
        case 'data_curta':
            return date('d/m/Y', $timestamp);
        default:
            return date('d/m/Y H:i', $timestamp);
    }
}

// Parâmetros com validação segura
$funcionario_id = isset($_GET['funcionario_id']) ? (int)$_GET['funcionario_id'] : 0;
$data_inicio = isset($_GET['data_inicio']) && !empty($_GET['data_inicio']) 
    ? $_GET['data_inicio'] 
    : date('Y-m-01');
$data_fim = isset($_GET['data_fim']) && !empty($_GET['data_fim']) 
    ? $_GET['data_fim'] 
    : date('Y-m-t');

// Validar datas
if (strtotime($data_inicio) === false) $data_inicio = date('Y-m-01');
if (strtotime($data_fim) === false) $data_fim = date('Y-m-t');

// Buscar lista de funcionários
$funcionarios = [];
try {
    $stmt = $pdo->query("SELECT id, nome, cpf, cargo FROM funcionarios WHERE status = 'ativo' ORDER BY nome");
    $funcionarios = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar funcionários: " . $e->getMessage());
}

// Buscar dados do funcionário selecionado
$funcionario = null;
$registros = [];
$resumo = [
    'total_horas' => 0,
    'total_minutos' => 0,
    'dias_trabalhados' => 0,
    'total_atrasos' => 0,
    'total_faltas' => 0
];

if ($funcionario_id > 0) {
    try {
        // Buscar dados do funcionário
        $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = ?");
        $stmt->execute([$funcionario_id]);
        $funcionario = $stmt->fetch();
        
        // Buscar registros de ponto no período
        $sql = "
            SELECT 
                p.*,
                DATE(p.data_hora) as data_registro,
                TIME(p.data_hora) as hora_registro
            FROM pontos p
            WHERE p.funcionario_id = :funcionario_id
                AND DATE(p.data_hora) BETWEEN :data_inicio AND :data_fim
            ORDER BY p.data_hora DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':funcionario_id' => $funcionario_id,
            ':data_inicio' => $data_inicio,
            ':data_fim' => $data_fim
        ]);
        $registros = $stmt->fetchAll();
        
        // Calcular resumo
        $dias = [];
        foreach ($registros as $reg) {
            $data = $reg['data_registro'];
            if (!isset($dias[$data])) {
                $dias[$data] = ['entradas' => [], 'saidas' => []];
                $resumo['dias_trabalhados']++;
            }
            
            if (in_array($reg['tipo'], ['entrada', 'inicio_expediente'])) {
                $dias[$data]['entradas'][] = strtotime($reg['data_hora']);
            } elseif (in_array($reg['tipo'], ['saida', 'fim_expediente'])) {
                $dias[$data]['saidas'][] = strtotime($reg['data_hora']);
            }
        }
        
        // Calcular horas trabalhadas
        foreach ($dias as $data => $marcacoes) {
            if (!empty($marcacoes['entradas']) && !empty($marcacoes['saidas'])) {
                $entrada = min($marcacoes['entradas']);
                $saida = max($marcacoes['saidas']);
                $diferenca = $saida - $entrada;
                
                // Subtrair intervalo (considerando 1 hora padrão)
                $diferenca -= 3600; // 1 hora de intervalo
                $diferenca = max(0, $diferenca);
                
                $resumo['total_segundos'] = ($resumo['total_segundos'] ?? 0) + $diferenca;
            }
        }
        
        $total_segundos = $resumo['total_segundos'] ?? 0;
        $resumo['total_horas'] = floor($total_segundos / 3600);
        $resumo['total_minutos'] = floor(($total_segundos % 3600) / 60);
        
    } catch (PDOException $e) {
        error_log("Erro no extrato: " . $e->getMessage());
        $erro_msg = "Erro ao gerar extrato: " . $e->getMessage();
    }
}

$total_formatado = sprintf("%02d:%02d", $resumo['total_horas'], $resumo['total_minutos']);
?>

<div class="container-fluid mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4><i class="fas fa-file-alt"></i> Extrato do Funcionário</h4>
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
                            <?= htmlspecialchars($func['nome']) ?> - <?= htmlspecialchars($func['cargo'] ?? 'Sem cargo') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Início</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Fim</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Gerar Extrato
                    </button>
                </div>
            </form>
            
            <?php if (isset($erro_msg)): ?>
                <div class="alert alert-danger"><?= $erro_msg ?></div>
            <?php endif; ?>
            
            <?php if ($funcionario_id > 0 && $funcionario): ?>
                <!-- Informações do Funcionário -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5><i class="fas fa-user"></i> Dados do Funcionário</h5>
                                        <p><strong>Nome:</strong> <?= htmlspecialchars($funcionario['nome']) ?></p>
                                        <p><strong>CPF:</strong> <?= $funcionario['cpf'] ?></p>
                                        <p><strong>Cargo:</strong> <?= htmlspecialchars($funcionario['cargo'] ?? 'Não informado') ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h5><i class="fas fa-chart-bar"></i> Resumo do Período</h5>
                                        <p><strong>Período:</strong> <?= formatarDataLocalizada($data_inicio, 'data_curta') ?> a <?= formatarDataLocalizada($data_fim, 'data_curta') ?></p>
                                        <p><strong>Dias trabalhados:</strong> <?= $resumo['dias_trabalhados'] ?></p>
                                        <p><strong>Total de horas:</strong> <?= $total_formatado ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabela de Registros -->
                <div class="table-responsive">
                    <table class="table table-striped table-bordered" id="tabelaExtrato">
                        <thead class="table-dark">
                            <tr>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Tipo</th>
                                <th>Origem</th>
                                <th>Observação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($registros)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">Nenhum registro encontrado no período</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($registros as $reg): ?>
                                    <tr>
                                        <td><?= formatarDataLocalizada($reg['data_registro'], 'data_curta') ?></td>
                                        <td><?= date('H:i:s', strtotime($reg['hora_registro'])) ?></
                                        <td>
                                            <?php
                                            $tipoLabels = [
                                                'entrada' => 'Entrada',
                                                'saida' => 'Saída',
                                                'intervalo_inicio' => 'Início Intervalo',
                                                'intervalo_fim' => 'Fim Intervalo',
                                                'inicio_expediente' => 'Início Expediente',
                                                'fim_expediente' => 'Fim Expediente'
                                            ];
                                            $tipoLabel = $tipoLabels[$reg['tipo']] ?? ucfirst($reg['tipo']);
                                            $badgeClass = in_array($reg['tipo'], ['entrada', 'inicio_expediente']) ? 'success' : 'danger';
                                            ?>
                                            <span class="badge bg-<?= $badgeClass ?>"><?= $tipoLabel ?></span>
                                        </
                                        <td>
                                            <i class="fas fa-<?= $reg['origem'] === 'web' ? 'laptop' : 'mobile-alt' ?>"></i>
                                            <?= $reg['origem'] ?? 'web' ?>
                                        </
                                        <td><?= htmlspecialchars($reg['observacao'] ?? '-') ?></
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </div>
                
                <!-- Botões de exportação -->
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
                
            <?php elseif ($funcionario_id > 0 && !$funcionario): ?>
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h5>Funcionário não encontrado</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function exportarExcel() {
    const tabela = document.getElementById('tabelaExtrato');
    const funcionarioNome = document.querySelector('select[name="funcionario_id"] option:checked')?.text || 'Funcionário';
    const dataInicio = document.querySelector('input[name="data_inicio"]').value;
    const dataFim = document.querySelector('input[name="data_fim"]').value;
    
    let html = `
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Extrato do Funcionário</title>
        </head>
        <body>
            <h2>Extrato de Ponto</h2>
            <p>Funcionário: ${funcionarioNome}</p>
            <p>Período: ${dataInicio} a ${dataFim}</p>
            <p>Gerado em: ${new Date().toLocaleString('pt-BR')}</p>
            ${tabela.outerHTML}
        </body>
        </html>
    `;
    
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `extrato_${funcionarioNome.replace(/[^a-z0-9]/gi, '_')}_${dataInicio}_a_${dataFim}.xls`;
    link.click();
    URL.revokeObjectURL(link.href);
}

function exportarPDF() {
    window.print();
}
</script>

<style>
@media print {
    .btn-group, form, .card-header .btn {
        display: none !important;
    }
    .card {
        border: none !important;
    }
}
.btn-group {
    gap: 10px;
}
@media (max-width: 768px) {
    .btn-group {
        flex-direction: column;
    }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>