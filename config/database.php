<?php

/**
 * Configuração do Banco de Dados
 */

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
    
    $db = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
} catch (Exception $e) {
    // Em produção, logar erro sem expor detalhes
    error_log('Database error: ' . $e->getMessage());
    
    // Não falhar completamente, alguns endpoints podem funcionar sem DB
    $db = null;
}
