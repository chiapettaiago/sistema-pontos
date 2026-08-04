<?php
// modules/ponto/processar.php - Processa registro de ponto e justificativas
session_start();
require_once '../../config/database.php';

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['mensagem'] = 'Faça login primeiro';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: ../../login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$funcionario_id = $_SESSION['funcionario_id'];
$filial_id = $_SESSION['filial_id'];
$empresa_id = $_SESSION['empresa_id'];

$tipo = $_POST['tipo'] ?? '';

// ============================================
// PROCESSAR JUSTIFICATIVA
// ============================================
if ($tipo === 'justificativa') {
    $justificativa = trim($_POST['justificativa'] ?? '');
    
    if (empty($justificativa)) {
        $_SESSION['mensagem'] = 'Digite uma justificativa';
        $_SESSION['tipo_mensagem'] = 'error';
        header('Location: ponto.php');
        exit;
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO justificativas (funcionario_id, empresa_id, filial_id, justificativa, data, status) 
                              VALUES (:funcionario_id, :empresa_id, :filial_id, :justificativa, NOW(), 'pendente')");
        $stmt->execute([
            ':funcionario_id' => $funcionario_id,
            ':empresa_id' => $empresa_id,
            ':filial_id' => $filial_id,
            ':justificativa' => $justificativa
        ]);
        
        $_SESSION['mensagem'] = 'Justificativa enviada com sucesso! Aguarde aprovação.';
        $_SESSION['tipo_mensagem'] = 'success';
        
    } catch (Exception $e) {
        $_SESSION['mensagem'] = 'Erro ao enviar justificativa: ' . $e->getMessage();
        $_SESSION['tipo_mensagem'] = 'error';
    }
    
    header('Location: ponto.php');
    exit;
}

// ============================================
// PROCESSAR PONTO
// ============================================
$tipos_validos = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];

if (!in_array($tipo, $tipos_validos)) {
    $_SESSION['mensagem'] = 'Tipo de ponto inválido';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: ponto.php');
    exit;
}

// Verificar se já existe ponto do mesmo tipo hoje
$stmt = $db->prepare("SELECT id FROM pontos 
                      WHERE funcionario_id = :funcionario_id 
                      AND tipo = :tipo 
                      AND DATE(data_hora) = CURDATE()");
$stmt->execute([
    ':funcionario_id' => $funcionario_id,
    ':tipo' => $tipo
]);

if ($stmt->fetch()) {
    $_SESSION['mensagem'] = 'Você já registrou ' . $tipo . ' hoje';
    $_SESSION['tipo_mensagem'] = 'error';
    header('Location: ponto.php');
    exit;
}

// Verificar sequência correta
$stmt = $db->prepare("SELECT tipo FROM pontos 
                      WHERE funcionario_id = :funcionario_id 
                      AND DATE(data_hora) = CURDATE() 
                      ORDER BY data_hora DESC LIMIT 1");
$stmt->execute([':funcionario_id' => $funcionario_id]);
$ultimo = $stmt->fetch();

$sequencia = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];
$indice_atual = array_search($tipo, $sequencia);

if ($ultimo) {
    $indice_anterior = array_search($ultimo['tipo'], $sequencia);
    
    if ($indice_atual != $indice_anterior + 1) {
        $nomes = [
            'entrada' => 'ENTRADA',
            'saida_almoco' => 'SAÍDA ALMOÇO',
            'volta_almoco' => 'VOLTA ALMOÇO',
            'saida' => 'SAÍDA'
        ];
        
        $_SESSION['mensagem'] = 'Sequência incorreta! Próximo ponto deve ser: ' . $nomes[$sequencia[$indice_anterior + 1]] ?? 'finalizar expediente';
        $_SESSION['tipo_mensagem'] = 'error';
        header('Location: ponto.php');
        exit;
    }
}

// Registrar ponto
try {
    $stmt = $db->prepare("INSERT INTO pontos (funcionario_id, filial_id, empresa_id, tipo, data_hora, ip_address) 
                          VALUES (:funcionario_id, :filial_id, :empresa_id, :tipo, NOW(), :ip)");
    $stmt->execute([
        ':funcionario_id' => $funcionario_id,
        ':filial_id' => $filial_id,
        ':empresa_id' => $empresa_id,
        ':tipo' => $tipo,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    $nomes_ponto = [
        'entrada' => 'ENTRADA',
        'saida_almoco' => 'SAÍDA PARA ALMOÇO',
        'volta_almoco' => 'VOLTA DO ALMOÇO',
        'saida' => 'SAÍDA'
    ];
    
    $_SESSION['mensagem'] = 'Ponto registrado com sucesso! ' . $nomes_ponto[$tipo] . ' às ' . date('H:i:s');
    $_SESSION['tipo_mensagem'] = 'success';
    
} catch (Exception $e) {
    $_SESSION['mensagem'] = 'Erro ao registrar ponto: ' . $e->getMessage();
    $_SESSION['tipo_mensagem'] = 'error';
}

header('Location: ponto.php');
exit;
?>