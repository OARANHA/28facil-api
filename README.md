# 28Facil API - Sistema de Licenciamento

![28Facil](public/portal/assets/logo.jpg)

Sistema completo de licenciamento com portal web para gestão de licenças de software.

## 🚀 Features

### Portal Web
- ✅ Cadastro e login de usuários
- ✅ Dashboard para gerenciar licenças
- ✅ Geração de purchase codes
- ✅ Visualização de ativações
- ✅ Painel administrativo
- ✅ Interface responsiva (Tailwind CSS)

### API Backend
- ✅ Autenticação JWT
- ✅ Validação de purchase codes
- ✅ Ativação de licenças
- ✅ Health checks
- ✅ CRUD completo de licenças
- ✅ Sistema de API Keys legado

## 📚 Estrutura do Projeto

```
28facil-api/
├── public/
│   ├── index.php              # Router principal da API
│   └── portal/
│       ├── index.html         # Login/Cadastro
│       ├── dashboard.html     # Dashboard do cliente
│       └── assets/
│           ├── js/
│           │   ├── app.js
│           │   └── dashboard.js
│           ├── logo.jpg
│           └── favicon.ico
├── src/
│   └── Controllers/
│       ├── AuthController.php
│       └── LicenseController.php
├── database/
│   └── migrations/
│       ├── 001_create_api_keys_table.sql
│       ├── 002_create_users_table.sql
│       ├── 003_create_licenses_table.sql
│       └── 004_create_license_activations_table.sql
├── config/
│   └── database.php
└── .env.example
```

## 🛠️ Instalação

### 1. Criar banco de dados

```bash
docker exec -i 28facil-mysql mysql -uroot -p28facil_root_pass <<EOF
CREATE DATABASE IF NOT EXISTS 28facil_api;
EOF
```

### 2. Executar migrations

```bash
# Em ordem:
cat database/migrations/001_create_api_keys_table.sql | \
  docker exec -i 28facil-mysql mysql -uroot -p28facil_root_pass 28facil_api

cat database/migrations/002_create_users_table.sql | \
  docker exec -i 28facil-mysql mysql -uroot -p28facil_root_pass 28facil_api

cat database/migrations/003_create_licenses_table.sql | \
  docker exec -i 28facil-mysql mysql -uroot -p28facil_root_pass 28facil_api

cat database/migrations/004_create_license_activations_table.sql | \
  docker exec -i 28facil-mysql mysql -uroot -p28facil_root_pass 28facil_api
```

### 3. Deploy via Portainer

1. **Stacks → 28facil-api → Pull and redeploy**
2. Aguardar o container reiniciar
3. Acessar: `https://api.28facil.com.br/portal/`

## 👤 Usuário Admin Padrão

Após executar a migration `002_create_users_table.sql`:

- **Email:** `admin@28facil.com.br`
- **Senha:** `Admin@2026`

⚠️ **Altere a senha após o primeiro login!**

## 🔗 Endpoints da API

### Públicos (sem autenticação)

#### Health Check
```bash
GET /health
# ou
GET https://api.28facil.com.br/health
```

#### Registrar Usuário
```bash
POST /api/auth/register
Content-Type: application/json

{
  "name": "João Silva",
  "email": "joao@exemplo.com",
  "password": "senha123"
}
```

#### Login
```bash
POST /api/auth/login
Content-Type: application/json

{
  "email": "joao@exemplo.com",
  "password": "senha123"
}

# Retorna:
{
  "success": true,
  "token": "eyJ...",
  "user": {...}
}
```

### Rotas para Aplicação (AiVoPro)

#### Validar Purchase Code
```bash
POST /api/license/validate
Content-Type: application/json

{
  "purchase_code": "ABCD1234-EFGH5678-IJKL9012-MNOP3456"
}

# Retorna:
{
  "valid": true,
  "license": {
    "id": 1,
    "product": "AiVoPro",
    "type": "lifetime",
    "status": "active",
    "max_activations": 1,
    "active_activations": 0,
    "can_activate": true
  }
}
```

#### Ativar Licença
```bash
POST /api/license/activate
Content-Type: application/json

{
  "purchase_code": "ABCD1234-EFGH5678-IJKL9012-MNOP3456",
  "domain": "meusite.com.br",
  "installation_hash": "sha256_hash_unico_da_instalacao",
  "installation_name": "Produção"
}

# Retorna:
{
  "success": true,
  "activated": true,
  "license_key": "28fc_abc123...",
  "message": "Licença ativada com sucesso"
}
```

