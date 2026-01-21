<?php

/**
 * 28Facil API - Servidor Principal
 * 
 * Sistema completo de licenciamento com portal web
 */

header('Content-Type: application/json');
header('X-Powered-By: 28Facil/2.0');

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization, X-License-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'TwentyEightFacil\\';
    $baseDir = __DIR__ . '/../src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Carregar configurações
require_once __DIR__ . '/../config/database.php';

use TwentyEightFacil\Controllers\AuthController;
use TwentyEightFacil\Controllers\LicenseController;

// Helper para pegar Authorization header (compatibilidade HTTP/2)
function getAuthorizationHeader() {
    // Tentar várias formas de obter o header
    $headers = null;
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    } elseif (function_exists('getallheaders')) {
        $requestHeaders = getallheaders();
        foreach ($requestHeaders as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $headers = trim($value);
                break;
            }
        }
    }
    return $headers;
}

// Parse da URL
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Remove /api prefix se existir
$path = preg_replace('/^\/api/', '', $path);

// Roteamento
try {
    // Health Check (público)
    if ($path === '/' || $path === '/health') {
        healthCheck();
        exit;
    }
    
    // API Spec (público)
    if ($path === '/api.json') {
        apiSpec();
        exit;
    }
    
    // ===========================
    // Rotas Públicas de Licenciamento
    // ===========================
    
    // Validar purchase code (usado pela aplicação)
    if ($path === '/license/validate' && $method === 'POST') {
        $controller = new LicenseController($db);
        echo json_encode($controller->validate(), JSON_PRETTY_PRINT);
        exit;
    }
    
    // Ativar licença (usado pela aplicação)
    if ($path === '/license/activate' && $method === 'POST') {
        $controller = new LicenseController($db);
        echo json_encode($controller->activate(), JSON_PRETTY_PRINT);
        exit;
    }
    
    // Check licença (health check da aplicação)
    if ($path === '/license/check' && $method === 'GET') {
        $controller = new LicenseController($db);
        echo json_encode($controller->check(), JSON_PRETTY_PRINT);
        exit;
    }
    
    // ===========================
    // Rotas de Autenticação (públicas)
    // ===========================
    
    if ($path === '/auth/register' && $method === 'POST') {
        $controller = new AuthController($db);
        echo json_encode($controller->register(), JSON_PRETTY_PRINT);
        exit;
    }
    
    if ($path === '/auth/login' && $method === 'POST') {
        $controller = new AuthController($db);
        echo json_encode($controller->login(), JSON_PRETTY_PRINT);
        exit;
    }
    
    // ===========================
    // Rotas Protegidas (requerem autenticação)
    // ===========================
    
    // Obter token do header (usando função helper)
    $authHeader = getAuthorizationHeader();
    $token = $authHeader ? str_replace('Bearer ', '', $authHeader) : '';
    
    // Validar token
    $userId = AuthController::validateToken($token);
    
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Não autenticado']);
        exit;
    }
    
    // Buscar dados do usuário
    $stmt = $db->prepare("SELECT id, role FROM users WHERE id = ? AND status = 'active'");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentUser = $result->fetch_assoc();
    
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['error' => 'Usuário inválido ou inativo']);
        exit;
    }
    
    $isAdmin = $currentUser['role'] === 'admin';
    
    // Dados do usuário logado
    if ($path === '/auth/me' && $method === 'GET') {
        $controller = new AuthController($db);
        echo json_encode($controller->me($userId), JSON_PRETTY_PRINT);
        exit;
    }
    
    // Listar usuários (apenas admin)
    if ($path === '/users' && $method === 'GET') {
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado. Apenas administradores.']);
            exit;
        }
        
        $stmt = $db->prepare("
            SELECT id, name, email, role, status, created_at 
            FROM users 
            WHERE status = 'active'
            ORDER BY name ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'count' => count($users)
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Listar licenças
    if ($path === '/licenses' && $method === 'GET') {
        $controller = new LicenseController($db);
        echo json_encode($controller->list($userId, $isAdmin), JSON_PRETTY_PRINT);
        exit;
    }
    
    // Criar licença (admin ou qualquer usuário para si mesmo)
    if ($path === '/licenses' && $method === 'POST') {
        $controller = new LicenseController($db);
        echo json_encode($controller->create($userId, $isAdmin), JSON_PRETTY_PRINT);
        exit;
    }
    
    // Detalhes de uma licença
    if (preg_match('/^\/licenses\/(\d+)$/', $path, $matches) && $method === 'GET') {
        $licenseId = (int)$matches[1];
        $controller = new LicenseController($db);
        echo json_encode($controller->get($licenseId, $userId, $isAdmin), JSON_PRETTY_PRINT);
        exit;
    }
    
    // ===========================
    // Rotas antigas (API Keys)
    // ===========================
    
    if ($path === '/auth/validate' && $method === 'GET') {
        validateApiKey();
        exit;
    }
    
    // Rota não encontrada
    notFound();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

// =====================================================
// FUNÇÕES AUXILIARES
// =====================================================

function healthCheck() {
    global $db;
    
    $response = [
        'status' => 'success',
        'message' => '28Facil API Server is running!',
        'version' => '2.0.0',
        'timestamp' => date('c'),
        'php_version' => phpversion(),
        'features' => [
            'licensing' => 'enabled',
            'portal' => 'enabled',
            'api_keys' => 'enabled'
        ]
    ];
    
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
        echo json_encode(['error' => 'API specification not found']);
    }
}

function validateApiKey() {
    global $db;
    
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
    
    if (!$apiKey) {
        http_response_code(401);
        echo json_encode([
            'valid' => false,
            'error' => 'API Key not provided. Use header: X-API-Key'
        ]);
        return;
    }
    
    if (!str_starts_with($apiKey, '28fc_')) {
        http_response_code(401);
        echo json_encode([
            'valid' => false,
            'error' => 'Invalid API Key format'
        ]);
        return;
    }
    
    $keyHash = hash('sha256', $apiKey);
    
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
        'message' => 'Check /api.json for available endpoints',
        'portal' => 'https://api.28facil.com.br/portal/'
    ]);
}