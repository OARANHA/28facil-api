<?php
namespace App\Security;

/**
 * CSRF Token Manager
 * Gera e valida tokens CSRF para proteção contra ataques Cross-Site Request Forgery
 */
class CsrfToken
{
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_EXPIRY = 3600; // 1 hora
    
    /**
     * Gera um novo token CSRF
     */
    public static function generate(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        
        $_SESSION[self::TOKEN_NAME] = [
            'token' => $token,
            'expires' => time() + self::TOKEN_EXPIRY
        ];
        
        return $token;
    }
    
    /**
     * Obtém o token CSRF atual
     */
    public static function get(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return self::generate();
        }
        
        $tokenData = $_SESSION[self::TOKEN_NAME];
        
        // Verificar expiração
        if ($tokenData['expires'] < time()) {
            return self::generate();
        }
        
        return $tokenData['token'];
    }
    
    /**
     * Valida um token CSRF
     */
    public static function validate(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!$token || !isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }
        
        $tokenData = $_SESSION[self::TOKEN_NAME];
        
        // Verificar expiração
        if ($tokenData['expires'] < time()) {
            unset($_SESSION[self::TOKEN_NAME]);
            return false;
        }
        
        // Comparação segura contra timing attacks
        return hash_equals($tokenData['token'], $token);
    }
    
    /**
     * Middleware para validar CSRF em requisições POST/PUT/DELETE
     */
    public static function middleware(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
        
        // Métodos seguros não precisam CSRF
        if (in_array($method, $safeMethods)) {
            return true;
        }
        
        // Buscar token no header ou body
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
        
        if (!self::validate($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'CSRF token inválido ou expirado',
                'code' => 'CSRF_VALIDATION_FAILED'
            ]);
            exit;
        }
        
        return true;
    }
    
    /**
     * Gera meta tag HTML com token
     */
    public static function metaTag(): string
    {
        $token = self::get();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    /**
     * Gera input hidden HTML com token
     */
    public static function inputField(): string
    {
        $token = self::get();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
