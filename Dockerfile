# Dockerfile para 28Facil API com PostgreSQL
FROM php:8.2-apache

# Instalar dependências do sistema e extensões PHP necessárias + postgresql-client
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    postgresql-client \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite e mod_headers do Apache
RUN a2enmod rewrite headers

# Configurar timezone para America/Sao_Paulo
RUN ln -sf /usr/share/zoneinfo/America/Sao_Paulo /etc/localtime \
    && echo "America/Sao_Paulo" > /etc/timezone

# Configurar PHP
RUN echo "date.timezone = America/Sao_Paulo" > /usr/local/etc/php/conf.d/timezone.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "error_log = /var/www/html/logs/php_errors.log" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/custom.ini

# Definir working directory
WORKDIR /var/www/html

# Copiar código da aplicação ANTES de configurar o Apache
COPY . /var/www/html/

# Verificar se o diretório public existe, se não, criar
RUN mkdir -p /var/www/html/public

# Configurar DocumentRoot do Apache DEPOIS de copiar os arquivos
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|DocumentRoot /var/www/html/public|DocumentRoot /var/www/html/public|g' /etc/apache2/apache2.conf

# Adicionar configuração de .htaccess, ServerName e PassEnv para variáveis de ambiente
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf \
    && echo '<Directory /var/www/html/public>' >> /etc/apache2/apache2.conf \
    && echo '    AllowOverride All' >> /etc/apache2/apache2.conf \
    && echo '    Require all granted' >> /etc/apache2/apache2.conf \
    && echo '    Options -Indexes +FollowSymLinks' >> /etc/apache2/apache2.conf \
    && echo '</Directory>' >> /etc/apache2/apache2.conf \
    && echo '' >> /etc/apache2/apache2.conf \
    && echo '# Passar variáveis de ambiente do Docker para PHP' >> /etc/apache2/apache2.conf \
    && echo 'PassEnv DB_CONNECTION' >> /etc/apache2/apache2.conf \
    && echo 'PassEnv DB_HOST' >> /etc/apache2/apache2.conf \
    && echo 'PassEnv DB_PORT' >> /etc/apache2/apache2.conf \
    && echo 'PassEnv DB_DATABASE' >> /etc/apache2/apache2.conf \
    && echo 'PassEnv DB_USERNAME' >> /etc/apache2/apache2.conf \
    && echo 'PassEnv DB_PASSWORD' >> /etc/apache2/apache2.conf \
    && echo 'PassEnv APP_ENV' >> /etc/apache2/apache2.conf \
    && echo 'PassEnv APP_DEBUG' >> /etc/apache2/apache2.conf \
    && echo 'PassEnv APP_URL' >> /etc/apache2/apache2.conf \
    && echo 'PassEnv APP_TIMEZONE' >> /etc/apache2/apache2.conf \
    && echo 'PassEnv JWT_SECRET' >> /etc/apache2/apache2.conf \
    && echo 'PassEnv JWT_EXPIRATION' >> /etc/apache2/apache2.conf \
    && echo 'PassEnv API_KEY_PREFIX' >> /etc/apache2/apache2.conf

# Criar diretório de logs
RUN mkdir -p /var/www/html/logs && chmod 777 /var/www/html/logs

# Copiar e dar permissão ao entrypoint
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Definir permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/logs

# Expor porta 80
EXPOSE 80

# Usar entrypoint para executar migrations e iniciar Apache
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
