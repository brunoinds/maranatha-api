# Imagem única da API: papel web (nginx + php-fpm) e scheduler (mesmo código, outro comando no Kamal)
FROM serversideup/php:8.5.12-fpm-nginx-trixie

USER root

# gd: dompdf e Intervention; intl e bcmath: formatação e cálculos; zip (binário): backup do bundle web;
# git: o codedge/laravel-fpdf só baixa pelo código-fonte
RUN install-php-extensions gd intl bcmath \
    && apt-get update \
    && apt-get install -y --no-install-recommends zip unzip git sqlite3 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV PHP_OPCACHE_ENABLE=1 \
    PHP_MEMORY_LIMIT=512M \
    PHP_UPLOAD_MAX_FILE_SIZE=512M \
    PHP_POST_MAX_SIZE=512M \
    PHP_MAX_EXECUTION_TIME=60 \
    NGINX_CLIENT_MAX_BODY_SIZE=512M

WORKDIR /var/www/html

# Dependências antes do código, para o cache de build não refazer o composer a cada commit
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache resources/ionic database/data \
    && ln -sfn ../storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache resources/ionic database/data

USER www-data
