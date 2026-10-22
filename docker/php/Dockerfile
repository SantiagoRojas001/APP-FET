# Imagen de desarrollo para la API Laravel.
FROM php:8.4-cli-alpine

RUN apk add --no-cache git unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql zip bcmath \
    && rm -rf /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/app-fet.ini

WORKDIR /var/www/backend

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
