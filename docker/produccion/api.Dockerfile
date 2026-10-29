# Imagen de producción de la API: PHP-FPM con el código y las dependencias sin las de desarrollo.
# Se construye desde la raíz del repositorio: docker compose -f docker-compose.prod.yml build
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

FROM php:8.4-fpm-alpine
RUN apk add --no-cache libpq-dev libzip-dev libpng-dev \
    && docker-php-ext-install pdo_pgsql zip bcmath gd opcache \
    && rm -rf /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app-fet.ini
COPY docker/produccion/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/backend
COPY --from=vendor /app/vendor ./vendor
COPY backend/ ./
RUN rm -f .env && composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/produccion/api-entrypoint.sh /usr/local/bin/api-entrypoint
RUN chmod +x /usr/local/bin/api-entrypoint

ENTRYPOINT ["api-entrypoint"]
CMD ["php-fpm"]
