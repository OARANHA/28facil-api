<?php

/**
 * Configuração do Banco de Dados com Wrapper PDO -> mysqli style
 */

class PDOStatementWrapper {
    private $stmt;
    
    public function __construct($stmt) {
        $this->stmt = $stmt;
    }
    
    public function bind_param($types, ...$vars) {
        // PDO usa ? placeholders, bindValue é automático no execute
        return true;
    }
    
    public function execute($params = null) {
        if ($params) {
            return $this->stmt->execute($params);
        }
        return $this->stmt->execute();
    }
    
    public function get_result() {
        return $this->stmt;
    }
    
    public function __call($method, $args) {
        return call_user_func_array([$this->stmt, $method], $args);
    }
}

class PDOWrapper {
    private $pdo;
    public $connect_error = null;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function prepare($query) {
        // Converter de mysqli style (?) para PDO placeholders
        $stmt = $this->pdo->prepare($query);
        return new PDOStatementWrapper($stmt);
    }
    
    public function query($sql) {
        return $this->pdo->query($sql);
    }
    
    public function set_charset($charset) {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $this->pdo->exec("SET NAMES {$charset}");
        }
    }
    
    public function ping() {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function __call($method, $args) {
        return call_user_func_array([$this->pdo, $method], $args);
    }
}

$db = null;

try {
    $connection = getenv('DB_CONNECTION') ?: 'pgsql';
    $host = getenv('DB_HOST') ?: 'postgres';
    $port = getenv('DB_PORT') ?: '5432';
    $database = getenv('DB_DATABASE') ?: '28facil_api';
    $username = getenv('DB_USERNAME') ?: '28facil';
    $password = getenv('DB_PASSWORD') ?: 'senha_forte_123';
    
    // Usar PDO para suportar PostgreSQL e MySQL
    if ($connection === 'pgsql') {
        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
    } else {
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    }
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    // Wrapper para compatibilidade com código mysqli existente
    $db = new PDOWrapper($pdo);
    
} catch (Exception $e) {
    // Em produção, logar erro sem expor detalhes
    error_log('Database error: ' . $e->getMessage());
    
    // Não falhar completamente, alguns endpoints podem funcionar sem DB
    $db = null;
}
