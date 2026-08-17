<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=appcas29_pontofacil;charset=utf8mb4', 'root', '');
    echo "Connected successfully to appcas29_pontofacil!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
