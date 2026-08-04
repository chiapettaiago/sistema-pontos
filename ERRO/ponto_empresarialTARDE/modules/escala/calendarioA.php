<?php
// modules/escala/calendario.php - Calendário de Escalas
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
$funcionario_id = $_GET['funcionario'] ?? '';
$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');

// Buscar funcionário específico ou listar todos
if ($funcionario_id) {
    $stmt = $db->prepare("SELECT id, nome, matricula FROM funcionarios WHERE id = :id AND empresa_id = :empresa_id");
    $stmt->execute([':id' => $funcionario_id, ':empresa_id' => $empresa_id]);
    $funcionario = $stmt->fetch();
    
    if (!$funcionario) {
        header('Location: index.php');
        exit;
    }
    
    // Buscar escala do funcionário - CORRIGIDO: usar carga_horaria_diaria em vez de horas_por_dia
    $stmt = $db->prepare("
        SELECT fe.*, et.nome as escala_nome, et.tipo as escala_tipo, 
               et.carga_horaria_diaria as horas_por_dia,
               et.dias_trabalho, et.dias_descanso
        FROM funcionario_escala fe
        JOIN escala_tipos et ON fe.escala_tipo_id = et.id
        WHERE fe.funcionario_id = :funcionario_id 
        AND fe.ativo = 1
        AND (fe.data_fim IS NULL OR fe.data_fim >= CURDATE())
        ORDER BY fe.data_inicio DESC
        LIMIT 1
    ");
    $stmt->execute([':funcionario_id' => $funcionario_id]);
    $escala_atual = $stmt->fetch();
    
    if (!$escala_atual) {
        echo "<div class='alert alert-warning'>Funcionário não possui escala cadastrada.</div>";
        echo "<a href='index.php' class='btn btn-secondary'>Voltar</a>";
        require_once '../../includes/footer.php';
        exit;
    }
    
    // Gerar dias do mês
    $dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
    $primeiro_dia = mktime(0, 0, 0, $mes, 1, $ano);
    $dia_semana_inicio = date('w', $primeiro_dia);
    
} else {
    // Buscar todos os funcionários com escala
    $stmt = $db->prepare("
        SELECT f.id, f.nome, f.matricula, 
               fe.data_inicio, fe.data_fim,
               et.nome as escala_nome, et.tipo as escala_tipo,
               et.carga_horaria_diaria as horas_por_dia
        FROM funcionarios f
        JOIN funcionario_escala fe ON f.id = fe.funcionario_id
        JOIN escala_tipos et ON fe.escala_tipo_id = et.id
        WHERE f.empresa_id = :empresa_id 
        AND f.status = 'ativo'
        AND fe.ativo = 1
        AND (fe.data_fim IS NULL OR fe.data_fim >= CURDATE())
        ORDER BY f.nome
    ");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $funcionarios_escala = $stmt->fetchAll();
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

.calendario-card {
    background: var(--bg-primary);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.calendario-mes {
    padding: 20px;
    text-align: center;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.calendario-mes h3 {
    margin: 0;
    font-size: 24px;
}

.calendario-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: var(--bg-primary);
}

.calendario-dia-semana {
    padding: 12px;
    text-align: center;
    font-weight: 600;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
}

.calendario-dia {
    min-height: 100px;
    padding: 8px;
    border-right: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
    position: relative;
}

.calendario-dia .numero {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
}

.calendario-dia.trabalho {
    background: rgba(102, 126, 234, 0.1);
}

.calendario-dia.descanso {
    background: rgba(16, 185, 129, 0.1);
}

.calendario-dia.feriado {
    background: rgba(239, 68, 68, 0.1);
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 500;
}

.status-trabalho { background: #667eea; color: white; }
.status-descanso { background: #10b981; color: white; }
.status-feriado { background: #ef4444; color: white; }

.horario-info {
    font-size: 10px;
    color: var(--text-secondary);
    margin-top: 5px;
}

.funcionario-card {
    background: var(--bg-primary);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 12px;
    border: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.mes-navegacao {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-bottom: 20px;
}

.mes-navegacao a {
    background: var(--bg-secondary);
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-primary);
}

@media (max-width: 768px) {
    .calendario-dia {
        min-height: 80px;
        font-size: 12px;
    }
    .calendario-dia .numero {
        font-size: 12px;
    }
}
</style>

<div class="calendario-container">
    <div class="module-header">
        <div class="module-title">
            <h2><i class="fas fa-calendar-alt"></i> Calendário de Escalas</h2>
            <p>Visualize as escalas dos funcionários</p>
        </div>
        <div class="module-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <?php if ($funcionario_id && isset($escala_atual)): ?>
        <!-- Calendário do funcionário específico -->
        <div class="calendario-card">
            <div class="calendario-mes">
                <h3><?php echo $funcionario['nome']; ?></h3>
                <p>Escala: <?php echo htmlspecialchars($escala_atual['escala_nome']); ?></p>
                <p>Carga horária: <?php echo $escala_atual['horas_por_dia']; ?> horas/dia</p>
            </div>
            
            <div class="mes-navegacao">
                <a href="?funcionario=<?php echo $funcionario_id; ?>&mes=<?php echo $mes-1; ?>&ano=<?php echo $ano; ?>">
                    <i class="fas fa-chevron-left"></i> Mês Anterior
                </a>
                <span><strong><?php echo strftime('%B de %Y', mktime(0, 0, 0, $mes, 1, $ano)); ?></strong></span>
                <a href="?funcionario=<?php echo $funcionario_id; ?>&mes=<?php echo $mes+1; ?>&ano=<?php echo $ano; ?>">
                    Próximo Mês <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            
            <div class="calendario-grid">
                <?php
                $dias_semana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                foreach ($dias_semana as $dia):
                ?>
                    <div class="calendario-dia-semana"><?php echo $dia; ?></div>
                <?php endforeach; ?>
                
                <?php
                // Dias vazios no início
                for ($i = 0; $i < $dia_semana_inicio; $i++):
                ?>
                    <div class="calendario-dia"></div>
                <?php endfor; ?>
                
                <?php
                // Gerar dias do mês
                $tipo_escala = $escala_atual['escala_tipo'];
                $dias_trabalho = $escala_atual['dias_trabalho'] ?? 5;
                $dias_descanso = $escala_atual['dias_descanso'] ?? 2;
                $contador = 0;
                $ciclo = 0;
                
                for ($dia = 1; $dia <= $dias_no_mes; $dia++):
                    $data_atual = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                    
                    // Buscar feriado
                    $stmt_feriado = $db->prepare("SELECT nome FROM feriados WHERE data_feriado = :data AND empresa_id = :empresa_id");
                    $stmt_feriado->execute([':data' => $data_atual, ':empresa_id' => $empresa_id]);
                    $feriado = $stmt_feriado->fetch();
                    
                    // Determinar tipo de dia baseado na escala
                    $tipo_dia = 'trabalho';
                    if ($feriado) {
                        $tipo_dia = 'feriado';
                        $descricao = $feriado['nome'];
                    } else {
                        if ($tipo_escala == '5x2') {
                            // 5x2: trabalho de segunda a sexta, descanso sábado/domingo
                            $dia_semana_num = date('w', mktime(0, 0, 0, $mes, $dia, $ano));
                            if ($dia_semana_num == 0 || $dia_semana_num == 6) {
                                $tipo_dia = 'descanso';
                            }
                        } elseif ($tipo_escala == '6x1') {
                            // 6x1: 6 dias trabalho, 1 descanso
                            $contador++;
                            if ($contador > $dias_trabalho) {
                                $contador = 1;
                                $ciclo++;
                            }
                            if ($ciclo % ($dias_trabalho + $dias_descanso) >= $dias_trabalho) {
                                $tipo_dia = 'descanso';
                            }
                        } elseif ($tipo_escala == '12x36') {
                            // 12x36: dia sim, dia não
                            $dias_desde_inicio = (strtotime($data_atual) - strtotime($escala_atual['data_inicio'])) / 86400;
                            if (floor($dias_desde_inicio) % 2 == 0) {
                                $tipo_dia = 'trabalho';
                            } else {
                                $tipo_dia = 'descanso';
                            }
                        }
                    }
                    ?>
                    <div class="calendario-dia <?php echo $tipo_dia; ?>">
                        <div class="numero"><?php echo $dia; ?></div>
                        <?php if ($tipo_dia == 'trabalho'): ?>
                            <span class="status-badge status-trabalho">Trabalho</span>
                            <div class="horario-info">
                                <?php echo date('H:i', strtotime($escala_atual['horario_entrada'] ?? '08:00')); ?> - 
                                <?php echo date('H:i', strtotime($escala_atual['horario_saida'] ?? '18:00')); ?>
                            </div>
                        <?php elseif ($tipo_dia == 'descanso'): ?>
                            <span class="status-badge status-descanso">Descanso</span>
                        <?php elseif ($tipo_dia == 'feriado'): ?>
                            <span class="status-badge status-feriado"><?php echo $descricao ?? 'Feriado'; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Lista de todos os funcionários -->
        <div class="calendario-card">
            <div class="calendario-mes">
                <h3>Funcionários com Escala</h3>
                <p>Clique em um funcionário para ver o calendário detalhado</p>
            </div>
            <div style="padding: 20px;">
                <?php if (empty($funcionarios_escala)): ?>
                    <div class="alert alert-warning">Nenhum funcionário com escala cadastrada.</div>
                <?php else: ?>
                    <?php foreach ($funcionarios_escala as $func): ?>
                        <div class="funcionario-card">
                            <div>
                                <strong><?php echo htmlspecialchars($func['nome']); ?></strong><br>
                                <small>Matrícula: <?php echo htmlspecialchars($func['matricula']); ?></small>
                            </div>
                            <div>
                                <span class="status-badge status-trabalho"><?php echo htmlspecialchars($func['escala_nome']); ?></span>
                                <a href="?funcionario=<?php echo $func['id']; ?>" class="btn btn-primary" style="margin-left: 10px;">
                                    <i class="fas fa-calendar"></i> Ver Calendário
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>