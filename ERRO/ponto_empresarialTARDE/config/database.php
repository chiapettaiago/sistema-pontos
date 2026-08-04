<?php
require_once __DIR__ . '/../includes/config.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        $this->host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $this->db_name = defined('DB_NAME') ? DB_NAME : 'ponto_empresarial';
        $this->username = defined('DB_USER') ? DB_USER : '';
        $this->password = defined('DB_PASS') ? DB_PASS : '';
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=utf8mb4',
                $this->username,
                $this->password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec('SET NAMES utf8mb4');
            $this->conn->exec("SET time_zone = '-03:00'");
        } catch (PDOException $e) {
            error_log('Erro de conexao: ' . $e->getMessage());
            throw new Exception('Erro ao conectar com o banco de dados');
        }

        return $this->conn;
    }
}
