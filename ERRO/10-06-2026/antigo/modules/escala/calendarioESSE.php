<?php
// modules/escala/calendario.php - Visualização em calendário das escalas
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($usuario_tipo, ['super_admin', 'admin_empresa', 'gestor'])) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Calendário de Escalas';
$activePage = 'escala';
require_once '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$empresa_id = $_SESSION['empresa_id'] ?? 1;

// Parâmetros
$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');
$funcionario_id = $_GET['funcionario'] ?? '';

// Primeiro dia do mês
$primeiro_dia = mktime(0, 0, 0, $mes, 1, $ano);
$dias_no_mes = date('t', $primeiro_dia);
$dia_semana_inicio = date('w', $primeiro_dia);

// Ajustar para segunda-feira como primeiro dia da semana
$dia_semana_inicio = $dia_semana_inicio == 0 ? 6 : $dia_semana_inicio - 1;

// Nome do mês
$nomes_meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

// Navegação
$mes_anterior = $mes == 1 ? 12 : $mes - 1;
$ano_anterior = $mes == 1 ? $ano - 1 : $ano;
$mes_proximo = $mes == 12 ? 1 : $mes + 1;
$ano_proximo = $mes == 12 ? $ano + 1 : $ano;

// Buscar funcionários
$stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios 
                      WHERE empresa_id = :empresa_id AND status = 'ativo' 
                      ORDER BY nome");
$stmt->execute([':empresa_id' => $empresa_id]);
$funcionarios = $stmt->fetchAll();

// Buscar funcionário selecionado
$funcionario_selecionado = null;
if ($funcionario_id) {
    $stmt = $db->prepare("SELECT nome, matricula FROM funcionarios WHERE id = :id");
    $stmt->execute([':id' => $funcionario_id]);
    $funcionario_selecionado = $stmt->fetch();
}

// Buscar escalas ativas
$query = "SELECT fe.*, f.nome as funcionario_nome, f.id as funcionario_id,
          et.nome as escala_nome, et.tipo as escala_tipo, et.horas_por_dia
          FROM funcionario_escala fe
          JOIN funcionarios f ON fe.funcionario_id = f.id
          JOIN escala_tipos et ON fe.escala_tipo_id = et.id
          WHERE f.empresa_id = :empresa_id AND fe.ativo = 1";

$params = [':empresa_id' => $empresa_id];

if ($funcionario_id) {
    $query .= " AND fe.funcionario_id = :funcionario_id";
    $params[':funcionario_id'] = $funcionario_id;
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$escalas = $stmt->fetchAll();

// Buscar escalas diárias (plantões específicos)
$query = "SELECT ed.*, f.nome as funcionario_nome 
          FROM escala_diaria ed
          JOIN funcionarios f ON ed.funcionario_id = f.id
          WHERE f.empresa_id = :empresa_id 
          AND MONTH(ed.data) = :mes AND YEAR(ed.data) = :ano";

$params_diaria = [
    ':empresa_id' => $empresa_id,
    ':mes' => $mes,
    ':ano' => $ano
];

if ($funcionario_id) {
    $query .= " AND ed.funcionario_id = :funcionario_id";
    $params_diaria[':funcionario_id'] = $funcionario_id;
}

$stmt = $db->prepare($query);
$stmt->execute($params_diaria);
$escalas_diarias = $stmt->fetchAll();

// Função para determinar se o funcionário trabalha em uma data específica
function funcionarioTrabalha($data, $escala) {
    $dia_semana = date('N', strtotime($data)); // 1=Segunda a 7=Domingo
    
    // Se tem dias de trabalho personalizados
    if (!empty($escala['dias_trabalho'])) {
        $dias_trabalho = explode(',', $escala['dias_trabalho']);
        return in_array($dia_semana, $dias_trabalho);
    }
    
    // Escalas padrão
    switch ($escala['escala_tipo']) {
        case '5x2':
            // Trabalha segunda a sexta (1-5), folga sábado(6) e domingo(7)
            return $dia_semana <= 5;
        case '6x1':
            // Trabalha segunda a sábado (1-6), folga domingo(7)
            return $dia_semana <= 6;
        case '12x36':
            // Alterna entre trabalho e folga a cada 12h
            // Simplificado - precisa de lógica mais complexa
            return true;
        default:
            return true;
    }
}
?>

<style>
.calendario-container {
    max-width: 1200px;
    margin: 0 auto;
}

.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.module-title h2 {
    margin: 0;
    font-size: 24px;
}

.module-title p {
    margin: 8px 0 0;
    color: var(--text-secondary);
}

.navegacao-mes {
    display: flex;
    align-items: center;
    gap: 20px;
    justify-content: center;
    margin-bottom: 24px;
}

.btn-mes {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 8px 16px;
    text-decoration: none;
    color: var(--text-primary);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.mes-atual {
    font-size: 20px;
    font-weight: 700;
}

.filters-card {
    background: var(--bg-primary);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    border: 1px solid var(--border-color);
}

.filters-form {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-size: 12px;
    color: var(--text-secondary);
}

.filter-group select {
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--bg-primary);
    color: var(--text-primary);
    min-width: 220px;
}

.btn-filter {
    padding: 10px 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
}

.calendario {
    background: var(--bg-primary);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.calendario-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.dia-semana {
    padding: 16px;
    text-align: center;
    font-weight: 600;
    font-size: 14px;
    border-right: 1px solid rgba(255,255,255,0.1);
}

.dia-semana:last-child {
    border-right: none;
}

.calendario-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}

.dia {
    min-height: 120px;
    border-right: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
    padding: 8px;
    position: relative;
}

.dia:nth-child(7n) {
    border-right: none;
}

.dia-embranco {
    background: var(--bg-secondary);
    min-height: 120px;
    border-right: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
}

.numero-dia {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--text-primary);
}

