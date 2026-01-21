#!/bin/bash

# Script para rodar migrations manualmente no PostgreSQL
# Uso: ./scripts/run-migrations.sh [numero_da_migration]

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}===========================================" 
echo -e "28Facil - Migration Runner"
echo -e "===========================================${NC}\n"

# Verificar se o container está rodando
if ! docker ps | grep -q "28facil-postgres"; then
    echo -e "${RED}❌ Container 28facil-postgres não está rodando!${NC}"
    exit 1
fi

# Se passar número da migration como argumento
if [ $# -eq 1 ]; then
    MIGRATION_FILE="$1"
    
    if [ ! -f "database/migrations_postgres/$MIGRATION_FILE" ]; then
        echo -e "${RED}❌ Migration não encontrada: $MIGRATION_FILE${NC}"
        exit 1
    fi
    
    echo -e "${YELLOW}Rodando migration: $MIGRATION_FILE${NC}"
    
    docker exec -i 28facil-postgres psql -U 28facil -d 28facil_api < "database/migrations_postgres/$MIGRATION_FILE"
    
    echo -e "${GREEN}✓ Migration executada com sucesso!${NC}"
else
    # Rodar todas as migrations na ordem
    echo -e "${YELLOW}Rodando todas as migrations...${NC}\n"
    
    for migration in database/migrations_postgres/*.sql; do
        filename=$(basename "$migration")
        echo -e "${YELLOW}➤ Executando: $filename${NC}"
        
        if docker exec -i 28facil-postgres psql -U 28facil -d 28facil_api < "$migration" 2>&1 | grep -v "already exists" | grep -v "NOTICE"; then
            echo -e "${GREEN}  ✓ $filename concluída${NC}"
        else
            echo -e "${GREEN}  ✓ $filename já executada${NC}"
        fi
        echo ""
    done
    
    echo -e "${GREEN}✓ Todas as migrations foram processadas!${NC}"
fi

echo -e "\n${GREEN}===========================================" 
echo -e "Estrutura do banco:"
echo -e "===========================================${NC}"

# Mostrar tabelas
docker exec -it 28facil-postgres psql -U 28facil -d 28facil_api -c "\dt"

echo -e "\n${GREEN}Pronto!${NC}"
