-- =====================================================
-- MIGRAÇÃO: Adicionar campos phone e company na tabela users
-- Compatível com PostgreSQL 16+
-- =====================================================

-- Adicionar campos se não existirem
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL,
ADD COLUMN IF NOT EXISTS company VARCHAR(255) NULL;

-- Criar índice para company
CREATE INDEX IF NOT EXISTS idx_users_company ON users(company);

-- Comentários
COMMENT ON COLUMN users.phone IS 'Telefone de contato';
COMMENT ON COLUMN users.company IS 'Empresa/Razão social do cliente';

-- Verificar
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'users' 
AND column_name IN ('phone', 'company');
