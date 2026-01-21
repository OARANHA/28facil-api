-- =====================================================
-- MIGRAÇÃO: Sistema de API Keys - 28Fácil
-- Compatível com PostgreSQL 16+
-- IDEMPOTENTE: Pode ser executada múltiplas vezes
-- =====================================================

-- Criar tipos ENUM personalizados (se não existirem)
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'api_key_status') THEN
        CREATE TYPE api_key_status AS ENUM ('active', 'revoked');
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'http_method') THEN
        CREATE TYPE http_method AS ENUM ('GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD');
    END IF;
END $$;

-- Função de trigger para updated_at (se não existir)
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Tabela principal de API Keys
CREATE TABLE IF NOT EXISTS api_keys (
    id BIGSERIAL PRIMARY KEY,
    
    -- Identificação da Key
    key_hash VARCHAR(64) NOT NULL UNIQUE,
    key_prefix VARCHAR(20) NOT NULL,
    
    -- Proprietário
    user_id BIGINT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    
    -- Permissões e Limites
    permissions JSONB NULL DEFAULT '[]'::jsonb,
    rate_limit INTEGER NOT NULL DEFAULT 1000,
    
    -- Status
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    expires_at TIMESTAMP WITH TIME ZONE NULL,
    
    -- Estatísticas de Uso
    last_used_at TIMESTAMP WITH TIME ZONE NULL,
    usage_count BIGINT NOT NULL DEFAULT 0,
    last_ip INET NULL,
    last_user_agent TEXT NULL,
    
    -- Auditoria
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP WITH TIME ZONE NULL,
    revoked_by BIGINT NULL,
    revoked_reason TEXT NULL
);

-- Índices otimizados (se não existirem)
CREATE INDEX IF NOT EXISTS idx_api_keys_key_hash ON api_keys(key_hash);
CREATE INDEX IF NOT EXISTS idx_api_keys_user_id ON api_keys(user_id);
CREATE INDEX IF NOT EXISTS idx_api_keys_is_active ON api_keys(is_active);
CREATE INDEX IF NOT EXISTS idx_api_keys_expires_at ON api_keys(expires_at);
CREATE INDEX IF NOT EXISTS idx_api_keys_created_at ON api_keys(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_api_keys_permissions_gin ON api_keys USING GIN (permissions);

-- Trigger para updated_at (se não existir)
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'update_api_keys_updated_at') THEN
        CREATE TRIGGER update_api_keys_updated_at BEFORE UPDATE ON api_keys
            FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
    END IF;
END $$;

-- Comentários
COMMENT ON TABLE api_keys IS 'API Keys do sistema 28Fácil';
COMMENT ON COLUMN api_keys.key_hash IS 'Hash SHA256 da API key completa';
COMMENT ON COLUMN api_keys.key_prefix IS 'Prefixo visível (ex: 28fc_a1b2c3d4)';
COMMENT ON COLUMN api_keys.permissions IS 'Array de permissões: ["read", "write", "delete"]';
COMMENT ON COLUMN api_keys.rate_limit IS 'Requisições permitidas por hora';

-- Tabela de Logs de Uso (OPCIONAL - para auditoria detalhada)
CREATE TABLE IF NOT EXISTS api_key_logs (
    id BIGSERIAL PRIMARY KEY,
    api_key_id BIGINT NOT NULL,
    
    -- Dados da Requisição
    endpoint VARCHAR(500) NOT NULL,
    method http_method NOT NULL,
    ip_address INET NOT NULL,
    user_agent TEXT NULL,
    
    -- Dados da Resposta
    status_code INTEGER NULL,
    response_time_ms INTEGER NULL,
    error_message TEXT NULL,
    
    -- Timestamp
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Adicionar FK se não existir
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'api_key_logs_api_key_id_fkey'
    ) THEN
        ALTER TABLE api_key_logs
        ADD CONSTRAINT api_key_logs_api_key_id_fkey
        FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE;
    END IF;
END $$;

-- Índices para logs (se não existirem)
CREATE INDEX IF NOT EXISTS idx_api_key_logs_api_key_id ON api_key_logs(api_key_id);
CREATE INDEX IF NOT EXISTS idx_api_key_logs_created_at ON api_key_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_api_key_logs_endpoint ON api_key_logs(endpoint);
CREATE INDEX IF NOT EXISTS idx_api_key_logs_status_code ON api_key_logs(status_code);

COMMENT ON TABLE api_key_logs IS 'Logs de uso das API Keys';

-- Inserir exemplo de teste (se não existir)
INSERT INTO api_keys (
    key_hash,
    key_prefix,
    user_id,
    name,
    description,
    permissions,
    rate_limit
) VALUES (
    encode(digest('28fc_example_test_key_do_not_use_in_production', 'sha256'), 'hex'),
    '28fc_example',
    1,
    'Key de Teste',
    'Esta é uma key de exemplo para testes. REMOVER EM PRODUÇÃO!',
    '["read", "write"]'::jsonb,
    500
) ON CONFLICT (key_hash) DO NOTHING;

-- Verificar
SELECT 
    id,
    key_prefix,
    name,
    permissions,
    is_active,
    usage_count,
    created_at
FROM api_keys;
