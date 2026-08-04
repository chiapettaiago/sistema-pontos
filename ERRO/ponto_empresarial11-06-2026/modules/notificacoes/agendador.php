<?php
// modules/notificacoes/agendador.php - Agendador de tarefas (executar via CRON)
require_once '../../includes/mail.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Verificar se é execução via linha de comando ou web
$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli) {
    // Para execução web, verificar token de segurança
    $token = $_GET['token'] ?? '';
    if ($token !== 'SEU_TOKEN_SECRETO_AQUI') {
        die('Acesso não autorizado');
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Iniciando agendador de notificações\n";

// 1. Verificar solicitações pendentes há mais de 7 dias
$query = "SELECT s.*, f.nome, f.email, f.id as funcionario_id
          FROM solicitacoes s
          JOIN funcionarios f ON s.funcionario_id = f.id
          WHERE s.status = 'pendente' 
          AND s.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)";
$stmt = $db->query($query);
$solicitacoes_pendentes = $stmt->fetchAll();

foreach ($solicitacoes_pendentes as $s) {
    // Enviar lembrete para o gestor
    $gestores = $db->query("SELECT email, nome FROM funcionarios WHERE tipo_usuario IN ('admin', 'gestor')");
    while ($gestor = $gestores->fetch()) {
        $assunto = "⏰ Lembrete: Solicitação pendente há {$s['created_at']}";
        $mensagem = "O funcionário {$s['nome']} possui uma solicitação de {$s['tipo']} pendente desde " . date('d/m/Y', strtotime($s['created_at']));
        enviarEmail($gestor['email'], $assunto, $mensagem);
    }
    echo "Lembrete enviado para solicitação ID: {$s['id']}\n";
}

// 2. Verificar quem ainda não bateu ponto hoje (após as 10h)
if (date('H') >= 10) {
    $query = "SELECT f.id, f.nome, f.email, f.matricula
              FROM funcionarios f
              WHERE f.status = 'ativo'
              AND f.id NOT IN (
                  SELECT funcionario_id FROM pontos 
                  WHERE DATE(data_hora) = CURDATE() AND tipo = 'entrada'
              )";
    $stmt = $db->query($query);
    $sem_ponto = $stmt->fetchAll();
    
    foreach ($sem_ponto as $func) {
        enviarLembretePonto($func['email'], $func['nome'], 'Entrada (até as 10h)');
        echo "Lembrete enviado para: {$func['nome']}\n";
    }
}

// 3. Verificar atrasos do dia anterior
$query = "SELECT p.*, f.nome, f.email, f.matricula,
          TIME(p.data_hora) as hora_registrada
          FROM pontos p
          JOIN funcionarios f ON p.funcionario_id = f.id
          WHERE DATE(p.data_hora) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
          AND p.tipo = 'entrada'
          AND TIME(p.data_hora) > '08:15:00'"; // Atraso > 15 minutos
$stmt = $db->query($query);
$atrasos = $stmt->fetchAll();

foreach ($atrasos as $atraso) {
    $hora_esperada = '08:00';
    $minutos_atraso = (strtotime($atraso['hora_registrada']) - strtotime($hora_esperada)) / 60;
    
    enviarAlertaAtraso(
        $atraso['email'],
        $atraso['nome'],
        date('d/m/Y', strtotime($atraso['data_hora'])),
        $hora_esperada,
        date('H:i', strtotime($atraso['hora_registrada'])),
        round($minutos_atraso)
    );
    echo "Alerta de atraso enviado para: {$atraso['nome']}\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Agendador finalizado\n";