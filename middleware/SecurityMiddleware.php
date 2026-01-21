<?php
/**
 * Security Middleware - Headers de Segurança
 * Adiciona headers essenciais para proteção contra XSS, clickjacking, etc.
 */

class SecurityMiddleware
{
    /**
     * Aplica headers de segurança em todas requisições
     */
    public static function apply()
    {
        // Previne clickjacking
        header('X-Frame-Options: DENY');
        
        // Previne MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Protege contra XSS em navegadores antigos
        header('X-XSS-Protection: 1; mode=block');
        
        // Política de referrer restrita
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Força HTTPS (apenas em produção)
        if ($_SERVER['HTTPS'] ?? false) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Content Security Policy
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));
        
        // Permissions Policy (antiga Feature Policy)
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
    
    /**
     * Valida origem da requisição
     */
    public static function validateOrigin()
    {
        $allowedOrigins = [
            'https://api.28facil.com.br',
            'http://localhost:8000',
            'http://127.0.0.1:8000'
        ];
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
            
            // Responder OPTIONS preflight
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(200);
                exit;
            }
        }
    }
}
