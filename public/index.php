<?php

/**
 * 28Facil API - Servidor Principal
 * 
 * Sistema completo de licenciamento com portal web
 */

// LOG DEBUG
error_log("[DEBUG] REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));

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
use TwentyEightFacil\Controllers\UserController;

// Helper para pegar Authorization header
function getAuthorizationHeader() {
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

// LOG DEBUG
error_log("[DEBUG] Parsed path: $path | Method: $method");

// Roteamento
try {
    // Health Check (público)
    if ($path === '/' || $path === '/health') {
        error_log("[DEBUG] Rota: /health");
        healthCheck();
        exit;
    }
    
    // API Spec (público) - IMPORTANTE: deve ser antes de qualquer autenticação
    if ($path === '/api.json') {
        error_log("[DEBUG] Rota: /api.json - EXECUTANDO");
        apiSpec();
        exit;
    }
    
    // ===========================
    // Rotas Públicas de Licenciamento
    // ===========================
    
    if ($path === '/license/validate' && $method === 'POST') {
        $controller = new LicenseController($db);
        echo json_encode($controller->validate(), JSON_PRETTY_PRINT);
        exit;
    }
    
    if ($path === '/license/activate' && $method === 'POST') {
        $controller = new LicenseController($db);
        echo json_encode($controller->activate(), JSON_PRETTY_PRINT);
        exit;
    }
    
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
    
    error_log("[DEBUG] Entrando no middleware de autenticação - Path: $path");
    
    $authHeader = getAuthorizationHeader();
    $token = $authHeader ? str_replace('Bearer ', '', $authHeader) : '';
    $userId = AuthController::validateToken($token);
    
    if (!$userId) {
        error_log("[DEBUG] Autenticação falhou - Token: " . ($token ? 'presente' : 'ausente'));
        http_response_code(401);
        echo json_encode(['error' => 'Não autenticado']);
        exit;
    }
    
    // Buscar dados do usuário
    $stmt = $db->prepare("SELECT id, role FROM users WHERE id = :id AND status = 'active'");
    $stmt->execute(['id' => $userId]);
    $currentUser = $stmt->fetch();
    
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
    
    // ===========================
    // Rotas de Usuários/Clientes
    // ===========================
    
    $userController = new UserController($db);
    
    // Listar usuários
    if ($path === '/users' && $method === 'GET') {
        echo json_encode($userController->list($isAdmin), JSON_PRETTY_PRINT);
        exit;
    }
    
    // Criar usuário
    if ($path === '/users' && $method === 'POST') {
        echo json_encode($userController->create($isAdmin), JSON_PRETTY_PRINT);
        exit;
    }
    
    // Obter usuário específico
    if (preg_match('/^\/users\/(\d+)$/', $path, $matches) && $method === 'GET') {
        $targetUserId = (int)$matches[1];
        echo json_encode($userController->get($targetUserId, $userId, $isAdmin), JSON_PRETTY_PRINT);
        exit;
    }
    
    // Atualizar usuário
    if (preg_match('/^\/users\/(\d+)$/', $path, $matches) && $method === 'PUT') {
        $targetUserId = (int)$matches[1];
        echo json_encode($userController->update($targetUserId, $userId, $isAdmin), JSON_PRETTY_PRINT);
        exit;
    }
    
    // Desativar usuário
    if (preg_match('/^\/users\/(\d+)$/', $path, $matches) && $method === 'DELETE') {
        $targetUserId = (int)$matches[1];
        echo json_encode($userController->delete($targetUserId, $isAdmin), JSON_PRETTY_PRINT);
        exit;
    }
    
    // Resetar senha do usuário
    if (preg_match('/^\/users\/(\d+)\/reset-password$/', $path, $matches) && $method === 'POST') {
        $targetUserId = (int)$matches[1];
        echo json_encode($userController->resetPassword($targetUserId, $isAdmin), JSON_PRETTY_PRINT);
        exit;
    }
    
    // ===========================
    // Rotas de Licenças
    // ===========================
    
    if ($path === '/licenses' && $method === 'GET') {
        $controller = new LicenseController($db);
        echo json_encode($controller->list($userId, $isAdmin), JSON_PRETTY_PRINT);
        exit;
    }
    
    if ($path === '/licenses' && $method === 'POST') {
        $controller = new LicenseController($db);
        echo json_encode($controller->create($userId, $isAdmin), JSON_PRETTY_PRINT);
        exit;
    }
    
    if (preg_match('/^\/licenses\/(\d+)$/', $path, $matches) && $method === 'GET') {
        $licenseId = (int)$matches[1];
        $controller = new LicenseController($db);
        echo json_encode($controller->get($licenseId, $userId, $isAdmin), JSON_PRETTY_PRINT);
        exit;
    }
    
    // API Key antiga
    if ($path === '/auth/validate' && $method === 'GET') {
        validateApiKey();
        exit;
    }
    
    notFound();
    
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro interno do servidor',
        'message' => getenv('APP_DEBUG') === 'true' ? $e->getMessage() : 'Internal server error'
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
        'version' => '2.0.1',
        'timestamp' => date('c'),
        'php_version' => phpversion(),
        'features' => [
            'licensing' => 'enabled',
            'portal' => 'enabled',
            'users' => 'enabled',
            'api_keys' => 'enabled'
        ]
    ];
    
    try {
        if ($db) {
            $db->query('SELECT 1');
            $response['database'] = [
                'status' => 'connected',
                'type' => getenv('DB_CONNECTION') ?: 'pgsql',
                'host' => getenv('DB_HOST') ?: 'postgres',
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
    error_log("[DEBUG] apiSpec() chamada");
    
    $specPath = __DIR__ . '/../api.json';
    
    error_log("[DEBUG] Procurando arquivo: $specPath");
    error_log("[DEBUG] Arquivo existe: " . (file_exists($specPath) ? 'SIM' : 'NÃO'));
    
    if (file_exists($specPath)) {
        $content = file_get_contents($specPath);
        error_log("[DEBUG] Conteúdo carregado: " . strlen($content) . " bytes");
        echo $content;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API specification not found', 'path' => $specPath]);
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
        WHERE key_hash = :key_hash 
        AND is_active = true
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    
    $stmt->execute(['key_hash' => $keyHash]);
    $row = $stmt->fetch();
    
    if ($row) {
        $updateStmt = $db->prepare("
            UPDATE api_keys 
            SET 
                last_used_at = NOW(),
                usage_count = usage_count + 1,
                last_ip = :ip
            WHERE id = :id
        ");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $updateStmt->execute(['ip' => $ip, 'id' => $row['id']]);
        
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
}

function notFound() {
    http_response_code(404);
    echo json_encode([
        'error' => 'Endpoint not found',
        'message' => 'Check /api.json for available endpoints',
        'portal' => 'https://api.28facil.com.br/portal/'
    ]);
}