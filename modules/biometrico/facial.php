<?php
require_once __DIR__ . '/../../includes/auth_check.php';
forceAuthentication();
requireAdmin();

$funcionarioId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($funcionarioId <= 0) {
    $_SESSION['error'] = 'Funcionário inválido.';
    header('Location: ' . rtrim(BASE_URL, '/') . '/modules/funcionarios/index.php');
    exit;
}

header('Location: ' . rtrim(BASE_URL, '/') . '/modules/funcionarios/cadastro_facial.php?id=' . $funcionarioId);
exit;
