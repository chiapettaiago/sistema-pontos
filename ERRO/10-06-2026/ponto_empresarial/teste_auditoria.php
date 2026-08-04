<?php
// teste_auditoria.php - Página para testar registro de logs
session_start();
require_once 'config/database.php';
require_once 'includes/funcoes_auditoria.php';

$database = new Database();
$db = $database->getConnection();

$mensagem = '';
$erro = '';

// Testar diferentes tipos de log
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_teste = $_POST['tipo_teste'] ?? '';
    
    switch ($tipo_teste) {
        case 'login':
            $resultado = registrarLogAuditoria($db, 'LOGIN', 'login', $_SESSION['usuario_id'], 'Login de teste - auditoria');
            $mensagem = $resultado ? '✅ Log de LOGIN registrado com sucesso!' : '❌ Falha ao registrar log';
            break;
            
        case 'insert':
            $dados = ['nome' => 'Usuario Teste', 'email' => 'teste@teste.com'];
            $resultado = registrarLogAuditoria($db, 'INSERT', 'funcionarios', 999, 'Inserção de teste', null, $dados);
            $mensagem = $resultado ? '✅ Log de INSERT registrado com sucesso!' : '❌ Falha ao registrar log';
            break;
            
        case 'update':
            $dados_old = ['nome' => 'Nome Antigo', 'email' => 'antigo@teste.com'];
            $dados_new = ['nome' => 'Nome Novo', 'email' => 'novo@teste.com'];
            $resultado = registrarLogAuditoria($db, 'UPDATE', 'funcionarios', 999, 'Atualização de teste', $dados_old, $dados_new);
            $mensagem = $resultado ? '✅ Log de UPDATE registrado com sucesso!' : '❌ Falha ao registrar log';
            break;
            
        case 'delete':
            $dados = ['nome' => 'Usuario Removido', 'email' => 'remove@teste.com'];
            $resultado = registrarLogAuditoria($db, 'DELETE', 'funcionarios', 999, 'Remoção de teste', $dados, null);
            $mensagem = $resultado ? '✅ Log de DELETE registrado com sucesso!' : '❌ Falha ao registrar log';
            break;
            
        case 'view':
            $resultado = registrarLogAuditoria($db, 'VIEW', 'relatorios', null, 'Visualizou relatório de pontos');
            $mensagem = $resultado ? '✅ Log de VIEW registrado com sucesso!' : '❌ Falha ao registrar log';
            break;
            
        case 'export':
            $resultado = registrarLogAuditoria($db, 'EXPORT', 'relatorios', null, 'Exportou relatório para Excel');
            $mensagem = $resultado ? '✅ Log de EXPORT registrado com sucesso!' : '❌ Falha ao registrar log';
            break;
    }
}

// Buscar últimos logs
$stmt = $db->prepare("SELECT * FROM logs_auditoria ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$ultimos_logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Teste de Auditoria</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-login { background: #10b981; color: white; }
        .btn-insert { background: #3b82f6; color: white; }
        .btn-update { background: #f59e0b; color: white; }
        .btn-delete { background: #ef4444; color: white; }
        .btn-view { background: #8b5cf6; color: white; }
        .btn-export { background: #ec4898; color: white; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
        }
        .success {
            background: #d1fae5;
            color: #059669;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        a {
            color: #667eea;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>🧪 Teste do Módulo de Auditoria</h1>
            <p>Clique nos botões abaixo para testar o registro de logs</p>
            
            <?php if ($mensagem): ?>
                <div class="success"><?php echo $mensagem; ?></div>
            <?php endif; ?>
            
            <?php if ($erro): ?>
                <div class="error"><?php echo $erro; ?></div>
            <?php endif; ?>
            
            <div class="btn-group">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="tipo_teste" value="login">
                    <button type="submit" class="btn-login">🔐 Testar LOGIN</button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="tipo_teste" value="insert">
                    <button type="submit" class="btn-insert">➕ Testar INSERT</button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="tipo_teste" value="update">
                    <button type="submit" class="btn-update">✏️ Testar UPDATE</button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="tipo_teste" value="delete">
                    <button type="submit" class="btn-delete">🗑️ Testar DELETE</button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="tipo_teste" value="view">
                    <button type="submit" class="btn-view">👁️ Testar VIEW</button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="tipo_teste" value="export">
                    <button type="submit" class="btn-export">📎 Testar EXPORT</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <h2>📋 Últimos Logs Registrados</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data/Hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Módulo</th>
                        <th>Descrição</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimos_logs as $log): ?>
                    <tr>
                        <td><?php echo $log['id']; ?></td>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($log['usuario_nome'] ?? 'Sistema'); ?></td>
                        <td><?php echo $log['acao']; ?></td>
                        <td><?php echo ucfirst($log['modulo']); ?></td>
                        <td><?php echo htmlspecialchars(substr($log['descricao'] ?? '', 0, 50)); ?></td>
                        <td>
                            <a href="modules/auditoria/detalhes.php?id=<?php echo $log['id']; ?>" target="_blank">
                                <i class="fas fa-eye"></i> Detalhes
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card">
            <h2>🔗 Links para os módulos</h2>
            <ul>
                <li><a href="modules/auditoria/index.php" target="_blank">📊 Painel de Auditoria (com filtros)</a></li>
                <li><a href="modules/auditoria/exportar.php" target="_blank">📎 Exportar Logs (CSV)</a></li>
                <li><a href="modules/auditoria/limpar.php" target="_blank">🗑️ Limpar Logs Antigos</a></li>
            </ul>
        </div>
        
        <div class="card">
            <h2>📌 Instruções para teste completo</h2>
            <ol>
                <li><strong>1. Testar registro automático:</strong> Faça login no sistema, cadastre um funcionário, edite, exclua</li>
                <li><strong>2. Verificar logs:</strong> Acesse <code>modules/auditoria/index.php</code> e veja se os logs aparecem</li>
                <li><strong>3. Filtrar logs:</strong> Teste os filtros por data, usuário, ação e módulo</li>
                <li><strong>4. Ver detalhes:</strong> Clique no ícone de olho para ver detalhes completos (incluindo comparação)</li>
                <li><strong>5. Exportar:</strong> Teste a exportação para CSV</li>
                <li><strong>6. Limpar:</strong> Teste a limpeza de logs antigos</li>
            </ol>
        </div>
    </div>
</body>
</html>