#!/bin/sh
# Prepara Laravel con las variables del entorno de producción y arranca PHP-FPM.
set -e

php artisan migrate --force
php artisan db:seed --class=CatalogoSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
