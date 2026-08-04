<?php
// modules/funcionarios/visualizar.php - Visualizar Detalhes do Funcionário COM FOTO
$pageTitle = 'Detalhes do Funcionário';
$activePage = 'funcionarios';
require_once '../../includes/header.php';
require_once '../../config/database.php';

checkModuleAccess('funcionarios');

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? 0;

// Buscar dados do funcionário
$query = "SELECT f.*, 
          fil.nome_fantasia as filial_nome,
          c.nome as cargo_nome,
          d.nome as departamento_nome,
          fj.jornada_id,
          j.nome as jornada_nome,
          (SELECT COUNT(*) FROM pontos WHERE funcionario_id = f.id) as total_pontos,
          (SELECT COUNT(*) FROM pontos WHERE funcionario_id = f.id AND DATE(data_hora) = CURDATE()) as pontos_hoje
          FROM funcionarios f
          LEFT JOIN filiais fil ON f.filial_id = fil.id
          LEFT JOIN cargos c ON f.cargo_id = c.id
          LEFT JOIN departamentos d ON f.departamento_id = d.id
          LEFT JOIN funcionario_jornada fj ON f.id = fj.funcionario_id AND (fj.data_fim IS NULL OR fj.data_fim >= CURDATE())
          LEFT JOIN jornadas j ON fj.jornada_id = j.id
          WHERE f.id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $id]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    header('Location: index.php');
    exit;
}

// Buscar últimos pontos
$query = "SELECT * FROM pontos 
          WHERE funcionario_id = :id 
          ORDER BY data_hora DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $id]);
$ultimosPontos = $stmt->fetchAll();

// Buscar resumo mensal
$query = "SELECT DATE_FORMAT(data_hora, '%Y-%m') as mes, 
          COUNT(*) as total_pontos,
          MIN(DATE(data_hora)) as primeiro_dia,
          MAX(DATE(data_hora)) as ultimo_dia
          FROM pontos 
          WHERE funcionario_id = :id 
          GROUP BY DATE_FORMAT(data_hora, '%Y-%m')
          ORDER BY mes DESC LIMIT 6";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $id]);
$resumoMensal = $stmt->fetchAll();
?>

