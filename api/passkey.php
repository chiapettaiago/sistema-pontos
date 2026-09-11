<?php

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/passkey.php';

$db = getDB();
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$acao = (string) ($input['acao'] ?? $_GET['acao'] ?? '');

try {
    passkeyEnsureTable($db);
    $webauthn = passkeyServer();

    if ($acao === 'cadastro_opcoes') {
        $usuario = passkeySessionIdentity();
        $stmt = $db->prepare('SELECT credencial_id FROM credenciais_passkey WHERE usuario_tipo=:tipo AND usuario_id=:id');
        $stmt->execute([':tipo'=>$usuario['tipo'], ':id'=>$usuario['id']]);
        $existentes = array_column($stmt->fetchAll(), 'credencial_id');
        $args = $webauthn->getCreateArgs(passkeyUserHandle($usuario['tipo'], $usuario['id']), $usuario['email'], $usuario['nome'], 60, 'required', 'required', false, $existentes);
        $_SESSION['passkey_challenge'] = $webauthn->getChallenge()->getBinaryString();
        $_SESSION['passkey_cadastro'] = $usuario;
        passkeyJson(true, ['options'=>$args]);
    }

    if ($acao === 'cadastro_concluir') {
        $usuario = passkeySessionIdentity();
        $pendente = $_SESSION['passkey_cadastro'] ?? [];
        if (($pendente['tipo'] ?? '') !== $usuario['tipo'] || (int)($pendente['id'] ?? 0) !== $usuario['id'] || empty($_SESSION['passkey_challenge'])) throw new RuntimeException('Cadastro expirado. Tente novamente.');
        $data = $webauthn->processCreate(passkeyDecode($input['clientDataJSON'] ?? ''), passkeyDecode($input['attestationObject'] ?? ''), $_SESSION['passkey_challenge'], true, true, false);
        $stmt = $db->prepare("INSERT INTO credenciais_passkey (usuario_tipo,usuario_id,credencial_id,chave_publica,contador_assinatura,transportes,nome) VALUES (:tipo,:usuario,:credencial,:chave,:contador,:transportes,:nome)");
        $stmt->execute([':tipo'=>$usuario['tipo'], ':usuario'=>$usuario['id'], ':credencial'=>$data->credentialId, ':chave'=>$data->credentialPublicKey, ':contador'=>(int)$data->signatureCounter, ':transportes'=>json_encode($input['transports'] ?? []), ':nome'=>mb_substr(trim((string)($input['nome'] ?? 'Meu dispositivo')),0,100)]);
        unset($_SESSION['passkey_challenge'], $_SESSION['passkey_cadastro']);
        passkeyJson(true, [], 'Biometria do dispositivo cadastrada com sucesso.');
    }

    if ($acao === 'login_opcoes') {
        $args = $webauthn->getGetArgs([], 60, false, false, false, true, true, 'required');
        $_SESSION['passkey_challenge'] = $webauthn->getChallenge()->getBinaryString();
        passkeyJson(true, ['options'=>$args]);
    }

    if ($acao === 'ponto_opcoes') {
        $empresaId = (int) ($input['empresa_id'] ?? 0);
        $chave = (string) ($input['chave'] ?? '');
        $esperada = $empresaId > 0 ? hash_hmac('sha256', (string) $empresaId, DB_PASS . '|ponto-publico') : '';
        if ($empresaId <= 0 || $chave === '' || !hash_equals($esperada, $chave)) throw new RuntimeException('Link do quiosque inválido.');
        $args = $webauthn->getGetArgs([], 60, false, false, false, true, true, 'required');
        $_SESSION['passkey_challenge'] = $webauthn->getChallenge()->getBinaryString();
        $_SESSION['passkey_ponto_empresa'] = $empresaId;
        passkeyJson(true, ['options'=>$args]);
    }

    if ($acao === 'ponto_concluir') {
        $empresaId = (int) ($_SESSION['passkey_ponto_empresa'] ?? 0);
        if ($empresaId <= 0 || empty($_SESSION['passkey_challenge'])) throw new RuntimeException('Tentativa de ponto expirada.');
        $latitude = $input['latitude'] ?? null; $longitude = $input['longitude'] ?? null;
        if (!is_numeric($latitude) || !is_numeric($longitude)) throw new RuntimeException('Permita a localização precisa para registrar o ponto.');
        $latitude=(float)$latitude; $longitude=(float)$longitude;
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) throw new RuntimeException('Localização inválida.');

        $credencialId = passkeyDecode($input['id'] ?? '');
        $stmt=$db->prepare('SELECT * FROM credenciais_passkey WHERE credencial_id=:credencial LIMIT 1');
        $stmt->bindValue(':credencial',$credencialId,PDO::PARAM_LOB); $stmt->execute(); $credencial=$stmt->fetch();
        if (!$credencial) throw new RuntimeException('Biometria deste dispositivo não está cadastrada.');
        $userHandle=passkeyDecode($input['userHandle'] ?? '');
        if (!hash_equals(passkeyUserHandle($credencial['usuario_tipo'],(int)$credencial['usuario_id']),$userHandle)) throw new RuntimeException('Credencial não pertence a este usuário.');
        $webauthn->processGet(passkeyDecode($input['clientDataJSON'] ?? ''),passkeyDecode($input['authenticatorData'] ?? ''),passkeyDecode($input['signature'] ?? ''),$credencial['chave_publica'],$_SESSION['passkey_challenge'],(int)$credencial['contador_assinatura'],true,true);

        if ($credencial['usuario_tipo'] === 'funcionario') {
            $funcionarioId=(int)$credencial['usuario_id'];
        } else {
            $stmt=$db->prepare("SELECT id FROM funcionarios WHERE usuario_sistema_id=:id AND status='ativo' LIMIT 1"); $stmt->execute([':id'=>$credencial['usuario_id']]);
            $funcionarioId=(int)($stmt->fetchColumn() ?: 0);
            if (!$funcionarioId) { $stmt=$db->prepare("SELECT id FROM funcionarios WHERE id=:id AND status='ativo' LIMIT 1"); $stmt->execute([':id'=>$credencial['usuario_id']]); $funcionarioId=(int)($stmt->fetchColumn() ?: 0); }
        }
        if (!$funcionarioId) throw new RuntimeException('Esta conta não possui vínculo com um colaborador ativo.');
        $stmt=$db->prepare("SELECT f.id,f.nome,f.filial_id,f.empresa_id,fi.latitude filial_latitude,fi.longitude filial_longitude,fi.raio_permitido filial_raio FROM funcionarios f LEFT JOIN filiais fi ON fi.id=f.filial_id WHERE f.id=:id AND f.status='ativo' LIMIT 1");
        $stmt->execute([':id'=>$funcionarioId]); $funcionario=$stmt->fetch();
        if (!$funcionario || (int)$funcionario['empresa_id'] !== $empresaId) throw new RuntimeException('A biometria não pertence a um colaborador desta empresa.');

        if ($funcionario['filial_latitude'] !== null && $funcionario['filial_longitude'] !== null) {
            $earth=6371000; $dLat=deg2rad($latitude-(float)$funcionario['filial_latitude']); $dLon=deg2rad($longitude-(float)$funcionario['filial_longitude']);
            $a=sin($dLat/2)**2+cos(deg2rad($latitude))*cos(deg2rad((float)$funcionario['filial_latitude']))*sin($dLon/2)**2; $distancia=$earth*2*atan2(sqrt($a),sqrt(1-$a));
            if ($distancia > max(1,(float)($funcionario['filial_raio'] ?? 100))) throw new RuntimeException('Ponto bloqueado: dispositivo fora do raio permitido da empresa.');
        }
        $stmt=$db->prepare('SELECT tipo,data_hora FROM pontos WHERE funcionario_id=:id AND DATE(data_hora)=CURDATE() ORDER BY data_hora DESC LIMIT 1'); $stmt->execute([':id'=>$funcionarioId]); $ultimo=$stmt->fetch();
        if ($ultimo && time()-strtotime($ultimo['data_hora']) < 120*60) throw new RuntimeException('Aguarde o intervalo mínimo para registrar outra batida.');
        $sequencia=['entrada'=>'saida_almoco','saida_almoco'=>'volta_almoco','volta_almoco'=>'saida']; $tipo=$ultimo ? ($sequencia[$ultimo['tipo']] ?? null) : 'entrada';
        if (!$tipo) throw new RuntimeException('O expediente de hoje já foi finalizado.');
        $stmt=$db->prepare("INSERT INTO pontos (funcionario_id,filial_id,empresa_id,tipo,data_hora,latitude,longitude,origem) VALUES (:funcionario,:filial,:empresa,:tipo,NOW(),:latitude,:longitude,'totem')");
        $stmt->execute([':funcionario'=>$funcionarioId,':filial'=>$funcionario['filial_id'],':empresa'=>$empresaId,':tipo'=>$tipo,':latitude'=>$latitude,':longitude'=>$longitude]);
        $novoContador=$webauthn->getSignatureCounter(); $stmt=$db->prepare('UPDATE credenciais_passkey SET contador_assinatura=:contador,ultimo_uso=NOW() WHERE id=:id'); $stmt->execute([':contador'=>$novoContador ?? (int)$credencial['contador_assinatura'],':id'=>$credencial['id']]);
        unset($_SESSION['passkey_challenge'],$_SESSION['passkey_ponto_empresa']);
        $nomes=['entrada'=>'Entrada','saida_almoco'=>'Saída para almoço','volta_almoco'=>'Volta do almoço','saida'=>'Saída'];
        passkeyJson(true,['funcionario'=>$funcionario['nome'],'funcionario_id'=>$funcionarioId,'tipo'=>$tipo,'tipo_nome'=>$nomes[$tipo] ?? $tipo],'Ponto registrado com biometria.');
    }

    if ($acao === 'login_concluir') {
        if (empty($_SESSION['passkey_challenge'])) throw new RuntimeException('Tentativa de login expirada.');
        $credencialId = passkeyDecode($input['id'] ?? '');
        $stmt = $db->prepare('SELECT * FROM credenciais_passkey WHERE credencial_id=:credencial LIMIT 1');
        $stmt->bindValue(':credencial', $credencialId, PDO::PARAM_LOB); $stmt->execute();
        $credencial = $stmt->fetch();
        if (!$credencial) throw new RuntimeException('Dispositivo não cadastrado.');
        $userHandle = passkeyDecode($input['userHandle'] ?? '');
        if (!hash_equals(passkeyUserHandle($credencial['usuario_tipo'], (int)$credencial['usuario_id']), $userHandle)) throw new RuntimeException('Credencial não pertence a este usuário.');
        $webauthn->processGet(passkeyDecode($input['clientDataJSON'] ?? ''), passkeyDecode($input['authenticatorData'] ?? ''), passkeyDecode($input['signature'] ?? ''), $credencial['chave_publica'], $_SESSION['passkey_challenge'], (int)$credencial['contador_assinatura'], true, true);
        $novoContador = $webauthn->getSignatureCounter();
        $stmt=$db->prepare('UPDATE credenciais_passkey SET contador_assinatura=:contador, ultimo_uso=NOW() WHERE id=:id');
        $stmt->execute([':contador'=>$novoContador ?? (int)$credencial['contador_assinatura'], ':id'=>$credencial['id']]);
        unset($_SESSION['passkey_challenge']);
        $redirect = passkeyFinishLogin($db, $credencial);
        passkeyJson(true, ['redirect'=>$redirect], 'Login realizado com sucesso.');
    }

    passkeyJson(false, [], 'Ação inválida.', 400);
} catch (Throwable $e) {
    error_log('Erro WebAuthn: '.$e->getMessage());
    passkeyJson(false, [], $e->getMessage(), 400);
}
