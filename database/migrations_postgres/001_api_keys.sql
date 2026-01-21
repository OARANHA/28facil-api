-- =====================================================
-- MIGRAÇÃO: Sistema de API Keys - 28Fácil
-- Compatível com PostgreSQL 16+
-- Convertido de MySQL para PostgreSQL
-- =====================================================

-- Criar tipos ENUM personalizados
CREATE TYPE api_key_status AS ENUM ('active', 'revoked');
CREATE TYPE http_method AS ENUM ('GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD');

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

-- Índices otimizados
CREATE INDEX idx_api_keys_key_hash ON api_keys(key_hash);
CREATE INDEX idx_api_keys_user_id ON api_keys(user_id);
CREATE INDEX idx_api_keys_is_active ON api_keys(is_active);
CREATE INDEX idx_api_keys_expires_at ON api_keys(expires_at);
CREATE INDEX idx_api_keys_created_at ON api_keys(created_at DESC);

-- Índice GIN para busca JSON
CREATE INDEX idx_api_keys_permissions_gin ON api_keys USING GIN (permissions);

-- Comentários
COMMENT ON TABLE api_keys IS 'API Keys do sistema 28Fácil';
COMMENT ON COLUMN api_keys.key_hash IS 'Hash SHA256 da API key completa';
COMMENT ON COLUMN api_keys.key_prefix IS 'Prefixo visível (ex: 28fc_a1b2c3d4)';
COMMENT ON COLUMN api_keys.permissions IS 'Array de permissões: ["read", "write", "delete"]';
COMMENT ON COLUMN api_keys.rate_limit IS 'Requisições permitidas por hora';

-- Trigger para atualizar updated_at automaticamente
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_api_keys_updated_at BEFORE UPDATE ON api_keys
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

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
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Chave estrangeira
    FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE
);

-- Índices para logs
CREATE INDEX idx_api_key_logs_api_key_id ON api_key_logs(api_key_id);
CREATE INDEX idx_api_key_logs_created_at ON api_key_logs(created_at DESC);
CREATE INDEX idx_api_key_logs_endpoint ON api_key_logs(endpoint);
CREATE INDEX idx_api_key_logs_status_code ON api_key_logs(status_code);

COMMENT ON TABLE api_key_logs IS 'Logs de uso das API Keys';

-- Inserir exemplo de teste
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

-- =====================================================
-- ROW-LEVEL SECURITY (RLS) - Preparado para Multi-Tenant
-- =====================================================
-- Descomentar quando necessário:

-- ALTER TABLE api_keys ENABLE ROW LEVEL SECURITY;

-- CREATE POLICY api_keys_isolation ON api_keys
--     USING (user_id = current_setting('app.current_user_id')::bigint);

-- CREATE POLICY api_keys_admin_all ON api_keys
--     USING (current_setting('app.user_role', true) = 'admin');
