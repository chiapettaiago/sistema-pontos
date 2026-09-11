<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/funcoes_auditoria.php';

$tipoUsuario=$_SESSION['usuario_tipo'] ?? '';
if(!in_array($tipoUsuario,['super_admin','admin_empresa','gestor'],true)){http_response_code(403);exit('Acesso negado.');}
$empresaId=(int)($_SESSION['empresa_id'] ?? 0); $editId=(int)($_GET['id'] ?? $_POST['id'] ?? 0);
if($empresaId<=0||$editId<=0){http_response_code(400);exit('Batida inválida.');}
$db=(new Database())->getConnection();
$sql="SELECT p.*,f.nome funcionario_nome,f.matricula,f.empresa_id funcionario_empresa,f.filial_id funcionario_filial FROM pontos p JOIN funcionarios f ON f.id=p.funcionario_id WHERE p.edit_id=:id AND f.empresa_id=:empresa";
$params=[':id'=>$editId,':empresa'=>$empresaId];
if($tipoUsuario==='gestor'){ $sql.=' AND f.filial_id=:filial'; $params[':filial']=(int)($_SESSION['usuario_filial_id'] ?? 0); }
$stmt=$db->prepare($sql);$stmt->execute($params);$batida=$stmt->fetch();
if(!$batida){http_response_code(404);exit('Batida não encontrada ou fora da sua área de gestão.');}
$erro=''; $tipos=['entrada'=>'Entrada','saida_almoco'=>'Saída almoço','volta_almoco'=>'Volta almoço','saida'=>'Saída','extra_entrada'=>'Entrada extra','extra_saida'=>'Saída extra'];
if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCSRFToken(); $tipo=(string)($_POST['tipo']??''); $data=(string)($_POST['data']??''); $hora=(string)($_POST['hora']??''); $justificativa=trim((string)($_POST['justificativa']??''));
    $dataHora=DateTime::createFromFormat('!Y-m-d H:i',$data.' '.$hora); $valida=$dataHora&&$dataHora->format('Y-m-d H:i')===$data.' '.$hora;
    if(!isset($tipos[$tipo]))$erro='Tipo de batida inválido.'; elseif(!$valida)$erro='Informe data e horário válidos.'; elseif(mb_strlen($justificativa)<5)$erro='Informe uma justificativa com pelo menos 5 caracteres.';
    else{
        $stmt=$db->prepare('SELECT edit_id FROM pontos WHERE funcionario_id=:funcionario AND tipo=:tipo AND data_hora=:data_hora AND edit_id<>:id LIMIT 1');
        $stmt->execute([':funcionario'=>$batida['funcionario_id'],':tipo'=>$tipo,':data_hora'=>$dataHora->format('Y-m-d H:i:s'),':id'=>$editId]);
        if($stmt->fetch())$erro='Já existe uma batida idêntica para este colaborador.';
        else{
            $antes=['tipo'=>$batida['tipo'],'data_hora'=>$batida['data_hora'],'justificativa'=>$batida['justificativa']]; $depois=['tipo'=>$tipo,'data_hora'=>$dataHora->format('Y-m-d H:i:s'),'justificativa'=>$justificativa];
            $db->beginTransaction(); try{$stmt=$db->prepare("UPDATE pontos SET tipo=:tipo,data_hora=:data_hora,justificativa=:justificativa,aprovado_por=:aprovado,status_aprovacao='aprovado' WHERE edit_id=:id");$stmt->execute([':tipo'=>$tipo,':data_hora'=>$depois['data_hora'],':justificativa'=>$justificativa,':aprovado'=>(int)$_SESSION['usuario_id'],':id'=>$editId]);registrarLogAuditoria($db,'UPDATE','pontos',$editId,'Corrigiu batida de '.$batida['funcionario_nome'],$antes,$depois);$db->commit();$_SESSION['mensagem_batida']='Batida atualizada com sucesso.';header('Location: gerenciar_batidas.php?mes='.substr($depois['data_hora'],0,7));exit;}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Erro ao editar batida: '.$e->getMessage());$erro='Não foi possível atualizar a batida.';}
        }
    }
}
$pageTitle='Editar Batida';$activePage='relatorios';require_once __DIR__.'/../../includes/header.php';$csrf=generateCSRFToken();
?>
<div class="pf-page-header"><h1><i class="fas fa-pen-to-square me-2 text-primary"></i>Editar batida</h1><p class="text-muted"><?= htmlspecialchars($batida['funcionario_nome'].' · '.$batida['matricula']) ?></p></div>
<?php if($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
<div class="card"><div class="card-body p-4"><form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= $editId ?>"><div class="col-md-4"><label class="form-label">Data</label><input required class="form-control" type="date" name="data" value="<?= htmlspecialchars($_POST['data']??date('Y-m-d',strtotime($batida['data_hora']))) ?>"></div><div class="col-md-4"><label class="form-label">Horário</label><input required class="form-control" type="time" name="hora" value="<?= htmlspecialchars($_POST['hora']??date('H:i',strtotime($batida['data_hora']))) ?>"></div><div class="col-md-4"><label class="form-label">Tipo</label><select required class="form-select" name="tipo"><?php $tipoAtual=$_POST['tipo']??$batida['tipo'];foreach($tipos as $valor=>$rotulo): ?><option value="<?= $valor ?>" <?= $tipoAtual===$valor?'selected':'' ?>><?= htmlspecialchars($rotulo) ?></option><?php endforeach; ?></select></div><div class="col-12"><label class="form-label">Justificativa da alteração</label><textarea required minlength="5" maxlength="1000" class="form-control" rows="4" name="justificativa" placeholder="Explique por que esta batida está sendo corrigida."><?= htmlspecialchars($_POST['justificativa']??$batida['justificativa']??'') ?></textarea></div><div class="col-12 d-flex gap-2"><button class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar alteração</button><a class="btn btn-outline-secondary" href="gerenciar_batidas.php?mes=<?= date('Y-m',strtotime($batida['data_hora'])) ?>">Cancelar</a></div></form></div></div>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>
