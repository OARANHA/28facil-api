# Guia de Licenciamento - 28Facil API

## 📝 Índice

1. [Estrutura do Banco de Dados](#estrutura-do-banco-de-dados)
2. [Como Cadastrar Licenças](#como-cadastrar-licenças)
3. [Fluxo de Ativação LicenseBoxAPI](#fluxo-de-ativação-licenseboxapi)
4. [Exemplos de Uso](#exemplos-de-uso)
5. [Troubleshooting](#troubleshooting)

---

## Estrutura do Banco de Dados

### Tabela `licenses`

Armazena as licenças cadastradas no sistema:

```sql
CREATE TABLE licenses (
    id SERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL,
    user_id INTEGER REFERENCES users(id),
    purchase_code VARCHAR(255) UNIQUE NOT NULL,  -- Código que o cliente usa
    product_name VARCHAR(255) NOT NULL,          -- Ex: "28Pro", "AiVoPro"
    license_type VARCHAR(50) DEFAULT 'lifetime', -- lifetime, annual, monthly, trial
    status VARCHAR(50) DEFAULT 'active',         -- active, inactive, suspended
    max_activations INTEGER DEFAULT 1,           -- Quantas instalações permitidas
    expires_at TIMESTAMP,                        -- NULL = vitalícia
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### Tabela `license_activations`

Armazena cada ativação/instalação de uma licença:

```sql
CREATE TABLE license_activations (
    id SERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL,
    license_id INTEGER REFERENCES licenses(id),
    license_key VARCHAR(255) UNIQUE NOT NULL,    -- Chave gerada após ativação
    domain VARCHAR(255),                         -- Domínio ou nome do cliente
    installation_hash VARCHAR(255),              -- Hash da instalação
    installation_name VARCHAR(255),              -- Nome amigável
    server_ip VARCHAR(45),
    user_agent TEXT,
    metadata JSONB,                              -- Dados extras
    status VARCHAR(50) DEFAULT 'active',         -- active, inactive
    activated_at TIMESTAMP DEFAULT NOW(),
    deactivated_at TIMESTAMP,
    last_check_at TIMESTAMP,
    check_count INTEGER DEFAULT 0
);
```

---

## Como Cadastrar Licenças

### Opção 1: Via Portal Web (Admin)

1. Acesse: `https://api.28facil.com.br/portal/`
2. Faça login como **admin**
3. Vá em **Dashboard** > **+ Nova Licença**
4. Preencha:
   - **Cliente**: Selecione o usuário/cliente
   - **Nome do Produto**: Ex: `28Pro`, `AiVoPro`
   - **Tipo de Licença**: Vitalícia, Anual, Mensal, Trial
   - **Máximo de Ativações**: Quantas instalações permitidas (geralmente 1)
5. Clique em **Criar**
6. **Copie o Purchase Code gerado** (formato: `XXXX-XXXX-XXXX-XXXX`)
7. Envie o **Purchase Code** para o cliente

### Opção 2: Via API (Admin)

```bash
curl -X POST https://api.28facil.com.br/licenses \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN_ADMIN" \
  -d '{
    "user_id": 2,
    "product_name": "28Pro",
    "license_type": "lifetime",
    "max_activations": 1
  }'
```

**Resposta:**
```json
{
  "success": true,
  "message": "Licença criada com sucesso",
  "license": {
    "id": 5,
    "uuid": "a1b2c3d4-e5f6-...",
    "purchase_code": "8A3F2E1D-9B7C-4D6E-A5F8-1C2D3E4F5A6B",
    "product_name": "28Pro",
    "license_type": "lifetime",
    "max_activations": 1
  }
}
```

### Opção 3: Inserir Manualmente no Banco

```sql
INSERT INTO licenses (
    uuid, 
    user_id, 
    purchase_code, 
    product_name, 
    license_type, 
    max_activations
) VALUES (
    gen_random_uuid(),
    2,  -- ID do usuário/cliente
    '8A3F2E1D-9B7C-4D6E-A5F8-1C2D3E4F5A6B',
    '28Pro',
    'lifetime',
    1
);
```

---

## Fluxo de Ativação LicenseBoxAPI

### Passo 1: Cliente Recebe Purchase Code

Você (admin) cria a licença e envia o **Purchase Code** para o cliente:
```
8A3F2E1D-9B7C-4D6E-A5F8-1C2D3E4F5A6B
```

### Passo 2: Cliente Instala o Sistema (28Pro)

O cliente acessa `/install` no sistema 28Pro e:
1. Informa o **Purchase Code**
2. Informa o **Client Name** (nome dele ou empresa)
3. Clica em "Ativar"

### Passo 3: Sistema 28Pro Chama a API

O instalador faz uma requisição para:

```bash
POST https://api.28facil.com.br/api/activate_license
Content-Type: application/json
LB-API-KEY: 50C38D45-FB74CA87-B6D6086C-E10DF77A

{
  "product_id": "2006AB23",
  "license_code": "8A3F2E1D-9B7C-4D6E-A5F8-1C2D3E4F5A6B",
  "client_name": "João Silva Salão",
  "verify_type": "envato"
}
```

### Passo 4: API Valida e Ativa

A API:
1. ✅ Busca a licença pelo `purchase_code` (`license_code`)
2. ✅ Verifica se está `active`
3. ✅ Verifica se não expirou (`expires_at`)
4. ✅ Verifica se não atingiu limite de ativações (`max_activations`)
5. ✅ Cria registro em `license_activations`
6. ✅ Retorna `lic_response` (base64)

**Resposta de Sucesso:**
```json
{
  "status": true,
  "message": "Licença ativada com sucesso!",
  "lic_response": "eyJwcm9kdWN0X2lkIjoiMjAwNkFCMjMiLCJsaWNl..."
}
```

### Passo 5: Sistema 28Pro Salva a Licença

O instalador salva o `lic_response` em um arquivo local e considera a instalação ativada.

---

## Exemplos de Uso

### 1. Testar Conexão

```bash
curl -X POST https://api.28facil.com.br/api/check_connection_ext \
  -H "Content-Type: application/json" \
  -H "LB-API-KEY: 50C38D45-FB74CA87-B6D6086C-E10DF77A" \
  -d '{}'
```

**Resposta:**
```json
{
  "status": true,
  "message": "Connection successful",
  "server_time": "2026-01-24 02:00:00"
}
```

### 2. Verificar Versão do Produto

```bash
curl -X POST https://api.28facil.com.br/api/latest_version \
  -H "Content-Type: application/json" \
  -H "LB-API-KEY: 50C38D45-FB74CA87-B6D6086C-E10DF77A" \
  -d '{
    "product_id": "2006AB23"
  }'
```

**Resposta:**
```json
{
  "status": true,
  "product_id": "2006AB23",
  "current_version": "v2.1.0",
  "latest_version": "v2.1.0",
  "update_available": false
}
```

### 3. Ativar Licença (Primeiro Uso)

```bash
curl -X POST https://api.28facil.com.br/api/activate_license \
  -H "Content-Type: application/json" \
  -H "LB-API-KEY: 50C38D45-FB74CA87-B6D6086C-E10DF77A" \
  -d '{
    "product_id": "2006AB23",
    "license_code": "8A3F2E1D-9B7C-4D6E-A5F8-1C2D3E4F5A6B",
    "client_name": "João Silva Salão",
    "verify_type": "envato"
  }'
```

**Resposta de Sucesso:**
```json
{
  "status": true,
  "message": "Licença ativada com sucesso!",
  "lic_response": "eyJwcm9kdWN0X2lkIjoiMjAwNkFCMjMi..."
}
```

### 4. Verificar Licença Ativada

```bash
curl -X POST https://api.28facil.com.br/api/verify_license \
  -H "Content-Type: application/json" \
  -H "LB-API-KEY: 50C38D45-FB74CA87-B6D6086C-E10DF77A" \
  -d '{
    "product_id": "2006AB23",
    "license_code": "8A3F2E1D-9B7C-4D6E-A5F8-1C2D3E4F5A6B",
    "client_name": "João Silva Salão"
  }'
```

**Resposta:**
```json
{
  "status": true,
  "message": "Verified! Thanks for purchasing.",
  "license_type": "lifetime"
}
```

### 5. Desativar Licença

```bash
curl -X POST https://api.28facil.com.br/api/deactivate_license \
  -H "Content-Type: application/json" \
  -H "LB-API-KEY: 50C38D45-FB74CA87-B6D6086C-E10DF77A" \
  -d '{
    "product_id": "2006AB23",
    "license_code": "8A3F2E1D-9B7C-4D6E-A5F8-1C2D3E4F5A6B",
    "client_name": "João Silva Salão"
  }'
```

**Resposta:**
```json
{
  "status": true,
  "message": "Licença desativada com sucesso"
}
```

---

## Troubleshooting

### Erro: "Código de licença inválido ou não encontrado"

**Causa**: O `license_code` enviado não existe na tabela `licenses`

**Solução**:
1. Verifique se a licença foi cadastrada:
   ```sql
   SELECT * FROM licenses WHERE purchase_code = 'SEU_CODIGO';
   ```
2. Se não existir, cadastre uma nova licença
3. Certifique-se de usar o `purchase_code` exato (case-sensitive)

### Erro: "Licença suspensa ou inativa"

**Causa**: Campo `status` da licença não está como `'active'`

**Solução**:
```sql
UPDATE licenses 
SET status = 'active' 
WHERE purchase_code = 'SEU_CODIGO';
```

### Erro: "Licença expirada"

**Causa**: Campo `expires_at` está no passado

**Solução**:
```sql
-- Tornar vitalícia (NULL = sem expiração)
UPDATE licenses 
SET expires_at = NULL 
WHERE purchase_code = 'SEU_CODIGO';

-- OU estender por 1 ano
UPDATE licenses 
SET expires_at = NOW() + INTERVAL '1 year' 
WHERE purchase_code = 'SEU_CODIGO';
```

### Erro: "Limite de ativações atingido"

**Causa**: Já existem `max_activations` ativações ativas para essa licença

**Solução**:

**Opção 1**: Aumentar limite
```sql
UPDATE licenses 
SET max_activations = 5 
WHERE purchase_code = 'SEU_CODIGO';
```

**Opção 2**: Desativar ativação antiga
```sql
UPDATE license_activations
SET status = 'inactive', deactivated_at = NOW()
WHERE license_id = (SELECT id FROM licenses WHERE purchase_code = 'SEU_CODIGO')
  AND id = 123;  -- ID da ativação antiga
```

### Erro: "Token CSRF inválido ou expirado" (HTTP 500)

**Causa**: Middleware CSRF bloqueando a requisição

**Solução**: Já corrigido! Certifique-se de:
1. Atualizar o código: `git pull origin main`
2. Reiniciar container: `docker restart 28facil-api`
3. Todos os endpoints `/api/*_license` estão isentos de CSRF

### Erro: "product_id é obrigatório" (HTTP 400)

**Causa**: Payload JSON está incompleto ou malformado

**Solução**: Certifique-se de enviar JSON válido:
```json
{
  "product_id": "2006AB23",          // Obrigatório
  "license_code": "XXXX-XXXX-...",  // Obrigatório
  "client_name": "Nome Real",        // Obrigatório
  "verify_type": "envato"            // Opcional
}
```

### Verificar Logs do Servidor

Para ver erros internos:
```bash
ssh root@158.220.97.145
docker logs 28facil-api --tail=50 -f
```

---

## Checklist de Validação

Antes de distribuir uma licença para o cliente, verifique:

- [ ] Licença cadastrada na tabela `licenses`
- [ ] Campo `status` = `'active'`
- [ ] Campo `expires_at` = `NULL` (vitalícia) ou data futura
- [ ] Campo `max_activations` >= 1
- [ ] `purchase_code` copiado corretamente (com hífens)
- [ ] Cliente recebeu o `purchase_code`
- [ ] Testado endpoint `/api/activate_license` no Swagger
- [ ] Container reiniciado após updates (`docker restart 28facil-api`)

---

## Próximos Passos

- [ ] Criar interface no portal para gerenciar ativações
- [ ] Implementar notificações por email ao ativar/desativar
- [ ] Adicionar logs de auditoria para rastreamento
- [ ] Criar relatório de licenças ativas/expiradas
- [ ] Implementar webhook para notificar eventos de licenciamento