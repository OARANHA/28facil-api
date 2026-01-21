<?php
namespace App\Security;

/**
 * Security Utilities
 * Funções auxiliares de segurança
 */
class SecurityUtils
{
    /**
     * Sanitiza string para prevenir XSS
     */
    public static function sanitize(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Sanitiza array recursivamente
     */
    public static function sanitizeArray(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = self::sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Valida email
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Valida senha forte
     */
    public static function validatePassword(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Senha deve ter no mínimo 8 caracteres';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Senha deve conter ao menos uma letra maiúscula';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Senha deve conter ao menos uma letra minúscula';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Senha deve conter ao menos um número';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Mascara purchase code para exibição segura
     */
    public static function maskPurchaseCode(string $code): string
    {
        if (strlen($code) < 12) {
            return $code;
        }
        
        $parts = explode('-', $code);
        
        if (count($parts) !== 4) {
            return $code;
        }
        
        // Manter primeiro e último segmento
        return $parts[0] . '-****-****-' . $parts[3];
    }
    
    /**
     * Gera hash seguro de senha
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    
    /**
     * Verifica hash de senha
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Gera token aleatório seguro
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Obtém IP real do cliente (mesmo atrás de proxy)
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_REAL_IP',         // Nginx proxy
            'HTTP_X_FORWARDED_FOR',   // Proxy padrão
            'REMOTE_ADDR'             // Conexão direta
        ];
        
        foreach ($headers as $header) {
            if (isset($_SERVER[$header]) && filter_var($_SERVER[$header], FILTER_VALIDATE_IP)) {
                return $_SERVER[$header];
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Log de evento de segurança
     */
    public static function logSecurityEvent(string $event, array $data = []): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => self::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'data' => $data
        ];
        
        $logFile = __DIR__ . '/../../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents(
            $logFile,
            json_encode($logEntry) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
