# 🚀 28Facil API

API REST do sistema 28Facil - Autenticação, validação e gerenciamento de API Keys.

---

## 🎯 Funcionalidades

- **Autenticação via API Keys**: Sistema robusto de API Keys com hash SHA256
- **Rate Limiting**: Controle de taxa de requisições por hora
- **Permissões Granulares**: `read`, `write`, `delete`
- **Auditoria**: Logs de uso e histórico completo
- **Health Checks**: Endpoints de saúde e versão
- **OpenAPI 3.0**: Documentação completa em `/api.json`
- **Scripts CLI**: Gerenciamento fácil de API Keys via linha de comando

---

## 📑 Endpoints Principais

### Health Check
```bash
GET /
GET /health
```

**Resposta:**
```json
{
  "status": "success",
  "message": "28Facil API Server is running!",
  "version": "1.0.0",
  "timestamp": "2026-01-20T04:00:00-03:00",
  "database": {
    "status": "connected",
    "host": "mysql",
    "database": "28facil_api"
  }
}
```

### Validar API Key
```bash
GET /auth/validate
Header: X-API-Key: 28fc_sua_key_aqui
```

**Resposta (sucesso):**
```json
{
  "valid": true,
  "user_id": 1,
  "name": "Minha API Key",
  "prefix": "28fc_a1b2c3d4",
  "permissions": ["read", "write"],
  "rate_limit": 1000,
  "usage_count": 42,
  "last_used_at": "2026-01-20T04:30:00-03:00"
}
```

**Resposta (inválida):**
```json
{
  "valid": false,
  "error": "Invalid or expired API key"
}
```

### OpenAPI Specification
```bash
GET /api.json
```

Retorna a documentação completa da API em formato OpenAPI 3.0.

---

## 🔑 Sistema de API Keys

### Formato
```
28fc_[48 caracteres hexadecimais]

Exemplo:
28fc_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6
```

### Gerar Nova Key (via CLI)

```bash
# Gerar key simples
php scripts/generate-key.php "Minha Primeira Key"

# Gerar key com permissões personalizadas
php scripts/generate-key.php "Key Admin" 1 "read,write,delete" 5000

# Parâmetros:
# 1. Nome da key (obrigatório)
# 2. User ID (opcional, padrão: null)
# 3. Permissões separadas por vírgula (opcional, padrão: "read")
# 4. Rate limit (opcional, padrão: 1000)
```

**Saída:**
```
🔑 Gerando nova API Key...

✅ API Key criada com sucesso!

ID:          1
Nome:        Minha Primeira Key
Usuário ID:  N/A
Permissões:  read
Rate Limit:  1000 req/hora
Prefixo:     28fc_a1b2c3d4

⚠️  API KEY (guarde em local seguro!): 

    28fc_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6

⚠️  Esta key NÃO será mostrada novamente!
```

### Listar Keys

```bash
# Listar todas as keys ativas
php scripts/list-keys.php

# Listar keys de um usuário específico
php scripts/list-keys.php 1

# Listar todas as keys (incluindo inativas)
php scripts/list-keys.php --all

# Listar keys de um usuário incluindo inativas
php scripts/list-keys.php 1 --all
```

**Saída:**
```
🔑 API Keys - 28Facil
================================

ID    Prefixo         Nome                      User     Usos       Status   Criada    
------------------------------------------------------------------------------------------
1     28fc_a1b2c3d4   Minha Primeira Key        N/A      0          ✅ Ativa  20/01/2026
2     28fc_x9y8z7w6   Key Admin                 1        142        ✅ Ativa  20/01/2026

Total: 2 key(s)
```

### Revogar Key

```bash
# Revogar key por ID
php scripts/revoke-key.php 5

# Revogar com motivo
php scripts/revoke-key.php 5 "Chave comprometida após vazamento"
```

**Saída:**
```
⚠️  Revogar API Key
================================

ID:      5
Nome:    Key Comprometida
Prefixo: 28fc_old_key1
Motivo:  Chave comprometida após vazamento

Tem certeza? (s/N): s

✅ API Key revogada com sucesso!
```

### Gerar Key (via PHP/API KeyManager)

```php
use App\Services\ApiKeyManager;

$key = ApiKeyManager::generate(
    name: 'Integração WhatsApp',
    userId: 1,
    permissions: ['read', 'write'],
    rateLimit: 1000
);

echo "Nova API Key: " . $key['key'];
// Guardar em local seguro! NÃO será mostrada novamente.
```

### Validar Key (via PHP)

```php
$result = ApiKeyManager::validate('28fc_sua_key_aqui');

if ($result) {
    echo "Key válida! Usuário: " . $result['user_id'];
} else {
    echo "Key inválida ou expirada";
}
```

### Revogar Key (via PHP)

```php
ApiKeyManager::revoke(
    keyId: 5,
    reason: 'Comprometida após vazamento'
);
```

---

