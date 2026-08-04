<?php
// config/timezone.php - Configuração de Timezone
date_default_timezone_set('America/Sao_Paulo');

// Se estiver usando PDO, configurar também
$db->exec("SET time_zone = '-03:00'");
?>