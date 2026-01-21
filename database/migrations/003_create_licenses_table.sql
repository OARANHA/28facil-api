-- Migration: Criar tabela de licenças
-- Descrição: Licenças (purchase codes) geradas para clientes

CREATE TABLE IF NOT EXISTS licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    purchase_code VARCHAR(100) UNIQUE NOT NULL,
    product_name VARCHAR(100) DEFAULT 'AiVoPro',
    product_version VARCHAR(20) DEFAULT '1.0',
    license_type ENUM('lifetime', 'annual', 'monthly', 'trial') DEFAULT 'lifetime',
    status ENUM('active', 'expired', 'suspended', 'revoked') DEFAULT 'active',
    max_activations INT DEFAULT 1,
    price_paid DECIMAL(10,2) DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'BRL',
    
    -- Metadados
    metadata JSON,
    notes TEXT,
    
    -- Datas
    activated_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP NULL,
    revoked_reason TEXT,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_purchase_code (purchase_code),
    INDEX idx_uuid (uuid),
    INDEX idx_status (status),
    INDEX idx_license_type (license_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;