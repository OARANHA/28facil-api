<?php
namespace App\Security;

/**
 * Rate Limiter
 * Previne brute-force e abuso de endpoints
 */
class RateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_TIME = 300; // 5 minutos
    private const CLEANUP_PROBABILITY = 0.01; // 1% de chance de limpar dados antigos
    
    private string $storageDir;
    
    public function __construct(string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? sys_get_temp_dir() . '/rate_limiter';
        
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }
    
    /**
     * Verifica se o identificador está bloqueado
     */
    public function isBlocked(string $identifier): bool
    {
        $this->cleanup();
        
        $file = $this->getFilePath($identifier);
        
        if (!file_exists($file)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        if (!$data) {
            return false;
        }
        
        // Verificar se ainda está no período de lockout
        if (isset($data['locked_until']) && $data['locked_until'] > time()) {
            return true;
        }
        
        // Limpar lockout expirado
        if (isset($data['locked_until']) && $data['locked_until'] <= time()) {
            unlink($file);
            return false;
        }
        
        return false;
    }
    
    /**
     * Registra uma tentativa
     */
    public function attempt(string $identifier): array
    {
        if ($this->isBlocked($identifier)) {
            return [
                'allowed' => false,
                'reason' => 'Muitas tentativas. Aguarde alguns minutos.',
                'retry_after' => $this->getRetryAfter($identifier)
            ];
        }
        
        $file = $this->getFilePath($identifier);
        $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
        
        $now = time();
        $attempts = $data['attempts'] ?? [];
        
        // Filtrar tentativas antigas (mais de 1 hora)
        $attempts = array_filter($attempts, function($timestamp) use ($now) {
            return ($now - $timestamp) < 3600;
        });
        
        $attempts[] = $now;
        
        // Verificar se excedeu o limite
        if (count($attempts) >= self::MAX_ATTEMPTS) {
            $data = [
                'attempts' => [],
                'locked_until' => $now + self::LOCKOUT_TIME
            ];
            
            file_put_contents($file, json_encode($data));
            
            return [
                'allowed' => false,
                'reason' => 'Limite de tentativas excedido. Aguarde ' . (self::LOCKOUT_TIME / 60) . ' minutos.',
                'retry_after' => self::LOCKOUT_TIME
            ];
        }
        
        $data['attempts'] = $attempts;
        file_put_contents($file, json_encode($data));
        
        return [
            'allowed' => true,
            'remaining' => self::MAX_ATTEMPTS - count($attempts)
        ];
    }
    
    /**
     * Limpa as tentativas de um identificador (após login bem-sucedido)
     */
    public function clear(string $identifier): void
    {
        $file = $this->getFilePath($identifier);
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    /**
     * Obtém tempo restante de bloqueio em segundos
     */
    private function getRetryAfter(string $identifier): int
    {
        $file = $this->getFilePath($identifier);
        
        if (!file_exists($file)) {
            return 0;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        if (!isset($data['locked_until'])) {
            return 0;
        }
        
        $remaining = $data['locked_until'] - time();
        return max(0, $remaining);
    }
    
    /**
     * Gera caminho do arquivo para um identificador
     */
    private function getFilePath(string $identifier): string
    {
        $hash = hash('sha256', $identifier);
        return $this->storageDir . '/' . $hash . '.json';
    }
    
    /**
     * Limpa arquivos antigos periodicamente
     */
    private function cleanup(): void
    {
        // Executa limpeza aleatoriamente (1% de chance)
        if (mt_rand(1, 100) > (self::CLEANUP_PROBABILITY * 100)) {
            return;
        }
        
        $files = glob($this->storageDir . '/*.json');
        $now = time();
        
        foreach ($files as $file) {
            // Deletar arquivos com mais de 2 horas
            if (($now - filemtime($file)) > 7200) {
                unlink($file);
            }
        }
    }
    
    /**
     * Helper estático para rate limit de login
     */
    public static function checkLogin(string $email): array
    {
        $limiter = new self();
        $identifier = 'login:' . $email;
        return $limiter->attempt($identifier);
    }
    
    /**
     * Helper estático para limpar após login bem-sucedido
     */
    public static function clearLogin(string $email): void
    {
        $limiter = new self();
        $identifier = 'login:' . $email;
        $limiter->clear($identifier);
    }
}