.fim-de-semana .numero-dia {
    color: #ef4444;
}

.trabalho-item {
    font-size: 11px;
    padding: 4px 6px;
    margin-bottom: 4px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.trabalho-item-trabalho {
    background: #d1fae5;
    color: #059669;
}

.trabalho-item-folga {
    background: #fee2e2;
    color: #dc2626;
}

.trabalho-item-plantao {
    background: #fed7aa;
    color: #c2410c;
}

.trabalho-item:hover {
    transform: scale(1.02);
}

.feriado {
    background: #fef3c7;
    color: #d97706;
}

.legenda {
    display: flex;
    gap: 24px;
    justify-content: center;
    margin-top: 20px;
    padding: 16px;
    flex-wrap: wrap;
}

.legenda-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
}

.legenda-cor {
    width: 16px;
    height: 16px;
    border-radius: 4px;
}

.cor-trabalho { background: #d1fae5; border: 1px solid #059669; }
.cor-folga { background: #fee2e2; border: 1px solid #dc2626; }
.cor-plantao { background: #fed7aa; border: 1px solid #c2410c; }
.cor-feriado { background: #fef3c7; border: 1px solid #d97706; }

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--bg-primary);
    border-radius: 24px;
    padding: 24px;
    max-width: 400px;
    width: 90%;
}

@media (max-width: 768px) {
    .dia {
        min-height: 80px;
        font-size: 10px;
    }
    
    .trabalho-item {
        font-size: 9px;
        padding: 2px 4px;
    }
    
    .dia-semana {
        font-size: 10px;
        padding: 10px;
    }
}
</style>

<div class="calendario-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-calendar-alt"></i> Calendário de Escalas</h2>
            <p>Visualização das escalas de trabalho</p>
        </div>
    </div>

    <!-- Navegação do Mês -->
    <div class="navegacao-mes">
        <a href="?mes=<?php echo $mes_anterior; ?>&ano=<?php echo $ano_anterior; ?>&funcionario=<?php echo $funcionario_id; ?>" class="btn-mes">
            <i class="fas fa-chevron-left"></i> <?php echo $nomes_meses[$mes_anterior]; ?>
        </a>
        <div class="mes-atual"><?php echo $nomes_meses[$mes] . ' de ' . $ano; ?></div>
        <a href="?mes=<?php echo $mes_proximo; ?>&ano=<?php echo $ano_proximo; ?>&funcionario=<?php echo $funcionario_id; ?>" class="btn-mes">
            <?php echo $nomes_meses[$mes_proximo]; ?> <i class="fas fa-chevron-right"></i>
        </a>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-form">
            <div class="filter-group">
                <label>Funcionário</label>
                <select name="funcionario">
                    <option value="">Todos os funcionários</option>
                    <?php foreach ($funcionarios as $func): ?>
                        <option value="<?php echo $func['id']; ?>" <?php echo $funcionario_id == $func['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($func['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="mes" value="<?php echo $mes; ?>">
            <input type="hidden" name="ano" value="<?php echo $ano; ?>">
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn-filter">Aplicar Filtro</button>
            </div>
        </form>
    </div>

    <?php if ($funcionario_selecionado): ?>
        <div style="margin-bottom: 16px; padding: 12px; background: var(--bg-secondary); border-radius: 12px;">
            <strong>Exibindo escala de:</strong> <?php echo htmlspecialchars($funcionario_selecionado['nome']); ?> (<?php echo htmlspecialchars($funcionario_selecionado['matricula']); ?>)
        </div>
    <?php endif; ?>

    <!-- Calendário -->
    <div class="calendario">
        <div class="calendario-header">
            <div class="dia-semana">SEG</div>
            <div class="dia-semana">TER</div>
            <div class="dia-semana">QUA</div>
            <div class="dia-semana">QUI</div>
            <div class="dia-semana">SEX</div>
            <div class="dia-semana">SÁB</div>
            <div class="dia-semana">DOM</div>
        </div>
        <div class="calendario-grid">
            <?php
            $dia_atual = 1;
            $dia_semana_atual = $dia_semana_inicio;
            
            // Células vazias no início
            for ($i = 0; $i < $dia_semana_inicio; $i++) {
                echo '<div class="dia-embranco"></div>';
                $dia_atual--;
            }
            
            // Dias do mês
            for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
                $data = sprintf("%04d-%02d-%02d", $ano, $mes, $dia);
                $is_fim_semana = ($dia_semana_atual == 5 || $dia_semana_atual == 6);
                
                echo '<div class="dia ' . ($is_fim_semana ? 'fim-de-semana' : '') . '">';
                echo '<div class="numero-dia">' . $dia . '</div>';
                
                // Mostrar escalas dos funcionários
                foreach ($escalas as $escala) {
                    if (funcionarioTrabalha($data, $escala)) {
                        echo '<div class="trabalho-item trabalho-item-trabalho" onclick="verDetalhes(' . $escala['funcionario_id'] . ', \'' . $data . '\')">';
                        echo '<i class="fas fa-briefcase"></i> ' . htmlspecialchars(substr($escala['funcionario_nome'], 0, 20)) . ' (' . htmlspecialchars($escala['escala_nome']) . ')';
                        echo '</div>';
                    }
                }
                
                // Mostrar escalas diárias (plantões)
                foreach ($escalas_diarias as $ed) {
                    if ($ed['data'] == $data) {
                        echo '<div class="trabalho-item trabalho-item-plantao" onclick="verDetalhes(' . $ed['funcionario_id'] . ', \'' . $data . '\')">';
                        echo '<i class="fas fa-moon"></i> ' . htmlspecialchars(substr($ed['funcionario_nome'], 0, 20)) . ' (Plantão ' . $ed['turno'] . ')';
                        echo '</div>';
                    }
                }
                
                echo '</div>';
                
                $dia_semana_atual++;
                if ($dia_semana_atual >= 7) {
                    $dia_semana_atual = 0;
                }
            }
            
            // Células vazias no final
            $dias_restantes = 6 - $dia_semana_atual;
            for ($i = 0; $i < $dias_restantes; $i++) {
                echo '<div class="dia-embranco"></div>';
            }
            ?>
        </div>
    </div>

    <!-- Legenda -->
    <div class="legenda">
        <div class="legenda-item">
            <div class="legenda-cor cor-trabalho"></div>
            <span>Dia Trabalhado</span>
        </div>
        <div class="legenda-item">
            <div class="legenda-cor cor-folga"></div>
            <span>Dia de Folga</span>
        </div>
        <div class="legenda-item">
            <div class="legenda-cor cor-plantao"></div>
            <span>Plantão</span>
        </div>
        <div class="legenda-item">
            <div class="legenda-cor cor-feriado"></div>
            <span>Feriado</span>
        </div>
    </div>
</div>

<!-- Modal de Detalhes -->
<div id="modalDetalhes" class="modal">
    <div class="modal-content">
        <h3 id="modalTitulo">Detalhes</h3>
        <div id="modalBody"></div>
        <div style="margin-top: 20px; text-align: center;">
            <button onclick="fecharModal()" class="btn btn-secondary">Fechar</button>
        </div>
    </div>
</div>

<script>
function verDetalhes(funcionarioId, data) {
    window.location.href = 'index.php?funcionario_id=' + funcionarioId;
}

function fecharModal() {
    document.getElementById('modalDetalhes').style.display = 'none';
}
</script>

<?php require_once '../../includes/footer.php'; ?>