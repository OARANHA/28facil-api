<?php

/**
 * Middleware de Autenticação
 * 
 * Funções auxiliares para validação de API Keys
 */

/**
 * Verificar se requisição tem API Key válida
 * 
 * @return array|null Dados da key se válida, null se inválida
 */
function requireApiKey() {
    global $db;
    
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
    
    if (!$apiKey || !$db) {
        return null;
    }
    
    $keyHash = hash('sha256', $apiKey);
    
    $stmt = $db->prepare("
        SELECT 
            id, user_id, name, permissions, rate_limit
        FROM api_keys 
        WHERE key_hash = ? 
        AND is_active = 1
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    
    $stmt->bind_param('s', $keyHash);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'name' => $row['name'],
            'permissions' => json_decode($row['permissions'], true)
        ];
    }
    
    return null;
}

/**
 * Verificar se key tem permissão específica
 * 
 * @param array $keyData Dados da key
 * @param string $permission Permissão requerida (read, write, delete)
 * @return bool
 */
function hasPermission($keyData, $permission) {
    return in_array($permission, $keyData['permissions'] ?? []);
}

/**
 * Responder com erro de autenticação
 */
function unauthorized($message = 'Unauthorized') {
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized',
        'message' => $message
    ]);
    exit;
}

/**
 * Responder com erro de permissão
 */
function forbidden($message = 'Insufficient permissions') {
    http_response_code(403);
    echo json_encode([
        'error' => 'Forbidden',
        'message' => $message
    ]);
    exit;
}
