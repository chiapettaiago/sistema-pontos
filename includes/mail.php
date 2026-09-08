<?php
// includes/mail.php - Funções de envio de e-mail
require_once __DIR__ . '/../config/database.php';

// Configurações SMTP
define('SMTP_HOST', 'smtp.gmail.com');      // Servidor SMTP
define('SMTP_PORT', 587);                    // Porta SMTP
define('SMTP_USER', 'seuemail@gmail.com');   // Seu e-mail
define('SMTP_PASS', 'sua_senha');            // Sua senha
define('SMTP_FROM', 'noreply@pontofacil.com');
define('SMTP_FROM_NAME', 'Ponto Fácil');

// Função para enviar e-mail usando PHPMailer
function enviarEmail($para, $assunto, $corpoHtml, $corpoTexto = '') {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configurações do servidor
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Remetente e destinatário
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($para);
        
        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $corpoHtml;
        $mail->AltBody = $corpoTexto ?: strip_tags($corpoHtml);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erro ao enviar e-mail: " . $mail->ErrorInfo);
        return false;
    }
}

// Função para enviar notificação de aprovação de solicitação
function enviarAprovacaoSolicitacao($funcionario_email, $funcionario_nome, $solicitacao_tipo, $observacao = '') {
    $assunto = "✅ Sua solicitação foi aprovada - Ponto Fácil";
    
    $corpo = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9fafb; }
            .button { background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Ponto Fácil</h2>
                <p>Sistema de Ponto Empresarial</p>
            </div>
            <div class='content'>
                <h3>Olá, {$funcionario_nome}!</h3>
                <p>Sua solicitação de <strong>{$solicitacao_tipo}</strong> foi <strong style='color: #10b981;'>APROVADA</strong>!</p>
                " . ($observacao ? "<p><strong>Observação do gestor:</strong> {$observacao}</p>" : "") . "
                <p>Você pode acompanhar o status da sua solicitação acessando o sistema.</p>
                <br>
                <a href='" . (defined('SITE_URL') ? SITE_URL : '/') . "' class='button'>Acessar Sistema</a>
            </div>
            <div class='footer'>
                <p>Este é um e-mail automático, por favor não responda.</p>
                <p>&copy; 2024 Ponto Fácil - Todos os direitos reservados</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return enviarEmail($funcionario_email, $assunto, $corpo);
}

// Função para enviar notificação de rejeição
function enviarRejeicaoSolicitacao($funcionario_email, $funcionario_nome, $solicitacao_tipo, $motivo) {
    $assunto = "❌ Sua solicitação foi rejeitada - Ponto Fácil";
    
    $corpo = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9fafb; }
            .motivo { background: #fee2e2; padding: 10px; border-radius: 5px; margin: 10px 0; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Ponto Fácil</h2>
                <p>Sistema de Ponto Empresarial</p>
            </div>
            <div class='content'>
                <h3>Olá, {$funcionario_nome}!</h3>
                <p>Sua solicitação de <strong>{$solicitacao_tipo}</strong> foi <strong style='color: #ef4444;'>REJEITADA</strong>.</p>
                <div class='motivo'>
                    <strong>Motivo:</strong><br>
                    {$motivo}
                </div>
                <p>Entre em contato com seu gestor para mais informações.</p>
                <br>
                <a href='" . (defined('SITE_URL') ? SITE_URL : '/') . "' class='button'>Acessar Sistema</a>
            </div>
            <div class='footer'>
                <p>Este é um e-mail automático, por favor não responda.</p>
                <p>&copy; 2024 Ponto Fácil - Todos os direitos reservados</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return enviarEmail($funcionario_email, $assunto, $corpo);
}

// Função para enviar lembrete de ponto
function enviarLembretePonto($funcionario_email, $funcionario_nome, $horario) {
    $assunto = "⏰ Lembrete: Horário de registrar ponto - Ponto Fácil";
    
    $corpo = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9fafb; text-align: center; }
            .horario { font-size: 24px; font-weight: bold; color: #f59e0b; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Ponto Fácil</h2>
                <p>Sistema de Ponto Empresarial</p>
            </div>
            <div class='content'>
                <h3>Olá, {$funcionario_nome}!</h3>
                <p>Não se esqueça de registrar seu ponto!</p>
                <div class='horario'>
                    ⏰ {$horario}
                </div>
                <p>Registre sua entrada ou saída para manter sua frequência em dia.</p>
                <br>
                <a href='" . (defined('SITE_URL') ? SITE_URL : '') . "/modules/ponto/ponto.php' class='button'>Registrar Ponto Agora</a>
            </div>
            <div class='footer'>
                <p>Este é um e-mail automático, por favor não responda.</p>
                <p>&copy; 2024 Ponto Fácil - Todos os direitos reservados</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return enviarEmail($funcionario_email, $assunto, $corpo);
}

// Função para enviar alerta de atraso
function enviarAlertaAtraso($funcionario_email, $funcionario_nome, $data, $horario_esperado, $horario_registrado, $minutos_atraso) {
    $assunto = "⚠️ Alerta de Atraso - Ponto Fácil";
    
    $corpo = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9fafb; }
            .detalhes { background: #fee2e2; padding: 15px; border-radius: 5px; margin: 10px 0; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Ponto Fácil</h2>
                <p>Sistema de Ponto Empresarial</p>
            </div>
            <div class='content'>
                <h3>Olá, {$funcionario_nome}!</h3>
                <p>Identificamos um atraso no seu registro de ponto.</p>
                <div class='detalhes'>
                    <p><strong>Data:</strong> {$data}</p>
                    <p><strong>Horário esperado:</strong> {$horario_esperado}</p>
                    <p><strong>Horário registrado:</strong> {$horario_registrado}</p>
                    <p><strong>Atraso:</strong> {$minutos_atraso} minutos</p>
                </div>
                <p>Lembre-se de justificar o atraso no sistema.</p>
                <br>
                <a href='" . (defined('SITE_URL') ? SITE_URL : '') . "/modules/solicitacoes/nova.php' class='button'>Justificar Atraso</a>
            </div>
            <div class='footer'>
                <p>Este é um e-mail automático, por favor não responda.</p>
                <p>&copy; 2024 Ponto Fácil - Todos os direitos reservados</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return enviarEmail($funcionario_email, $assunto, $corpo);
}

// Função para enviar notificação em lote
function enviarNotificacaoLote($funcionarios, $assunto, $mensagem) {
    $enviados = 0;
    foreach ($funcionarios as $func) {
        if (enviarEmail($func['email'], $assunto, $mensagem)) {
            $enviados++;
        }
    }
    return $enviados;
}
?>
