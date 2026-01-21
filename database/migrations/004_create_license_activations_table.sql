-- Migration: Criar tabela de ativações de licenças
-- Descrição: Ativações de licenças em domínios/instalações

CREATE TABLE IF NOT EXISTS license_activations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    license_id INT NOT NULL,
    license_key VARCHAR(100) UNIQUE NOT NULL COMMENT 'Formato: 28fc_...',
    
    -- Informações da instalação
    domain VARCHAR(255) NOT NULL,
    installation_hash VARCHAR(64) NOT NULL,
    installation_name VARCHAR(255),
    
    -- Informações técnicas
    server_ip VARCHAR(45),
    user_agent TEXT,
    php_version VARCHAR(20),
    app_version VARCHAR(20),
    
    -- Status
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    
    -- Health check
    last_check_at TIMESTAMP NULL,
    last_check_status VARCHAR(50),
    check_count INT DEFAULT 0,
    
    -- Datas
    activated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deactivated_at TIMESTAMP NULL,
    deactivated_reason TEXT,
    
    -- Metadata
    metadata JSON,
    
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
    INDEX idx_license_id (license_id),
    INDEX idx_license_key (license_key),
    INDEX idx_domain (domain),
    INDEX idx_status (status),
    INDEX idx_last_check_at (last_check_at),
    UNIQUE KEY unique_license_domain (license_id, domain, installation_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;