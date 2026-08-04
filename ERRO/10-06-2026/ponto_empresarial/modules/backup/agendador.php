<?php
// modules/backup/agendador.php - Agendador de Backup (CRON)
// Executar via: php modules/backup/agendador.php
// Ou via web: http://localhost/ponto_empresarial/modules/backup/agendador.php?token=TOKEN

// Verificar permissão de execução
$is_cli = (php_sapi_name() === 'cli');
$token = $_GET['token'] ?? '';

require_once '../../config/database.php';
require_once '../../includes/mail.php';

$database = new Database();
$db = $database->getConnection();

// Verificar token se for execução web
if (!$is_cli) {
    // Buscar empresas
    $stmt = $db->query("SELECT DISTINCT empresa_id FROM backups_config WHERE backup_automatico = 1");
    $empresas = $stmt->fetchAll();
    
    $token_valido = false;
    foreach ($empresas as $emp) {
        $token_calc = md5($emp['empresa_id'] . 'backup_secret_' . date('Y'));
        if ($token === $token_calc) {
            $token_valido = true;
            break;
        }
    }
    
    if (!$token_valido) {
        die('Token inválido');
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Iniciando agendador de backup\n";

// Buscar empresas com backup automático ativo
$stmt = $db->prepare("SELECT * FROM backups_config WHERE backup_automatico = 1");
$stmt->execute();
$configuracoes = $stmt->fetchAll();

$backupDir = __DIR__ . '/backups/';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0777, true);
}

foreach ($configuracoes as $config) {
    $empresa_id = $config['empresa_id'];
    $frequencia = $config['frequencia'];
    $hora_backup = $config['hora_backup'];
    $dias_retencao = $config['dias_retencao'];
    
    // Verificar se deve executar
    $deve_executar = false;
    $ultimo_backup = null;
    
    // Buscar último backup
    $stmt = $db->prepare("SELECT MAX(created_at) as ultimo FROM backups_registros WHERE tipo = 'agendado'");
    $stmt->execute();
    $ultimo = $stmt->fetch();
    
    if ($ultimo && $ultimo['ultimo']) {
        $ultimo_backup = new DateTime($ultimo['ultimo']);
        $agora = new DateTime();
        
        if ($frequencia == 'diario') {
            $diff = $agora->diff($ultimo_backup);
            if ($diff->days >= 1) {
                $deve_executar = true;
            }
        } elseif ($frequencia == 'semanal') {
            $diff = $agora->diff($ultimo_backup);
            if ($diff->days >= 7) {
                $deve_executar = true;
            }
        } elseif ($frequencia == 'mensal') {
            $diff = $agora->diff($ultimo_backup);
            if ($diff->m >= 1) {
                $deve_executar = true;
            }
        }
    } else {
        $deve_executar = true;
    }
    
    if (!$deve_executar) {
        echo "[" . date('Y-m-d H:i:s') . "] Backup não necessário para empresa {$empresa_id}\n";
        continue;
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando backup para empresa {$empresa_id}\n";
    
    // Função para criar backup (mesma do criar.php)
    function criarBackupAgendado($db, $backupDir, $empresa_id) {
        $filename = 'backup_auto_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . $filename;
        
        $tables = [];
        $stmt = $db->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        $sql = "-- Backup Automático do Banco de Dados\n";
        $sql .= "-- Gerado em: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Empresa ID: " . $empresa_id . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        foreach ($tables as $table) {
            $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE 'empresa_id'");
            $stmt->execute();
            $hasEmpresaId = $stmt->rowCount() > 0;
            
            $stmt = $db->prepare("SHOW CREATE TABLE `$table`");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $sql .= "\n-- Estrutura da tabela `$table`\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $row['Create Table'] . ";\n\n";
            
            $sql .= "-- Dados da tabela `$table`\n";
            
            if ($hasEmpresaId) {
                $stmt = $db->prepare("SELECT * FROM `$table` WHERE empresa_id = :empresa_id OR empresa_id IS NULL");
                $stmt->execute([':empresa_id' => $empresa_id]);
            } else {
                $stmt = $db->prepare("SELECT * FROM `$table`");
                $stmt->execute();
            }
            
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $insert = "INSERT INTO `$table` (`" . implode("`, `", array_keys($rows[0])) . "`) VALUES ";
                $values = [];
                
                foreach ($rows as $row) {
                    $rowValues = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $rowValues[] = "NULL";
                        } else {
                            $rowValues[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $values[] = "(" . implode(", ", $rowValues) . ")";
                }
                $sql .= $insert . implode(",\n", $values) . ";\n\n";
            }
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        if (file_put_contents($filepath, $sql)) {
            $gzpath = $filepath . '.gz';
            $gz = gzopen($gzpath, 'wb9');
            gzwrite($gz, $sql);
            gzclose($gz);
            unlink($filepath);
            
            return basename($gzpath);
        }
        
        return false;
    }
    
    // Criar backup
    $backup_file = criarBackupAgendado($db, $backupDir, $empresa_id);
    
    if ($backup_file) {
        $tamanho = filesize($backupDir . $backup_file);
        
        $stmt = $db->prepare("INSERT INTO backups_registros (nome_arquivo, tamanho, tipo, status, created_at) 
                              VALUES (:nome, :tamanho, 'agendado', 'sucesso', NOW())");
        $stmt->execute([
            ':nome' => $backup_file,
            ':tamanho' => formatarTamanho($tamanho)
        ]);
        
        echo "[" . date('Y-m-d H:i:s') . "] Backup criado: {$backup_file}\n";
        
        // Notificar por e-mail se configurado
        if ($config['notificar_email'] && $config['email_notificacao']) {
            $assunto = "[PontoFácil] Backup diário realizado com sucesso";
            $mensagem = "O backup automático foi realizado com sucesso.\n\n";
            $mensagem .= "Arquivo: {$backup_file}\n";
            $mensagem .= "Tamanho: " . formatarTamanho($tamanho) . "\n";
            $mensagem .= "Data: " . date('d/m/Y H:i:s') . "\n";
            $mensagem .= "Empresa: {$empresa_id}\n";
            
            enviarEmail($config['email_notificacao'], $assunto, $mensagem, false);
        }
        
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] ERRO: Falha ao criar backup\n";
        
        $stmt = $db->prepare("INSERT INTO backups_registros (nome_arquivo, tipo, status, created_at) 
                              VALUES ('erro_' . NOW(), 'agendado', 'falha', NOW())");
        $stmt->execute();
    }
    
    // Limpar backups antigos
    $limite = date('Y-m-d H:i:s', strtotime("-{$dias_retencao} days"));
    $stmt = $db->prepare("SELECT nome_arquivo FROM backups_registros WHERE created_at < :limite AND tipo = 'agendado'");
    $stmt->execute([':limite' => $limite]);
    $backups_antigos = $stmt->fetchAll();
    
    foreach ($backups_antigos as $antigo) {
        $file = $backupDir . $antigo['nome_arquivo'];
        if (file_exists($file)) {
            unlink($file);
            echo "[" . date('Y-m-d H:i:s') . "] Removido backup antigo: {$antigo['nome_arquivo']}\n";
        }
        $stmt = $db->prepare("DELETE FROM backups_registros WHERE nome_arquivo = :nome");
        $stmt->execute([':nome' => $antigo['nome_arquivo']]);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Agendador finalizado\n";

function formatarTamanho($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}
?>