#!/bin/bash

###############################################################################
# Script de Migração de Dados: MySQL → PostgreSQL
# 
# Este script usa pgloader para migrar dados do MySQL para PostgreSQL
# 
# Uso:
#   ./scripts/migrate_data.sh
# 
# Pré-requisitos:
#   - Docker (ou pgloader instalado localmente)
#   - MySQL rodando com dados
#   - PostgreSQL rodando e vazio (apenas com schema)
###############################################################################

set -e  # Exit on error

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}===========================================" 
echo -e "  28Fácil - Migração MySQL → PostgreSQL"
echo -e "===========================================${NC}"
echo ""

# Ler variáveis do .env
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
else
    echo -e "${RED}Erro: Arquivo .env não encontrado!${NC}"
    exit 1
fi

# Configurações padrão
MYSQL_HOST=${MYSQL_HOST:-mysql}
MYSQL_PORT=${MYSQL_PORT:-3306}
MYSQL_USER=${MYSQL_USER:-28facil}
MYSQL_PASS=${MYSQL_PASS:-senha_forte_123}
MYSQL_DB=${MYSQL_DB:-28facil_api}

PG_HOST=${DB_HOST:-postgres}
PG_PORT=${DB_PORT:-5432}
PG_USER=${DB_USERNAME:-28facil}
PG_PASS=${DB_PASSWORD:-senha_forte_123}
PG_DB=${DB_DATABASE:-28facil_api}

echo -e "${YELLOW}Configurações:${NC}"
echo "  MySQL:      ${MYSQL_HOST}:${MYSQL_PORT}/${MYSQL_DB}"
echo "  PostgreSQL: ${PG_HOST}:${PG_PORT}/${PG_DB}"
echo ""

# Verificar se pgloader está disponível
if ! command -v pgloader &> /dev/null; then
    echo -e "${YELLOW}pgloader não encontrado. Usando via Docker...${NC}"
    USE_DOCKER=true
else
    USE_DOCKER=false
fi

# Criar arquivo de configuração do pgloader
cat > /tmp/pgloader_28facil.load <<EOF
LOAD DATABASE
    FROM mysql://${MYSQL_USER}:${MYSQL_PASS}@${MYSQL_HOST}:${MYSQL_PORT}/${MYSQL_DB}
    INTO pgsql://${PG_USER}:${PG_PASS}@${PG_HOST}:${PG_PORT}/${PG_DB}

WITH 
    include drop,
    create tables,
    create indexes,
    reset sequences,
    workers = 4,
    concurrency = 1,
    batch rows = 1000

CAST
    type datetime to timestamptz drop default drop not null using zero-dates-to-null,
    type date to date drop not null drop default using zero-dates-to-null,
    type tinyint to boolean using tinyint-to-boolean,
    type json to jsonb drop typemod

ALTER TABLE NAMES MATCHING ~/_/ RENAME TO ~/-/

ALTER SCHEMA '${MYSQL_DB}' RENAME TO 'public'

-- Excluir tabelas que já existem com schema diferente no PostgreSQL
-- EXCLUDING TABLE NAMES MATCHING 'migrations'

BEFORE LOAD DO
    \$\$ DROP SCHEMA IF EXISTS public CASCADE; \$\$,
    \$\$ CREATE SCHEMA public; \$\$;

AFTER LOAD DO
    \$\$ ALTER TABLE api_keys ADD CONSTRAINT api_keys_key_hash_unique UNIQUE (key_hash); \$\$,
    \$\$ ALTER TABLE users ADD CONSTRAINT users_email_unique UNIQUE (email); \$\$,
    \$\$ ALTER TABLE licenses ADD CONSTRAINT licenses_uuid_unique UNIQUE (uuid); \$\$,
    \$\$ ALTER TABLE licenses ADD CONSTRAINT licenses_purchase_code_unique UNIQUE (purchase_code); \$\$,
    \$\$ ALTER TABLE license_activations ADD CONSTRAINT license_activations_uuid_unique UNIQUE (uuid); \$\$,
    \$\$ ALTER TABLE license_activations ADD CONSTRAINT license_activations_license_key_unique UNIQUE (license_key); \$\$;
EOF

echo -e "${GREEN}Arquivo de configuração criado: /tmp/pgloader_28facil.load${NC}"
echo ""

# Fazer backup antes da migração
echo -e "${YELLOW}Fazendo backup do MySQL...${NC}"
docker-compose exec -T mysql mysqldump \
    -u ${MYSQL_USER} \
    -p${MYSQL_PASS} \
    ${MYSQL_DB} > /tmp/backup_mysql_$(date +%Y%m%d_%H%M%S).sql

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✔ Backup criado com sucesso!${NC}"
else
    echo -e "${RED}✘ Erro ao criar backup!${NC}"
    exit 1
fi

echo ""

# Executar pgloader
echo -e "${YELLOW}Iniciando migração...${NC}"
echo ""

if [ "$USE_DOCKER" = true ]; then
    docker run --rm \
        --network host \
        -v /tmp:/tmp \
        dimitri/pgloader:latest \
        pgloader /tmp/pgloader_28facil.load
else
    pgloader /tmp/pgloader_28facil.load
fi

if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}===========================================" 
    echo -e "  ✔ Migração concluída com sucesso!"
    echo -e "===========================================${NC}"
    echo ""
    
    # Validar dados
    echo -e "${YELLOW}Validando dados migrados...${NC}"
    echo ""
    
    docker-compose -f docker-compose.postgres.yml exec -T postgres \
        psql -U ${PG_USER} -d ${PG_DB} -c "
        SELECT 
            'users' as table_name, COUNT(*) as count FROM users
        UNION ALL
        SELECT 
            'licenses' as table_name, COUNT(*) as count FROM licenses
        UNION ALL
        SELECT 
            'api_keys' as table_name, COUNT(*) as count FROM api_keys
        UNION ALL
        SELECT 
            'license_activations' as table_name, COUNT(*) as count FROM license_activations;
    "
    
    echo ""
    echo -e "${GREEN}✅ Próximos passos:${NC}"
    echo "  1. Verificar counts acima com o MySQL"
    echo "  2. Testar endpoints da API"
    echo "  3. Executar testes automatizados"
    echo "  4. Atualizar .env para usar PostgreSQL"
    echo ""
else
    echo ""
    echo -e "${RED}===========================================" 
    echo -e "  ✘ Erro durante a migração!"
    echo -e "===========================================${NC}"
    echo ""
    echo -e "${YELLOW}Verifique os logs acima e tente novamente.${NC}"
    echo ""
    exit 1
fi

# Limpar arquivo temporário
rm -f /tmp/pgloader_28facil.load

echo -e "${GREEN}Concluído!${NC}"