## 🧪 Testar a API

### Via Script Bash

```bash
# Testar todos os endpoints
chmod +x examples/test-api.sh
./examples/test-api.sh

# Testar com sua API Key
API_KEY="28fc_sua_key_aqui" ./examples/test-api.sh

# Testar em ambiente local
API_URL="http://localhost:8000" ./examples/test-api.sh
```

### Via cURL

```bash
# Health check
curl https://api.28facil.com.br/health | jq .

# Validar API Key
curl -H "X-API-Key: 28fc_sua_key_aqui" \
     https://api.28facil.com.br/auth/validate | jq .

# Ver OpenAPI spec
curl https://api.28facil.com.br/api.json | jq -r '.info'
```

---

## 🗃️ Banco de Dados

### Estrutura

**Tabela: `api_keys`**
- `id`: ID único
- `key_hash`: Hash SHA256 da key completa (nunca armazenar em texto plano!)
- `key_prefix`: Prefixo visível (ex: `28fc_a1b2c3d4`)
- `user_id`: ID do dono da key
- `name`: Nome descritivo
- `permissions`: JSON array de permissões
- `rate_limit`: Requisições/hora
- `is_active`: Status ativo/revogado
- `expires_at`: Data de expiração (NULL = nunca)
- `usage_count`: Total de usos
- `last_used_at`: Último uso
- `last_ip`: Último IP
- `revoked_at`: Quando foi revogada
- `revoked_reason`: Motivo da revogação

**Tabela: `api_key_logs`** (opcional)
- Auditoria detalhada de todas as requisições
- Endpoint, método, IP, status, tempo de resposta

### Migração

```bash
# Via MySQL
mysql -u root -p 28facil_api < database/migrations/001_api_keys.sql

# Ou via Docker
docker compose exec mysql mysql -u root -p 28facil_api < database/migrations/001_api_keys.sql
```

---

## 🛡️ Segurança

### Boas Práticas

1. **NUNCA** armazene API Keys em texto plano no banco
2. **SEMPRE** use HTTPS em produção
3. **Rote** keys regularmente
4. **Monitore** uso suspeito via logs
5. **Revogue** imediatamente keys comprometidas
6. **Use** permissões granulares (princípio do menor privilégio)
7. **Nunca** commite keys no git ou código-fonte

### Rate Limiting

- Padrão: 1000 req/hora
- Configurável por key
- HTTP 429 quando excedido

### Headers de Segurança

```
X-API-Key: 28fc_sua_key_aqui
X-Request-ID: uuid-v4 (opcional)
User-Agent: MyApp/1.0
```

---

## 🛠️ Desenvolvimento

### Requisitos

- PHP 8.1+
- MySQL 8.0+
- Apache/Nginx com mod_rewrite
- Docker (opcional)

### Instalação Local

```bash
# Clonar
git clone https://github.com/OARANHA/28facil-api.git
cd 28facil-api

# Configurar .env
cp .env.example .env
nano .env

# Criar banco e migrar
mysql -u root -p -e "CREATE DATABASE 28facil_api;"
mysql -u root -p 28facil_api < database/migrations/001_api_keys.sql

# Iniciar servidor local
php -S localhost:8000 -t public

# Ou com Docker (veja 28facil-infra)
```

### Estrutura de Arquivos

```
28facil-api/
├── public/
│   ├── index.php           # Servidor principal
│   └── .htaccess           # Rewrites Apache
├── config/
│   └── database.php        # Conexão MySQL
├── middleware/
│   └── auth.php            # Autenticação
├── src/
│   └── Services/
│       └── ApiKeyManager.php # Gerenciador de keys
├── database/
│   └── migrations/
│       └── 001_api_keys.sql # Schema do banco
├── scripts/
│   ├── generate-key.php    # CLI: gerar keys
│   ├── list-keys.php       # CLI: listar keys
│   └── revoke-key.php      # CLI: revogar keys
├── examples/
│   └── test-api.sh         # Testes de integração
├── api.json                # OpenAPI 3.0 spec
└── README.md               # Este arquivo
```

---

## 📚 Documentação

### OpenAPI/Swagger

Acesse `/api.json` para a especificação completa.

Visualize com Swagger UI:
```bash
docker run -p 8080:8080 \
  -e SWAGGER_JSON=/api.json \
  -v $(pwd)/api.json:/api.json \
  swaggerapi/swagger-ui
```

Acesse: http://localhost:8080

---

## 🔗 Repositórios Relacionados

- **[28facil-infra](https://github.com/OARANHA/28facil-infra)**: Infraestrutura Docker + Traefik + SSL
- **[aivopro-integrity](https://github.com/OARANHA/aivopro-integrity)**: Cliente PHP de monitoramento

---

## 📝 Licença

MIT License - veja [LICENSE](LICENSE) para detalhes.

---

**Desenvolvido com ❤️ pela equipe 28Fácil**
