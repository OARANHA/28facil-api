# 🐛 Troubleshooting de Login - 28Facil API

Guia completo para diagnosticar e resolver problemas de autenticação no portal administrativo.

---

## 🚨 Sintomas Comuns

- ❌ Login falha com credenciais corretas
- ❌ Mensagem "Email ou senha incorretos"
- ❌ Página recarrega mas não autentica
- ❌ Cookie não está sendo definido

---

## ⚙️ Solução Rápida

### Opção 1: Redeploy no Portainer (✅ Recomendado)

O redeploy automático reseta a senha para `admin123`:

1. Acesse o **Portainer**
2. Vá em **Stacks** > `28facil-api`
3. Clique em **Editor** (ou diretamente em **Redeploy**)
4. Clique em **Redeploy from git repository**
5. Aguarde a conclusão

**Credenciais após redeploy:**
```
Email: admin@28facil.com.br
Senha: admin123
```

### Opção 2: Script de Diagnóstico

Se o redeploy não resolver, execute o script de diagnóstico:

```bash
# Acessar o container
docker exec -it 28facil-api bash

# Executar script de diagnóstico
php /var/www/html/scripts/fix-login.php
```

O script irá:
- ✅ Verificar conexão com banco
- ✅ Validar usuário admin
- ✅ Testar hash de senha
- ✅ Corrigir senha se necessário
- ✅ Fornecer comandos de teste

---

## 🔍 Diagnóstico Detalhado

### 1️⃣ Verificar Status do Container

```bash
# Ver se o container está rodando
docker ps | grep 28facil-api

# Ver logs recentes
docker logs 28facil-api --tail=50
```

### 2️⃣ Testar Endpoint de Login via cURL

```bash
curl -X POST https://api.28facil.com.br/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@28facil.com.br","password":"admin123"}' \
  -c cookies.txt \
  -v
```

**O que verificar na resposta:**
- ✅ Status HTTP 200 OK
- ✅ Header `Set-Cookie: 28facil_token=...`
- ✅ JSON com `success: true`
- ❌ Se HTTP 401: senha incorreta
- ❌ Se HTTP 403: usuário inativo

### 3️⃣ Verificar Banco de Dados

```bash
# Acessar container
docker exec -it 28facil-api bash

# Conectar ao PostgreSQL
psql -U 28facil -d 28facil_api -h postgres

# Verificar usuário admin
SELECT id, email, role, status FROM users WHERE email = 'admin@28facil.com.br';

# Verificar hash da senha
SELECT substring(password_hash, 1, 30) FROM users WHERE email = 'admin@28facil.com.br';
```

### 4️⃣ Debug no Navegador (DevTools)

1. Abra **DevTools** (F12)
2. Vá na aba **Network**
3. Tente fazer login
4. Clique na requisição `POST /api/auth/login`
5. Verifique:
   - **Request Headers**: `Content-Type: application/json`
   - **Request Payload**: email e senha corretos
   - **Response Headers**: `Set-Cookie: 28facil_token`
   - **Response**: `{"success": true, ...}`

**Possíveis problemas:**
- 🔴 **CORS**: Headers não permitidos
- 🔴 **Cookie bloqueado**: SameSite ou Secure incorreto
- 🔴 **Domínio errado**: Cookie não enviado para subdomínio

---

## 🛠️ Correções Manuais

### Resetar Senha Manualmente

```bash
# Dentro do container
php /var/www/html/scripts/reset-admin.php
```

Ou via SQL direto:

```sql
-- Gerar hash bcrypt da senha 'admin123'
-- Use um gerador online: https://bcrypt-generator.com/
-- Custo: 10

UPDATE users 
SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE email = 'admin@28facil.com.br';
```

### Ativar Usuário Inativo

```sql
UPDATE users 
SET status = 'active' 
WHERE email = 'admin@28facil.com.br';
```

### Limpar Tentativas de Login Falhadas

```sql
DELETE FROM login_attempts 
WHERE email = 'admin@28facil.com.br';
```

---

## 🔐 Problemas de Cookies e HTTPS

