-- =====================================================
-- MIGRAÇÃO: Tabela de Usuários - 28Fácil
-- Compatível com PostgreSQL 16+
-- =====================================================

-- Criar tipos ENUM
CREATE TYPE user_role AS ENUM ('admin', 'customer');
CREATE TYPE user_status AS ENUM ('active', 'inactive', 'suspended');

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

-- Índices
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_created_at ON users(created_at DESC);
CREATE INDEX idx_users_company ON users(company);

-- Trigger para updated_at
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Comentários
COMMENT ON TABLE users IS 'Usuários do portal de licenciamento 28Fácil';
COMMENT ON COLUMN users.email IS 'Email único do usuário';
COMMENT ON COLUMN users.password_hash IS 'Hash bcrypt da senha';
COMMENT ON COLUMN users.phone IS 'Telefone de contato';
COMMENT ON COLUMN users.company IS 'Empresa/Razão social do cliente';

-- Criar usuário admin padrão
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
