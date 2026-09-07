<?php
require_once __DIR__ . '/includes/config.php';

try {
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tabelas no banco: \n";
    print_r($tables);
    
    if (in_array('usuarios_sistema', $tables)) {
        $stmt = $pdo->query('SELECT email FROM usuarios_sistema');
        echo "\nUsuarios no sistema: \n";
        print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
    }
} catch (PDOException $e) {
    echo 'Falha ao consultar o banco.';
}
