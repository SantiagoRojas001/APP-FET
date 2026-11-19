#!/bin/sh
# Túnel temporal de Cloudflare hacia la web de desarrollo (puerto 5173).
# Lo reinicia y deja su enlace en FRONTEND_URL de backend/.env, para que los
# botones de los correos abran la app por el túnel. El enlace cambia en cada
# reinicio. Uso: ./scripts/tunel.sh
set -e
cd "$(dirname "$0")/.."

docker rm -f appfet-tunel >/dev/null 2>&1 || true
docker run -d --name appfet-tunel --restart unless-stopped cloudflare/cloudflared:latest \
  tunnel --no-autoupdate --url http://host.docker.internal:5173 >/dev/null

url=""
for _ in $(seq 1 30); do
  url=$(docker logs appfet-tunel 2>&1 | grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' | head -1)
  [ -n "$url" ] && break
  sleep 2
done
[ -n "$url" ] || { echo "No se obtuvo el enlace del túnel." >&2; exit 1; }

if grep -q '^FRONTEND_URL=' backend/.env; then
  sed -i.bak "s#^FRONTEND_URL=.*#FRONTEND_URL=$url#" backend/.env && rm -f backend/.env.bak
else
  printf '\nFRONTEND_URL=%s\n' "$url" >> backend/.env
fi
docker compose exec -T api php artisan config:clear >/dev/null 2>&1 || true

echo "$url"