#### Check Licença (Health Check)
```bash
GET /api/license/check
X-License-Key: 28fc_abc123...

# Retorna:
{
  "active": true,
  "status": "active",
  "domain": "meusite.com.br",
  "activated_at": "2026-01-21T00:00:00Z",
  "expires_at": null,
  "last_check_at": "2026-01-21T03:40:00Z"
}
```

### Rotas Protegidas (requerem token JWT)

#### Listar Minhas Licenças
```bash
GET /api/licenses
Authorization: Bearer {token}

# Retorna:
{
  "success": true,
  "licenses": [...]
}
```

#### Criar Nova Licença (Admin)
```bash
POST /api/licenses
Authorization: Bearer {token}
Content-Type: application/json

{
  "product_name": "AiVoPro",
  "license_type": "lifetime",
  "max_activations": 1
}
```

#### Detalhes da Licença
```bash
GET /api/licenses/{id}
Authorization: Bearer {token}

# Retorna:
{
  "success": true,
  "license": {
    "id": 1,
    "purchase_code": "...",
    "activations": [...]
  }
}
```

## 💻 Fluxo de Uso na Aplicação AiVoPro

### 1. Tela de License
```php
// Usuário insere o purchase code
$purchaseCode = $_POST['purchase_code'];

// Validar
$response = callAPI('POST', '/api/license/validate', [
    'purchase_code' => $purchaseCode
]);

if ($response['valid'] && $response['license']['can_activate']) {
    // Pode ativar
}
```

### 2. Ativar Licença
```php
$domain = $_SERVER['HTTP_HOST'];
$installationHash = hash('sha256', $domain . getSystemInfo());

$response = callAPI('POST', '/api/license/activate', [
    'purchase_code' => $purchaseCode,
    'domain' => $domain,
    'installation_hash' => $installationHash,
    'installation_name' => 'Produção'
]);

if ($response['activated']) {
    // Salvar license_key localmente
    file_put_contents('.license', $response['license_key']);
}
```

### 3. Verificar Licença (cron diário)
```php
$licenseKey = file_get_contents('.license');

$response = callAPI('GET', '/api/license/check', null, [
    'X-License-Key: ' . $licenseKey
]);

if (!$response['active']) {
    // Licença inválida/expirada
    redirectToLicenseScreen();
}
```

## 🌐 Acessos

- **Portal:** https://api.28facil.com.br/portal/
- **API:** https://api.28facil.com.br/api/
- **Health:** https://api.28facil.com.br/health
- **Docs:** https://api.28facil.com.br/api.json

## 🔐 Segurança

- Senhas: bcrypt hash
- JWT: HS256 (30 dias de validade)
- API Keys: SHA256 hash
- License Keys: formato `28fc_` + 64 chars hex
- Purchase Codes: formato `XXXX-XXXX-XXXX-XXXX`

## 📊 Banco de Dados

### Tabelas

- `users` - Usuários do portal
- `licenses` - Licenças (purchase codes)
- `license_activations` - Ativações em domínios
- `api_keys` - API Keys (sistema legado)

## 👨‍💻 Desenvolvimento

### Adicionar nova rota

1. Editar `public/index.php`
2. Adicionar case no switch/router
3. Criar método no controller apropriado

### Testar localmente

```bash
php -S localhost:8000 -t public/
```

## 📦 Deploy

### Via Git
```bash
git pull origin main
docker exec 28facil-api git pull
docker restart 28facil-api
```

### Via Portainer
1. Stacks → 28facil-api
2. Pull and redeploy

## 🐛 Troubleshooting

### Erro de conexão com banco
```bash
# Verificar se o MySQL está rodando
docker ps | grep mysql

# Testar conexão
docker exec -it 28facil-mysql mysql -uroot -p28facil_root_pass -e "SHOW DATABASES;"
```

### Portal não carrega
```bash
# Verificar logs do container
docker logs 28facil-api -f

# Testar API
curl https://api.28facil.com.br/health
```

### Licença não ativa
1. Verificar purchase code válido
2. Verificar limite de ativações
3. Checar status da licença no dashboard

## 📝 TODO

- [ ] Sistema de pagamento integrado
- [ ] Emails de notificação
- [ ] Renovação automática de licenças
- [ ] Relatórios e analytics
- [ ] Webhook para eventos de licença
- [ ] Suporte a multiple products
- [ ] Sistema de descontos/cupons

## 💬 Suporte

- **Email:** admin@28facil.com.br
- **Docs:** https://api.28facil.com.br/api.json
- **GitHub:** https://github.com/OARANHA/28facil-api

---

**Made with ❤️ by 28Facil Team**

© 2026 AiVoPro. All rights reserved.