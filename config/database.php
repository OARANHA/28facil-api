<?php

/**
 * Configuração do Banco de Dados
 */

$db = null;

try {
    $host = getenv('DB_HOST') ?: 'mysql';
    $database = getenv('DB_DATABASE') ?: '28facil_api';
    $username = getenv('DB_USERNAME') ?: '28facil';
    $password = getenv('DB_PASSWORD') ?: 'senha_forte_123';
    
    $db = new mysqli($host, $username, $password, $database);
    
    if ($db->connect_error) {
        throw new Exception('Database connection failed: ' . $db->connect_error);
    }
    
    $db->set_charset('utf8mb4');
    
} catch (Exception $e) {
    // Em produção, logar erro sem expor detalhes
    error_log('Database error: ' . $e->getMessage());
    
    // Não falhar completamente, alguns endpoints podem funcionar sem DB
    $db = null;
}
