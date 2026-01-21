#!/bin/bash

echo "====================================="
echo "28Facil API - Teste de Endpoints"
echo "====================================="
echo ""

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# URL base
BASE_URL="https://api.28facil.com.br"

echo "${YELLOW}1. Testando /health (deve retornar 200)${NC}"
curl -s -o /dev/null -w "Status: %{http_code}\n" $BASE_URL/health
echo ""

echo "${YELLOW}2. Testando /api.json (deve retornar 200)${NC}"
curl -s -o /dev/null -w "Status: %{http_code}\n" $BASE_URL/api.json
echo ""

echo "${YELLOW}3. Conte\u00fado do /api.json:${NC}"
curl -s $BASE_URL/api.json | head -n 20
echo ""
echo "..."
echo ""

echo "${YELLOW}4. Headers do /api.json:${NC}"
curl -I -s $BASE_URL/api.json | head -n 10
echo ""

echo "${YELLOW}5. Teste direto no container (localhost):${NC}"
docker exec 28facil-api curl -s -o /dev/null -w "Status: %{http_code}\n" http://localhost/api.json
echo ""

echo "${YELLOW}6. Verificar arquivo api.json existe:${NC}"
docker exec 28facil-api ls -lh /var/www/html/api.json 2>/dev/null || echo "${RED}Arquivo n\u00e3o encontrado!${NC}"
echo ""

echo "====================================="
echo "Teste conclu\u00eddo!"
echo "====================================="
