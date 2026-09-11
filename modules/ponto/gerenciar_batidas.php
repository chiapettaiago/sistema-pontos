<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$tipoUsuario = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($tipoUsuario, ['super_admin', 'admin_empresa', 'gestor'], true)) {
    http_response_code(403); exit('Acesso negado.');
}
$empresaId = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresaId <= 0) { http_response_code(403); exit('Empresa não identificada.'); }
$db = (new Database())->getConnection();
$mes = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['mes'] ?? '')) ? $_GET['mes'] : date('Y-m');
$funcionarioId = (int) ($_GET['funcionario_id'] ?? 0);
$inicio = $mes . '-01'; $fim = date('Y-m-t', strtotime($inicio));

$sqlFuncionarios = "SELECT id,nome,matricula FROM funcionarios WHERE empresa_id=:empresa AND status='ativo'";
$paramsFuncionarios=[':empresa'=>$empresaId];
if ($tipoUsuario === 'gestor') { $sqlFuncionarios .= ' AND filial_id=:filial'; $paramsFuncionarios[':filial']=(int)($_SESSION['usuario_filial_id'] ?? 0); }
$sqlFuncionarios .= ' ORDER BY nome'; $stmt=$db->prepare($sqlFuncionarios); $stmt->execute($paramsFuncionarios); $funcionarios=$stmt->fetchAll();

$sql="SELECT p.edit_id,p.tipo,p.data_hora,p.origem,p.justificativa,f.nome funcionario_nome,f.matricula,fi.nome_fantasia filial_nome FROM pontos p JOIN funcionarios f ON f.id=p.funcionario_id LEFT JOIN filiais fi ON fi.id=f.filial_id WHERE f.empresa_id=:empresa AND DATE(p.data_hora) BETWEEN :inicio AND :fim";
$params=[':empresa'=>$empresaId,':inicio'=>$inicio,':fim'=>$fim];
if ($tipoUsuario === 'gestor') { $sql.=' AND f.filial_id=:filial'; $params[':filial']=(int)($_SESSION['usuario_filial_id'] ?? 0); }
if ($funcionarioId > 0) { $sql.=' AND f.id=:funcionario'; $params[':funcionario']=$funcionarioId; }
$sql.=' ORDER BY p.data_hora DESC,f.nome'; $stmt=$db->prepare($sql); $stmt->execute($params); $batidas=$stmt->fetchAll();

$pageTitle='Gerenciar Batidas'; $activePage='gerenciar_batidas'; require_once __DIR__ . '/../../includes/header.php';
$mensagem=$_SESSION['mensagem_batida'] ?? null; unset($_SESSION['mensagem_batida']);
$tipos=['entrada'=>'Entrada','saida_almoco'=>'Saída almoço','volta_almoco'=>'Volta almoço','saida'=>'Saída','extra_entrada'=>'Entrada extra','extra_saida'=>'Saída extra'];
?>
<div class="pf-page-header"><h1><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Gerenciar batidas</h1><p class="text-muted">Consulte e corrija registros de ponto da sua equipe.</p></div>
<?php if($mensagem): ?><div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
<div class="card mb-4"><div class="card-body"><form method="get" class="row g-3 align-items-end"><div class="col-md-3"><label class="form-label">Mês</label><input class="form-control" type="month" name="mes" value="<?= htmlspecialchars($mes) ?>"></div><div class="col-md-5"><label class="form-label">Colaborador</label><select class="form-select" name="funcionario_id"><option value="">Todos</option><?php foreach($funcionarios as $f): ?><option value="<?= (int)$f['id'] ?>" <?= $funcionarioId===(int)$f['id']?'selected':'' ?>><?= htmlspecialchars($f['nome'].' ('.$f['matricula'].')') ?></option><?php endforeach; ?></select></div><div class="col-auto"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filtrar</button></div></form></div></div>
<div class="card pf-table-card"><div class="card-body p-0"><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>Data</th><th>Horário</th><th>Colaborador</th><th>Tipo</th><th>Filial</th><th>Origem</th><th></th></tr></thead><tbody><?php foreach($batidas as $b): ?><tr><td><?= date('d/m/Y',strtotime($b['data_hora'])) ?></td><td class="fw-semibold"><?= date('H:i',strtotime($b['data_hora'])) ?></td><td><?= htmlspecialchars($b['funcionario_nome']) ?><small class="d-block text-muted"><?= htmlspecialchars($b['matricula']) ?></small></td><td><?= htmlspecialchars($tipos[$b['tipo']] ?? $b['tipo']) ?></td><td><?= htmlspecialchars($b['filial_nome'] ?? 'Não definida') ?></td><td><?= htmlspecialchars($b['origem'] ?: 'web') ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="editar_batida.php?id=<?= (int)$b['edit_id'] ?>"><i class="fas fa-pen me-1"></i>Editar</a></td></tr><?php endforeach; ?><?php if(!$batidas): ?><tr><td colspan="7" class="text-center text-muted py-5">Nenhuma batida encontrada neste período.</td></tr><?php endif; ?></tbody></table></div></div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
