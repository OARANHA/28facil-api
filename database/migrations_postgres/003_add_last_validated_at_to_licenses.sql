-- Migration: Adicionar coluna last_validated_at à tabela licenses
-- Data: 2026-01-25
-- Descrição: Adiciona timestamp para rastrear última validação de licença

-- Adicionar coluna last_validated_at
ALTER TABLE licenses 
ADD COLUMN IF NOT EXISTS last_validated_at TIMESTAMP;

-- Criar índice para melhorar performance em queries que filtram por última validação
CREATE INDEX IF NOT EXISTS idx_licenses_last_validated_at 
ON licenses(last_validated_at);

-- Comentário na coluna
COMMENT ON COLUMN licenses.last_validated_at IS 'Data e hora da última validação da licença via API';