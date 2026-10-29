# Imagen de producción del frontend: compila con Vite y lo sirve Caddy, que además
# obtiene el certificado HTTPS y reenvía /api a PHP-FPM.
FROM node:24-alpine AS build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM caddy:2-alpine
COPY docker/produccion/Caddyfile /etc/caddy/Caddyfile
COPY --from=build /app/dist /srv
