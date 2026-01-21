# 🚀 Guia de Deploy - 28Facil API com PostgreSQL

## 📦 Pré-requisitos

- Docker e Docker Compose instalados
- Acesso ao servidor VPS
- Portas disponíveis: 80, 5432, 5050

## 🔧 Passos para Deploy

### 1. Clonar/Atualizar o Repositório

```bash
cd /caminho/do/projeto
git pull origin main
```

### 2. Configurar Variáveis de Ambiente

Copie o arquivo de exemplo:

```bash
cp .env.postgres.example .env
```

Edite o `.env` com suas configurações:

```bash
nano .env
```

### 3. Parar Containers Antigos (se existirem)

```bash
docker-compose -f docker-compose.postgres.yml down
```

### 4. Remover Volume do PostgreSQL (apenas se quiser resetar o banco)

⚠️ **ATENÇÃO**: Isso vai apagar todos os dados do banco!

```bash
docker volume rm 28facil-api_postgres_data
```

### 5. Build e Start dos Containers

```bash
# Build da imagem
docker-compose -f docker-compose.postgres.yml build --no-cache

# Iniciar os containers
docker-compose -f docker-compose.postgres.yml up -d
```

### 6. Verificar Logs

```bash
# Ver logs da API
docker-compose -f docker-compose.postgres.yml logs -f api

# Ver logs do PostgreSQL
docker-compose -f docker-compose.postgres.yml logs -f postgres
```

Você deve ver as mensagens:
- ✅ PostgreSQL está pronto!
- ✅ Todas as migrations foram executadas!
- 🌐 Iniciando Apache...

## 🔍 Verificar se está funcionando

### Testar a API

```bash
# Health check
curl http://localhost:8080/

# Acessar o portal
curl http://localhost:8080/portal/

# Verificar Swagger
curl http://localhost:8080/swagger/
```

### Acessar o pgAdmin

Abra no navegador: `http://localhost:5050`

- Email: admin@28facil.com.br
- Senha: admin123

## 🔄 Executar Migrations Manualmente (se necessário)

Se precisar executar as migrations manualmente:

```bash
# Entrar no container da API
docker exec -it 28facil-api bash

# Executar migrations
for file in /var/www/html/database/migrations_postgres/*.sql; do
    psql -h postgres -U 28facil -d 28facil_api -f "$file"
done
```

## 🛠️ Troubleshooting

### Problema: Erro 404 no /portal/

**Solução**: Verificar se o .htaccess foi atualizado corretamente

```bash
docker exec -it 28facil-api cat /var/www/html/public/.htaccess
```

### Problema: Migrations não executam

**Solução**: Verificar os logs do container

```bash
docker-compose -f docker-compose.postgres.yml logs api | grep migration
```

### Problema: PostgreSQL não conecta

**Solução**: Verificar se o PostgreSQL está rodando

```bash
docker exec -it 28facil-postgres pg_isready -U 28facil
```

### Problema: Container reiniciando constantemente

**Solução**: Ver os logs de erro

```bash
docker-compose -f docker-compose.postgres.yml logs --tail=50 api
```

## 📊 Monitoramento

### Ver status dos containers

```bash
docker-compose -f docker-compose.postgres.yml ps
```

### Ver uso de recursos

```bash
docker stats 28facil-api 28facil-postgres
```

### Acessar banco de dados diretamente

```bash
docker exec -it 28facil-postgres psql -U 28facil -d 28facil_api
```

## 🔄 Atualizações

Quando houver atualizações no código:

```bash
# 1. Fazer pull das atualizações
git pull origin main

# 2. Rebuild e restart
docker-compose -f docker-compose.postgres.yml up -d --build

# 3. Verificar logs
docker-compose -f docker-compose.postgres.yml logs -f api
```

## 🛡️ Backup do Banco de Dados

### Criar backup

```bash
docker exec -t 28facil-postgres pg_dump -U 28facil 28facil_api > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Restaurar backup

```bash
cat backup_20260121.sql | docker exec -i 28facil-postgres psql -U 28facil -d 28facil_api
```

## ✅ Checklist de Deploy

- [ ] Git pull das últimas atualizações
- [ ] Arquivo .env configurado corretamente
- [ ] Docker Compose build sem erros
- [ ] Containers rodando (docker ps)
- [ ] Migrations executadas com sucesso
- [ ] API respondendo em http://localhost:8080/
- [ ] Portal acessível em http://localhost:8080/portal/
- [ ] Logs sem erros críticos
- [ ] Backup do banco criado (se produção)

---

👍 **Pronto!** Sua API 28Facil com PostgreSQL está no ar!
