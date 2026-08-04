<?php
// api/index.php - Documentação da API
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API PontoFácil - Documentação</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
        }
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        .endpoint {
            background: white;
            border-radius: 16px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .endpoint-header {
            padding: 16px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .method {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .method-get { background: #d1fae5; color: #059669; }
        .method-post { background: #bfdbfe; color: #1e40af; }
        .method-put { background: #fed7aa; color: #c2410c; }
        .method-delete { background: #fee2e2; color: #dc2626; }
        .url {
            font-family: monospace;
            font-size: 14px;
            color: #1f2937;
        }
        .endpoint-body { padding: 20px; }
        .endpoint-body h4 { margin: 16px 0 8px 0; font-size: 14px; color: #4b5563; }
        .endpoint-body pre {
            background: #1f2937;
            color: #e5e7eb;
            padding: 16px;
            border-radius: 12px;
            overflow-x: auto;
            font-size: 12px;
            font-family: monospace;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            background: #e5e7eb;
            color: #4b5563;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 PontoFácil API</h1>
            <p>Documentação da API para aplicativos móveis - Versão 1.0.0</p>
            <p style="margin-top: 16px;">
                <span class="badge">Base URL: http://<?php echo $_SERVER['HTTP_HOST']; ?>/ponto_empresarial/api/</span>
            </p>
        </div>

        <div class="grid-2">
            <!-- Autenticação -->
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method method-post">POST</span>
                    <span class="url">auth.php</span>
                </div>
                <div class="endpoint-body">
                    <h4>Autenticação</h4>
                    <pre>{
    "email": "funcionario@email.com",
    "senha": "123456"
}</pre>
                    <h4>Resposta</h4>
                    <pre>{
    "success": true,
    "data": {
        "token": "abc123...",
        "usuario": {
            "id": 1,
            "nome": "Funcionário",
            "tipo": "funcionario"
        }
    }
}</pre>
                </div>
            </div>

            <!-- Registrar Ponto -->
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method method-post">POST</span>
                    <span class="url">pontos.php</span>
                </div>
                <div class="endpoint-body">
                    <h4>Registrar Ponto</h4>
                    <pre>{
    "tipo": "entrada",
    "latitude": "-22.9068",
    "longitude": "-43.1729",
    "foto_base64": "data:image/jpeg;base64,..."
}</pre>
                    <h4>Headers</h4>
                    <pre>Authorization: Bearer {token}</pre>
                </div>
            </div>

            <!-- Extrato -->
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method method-get">GET</span>
                    <span class="url">extrato.php?mes=2026-05</span>
                </div>
                <div class="endpoint-body">
                    <h4>Consultar Extrato</h4>
                    <pre>{
    "success": true,
    "data": {
        "dias": [...],
        "stats": {
            "dias_trabalhados": 20,
            "total_horas": "160:00"
        }
    }
}</pre>
                </div>
            </div>

            <!-- Solicitações -->
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method method-get">GET</span>
                    <span class="url">solicitacoes.php</span>
                </div>
                <div class="endpoint-body">
                    <h4>Listar Solicitações</h4>
                    <pre>GET /api/solicitacoes.php?status=pendente</pre>
                    <h4>Criar Solicitação</h4>
                    <pre>POST /api/solicitacoes.php
{
    "tipo": "ferias",
    "titulo": "Solicitação de Férias",
    "descricao": "Período de 20 dias",
    "data_inicio": "2026-06-01",
    "data_fim": "2026-06-20"
}</pre>
                </div>
            </div>

            <!-- Notificações -->
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method method-get">GET</span>
                    <span class="url">notificacoes.php</span>
                </div>
                <div class="endpoint-body">
                    <h4>Listar Notificações</h4>
                    <pre>GET /api/notificacoes.php?nao_lidas=1</pre>
                    <h4>Marcar como Lida</h4>
                    <pre>PUT /api/notificacoes.php?id=1</pre>
                </div>
            </div>

            <!-- Funcionário -->
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method method-get">GET</span>
                    <span class="url">funcionarios.php</span>
                </div>
                <div class="endpoint-body">
                    <h4>Dados do Funcionário</h4>
                    <pre>GET /api/funcionarios.php?meus_dados=1</pre>
                    <h4>Crachá Digital</h4>
                    <pre>GET /api/funcionarios.php?cracha=1</pre>
                </div>
            </div>
        </div>

        <div class="endpoint">
            <div class="endpoint-header">
                <span class="method method-get">GET</span>
                <span class="url">index.php</span>
            </div>
            <div class="endpoint-body">
                <h4>Códigos de Erro</h4>
                <pre>{
    "MISSING_TOKEN": "Token de autenticação não fornecido",
    "INVALID_TOKEN": "Token inválido ou expirado",
    "UNAUTHORIZED": "Acesso não autorizado",
    "NOT_FOUND": "Recurso não encontrado",
    "VALIDATION_ERROR": "Erro de validação"
}</pre>
            </div>
        </div>
    </div>
</body>
</html>