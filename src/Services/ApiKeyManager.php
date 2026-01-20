<?php

/**
 * Gerenciador de API Keys para 28Fácil
 * 
 * USO:
 * - ApiKeyManager::generate('Minha Key', $userId)
 * - ApiKeyManager::validate($apiKey)
 * - ApiKeyManager::revoke($keyId)
 */

namespace App\Services;

use DateTime;
use Illuminate\Support\Facades\DB;

class ApiKeyManager
{
    /**
     * Prefixo das API Keys
     */
    private const KEY_PREFIX = '28fc_';
    
    /**
     * Tamanho da chave secreta em bytes
     */
    private const SECRET_BYTES = 24;
    
    /**
     * Gera uma nova API Key
     *
     * @param string $name Nome descritivo da key
     * @param int|null $userId ID do usuário dono
     * @param array $permissions Permissões (ex: ['read', 'write'])
     * @param DateTime|null $expiresAt Data de expiração
     * @param int $rateLimit Limite de requisições por hora
     * @return array Dados da key criada (incluindo a key completa)
     */
    public static function generate(
        string $name,
        ?int $userId = null,
        array $permissions = ['read'],
        ?DateTime $expiresAt = null,
        int $rateLimit = 1000
    ): array {
        // Gerar bytes aleatórios seguros
        $randomBytes = random_bytes(self::SECRET_BYTES);
        $secret = bin2hex($randomBytes);
        
        // Montar a key completa
        $fullKey = self::KEY_PREFIX . $secret;
        
        // Gerar hash SHA256 para armazenar
        $keyHash = hash('sha256', $fullKey);
        
        // Gerar prefixo para identificação visual
        $keyPrefix = self::KEY_PREFIX . substr($secret, 0, 8);
        
        // Preparar dados para inserção
        $data = [
            'key_hash' => $keyHash,
            'key_prefix' => $keyPrefix,
            'user_id' => $userId,
            'name' => $name,
            'permissions' => json_encode($permissions),
            'rate_limit' => $rateLimit,
            'is_active' => true,
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        
        // Inserir no banco
        $apiKeyId = DB::table('api_keys')->insertGetId($data);
        
        // Retornar dados (ATENÇÃO: fullKey só é mostrado AQUI!)
        return [
            'id' => $apiKeyId,
            'key' => $fullKey, // ⚠️ Guardar em local seguro!
            'prefix' => $keyPrefix,
            'name' => $name,
            'permissions' => $permissions,
            'rate_limit' => $rateLimit,
            'expires_at' => $expiresAt?->format('c'),
            'created_at' => now()->toIso8601String(),
        ];
    }
    
    /**
     * Valida uma API Key
     *
     * @param string $apiKey A key fornecida pelo cliente
     * @return array|null Dados da key se válida, null se inválida
     */
    public static function validate(string $apiKey): ?array
    {
        // Verificar formato básico
        if (!str_starts_with($apiKey, self::KEY_PREFIX)) {
            return null;
        }
        
        // Gerar hash da key fornecida
        $keyHash = hash('sha256', $apiKey);
        
        // Buscar no banco
        $record = DB::table('api_keys')
            ->where('key_hash', $keyHash)
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();
        
        // Key não encontrada ou inválida
        if (!$record) {
            return null;
        }
        
        // Atualizar estatísticas de uso
        DB::table('api_keys')
            ->where('id', $record->id)
            ->update([
                'last_used_at' => now(),
                'usage_count' => DB::raw('usage_count + 1'),
                'last_ip' => request()?->ip(),
            ]);
        
        // Retornar dados da key (sem a key em si!)
        return [
            'id' => $record->id,
            'user_id' => $record->user_id,
            'name' => $record->name,
            'prefix' => $record->key_prefix,
            'permissions' => json_decode($record->permissions, true),
            'rate_limit' => $record->rate_limit,
            'usage_count' => $record->usage_count + 1,
            'last_used_at' => now()->toIso8601String(),
        ];
    }
    
    /**
     * Revogar (desativar) uma API Key
     *
     * @param int $keyId ID da key
     * @param string|null $reason Motivo da revogação
     * @return bool True se revogada com sucesso
     */
    public static function revoke(int $keyId, ?string $reason = null): bool
    {
        $affected = DB::table('api_keys')
            ->where('id', $keyId)
            ->update([
                'is_active' => false,
                'revoked_at' => now(),
                'revoked_reason' => $reason,
                'updated_at' => now(),
            ]);
        
        return $affected > 0;
    }
    
    /**
     * Listar API Keys de um usuário
     *
     * @param int $userId ID do usuário
     * @param bool $activeOnly Apenas keys ativas
     * @return array Lista de keys
     */
    public static function list(int $userId, bool $activeOnly = true): array
    {
        $query = DB::table('api_keys')
            ->where('user_id', $userId);
        
        if ($activeOnly) {
            $query->where('is_active', true);
        }
        
        $records = $query
            ->orderBy('created_at', 'desc')
            ->get();
        
        return $records->map(function($record) {
            return [
                'id' => $record->id,
                'prefix' => $record->key_prefix,
                'name' => $record->name,
                'permissions' => json_decode($record->permissions, true),
                'is_active' => (bool) $record->is_active,
                'rate_limit' => $record->rate_limit,
                'usage_count' => $record->usage_count,
                'last_used_at' => $record->last_used_at,
                'last_ip' => $record->last_ip,
                'expires_at' => $record->expires_at,
                'created_at' => $record->created_at,
                'revoked_at' => $record->revoked_at,
                'revoked_reason' => $record->revoked_reason,
            ];
        })->toArray();
    }
    
    /**
     * Verificar se uma key atingiu o rate limit
     *
     * @param int $keyId ID da key
     * @return bool True se atingiu o limite
     */
    public static function isRateLimited(int $keyId): bool
    {
        $record = DB::table('api_keys')
            ->where('id', $keyId)
            ->first(['rate_limit']);
        
        if (!$record) {
            return true;
        }
        
        // Contar requisições na última hora
        $count = DB::table('api_key_logs')
            ->where('api_key_id', $keyId)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        
        return $count >= $record->rate_limit;
    }
}
