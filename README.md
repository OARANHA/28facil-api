# 28Facil API - Sistema de Licenciamento

<div align="center">

![28Facil API](https://img.shields.io/badge/28Facil-API-blue?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15-336791?style=for-the-badge&logo=postgresql)
![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=for-the-badge&logo=docker)
![License](https://img.shields.io/badge/License-Proprietary-red?style=for-the-badge)

**Sistema completo de gerenciamento de licenças com portal web administrativo e APIs públicas**

[Documentação](https://api.28facil.com.br/swagger/) • [Portal Web](https://api.28facil.com.br/portal/) • [API Health](https://api.28facil.com.br/health)

</div>

---

## 🚀 Características

### 🏛️ Portal Administrativo Web
- ✅ Gerenciamento completo de licenças
- ✅ Painel de controle de usuários/clientes
- ✅ Dashboard com estatísticas em tempo real
- ✅ Geração automática de Purchase Codes
- ✅ Controle de ativações por licença
- ✅ Sistema de autenticação JWT com cookies HttpOnly
- ✅ Proteção CSRF em rotas administrativas

### 🔑 APIs de Licenciamento

#### **APIs Nativas 28Facil**
- `POST /license/validate` - Validar purchase code
- `POST /license/activate` - Ativar licença em domínio
- `GET /license/check` - Verificar status de licença ativa

#### **APIs 28Pro Installer** (usado pelo seu instalador customizado)
- `POST /api/license/activate` - Ativar licença (aceita product_id, purchase_code, domain, installation_hash)
- `POST /api/license/validate` - Validar licença
- `GET /api/license/check` - Verificar status

#### **Compatibilidade LicenseBoxAPI** (para integração GoFresha)
- `POST /api/check_connection_ext` - Testar conexão
- `POST /api/latest_version` - Versão do produto
- `POST /api/activate_license` - Ativar licença (formato LicenseBox)
- `POST /api/verify_license` - Verificar licença
- `POST /api/deactivate_license` - Desativar licença
- `POST /api/check_update` - Verificar atualizações

### 🔒 Segurança
- ✅ Autenticação JWT com refresh tokens
- ✅ Cookies HttpOnly e Secure
- ✅ Proteção CSRF para rotas administrativas
- ✅ APIs públicas isentas de CSRF (para instaladores)
- ✅ Headers de segurança (X-Frame-Options, X-Content-Type-Options, etc.)
- ✅ Senhas com hash bcrypt
- ✅ API Keys com hash SHA256
- ✅ Rate limiting client-side

---

## 📦 Deploy via Portainer

### Redeploy Automático

Quando você faz **"Redeploy from git repository"** no Portainer:

1. ✅ Container é recriado do zero
2. ✅ Migrations rodam automaticamente
3. ✅ **Senha do admin é resetada automaticamente para `admin123`**
4. ✅ Tentativas de login falhadas são limpas

### Credenciais Padrão Após Redeploy

```
URL: https://api.28facil.com.br/portal/
Email: admin@28facil.com.br
Senha: admin123
```

> ⚠️ **IMPORTANTE**: Altere a senha padrão imediatamente após o primeiro login!

### Como Alterar a Senha Padrão

Para definir uma senha diferente no reset automático, edite o arquivo:

```php
// scripts/reset-admin.php
$defaultPassword = 'admin123';  // <- Altere aqui
```

Commit e faça push. No próximo redeploy, a nova senha será usada.

---

## 💻 Stack Docker Compose (Portainer)

```yaml
version: '3.8'

services:
  28facil-api:
    image: 28facil-api:latest
    build:
      context: https://github.com/OARANHA/28facil-api.git#main
      dockerfile: Dockerfile
    container_name: 28facil-api
    restart: unless-stopped
    environment:
      - DB_CONNECTION=pgsql
      - DB_HOST=postgres
      - DB_PORT=5432
      - DB_DATABASE=28facil_api
      - DB_USERNAME=28facil
      - DB_PASSWORD=SuaSenhaSegura123
      - APP_ENV=production
      - APP_DEBUG=false
      - APP_URL=https://api.28facil.com.br
      - APP_TIMEZONE=America/Sao_Paulo
      - JWT_SECRET=SuaChaveSecretaJWT123
      - JWT_EXPIRATION=86400
    networks:
      - traefik
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.28facil-api.rule=Host(`api.28facil.com.br`)"
      - "traefik.http.routers.28facil-api.entrypoints=websecure"
      - "traefik.http.routers.28facil-api.tls.certresolver=letsencrypt"
      - "traefik.http.services.28facil-api.loadbalancer.server.port=80"

networks:
  traefik:
    external: true
```

---

## 📚 Documentação

- **[CHANGELOG.md](./CHANGELOG.md)** - Histórico de versões e mudanças
- **[GUIA_LICENCIAMENTO.md](./GUIA_LICENCIAMENTO.md)** - Guia completo de licenciamento
  - Como cadastrar licenças
  - Fluxo de ativação passo a passo
  - Troubleshooting de erros comuns
  - Exemplos de payloads para todos endpoints

### Swagger/OpenAPI

Documentação interativa disponível em:
- **Swagger UI**: https://api.28facil.com.br/swagger/
- **Especificação JSON**: https://api.28facil.com.br/api.json

---

## 🔧 Comandos Úteis

### Acessar Container

```bash
# SSH no servidor
ssh root@158.220.97.145

# Acessar container
docker exec -it 28facil-api bash
```

### Resetar Senha Manualmente

Se por algum motivo o reset automático não funcionar:

```bash
# Dentro do container
php /var/www/html/scripts/reset-admin.php
```

Ou direto do servidor:

```bash
docker exec -it 28facil-api php /var/www/html/scripts/reset-admin.php
```

### Ver Logs

```bash
# Logs do container
docker logs 28facil-api --tail=100 -f

# Logs PHP (dentro do container)
tail -f /var/www/html/logs/php_errors.log
```

### Atualizar Código Sem Redeploy

```bash
# Dentro do container
cd /var/www/html
git pull origin main
```

Entretanto, o redeploy via Portainer é recomendado para garantir consistência.

---

## 🧪 Testes

### Testar Ativação de Licença

```bash
curl -X POST https://api.28facil.com.br/api/license/activate \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": "2006AB23",
    "purchase_code": "SEU-PURCHASE-CODE",
    "domain": "localhost",
    "installation_hash": "abc123"
  }'
```

### Testar Health Check

```bash
curl https://api.28facil.com.br/health | jq
```

---

## 🛡️ Segurança em Produção

### Checklist de Segurança

- [ ] Alterar senha padrão do admin (`admin123`)
- [ ] Definir `JWT_SECRET` forte e único
- [ ] Definir `DB_PASSWORD` forte
- [ ] Configurar `APP_DEBUG=false` em produção
- [ ] Habilitar HTTPS com certificado SSL (Let's Encrypt via Traefik)
- [ ] Configurar backups regulares do banco PostgreSQL
- [ ] Monitorar logs de acesso e erros
- [ ] Implementar rate limiting por IP (futuro)

### Recomendações

1. **Não commitar credenciais** no repositório
2. **Usar variáveis de ambiente** para secrets
3. **Trocar senhas padrão** imediatamente
4. **Fazer backups regulares** do banco de dados
5. **Monitorar tentativas de login falhadas**

---

## 📞 Suporte

Em caso de dúvidas ou problemas:

1. Consulte o **[GUIA_LICENCIAMENTO.md](./GUIA_LICENCIAMENTO.md)**
2. Verifique os **logs** do container
3. Teste o **health check**: https://api.28facil.com.br/health
4. Consulte a **documentação Swagger**: https://api.28facil.com.br/swagger/

---

## 📄 Licença

Este projeto é proprietário e de uso restrito.

© 2026 28Facil - Todos os direitos reservados.