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
  "timestamp": "2026-01-20T04:00:00-03:00"
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
  "permissions": ["read", "write"],
  "rate_limit": 1000,
  "usage_count": 42
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

### Gerar Nova Key (via PHP)

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

### Validar Key

```php
$result = ApiKeyManager::validate('28fc_sua_key_aqui');

if ($result) {
    echo "Key válida! Usuário: " . $result['user_id'];
} else {
    echo "Key inválida ou expirada";
}
```

### Revogar Key

```php
ApiKeyManager::revoke(
    keyId: 5,
    reason: 'Comprometida após vazamento'
);
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

### Rate Limiting

- Padrão: 1000 req/hora
- Configurável por key
- HTTP 429 quando excedido

### Headers de Segurança

```
X-API-Key: 28fc_sua_key_aqui
X-Request-ID: uuid-v4
User-Agent: MyApp/1.0
```

---

## 🛠️ Desenvolvimento

### Requisitos

- PHP 8.1+
- MySQL 8.0+
- Composer
- Docker (opcional)

### Instalação Local

```bash
# Clonar
git clone https://github.com/OARANHA/28facil-api.git
cd 28facil-api

# Instalar dependências
composer install

# Configurar .env
cp .env.example .env
nano .env

# Migrar banco
php artisan migrate
# OU
mysql -u root -p 28facil_api < database/migrations/001_api_keys.sql

# Iniciar servidor local
php -S localhost:8000 -t public
```

### Rodar com Docker

Veja o repositório [28facil-infra](https://github.com/OARANHA/28facil-infra) para deploy completo com Traefik e SSL.

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

### Exemplos de Integração

Veja `examples/` para:
- PHP (Laravel, Slim, Native)
- JavaScript (Node.js/Express)
- Python (Flask, FastAPI)
- cURL

---

## 🔗 Repositórios Relacionados

- **[28facil-infra](https://github.com/OARANHA/28facil-infra)**: Infraestrutura Docker + Traefik
- **[aivopro-integrity](https://github.com/OARANHA/aivopro-integrity)**: Cliente PHP de monitoramento

---

## 📝 Licença

MIT License - veja [LICENSE](LICENSE) para detalhes.

---

**Desenvolvido com ❤️ pela equipe 28Fácil**
