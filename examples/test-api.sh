#!/bin/bash

# =====================================================
# Exemplos de Uso da API 28Facil
# =====================================================

API_URL="${API_URL:-https://api.28facil.com.br}"
API_KEY="${API_KEY:-}"  # Definir sua key aqui ou via env

echo ""
echo "🧪 Testando API 28Facil"
echo "================================"
echo ""

# Health Check
echo "1. Health Check:"
echo "GET $API_URL/"
echo ""
curl -s "$API_URL/" | jq .
echo ""
echo ""

# Health Detalhado
echo "2. Health Detalhado:"
echo "GET $API_URL/health"
echo ""
curl -s "$API_URL/health" | jq .
echo ""
echo ""

# OpenAPI Spec
echo "3. OpenAPI Specification:"
echo "GET $API_URL/api.json"
echo ""
curl -s "$API_URL/api.json" | jq -r '.info'
echo ""
echo ""

# Validar API Key (sem key)
echo "4. Validar sem API Key (deve falhar):"
echo "GET $API_URL/auth/validate"
echo ""
curl -s "$API_URL/auth/validate" | jq .
echo ""
echo ""

# Validar API Key (com key)
if [ -n "$API_KEY" ]; then
    echo "5. Validar com API Key:"
    echo "GET $API_URL/auth/validate"
    echo "Header: X-API-Key: $API_KEY"
    echo ""
    curl -s -H "X-API-Key: $API_KEY" "$API_URL/auth/validate" | jq .
    echo ""
else
    echo "5. Validar com API Key: PULADO (defina API_KEY env var)"
    echo ""
fi

echo "================================"
echo "✅ Testes concluídos!"
echo ""
