# Despliegue en producción

El servidor necesita Docker con Compose y un dominio que apunte a él (por ejemplo,
`practicas.fet.edu.co`), con los puertos 80 y 443 abiertos.

`docker-compose.prod.yml` levanta cuatro servicios:

| Servicio | Qué hace |
| --- | --- |
| `web` | Caddy: sirve el frontend compilado, obtiene y renueva el certificado HTTPS y reenvía `/api` a PHP-FPM. |
| `api` | Laravel en PHP-FPM, sin dependencias de desarrollo y con OPcache. Al arrancar aplica las migraciones, carga los catálogos y guarda en caché la configuración y las rutas. |
| `db` | PostgreSQL 17, sin puertos publicados. |
| `respaldo` | Cada 24 horas guarda la base (`pg_dump`) y los documentos privados (`storage/app/private`) en `./respaldos`, y borra los de más de `RETENCION_DIAS` días. |

## Primera instalación

```bash
# 1. Variables de producción (APP_DEBUG=false, clave de la base, servidor de correo…)
cp backend/.env.production.example backend/.env.production
#    Edita backend/.env.production. Para APP_KEY puedes generar una con:
#    docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"

# 2. Construir y levantar
export DOMINIO=practicas.fet.edu.co
docker compose -f docker-compose.prod.yml --env-file backend/.env.production up -d --build

# 3. Primera cuenta de administración (recibe la invitación por correo)
docker compose -f docker-compose.prod.yml --env-file backend/.env.production exec api php artisan fet:crear-admin coordinacion@fet.edu.co
```

El correo debe funcionar antes del paso 3, porque todas las cuentas se activan con una invitación.
Si el envío falla, corrige `MAIL_*` y vuelve a ejecutar el comando: reenvía la invitación.

Las cuentas y datos de ejemplo (`DemoSeeder`) nunca se cargan en producción.

## Actualizar

```bash
git pull
docker compose -f docker-compose.prod.yml --env-file backend/.env.production up -d --build
```

## Respaldos y restauración

Los respaldos quedan en `./respaldos` del servidor. Cópialos también fuera del servidor (por
ejemplo, a una unidad institucional) con la herramienta de copias que use la FET.

```bash
# Restaurar la base desde un respaldo
docker compose -f docker-compose.prod.yml --env-file backend/.env.production exec -T db \
  pg_restore -U app_fet -d app_fet --clean --if-exists < respaldos/base-AAAAMMDD-HHMM.dump

# Restaurar los documentos
docker compose -f docker-compose.prod.yml --env-file backend/.env.production run --rm -T \
  -v "$PWD/respaldos:/respaldos" --entrypoint sh api \
  -c "tar -xzf /respaldos/documentos-AAAAMMDD-HHMM.tar.gz -C storage/app"
```

## Lista de verificación

- `APP_ENV=production` y `APP_DEBUG=false`.
- `APP_KEY` y `DB_PASSWORD` largas y aleatorias; `backend/.env.production` no se sube a git.
- `FRONTEND_URL` con `https://` y el dominio real (se usa en los enlaces de los correos).
- Correo SMTP institucional probado (invitación, recuperación de contraseña y notificaciones).
- Respaldos copiados fuera del servidor y una restauración de prueba hecha.
