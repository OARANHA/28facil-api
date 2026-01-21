-- =====================================================
-- MIGRAÇÃO: Tabela de Usuários - 28Fácil
-- Compatível com PostgreSQL 16+
-- IDEMPOTENTE: Pode ser executada múltiplas vezes
-- =====================================================

-- Criar tipos ENUM (se não existirem)
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_role') THEN
        CREATE TYPE user_role AS ENUM ('admin', 'customer');
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_status') THEN
        CREATE TYPE user_status AS ENUM ('active', 'inactive', 'suspended');
    END IF;
END $$;

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NULL,
    company VARCHAR(255) NULL,
    role user_role DEFAULT 'customer',
    status user_status DEFAULT 'active',
    email_verified_at TIMESTAMP WITH TIME ZONE NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP WITH TIME ZONE NULL
);

-- Índices (se não existirem)
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_users_company ON users(company);

-- Trigger para updated_at (se não existir)
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'update_users_updated_at') THEN
        CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
            FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
    END IF;
END $$;

-- Comentários
COMMENT ON TABLE users IS 'Usuários do portal de licenciamento 28Fácil';
COMMENT ON COLUMN users.email IS 'Email único do usuário';
COMMENT ON COLUMN users.password_hash IS 'Hash bcrypt da senha';
COMMENT ON COLUMN users.phone IS 'Telefone de contato';
COMMENT ON COLUMN users.company IS 'Empresa/Razão social do cliente';

-- Criar usuário admin padrão (se não existir)
-- Senha: admin123
-- Hash gerado com: password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO users (name, email, password_hash, role, email_verified_at) 
VALUES (
    '28Facil Admin',
    'admin@28facil.com.br',
    '$2y$10$opCfKFr3M2tBC1RtnNSiOe.5/Ro9A1z2V2thSFEmX3WP2f.JzMN0S',
    'admin',
    CURRENT_TIMESTAMP
) ON CONFLICT (email) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    updated_at = CURRENT_TIMESTAMP;

-- Verificar
SELECT id, name, email, phone, company, role, status, created_at FROM users;
