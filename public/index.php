<?php

/**
 * 28Facil API - Servidor Principal
 * 
 * Endpoints:
 * - GET /               -> Health check
 * - GET /health         -> Health detalhado
 * - GET /api.json       -> OpenAPI spec
 * - GET /auth/validate  -> Validar API Key
 */

header('Content-Type: application/json');
header('X-Powered-By: 28Facil/1.0');

// CORS (ajustar em produção)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Carregar configurações
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

// Parse da URL
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Roteamento simples
switch ($path) {
    case '/':
    case '/health':
        healthCheck();
        break;
    
    case '/api.json':
        apiSpec();
        break;
    
    case '/auth/validate':
        validateApiKey();
        break;
    
    default:
        notFound();
        break;
}

// =====================================================
// ENDPOINTS
// =====================================================

function healthCheck() {
    global $db;
    
    $response = [
        'status' => 'success',
        'message' => '28Facil API Server is running!',
        'version' => '1.0.0',
        'timestamp' => date('c'),
        'php_version' => phpversion(),
    ];
    
    // Verificar conexão com banco
    try {
        if ($db && $db->ping()) {
            $response['database'] = [
                'status' => 'connected',
                'host' => getenv('DB_HOST') ?: 'mysql',
                'database' => getenv('DB_DATABASE') ?: '28facil_api'
            ];
        }
    } catch (Exception $e) {
        $response['database'] = [
            'status' => 'error',
            'message' => 'Database connection failed'
        ];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
}

function apiSpec() {
    $specPath = __DIR__ . '/../api.json';
    
    if (file_exists($specPath)) {
        echo file_get_contents($specPath);
    } else {
        http_response_code(404);
        echo json_encode([
            'error' => 'API specification not found'
        ]);
    }
}

function validateApiKey() {
    global $db;
    
    // Obter API Key do header
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
    
    if (!$apiKey) {
        http_response_code(401);
        echo json_encode([
            'valid' => false,
            'error' => 'API Key not provided. Use header: X-API-Key'
        ]);
        return;
    }
    
    // Validar formato
    if (!str_starts_with($apiKey, '28fc_')) {
        http_response_code(401);
        echo json_encode([
            'valid' => false,
            'error' => 'Invalid API Key format'
        ]);
        return;
    }
    
    // Hash da key
    $keyHash = hash('sha256', $apiKey);
    
    // Buscar no banco
    $stmt = $db->prepare("
        SELECT 
            id, user_id, name, key_prefix, 
            permissions, rate_limit, usage_count
        FROM api_keys 
        WHERE key_hash = ? 
        AND is_active = 1
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    
    $stmt->bind_param('s', $keyHash);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Atualizar estatísticas
        $updateStmt = $db->prepare("
            UPDATE api_keys 
            SET 
                last_used_at = NOW(),
                usage_count = usage_count + 1,
                last_ip = ?
            WHERE id = ?
        ");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $updateStmt->bind_param('si', $ip, $row['id']);
        $updateStmt->execute();
        
        // Resposta de sucesso
        echo json_encode([
            'valid' => true,
            'user_id' => $row['user_id'],
            'name' => $row['name'],
            'prefix' => $row['key_prefix'],
            'permissions' => json_decode($row['permissions']),
            'rate_limit' => (int)$row['rate_limit'],
            'usage_count' => (int)$row['usage_count'] + 1,
            'last_used_at' => date('c')
        ], JSON_PRETTY_PRINT);
    } else {
        // Key inválida
        http_response_code(401);
        echo json_encode([
            'valid' => false,
            'error' => 'Invalid or expired API key'
        ]);
    }
    
    $stmt->close();
}

function notFound() {
    http_response_code(404);
    echo json_encode([
        'error' => 'Endpoint not found',
        'message' => 'Check /api.json for available endpoints'
    ]);
}