### Sintoma: Cookie não é salvo no navegador

**Causa:** Cookies `HttpOnly` e `Secure` requerem HTTPS

**Verificação:**

```php
// No código: src/Controllers/AuthController.php
$isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

setcookie(
    '28facil_token',
    $token,
    [
        'expires' => time() + (86400 * 7),
        'path' => '/',
        'secure' => $isSecure,  // <- Deve ser true em produção
        'httponly' => true,
        'samesite' => 'Strict'
    ]
);
```

**Solução:**
1. Certifique-se que Traefik está gerando certificado SSL
2. Acesse sempre via `https://api.28facil.com.br`
3. Nunca via `http://` ou IP direto

---

## ⚡ Testes Automáticos

### Testar Login Completo

```bash
#!/bin/bash
# test-login.sh

API_URL="https://api.28facil.com.br"
EMAIL="admin@28facil.com.br"
PASSWORD="admin123"

echo "Testando login..."

RESPONSE=$(curl -s -X POST "$API_URL/api/auth/login" \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}" \
  -c cookies.txt \
  -w "\nHTTP_CODE:%{http_code}")

echo "$RESPONSE"

if echo "$RESPONSE" | grep -q '"success":true'; then
  echo "✅ Login bem-sucedido!"
  cat cookies.txt
else
  echo "❌ Login falhou!"
  exit 1
fi
```

---

## 📊 Monitoramento Contínuo

### Ver Logs em Tempo Real

```bash
# Logs do container
docker logs -f 28facil-api

# Logs PHP (se configurado)
docker exec -it 28facil-api tail -f /var/www/html/logs/app.log
```

### Verificar Saúde da API

```bash
curl https://api.28facil.com.br/health | jq
```

**Resposta esperada:**
```json
{
  "status": "healthy",
  "database": "connected",
  "timestamp": "2026-01-24T01:30:00-03:00"
}
```

---

## 📝 Checklist de Troubleshooting

- [ ] Container está rodando (`docker ps`)
- [ ] Banco de dados conectado (health check)
- [ ] Usuário admin existe no banco
- [ ] Usuário admin está com status `active`
- [ ] Hash de senha está correto (bcrypt)
- [ ] Endpoint `/api/auth/login` retorna HTTP 200
- [ ] Cookie `28facil_token` está sendo definido
- [ ] HTTPS está funcionando (certificado válido)
- [ ] Sem erros CORS no DevTools
- [ ] JavaScript do portal carrega corretamente

---

## 🆘 Perguntas Frequentes

### P: A senha padrão não funciona após redeploy

**R:** Execute o script de reset:
```bash
docker exec -it 28facil-api php /var/www/html/scripts/reset-admin.php
```

### P: Login funciona via cURL mas não no navegador

**R:** Problema de cookies/CORS. Verifique:
- HTTPS habilitado
- Domínio correto (`api.28facil.com.br`)
- Sem bloqueadores de cookie

### P: Cookie não persiste entre requisições

**R:** Verifique configurações do cookie:
- `secure: true` requer HTTPS
- `samesite: Strict` pode bloquear cross-site
- Verificar expiração (7 dias padrão)

### P: Como alterar a senha padrão do admin?

**R:** Edite `scripts/reset-admin.php`:
```php
$defaultPassword = 'MinhaNo...';  // Altere aqui
```

Commit, push e faça redeploy.

---

## 🐞 Reportar Bugs

Se o problema persistir:

1. Execute `scripts/fix-login.php`
2. Capture saída completa
3. Capture logs: `docker logs 28facil-api > logs.txt`
4. Capture screenshot do DevTools (Network tab)
5. Abra issue no repositório

---

## 📚 Referências

- [README.md](./README.md) - Documentação principal
- [GUIA_LICENCIAMENTO.md](./GUIA_LICENCIAMENTO.md) - Guia de licenciamento
- [DEPLOY.md](./DEPLOY.md) - Guia de deploy
- [AuthController.php](./src/Controllers/AuthController.php) - Código de autenticação

---

© 2026 28Facil - Sistema de Licenciamento
