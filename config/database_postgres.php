<?php

/**
 * Configuração do Banco de Dados - PostgreSQL
 * 
 * Migrado de MySQL (mysqli) para PostgreSQL (PDO)
 * 
 * Principais mudanças:
 * - mysqli -> PDO
 * - Prepared statements com placeholders posicionais ($1, $2)
 * - Suporte nativo a JSONB
 * - Melhor handling de erros
 */

$db = null;

try {
    $host = getenv('DB_HOST') ?: 'postgres';
    $port = getenv('DB_PORT') ?: '5432';
    $database = getenv('DB_DATABASE') ?: '28facil_api';
    $username = getenv('DB_USERNAME') ?: '28facil';
    $password = getenv('DB_PASSWORD') ?: 'senha_forte_123';
    
    // DSN para PostgreSQL
    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
    
    // Opções PDO otimizadas para PostgreSQL
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false, // Mudar para true em produção se necessário
    ];
    
    $db = new PDO($dsn, $username, $password, $options);
    
    // Configurar timezone (PostgreSQL)
    $db->exec("SET TIME ZONE 'America/Sao_Paulo'");
    
    // Configurar search_path se usar schemas por tenant
    // $db->exec("SET search_path TO public");
    
} catch (PDOException $e) {
    // Em produção, logar erro sem expor detalhes
    error_log('Database error: ' . $e->getMessage());
    
    // Resposta genérica em produção
    if (getenv('APP_ENV') === 'production') {
        die(json_encode([
            'error' => 'Database connection failed',
            'message' => 'Internal server error'
        ]));
    } else {
        // Apenas em desenvolvimento
        die(json_encode([
            'error' => 'Database connection failed',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]));
    }
}

/**
 * Função helper para executar queries com parâmetros
 * 
 * Uso:
 * $result = db_query("SELECT * FROM users WHERE id = $1", [123]);
 * $result = db_query("INSERT INTO users (name, email) VALUES ($1, $2)", ['João', 'joao@example.com']);
 * 
 * @param string $sql Query SQL com placeholders posicionais ($1, $2, etc)
 * @param array $params Parâmetros para bind
 * @return PDOStatement
 */
function db_query(string $sql, array $params = []): PDOStatement {
    global $db;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt;
}

/**
 * Buscar um único registro
 * 
 * @param string $sql
 * @param array $params
 * @return array|false
 */
function db_fetch_one(string $sql, array $params = []) {
    return db_query($sql, $params)->fetch();
}

/**
 * Buscar todos os registros
 * 
 * @param string $sql
 * @param array $params
 * @return array
 */
function db_fetch_all(string $sql, array $params = []): array {
    return db_query($sql, $params)->fetchAll();
}

/**
 * Executar query e retornar ID inserido
 * 
 * @param string $sql
 * @param array $params
 * @return string|false
 */
function db_insert(string $sql, array $params = []) {
    global $db;
    
    db_query($sql, $params);
    return $db->lastInsertId();
}

/**
 * Começar transação
 */
function db_begin_transaction(): void {
    global $db;
    $db->beginTransaction();
}

/**
 * Commit da transação
 */
function db_commit(): void {
    global $db;
    $db->commit();
}

/**
 * Rollback da transação
 */
function db_rollback(): void {
    global $db;
    $db->rollBack();
}

/**
 * Exemplo de uso com JSONB:
 * 
 * // Buscar licenças com metadata específica
 * $licenses = db_fetch_all(
 *     "SELECT * FROM licenses WHERE metadata @> $1",
 *     [json_encode(['type' => 'premium'])]
 * );
 * 
 * // Atualizar campo JSONB
 * db_query(
 *     "UPDATE licenses SET metadata = metadata || $1 WHERE id = $2",
 *     [json_encode(['updated' => true]), 123]
 * );
 */
