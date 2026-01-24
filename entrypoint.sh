#!/bin/bash
set -e

echo "🚀 Iniciando 28Facil API..."

# Aguardar PostgreSQL estar pronto
echo "⏳ Aguardando PostgreSQL..."
until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-28facil}"; do
  echo "PostgreSQL não está pronto ainda - aguardando..."
  sleep 2
done

echo "✅ PostgreSQL está pronto!"

# Executar migrations automaticamente
echo "🔄 Executando migrations..."

# Verificar se há arquivos SQL no diretório de migrations
if [ -d "/var/www/html/database/migrations_postgres" ] && [ "$(ls -A /var/www/html/database/migrations_postgres/*.sql 2>/dev/null)" ]; then
    export PGPASSWORD="${DB_PASSWORD}"
    
    for migration in /var/www/html/database/migrations_postgres/*.sql; do
        if [ -f "$migration" ]; then
            filename=$(basename "$migration")
            echo "  ➡️  Executando: $filename"
            
            psql -h "${DB_HOST:-postgres}" \
                 -p "${DB_PORT:-5432}" \
                 -U "${DB_USERNAME:-28facil}" \
                 -d "${DB_DATABASE:-28facil_api}" \
                 -f "$migration" 2>&1 | grep -v "already exists" || true
            
            echo "  ✅ $filename concluída"
        fi
    done
    
    unset PGPASSWORD
    echo "✅ Todas as migrations foram executadas!"
else
    echo "⚠️  Nenhuma migration encontrada em /var/www/html/database/migrations_postgres/"
fi

# Resetar senha do admin automaticamente
echo ""
echo "🔐 Resetando senha do admin..."
if [ -f "/var/www/html/scripts/reset-admin.php" ]; then
    php /var/www/html/scripts/reset-admin.php
    echo "✅ Senha do admin resetada!"
else
    echo "⚠️  Script de reset não encontrado em /var/www/html/scripts/reset-admin.php"
fi

# Criar diretório de logs se não existir
mkdir -p /var/www/html/logs
chmod 777 /var/www/html/logs

# Ajustar permissões
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

echo ""
echo "================================"
echo "✅ 28Facil API iniciada com sucesso!"
echo "================================"
echo "Portal: https://api.28facil.com.br/portal/"
echo "Admin: admin@28facil.com.br"
echo "Senha: admin123"
echo "================================"
echo ""

echo "🌐 Iniciando Apache..."
exec apache2-foreground