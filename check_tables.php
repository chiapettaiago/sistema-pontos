<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=appcas29_pontofacil;charset=utf8mb4', 'root', '');
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
    echo "Connection failed: " . $e->getMessage();
}
