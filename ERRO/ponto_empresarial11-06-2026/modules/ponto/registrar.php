<?php
session_start();

if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

header('Location: biometrico.php');
exit;
