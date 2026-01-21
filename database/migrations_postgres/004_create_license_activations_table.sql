-- =====================================================
-- MIGRAÇÃO: Tabela de Ativações de Licenças - 28Fácil
-- Compatível com PostgreSQL 16+
-- =====================================================

-- Criar tipo ENUM
CREATE TYPE activation_status AS ENUM ('active', 'inactive', 'suspended');

-- Tabela de ativações
CREATE TABLE IF NOT EXISTS license_activations (
    id SERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    license_id INTEGER NOT NULL,
    license_key VARCHAR(100) UNIQUE NOT NULL,
    
    -- Informações da instalação
    domain VARCHAR(255) NOT NULL,
    installation_hash VARCHAR(64) NOT NULL,
    installation_name VARCHAR(255),
    
    -- Informações técnicas
    server_ip INET NULL,
    user_agent TEXT,
    php_version VARCHAR(20),
    app_version VARCHAR(20),
    
    -- Status
    status activation_status DEFAULT 'active',
    
    -- Health check
    last_check_at TIMESTAMP WITH TIME ZONE NULL,
    last_check_status VARCHAR(50),
    check_count INTEGER DEFAULT 0,
    
    -- Datas
    activated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    deactivated_at TIMESTAMP WITH TIME ZONE NULL,
    deactivated_reason TEXT,
    
    -- Metadata (JSONB)
    metadata JSONB DEFAULT '{}'::jsonb,
    
    -- Chave estrangeira
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
    
    -- Constraint de unicidade
    CONSTRAINT unique_license_domain UNIQUE (license_id, domain, installation_hash)
);

-- Índices
CREATE INDEX idx_license_activations_license_id ON license_activations(license_id);
CREATE INDEX idx_license_activations_license_key ON license_activations(license_key);
CREATE INDEX idx_license_activations_domain ON license_activations(domain);
CREATE INDEX idx_license_activations_status ON license_activations(status);
CREATE INDEX idx_license_activations_last_check_at ON license_activations(last_check_at DESC);
CREATE INDEX idx_license_activations_activated_at ON license_activations(activated_at DESC);

-- Índice GIN para metadata
CREATE INDEX idx_license_activations_metadata_gin ON license_activations USING GIN (metadata);

-- Comentários
COMMENT ON TABLE license_activations IS 'Ativações de licenças em domínios/instalações';
COMMENT ON COLUMN license_activations.license_key IS 'Formato: 28fc_...';
COMMENT ON COLUMN license_activations.installation_hash IS 'Hash único da instalação';
COMMENT ON COLUMN license_activations.server_ip IS 'IP do servidor (tipo INET)';
