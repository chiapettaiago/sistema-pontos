<?php
// modules/ponto/processar.php - Processar ponto normal (SEM foto_facial)
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['mensagem'] = 'Faça login primeiro';
    header('Location: ../../login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$funcionario_id = $_SESSION['funcionario_id'] ?? null;

if (!$funcionario_id) {
    $stmt = $db->prepare("SELECT id FROM funcionarios WHERE email = :email");
    $stmt->execute([':email' => $_SESSION['usuario_email']]);
    $func = $stmt->fetch();
    if ($func) {
        $funcionario_id = $func['id'];
        $_SESSION['funcionario_id'] = $funcionario_id;
    }
}

$tipo = $_POST['tipo'] ?? '';
$tipos_validos = ['entrada', 'saida_almoco', 'volta_almoco', 'saida'];

if (!in_array($tipo, $tipos_validos)) {
    $_SESSION['mensagem'] = 'Tipo de ponto inválido';
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
    $_SESSION['mensagem'] = 'Você já registrou este ponto hoje';
    header('Location: ponto.php');
    exit;
}

// Verificar sequência
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
        $_SESSION['mensagem'] = 'Sequência incorreta!';
        header('Location: ponto.php');
        exit;
    }
}

// Registrar ponto (SEM foto_facial)
try {
    // Buscar filial do funcionário
    $stmt = $db->prepare("SELECT filial_id FROM funcionarios WHERE id = :id");
    $stmt->execute([':id' => $funcionario_id]);
    $func = $stmt->fetch();
    $filial_id = $func['filial_id'] ?? null;
    
    $stmt = $db->prepare("INSERT INTO pontos 
                          (funcionario_id, filial_id, tipo, data_hora, origem) 
                          VALUES (:funcionario_id, :filial_id, :tipo, NOW(), 'web')");
    $stmt->execute([
        ':funcionario_id' => $funcionario_id,
        ':filial_id' => $filial_id,
        ':tipo' => $tipo
    ]);
    
    $nomes_ponto = [
        'entrada' => 'ENTRADA',
        'saida_almoco' => 'SAÍDA PARA ALMOÇO',
        'volta_almoco' => 'VOLTA DO ALMOÇO',
        'saida' => 'SAÍDA'
    ];
    
    $_SESSION['mensagem'] = '✅ Ponto registrado com sucesso! ' . $nomes_ponto[$tipo];
    $_SESSION['tipo_mensagem'] = 'success';
    
} catch (Exception $e) {
    $_SESSION['mensagem'] = 'Erro ao registrar ponto: ' . $e->getMessage();
    $_SESSION['tipo_mensagem'] = 'error';
}

header('Location: ponto.php');
exit;
?>