<div class="module-header">
    <div class="module-title">
        <h2><i class="fas fa-user"></i> <?php echo htmlspecialchars($funcionario['nome']); ?></h2>
        <p>Matrícula: <?php echo htmlspecialchars($funcionario['matricula']); ?></p>
    </div>
    <div class="module-actions">
        <a href="editar.php?id=<?php echo $id; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Editar
        </a>
        <a href="alterar_senha.php?id=<?php echo $id; ?>" class="btn btn-secondary">
            <i class="fas fa-key"></i> Alterar Senha
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="view-container">
    <!-- Perfil com Foto -->
    <div class="profile-card">
        <div class="profile-avatar">
            <?php if ($funcionario['foto'] && file_exists('../../' . $funcionario['foto'])): ?>
                <img src="../../<?php echo $funcionario['foto']; ?>" alt="Foto de <?php echo htmlspecialchars($funcionario['nome']); ?>" class="profile-photo">
            <?php else: ?>
                <div class="avatar-large">
                    <?php echo strtoupper(substr($funcionario['nome'], 0, 1)); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="profile-info">
            <h3><?php echo htmlspecialchars($funcionario['nome']); ?></h3>
            <p><?php echo htmlspecialchars($funcionario['cargo_nome'] ?? 'Cargo não definido'); ?></p>
            <div class="profile-badges">
                <span class="status-badge status-<?php echo $funcionario['status']; ?>">
                    <?php 
                    $statusLabels = [
                        'ativo' => '✅ Ativo',
                        'ferias' => '🏖️ Férias',
                        'licenca' => '📋 Licença',
                        'desligado' => '❌ Desligado',
                        'afastado' => '⚠️ Afastado'
                    ];
                    echo $statusLabels[$funcionario['status']] ?? $funcionario['status'];
                    ?>
                </span>
                <span class="role-badge role-<?php echo $funcionario['tipo_usuario']; ?>">
                    <?php 
                    $tipos = [
                        'admin' => '👑 Administrador',
                        'gestor' => '📊 Gestor',
                        'supervisor' => '👁️ Supervisor',
                        'funcionario' => '👤 Funcionário'
                    ];
                    echo $tipos[$funcionario['tipo_usuario']] ?? $funcionario['tipo_usuario'];
                    ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Informações Pessoais -->
    <div class="info-card">
        <div class="info-header">
            <h3><i class="fas fa-address-card"></i> Informações Pessoais</h3>
        </div>
        <div class="info-content">
            <div class="info-row">
                <div class="info-label">Matrícula:</div>
                <div class="info-value"><?php echo htmlspecialchars($funcionario['matricula']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">CPF:</div>
                <div class="info-value"><?php echo htmlspecialchars($funcionario['cpf'] ?? '--'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">RG:</div>
                <div class="info-value"><?php echo htmlspecialchars($funcionario['rg'] ?? '--'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Data Nascimento:</div>
                <div class="info-value"><?php echo $funcionario['data_nascimento'] ? date('d/m/Y', strtotime($funcionario['data_nascimento'])) : '--'; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">E-mail:</div>
                <div class="info-value"><?php echo htmlspecialchars($funcionario['email']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">E-mail Pessoal:</div>
                <div class="info-value"><?php echo htmlspecialchars($funcionario['email_pessoal'] ?? '--'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Telefone:</div>
                <div class="info-value"><?php echo htmlspecialchars($funcionario['telefone'] ?? '--'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Celular:</div>
                <div class="info-value"><?php echo htmlspecialchars($funcionario['celular'] ?? '--'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Endereço -->
    <?php if ($funcionario['endereco'] || $funcionario['cidade']): ?>
    <div class="info-card">
        <div class="info-header">
            <h3><i class="fas fa-map-marker-alt"></i> Endereço</h3>
        </div>
        <div class="info-content">
            <div class="info-row">
                <div class="info-label">CEP:</div>
                <div class="info-value"><?php echo htmlspecialchars($funcionario['cep'] ?? '--'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Endereço:</div>
                <div class="info-value">
                    <?php echo htmlspecialchars($funcionario['endereco'] ?? ''); ?>, <?php echo htmlspecialchars($funcionario['numero'] ?? ''); ?>
                    <?php if ($funcionario['complemento']): ?><br><small>Complemento: <?php echo htmlspecialchars($funcionario['complemento']); ?></small><?php endif; ?>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Bairro:</div>
                <div class="info-value"><?php echo htmlspecialchars($funcionario['bairro'] ?? '--'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Cidade/UF:</div>
                <div class="info-value"><?php echo htmlspecialchars($funcionario['cidade'] ?? '--'); ?> / <?php echo htmlspecialchars($funcionario['estado'] ?? '--'); ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Informações Profissionais -->
    <div class="info-card">
        <div class="info-header">
            <h3><i class="fas fa-briefcase"></i> Informações Profissionais</h3>
        </div>
        <div class="info-content">
            <div class="info-row">
                <div class="info-label">Filial:</div>
                <div class="info-value"><?php echo htmlspecialchars($funcionario['filial_nome']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Departamento:</div>
                <div class="info-value"><?php echo htmlspecialchars($funcionario['departamento_nome'] ?? '--'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Cargo:</div>
                <div class="info-value"><?php echo htmlspecialchars($funcionario['cargo_nome'] ?? '--'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Jornada:</div>
                <div class="info-value"><?php echo htmlspecialchars($funcionario['jornada_nome'] ?? '--'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Data Admissão:</div>
                <div class="info-value"><?php echo date('d/m/Y', strtotime($funcionario['data_admissao'])); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Tipo Contrato:</div>
                <div class="info-value"><?php echo strtoupper($funcionario['tipo_contrato']); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Estatísticas de Ponto -->
    <div class="info-card">
        <div class="info-header">
            <h3><i class="fas fa-chart-line"></i> Estatísticas de Ponto</h3>
        </div>
        <div class="info-content">
            <div class="stats-mini">
                <div class="stat-mini">
                    <div class="stat-mini-value"><?php echo $funcionario['total_pontos']; ?></div>
                    <div class="stat-mini-label">Total de Registros</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value"><?php echo $funcionario['pontos_hoje']; ?></div>
                    <div class="stat-mini-label">Registros Hoje</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Últimos Pontos -->
    <div class="info-card">
        <div class="info-header">
            <h3><i class="fas fa-history"></i> Últimos Registros de Ponto</h3>
        </div>
        <div class="info-content">
            <?php if (empty($ultimosPontos)): ?>
                <p class="text-muted">Nenhum registro de ponto encontrado</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Tipo</th>
                                <th>Origem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimosPontos as $ponto): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($ponto['data_hora'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $ponto['tipo']; ?>">
                                        <?php 
                                        $tipos = [
                                            'entrada' => 'Entrada',
                                            'saida_almoco' => 'Saída Almoço',
                                            'volta_almoco' => 'Volta Almoço',
                                            'saida' => 'Saída'
                                        ];
                                        echo $tipos[$ponto['tipo']] ?? $ponto['tipo'];
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo $ponto['origem']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Resumo Mensal -->
    <?php if (!empty($resumoMensal)): ?>
    <div class="info-card">
        <div class="info-header">
            <h3><i class="fas fa-calendar-alt"></i> Resumo por Mês</h3>
        </div>
        <div class="info-content">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mês/Ano</th>
                            <th>Total Pontos</th>
                            <th>Primeiro Registro</th>
                            <th>Último Registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumoMensal as $resumo): ?>
                        <tr>
                            <td><?php 
                                $meses = [
                                    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março',
                                    '04' => 'Abril', '05' => 'Maio', '06' => 'Junho',
                                    '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro',
                                    '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
                                ];
                                $data = explode('-', $resumo['mes']);
                                echo $meses[$data[1]] . '/' . $data[0];
                            ?></td>
                            <td><?php echo $resumo['total_pontos']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($resumo['primeiro_dia'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($resumo['ultimo_dia'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.view-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 24px;
}

.profile-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.profile-avatar {
    flex-shrink: 0;
}

.profile-photo {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.avatar-large {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 48px;
    font-weight: bold;
}

.profile-info h3 {
    font-size: 24px;
    margin-bottom: 4px;
}

.profile-info p {
    color: #666;
    margin-bottom: 12px;
}

.profile-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.info-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.info-header {
    padding: 16px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e5e5e5;
}

.info-header h3 {
    margin: 0;
    font-size: 16px;
    color: #333;
}

.info-content {
    padding: 20px;
}

.info-row {
    display: flex;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid #f0f0f0;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    width: 140px;
    font-weight: 600;
    color: #666;
    font-size: 13px;
}

.info-value {
    flex: 1;
    color: #333;
    font-size: 14px;
}

.stats-mini {
    display: flex;
    gap: 24px;
}

.stat-mini {
    text-align: center;
    flex: 1;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 12px;
}

.stat-mini-value {
    font-size: 28px;
    font-weight: bold;
    color: #667eea;
}

.stat-mini-label {
    font-size: 12px;
    color: #666;
    margin-top: 4px;
}

.text-muted {
    color: #999;
    text-align: center;
    padding: 20px;
}

.badge-entrada { background: #d1fae5; color: #059669; }
.badge-saida_almoco { background: #fed7aa; color: #c2410c; }
.badge-volta_almoco { background: #bfdbfe; color: #1e40af; }
.badge-saida { background: #fee2e2; color: #dc2626; }

@media (max-width: 768px) {
    .view-container {
        grid-template-columns: 1fr;
    }
    
    .profile-card {
        flex-direction: column;
        text-align: center;
    }
    
    .info-row {
        flex-direction: column;
    }
    
    .info-label {
        width: 100%;
        margin-bottom: 4px;
    }
    
    .stats-mini {
        flex-direction: column;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>