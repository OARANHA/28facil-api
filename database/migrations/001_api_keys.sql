-- =====================================================
-- MIGRAÇÃO: Sistema de API Keys - 28Fácil
-- =====================================================

-- Tabela principal de API Keys
CREATE TABLE IF NOT EXISTS `api_keys` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Identificação da Key
    `key_hash` VARCHAR(64) NOT NULL UNIQUE COMMENT 'Hash SHA256 da API key completa',
    `key_prefix` VARCHAR(20) NOT NULL COMMENT 'Prefixo visível (ex: 28fc_a1b2c3d4)',
    
    -- Proprietário
    `user_id` BIGINT UNSIGNED NULL COMMENT 'ID do usuário dono da key',
    `name` VARCHAR(255) NOT NULL COMMENT 'Nome descritivo da API key',
    `description` TEXT NULL COMMENT 'Descrição opcional',
    
    -- Permissões e Limites
    `permissions` JSON NOT NULL DEFAULT '[]' COMMENT 'Array de permissões: ["read", "write", "delete"]',
    `rate_limit` INT UNSIGNED NOT NULL DEFAULT 1000 COMMENT 'Requisições permitidas por hora',
    
    -- Status
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Key ativa ou revogada',
    `expires_at` TIMESTAMP NULL COMMENT 'Data de expiração (NULL = nunca expira)',
    
    -- Estatísticas de Uso
    `last_used_at` TIMESTAMP NULL COMMENT 'Última vez que foi usada',
    `usage_count` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total de usos',
    `last_ip` VARCHAR(45) NULL COMMENT 'Último IP que usou',
    `last_user_agent` TEXT NULL COMMENT 'Último User-Agent',
    
    -- Auditoria
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `revoked_at` TIMESTAMP NULL COMMENT 'Quando foi revogada',
    `revoked_by` BIGINT UNSIGNED NULL COMMENT 'Quem revogou',
    `revoked_reason` TEXT NULL COMMENT 'Motivo da revogação',
    
    -- Índices
    INDEX `idx_key_hash` (`key_hash`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_expires_at` (`expires_at`),
    INDEX `idx_created_at` (`created_at`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='API Keys do sistema 28Fácil';

-- Tabela de Logs de Uso (OPCIONAL - para auditoria detalhada)
CREATE TABLE IF NOT EXISTS `api_key_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `api_key_id` BIGINT UNSIGNED NOT NULL,
    
    -- Dados da Requisição
    `endpoint` VARCHAR(500) NOT NULL COMMENT 'Endpoint acessado',
    `method` VARCHAR(10) NOT NULL COMMENT 'Método HTTP (GET, POST, etc)',
    `ip_address` VARCHAR(45) NOT NULL COMMENT 'IP do cliente',
    `user_agent` TEXT NULL COMMENT 'User-Agent do cliente',
    
    -- Dados da Resposta
    `status_code` INT UNSIGNED NULL COMMENT 'Código HTTP de resposta',
    `response_time_ms` INT UNSIGNED NULL COMMENT 'Tempo de resposta em ms',
    `error_message` TEXT NULL COMMENT 'Mensagem de erro se houver',
    
    -- Timestamp
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX `idx_api_key_id` (`api_key_id`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_endpoint` (`endpoint`(255)),
    
    -- Chave estrangeira
    FOREIGN KEY (`api_key_id`) REFERENCES `api_keys`(`id`) ON DELETE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Logs de uso das API Keys';

-- Inserir exemplos (REMOVER EM PRODUÇÃO!)
INSERT INTO `api_keys` (
    `key_hash`,
    `key_prefix`,
    `user_id`,
    `name`,
    `description`,
    `permissions`,
    `rate_limit`
) VALUES (
    SHA2('28fc_example_test_key_do_not_use_in_production', 256),
    '28fc_example',
    1,
    'Key de Teste',
    'Esta é uma key de exemplo para testes. REMOVER EM PRODUÇÃO!',
    JSON_ARRAY('read', 'write'),
    500
);

-- Verificar
SELECT 
    id,
    key_prefix,
    name,
    is_active,
    usage_count,
    created_at
FROM api_keys;
