-- =====================================================
-- MIGRAÇÃO: Tabela de Licenças - 28Fácil
-- Compatível com PostgreSQL 16+
-- =====================================================

-- Criar tipos ENUM
CREATE TYPE license_type AS ENUM ('lifetime', 'annual', 'monthly', 'trial');
CREATE TYPE license_status AS ENUM ('active', 'expired', 'suspended', 'revoked');

-- Tabela de licenças
CREATE TABLE IF NOT EXISTS licenses (
    id SERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    user_id INTEGER NOT NULL,
    purchase_code VARCHAR(100) UNIQUE NOT NULL,
    product_name VARCHAR(100) DEFAULT 'AiVoPro',
    product_version VARCHAR(20) DEFAULT '1.0',
    license_type license_type DEFAULT 'lifetime',
    status license_status DEFAULT 'active',
    max_activations INTEGER DEFAULT 1,
    price_paid DECIMAL(10,2) DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'BRL',
    
    -- Metadados (JSONB para melhor performance)
    metadata JSONB DEFAULT '{}'::jsonb,
    notes TEXT,
    
    -- Datas
    activated_at TIMESTAMP WITH TIME ZONE NULL,
    expires_at TIMESTAMP WITH TIME ZONE NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP WITH TIME ZONE NULL,
    revoked_reason TEXT,
    
    -- Chave estrangeira
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Índices
CREATE INDEX idx_licenses_user_id ON licenses(user_id);
CREATE INDEX idx_licenses_purchase_code ON licenses(purchase_code);
CREATE INDEX idx_licenses_uuid ON licenses(uuid);
CREATE INDEX idx_licenses_status ON licenses(status);
CREATE INDEX idx_licenses_license_type ON licenses(license_type);
CREATE INDEX idx_licenses_created_at ON licenses(created_at DESC);
CREATE INDEX idx_licenses_expires_at ON licenses(expires_at);

-- Índice GIN para busca JSONB
CREATE INDEX idx_licenses_metadata_gin ON licenses USING GIN (metadata);

-- Trigger para updated_at
CREATE TRIGGER update_licenses_updated_at BEFORE UPDATE ON licenses
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Comentários
COMMENT ON TABLE licenses IS 'Licenças (purchase codes) geradas para clientes';
COMMENT ON COLUMN licenses.metadata IS 'Metadados em formato JSONB para queries rápidas';
COMMENT ON COLUMN licenses.purchase_code IS 'Código de compra único';

-- Full-text search (opcional)
CREATE INDEX idx_licenses_fulltext ON licenses 
USING GIN (to_tsvector('portuguese', 
    COALESCE(product_name, '') || ' ' || 
    COALESCE(notes, '') || ' ' || 
    COALESCE(purchase_code, '')
));
