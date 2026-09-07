<?php
require_once __DIR__ . '/includes/config.php';
$db = $pdo;
$q = $db->query('SHOW CREATE TABLE pontos');
print_r($q->fetch());
