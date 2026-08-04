<?php
// modules/biometrico/diagnostico.php - Diagnostico rapido da biometria
$pageTitle = 'Diagnostico Biométrico';
$activePage = 'biometrico';
require_once '../../includes/header.php';
require_once '../../config/database.php';

checkModuleAccess('funcionarios');

$database = new Database();
$db = $database->getConnection();

function tableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare("SHOW TABLES LIKE :table");
    $stmt->execute([':table' => $table]);
    return (bool) $stmt->fetch();
}

function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE :column");
    $stmt->execute([':column' => $column]);
    return (bool) $stmt->fetch();
}

$checks = [
    'biometricos_faciais' => [
        'descritores' => false,
        'amostras' => false,
        'ativo' => false,
        'updated_at' => false,
    ],
    'biometricos_digitais' => [
        'funcionario_id' => false,
        'digital_template' => false,
        'ativo' => false,
        'updated_at' => false,
    ],
];

foreach (array_keys($checks) as $table) {
    if (tableExists($db, $table)) {
        foreach (array_keys($checks[$table]) as $column) {
            $checks[$table][$column] = columnExists($db, $table, $column);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico Biométrico</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f6f7fb; margin: 0; padding: 24px; color: #111827; }
        .wrap { max-width: 900px; margin: 0 auto; }
        .card { background: #fff; border-radius: 20px; padding: 22px; box-shadow: 0 16px 40px rgba(15,23,42,.08); margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f9fafb; }
        .ok { color: #065f46; font-weight: 700; }
        .no { color: #991b1b; font-weight: 700; }
        .pill { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .pill-ok { background:#d1fae5; color:#065f46; }
        .pill-no { background:#fee2e2; color:#991b1b; }
        pre { white-space: pre-wrap; word-break: break-word; background:#0f172a; color:#e2e8f0; padding:16px; border-radius:14px; overflow:auto; }
        .actions a { display:inline-block; margin-right:10px; margin-top:10px; padding:10px 14px; border-radius:12px; text-decoration:none; background:#111827; color:#fff; font-weight:700; }
        .actions a.secondary { background:#e5e7eb; color:#111827; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h2 style="margin-bottom:8px;">Diagnóstico Biométrico</h2>
        <p>Este painel mostra se as tabelas e colunas necessárias para facial e digital estão presentes.</p>
        <div class="actions">
            <a href="index.php">Voltar</a>
            <a class="secondary" href="cadastrar.php">Cadastrar biometria</a>
        </div>
    </div>

    <?php foreach ($checks as $table => $columns): ?>
    <div class="card">
        <h3><?php echo htmlspecialchars($table); ?></h3>
        <?php if (!tableExists($db, $table)): ?>
            <p class="no">Tabela não encontrada</p>
        <?php else: ?>
            <p class="ok">Tabela encontrada</p>
            <table>
                <thead>
                    <tr><th>Coluna</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($columns as $column => $status): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($column); ?></td>
                            <td>
                                <span class="pill <?php echo $status ? 'pill-ok' : 'pill-no'; ?>">
                                    <?php echo $status ? 'OK' : 'Faltando'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div class="card">
        <h3>Próxima ação</h3>
        <p>Se aparecer algo como <strong>Faltando</strong>, aplique o SQL de compatibilidade abaixo no servidor.</p>
        <pre><?php echo htmlspecialchars(file_get_contents(__DIR__ . '/../../sql/compat_biometria.sql')); ?></pre>
    </div>
</div>
</body>
</html>
