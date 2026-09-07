<?php
require_once __DIR__ . '/../includes/config.php';

class Database {
    private $conn;

    public function getConnection(): PDO {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }

        global $pdo;
        if ($pdo instanceof PDO) {
            $this->conn = $pdo;
            return $this->conn;
        }

        try {
            $this->conn = createDatabaseConnection();
            $this->conn->exec("SET time_zone = '-03:00'");
            return $this->conn;
        } catch (PDOException $e) {
            error_log('Falha na conexao MySQL: ' . $e->getMessage());
            throw new RuntimeException('Serviço de banco de dados temporariamente indisponível.', 0, $e);
        }
    }
}
