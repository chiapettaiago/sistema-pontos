<?php
// modules/funcionario/verificar_facial.php - Validação Facial
session_start();
header('Content-Type: application/json');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$foto_base64 = $input['foto'] ?? null;

if (!$foto_base64) {
    echo json_encode(['success' => false, 'message' => 'Foto não fornecida']);
    exit;
}

// Salvar foto temporariamente para análise
$tempDir = '../../uploads/temp/';
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0777, true);
}

$tempFile = $tempDir . 'temp_' . time() . '.jpg';
$foto_data = str_replace('data:image/jpeg;base64,', '', $foto_base64);
$foto_data = str_replace(' ', '+', $foto_data);
file_put_contents($tempFile, base64_decode($foto_data));

// Buscar funcionários com foto cadastrada
$stmt = $db->prepare("SELECT id, nome, matricula, foto FROM funcionarios WHERE foto IS NOT NULL AND foto != '' AND status = 'ativo'");
$stmt->execute();
$funcionarios = $stmt->fetchAll();

$reconhecido = false;
$funcionario_id = null;

// ============================================
// SIMULAÇÃO DE RECONHECIMENTO FACIAL
// ============================================
// Por enquanto, vamos fazer uma verificação SIMPLES
// Para produção, integre com Azure Face API, Amazon Rekognition ou outra

// PARA TESTE: Aceita qualquer rosto se tiver pelo menos um funcionário
// Isso deve ser substituído por uma API real de reconhecimento facial

if (count($funcionarios) > 0) {
    // Modo DEBUG: Se houver funcionários, reconhece o primeiro
    // NA PRÁTICA, você deve comparar as fotos com uma API real
    
    // Exemplo de integração fictícia:
    // $result = compararFacialAPI($tempFile, $funcionarios);
    
    // Por enquanto, vamos retornar que reconheceu
    // mas você precisa implementar a comparação real
    
    $reconhecido = true;
    $funcionario_id = $funcionarios[0]['id'];
    $funcionario_nome = $funcionarios[0]['nome'];
}

// Limpar arquivo temporário
@unlink($tempFile);

if ($reconhecido && $funcionario_id) {
    // Buscar dados completos do funcionário
    $stmt = $db->prepare("SELECT f.*, e.nome_empresa, fi.nome_fantasia as filial_nome 
                          FROM funcionarios f
                          LEFT JOIN empresa e ON f.empresa_id = e.id
                          LEFT JOIN filiais fi ON f.filial_id = fi.id
                          WHERE f.id = :id");
    $stmt->execute([':id' => $funcionario_id]);
    $funcionario = $stmt->fetch();
    
    if ($funcionario && $funcionario['status'] == 'ativo') {
        $_SESSION['funcionario_id'] = $funcionario['id'];
        $_SESSION['funcionario_nome'] = $funcionario['nome'];
        $_SESSION['funcionario_email'] = $funcionario['email'];
        $_SESSION['funcionario_matricula'] = $funcionario['matricula'];
        $_SESSION['usuario_tipo'] = 'funcionario';
        $_SESSION['empresa_id'] = $funcionario['empresa_id'];
        $_SESSION['filial_id'] = $funcionario['filial_id'];
        
        echo json_encode(['success' => true, 'message' => 'Login realizado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Funcionário inativo ou não encontrado']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Reconhecimento facial não identificado. Certifique-se de que sua foto está cadastrada.']);
}
?>