<?php
// includes/funcoes_auditoria.php - Funções para registrar logs de auditoria

/**
 * Registrar ação no log de auditoria
 * 
 * @param PDO $db Conexão com o banco de dados
 * @param string $acao Tipo da ação (INSERT, UPDATE, DELETE, LOGIN, LOGOUT, VIEW, EXPORT, BACKUP, RESTORE)
 * @param string $modulo Módulo onde a ação ocorreu (funcionarios, pontos, solicitacoes, etc)
 * @param int|null $registro_id ID do registro afetado
 * @param string|null $descricao Descrição da ação
 * @param array|null $dados_anteriores Dados antes da alteração (para UPDATE/DELETE)
 * @param array|null $dados_novos Dados depois da alteração (para INSERT/UPDATE)
 * @return bool
 */
function registrarLogAuditoria($db, $acao, $modulo, $registro_id = null, $descricao = null, $dados_anteriores = null, $dados_novos = null) {
    // Verificar se o log deve ser registrado baseado nas configurações
    if (!deveRegistrarLog($db, $acao)) {
        return true;
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO logs_auditoria 
            (usuario_id, usuario_nome, usuario_email, usuario_tipo, acao, modulo, registro_id, descricao, 
             dados_anteriores, dados_novos, ip_address, user_agent, created_at) 
            VALUES 
            (:usuario_id, :usuario_nome, :usuario_email, :usuario_tipo, :acao, :modulo, :registro_id, :descricao,
             :dados_anteriores, :dados_novos, :ip, :user_agent, NOW())");
        
        $stmt->execute([
            ':usuario_id' => $_SESSION['usuario_id'] ?? null,
            ':usuario_nome' => $_SESSION['usuario_nome'] ?? 'Sistema',
            ':usuario_email' => $_SESSION['usuario_email'] ?? null,
            ':usuario_tipo' => $_SESSION['usuario_tipo'] ?? 'sistema',
            ':acao' => $acao,
            ':modulo' => $modulo,
            ':registro_id' => $registro_id,
            ':descricao' => $descricao,
            ':dados_anteriores' => $dados_anteriores ? json_encode($dados_anteriores) : null,
            ':dados_novos' => $dados_novos ? json_encode($dados_novos) : null,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Erro ao registrar log de auditoria: " . $e->getMessage());
        return false;
    }
}

/**
 * Verificar se deve registrar log baseado nas configurações
 */
function deveRegistrarLog($db, $acao) {
    $empresa_id = $_SESSION['empresa_id'] ?? 1;
    
    try {
        $stmt = $db->prepare("SELECT * FROM auditoria_config WHERE empresa_id = :empresa_id");
        $stmt->execute([':empresa_id' => $empresa_id]);
        $config = $stmt->fetch();
        
        if (!$config) {
            return true;
        }
        
        switch ($acao) {
            case 'LOGIN': return $config['logar_login'];
            case 'LOGOUT': return $config['logar_logout'];
            case 'INSERT': return $config['logar_insert'];
            case 'UPDATE': return $config['logar_update'];
            case 'DELETE': return $config['logar_delete'];
            case 'VIEW': return $config['logar_view'];
            default: return true;
        }
    } catch (Exception $e) {
        return true;
    }
}

/**
 * Exemplo de uso em outros módulos:
 * 
 * // No cadastrar.php após inserir funcionário
 * registrarLogAuditoria($db, 'INSERT', 'funcionarios', $funcionario_id, "Cadastrou funcionário: $nome", null, $_POST);
 * 
 * // No editar.php após atualizar
 * registrarLogAuditoria($db, 'UPDATE', 'funcionarios', $id, "Editou funcionário: $nome", $dados_originais, $_POST);
 * 
 * // No excluir.php
 * registrarLogAuditoria($db, 'DELETE', 'funcionarios', $id, "Removeu funcionário: $nome", $funcionario, null);
 * 
 * // No login.php
 * registrarLogAuditoria($db, 'LOGIN', 'login', $usuario['id'], "Login realizado: {$email}", null, null);
 * 
 * // No logout.php
 * registrarLogAuditoria($db, 'LOGOUT', 'login', $_SESSION['usuario_id'], "Logout realizado", null, null);
 */
?>