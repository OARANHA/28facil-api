# Changelog - 28Facil API

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

## [2.1.0] - 2026-01-24

### ✨ Adicionado

#### Health Check Aprimorado
- Endpoint `/health` agora retorna informações detalhadas sobre todos os endpoints disponíveis
- Lista completa dos 6 endpoints de compatibilidade LicenseBoxAPI
- Estatísticas em tempo real de licenças (total, ativas, inativas, suspensas)
- Informações de método HTTP e tipo de autenticação para cada endpoint
- Versão da API e recursos disponíveis

#### Dashboard Web Atualizado
- Nova seção "Endpoints de Licenciamento" no dashboard
- Visualização dos endpoints LicenseBoxAPI Compatibility com status em tempo real
- Links diretos para documentação Swagger
- Badges visuais indicando método HTTP (POST/GET)
- Indicador de status da API (online/offline)
- Organização melhorada das funcionalidades de gerenciamento de licenças

### 🔧 Corrigido

#### Proteção CSRF para APIs Públicas
- **Problema**: Endpoints de licenciamento retornavam HTTP 500 com erro "Token CSRF inválido ou expirado"
- **Solução**: Adicionados todos os endpoints de licenciamento à lista de exceções CSRF em `middleware/CsrfProtection.php`
- **Impacto**: Instaladores e integrações externas agora podem autenticar usando apenas header `LB-API-KEY`

**Endpoints isentos de CSRF:**
```php
'/api/activate_license',
'/api/verify_license',
'/api/check_connection_ext',
'/api/latest_version',
'/api/check_update',
'/api/deactivate_license'
```

### 📦 Endpoints de Licenciamento

#### LicenseBoxAPI Compatibility (Implementado em 2026-01-23)

Todos os endpoints aceitam autenticação via header `LB-API-KEY`:

1. **POST** `/license/check_connection_ext`
   - Testa conexão com o servidor de licenças
   - Retorna status de conectividade

2. **POST** `/license/latest_version`
   - Retorna versão mais recente do produto
   - Útil para verificação de atualizações

3. **POST** `/license/activate_compat`
   - Ativa uma licença no sistema
   - Compatível com formato LicenseBoxAPI
   - Aceita: `product_id`, `license_code`, `client_name`, `verify_type`

4. **POST** `/license/verify_compat`
   - Verifica status de uma licença ativa
   - Valida estado atual da licença

5. **POST** `/license/deactivate_compat`
   - Desativa uma licença
   - Libera slot de ativação

6. **POST** `/license/check_update`
   - Verifica disponibilidade de atualizações
   - Retorna informações de versão

#### Endpoints Padrão 28Facil

- **POST** `/license/validate` - Validação pública de licença
- **POST** `/license/activate` - Ativação pública de licença
- **GET** `/license/check` - Verificação rápida de licença
- **GET** `/licenses` - Lista licenças (autenticado)
- **POST** `/licenses` - Cria nova licença (autenticado)

### 📝 Documentação

- Swagger atualizado com especificações OpenAPI 3.0 completas
- Disponível em: `https://api.28facil.com.br/swagger/`
- Especificação JSON em: `https://api.28facil.com.br/api.json`

### 🔒 Segurança

- Proteção CSRF mantida para endpoints administrativos
- APIs públicas de licenciamento isentas de CSRF por design
- Autenticação via `LB-API-KEY` header para endpoints compatíveis
- Headers de segurança CORS configurados para aceitar headers customizados

### 🚀 Deploy

Para aplicar as atualizações em produção:

```bash
# SSH no servidor
ssh root@158.220.97.145

# Acessar container
docker exec -it 28facil-api bash

# Atualizar código
cd /var/www/html
git pull origin main

# Sair e reiniciar
exit
docker restart 28facil-api
```

### 🧪 Testes

Testar endpoint de ativação após deploy:

```bash
curl -X POST https://api.28facil.com.br/api/activate_license \
  -H "Content-Type: application/json" \
  -H "LB-API-KEY: 50C38D45-FB74CA87-B6D6086C-E10DF77A" \
  -d '{
    "product_id": "2006AB23",
    "license_code": "TEST",
    "client_name": "TEST",
    "verify_type": "envata"
  }'
```

**Resultado esperado**: HTTP 200 (não mais HTTP 500)

---

## [2.0.0] - 2026-01-20

### Inicial
- Sistema completo de licenciamento
- Portal web administrativo
- Autenticação com JWT
- Gerenciamento de usuários
- API Keys com hash SHA256
- Proteção CSRF
- Deploy via Docker + Traefik

---

## Commits Relevantes

- [`4fe8fe1`](https://github.com/OARANHA/28facil-api/commit/4fe8fe13a9eac2dbad48166df36296e4b5ae7fb2) - feat: Add licensing endpoints section to dashboard
- [`10c64dd`](https://github.com/OARANHA/28facil-api/commit/10c64ddf995ec8e8fb94d785515d5d823b8bb188) - feat: Enhanced health check with licensing endpoints details
- [`5d38279`](https://github.com/OARANHA/28facil-api/commit/5d382797fe79748d20807dd3a2385802e1c04e7a) - fix: Add license endpoints to CSRF exception list
- [`2416fa5`](https://github.com/OARANHA/28facil-api/commit/2416fa5eb6c47f3c863d965d81bedbac6970d4d6) - feat: Add LicenseBoxAPI compatibility endpoints

---

## Próximos Passos

- [ ] Implementar rate limiting por IP
- [ ] Adicionar logs de auditoria para ativações de licença
- [ ] Criar dashboard de analytics de uso da API
- [ ] Implementar webhook notifications para eventos de licença
- [ ] Adicionar suporte a licenças flutuantes (floating licenses)
- [ ] Criar testes automatizados (PHPUnit) para endpoints de licenciamento