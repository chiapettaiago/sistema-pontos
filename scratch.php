<?php
$db = new PDO('mysql:host=localhost;dbname=appcas29_pontofacil;charset=utf8mb4', 'root', '');
$q = $db->query('SHOW CREATE TABLE pontos');
print_r($q->fetch());
