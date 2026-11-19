#!/bin/sh
# Túnel hacia la web de desarrollo (puerto 5173) para entrar desde otra red.
# Con NGROK_AUTHTOKEN en backend/.env usa ngrok con la dirección fija que la
# cuenta tiene asignada (o la de NGROK_DOMAIN, si se indica); sin token, usa un
# túnel temporal de Cloudflare, cuyo enlace cambia en cada reinicio. Deja el enlace en FRONTEND_URL, que usan los botones de los
# correos. Uso: ./scripts/tunel.sh
set -e
cd "$(dirname "$0")/.."

valor() { grep -E "^$1=" backend/.env | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'"; }
token=$(valor NGROK_AUTHTOKEN)
dominio=$(valor NGROK_DOMAIN)

docker rm -f appfet-tunel >/dev/null 2>&1 || true

if [ -n "$token" ]; then
  docker run -d --name appfet-tunel --restart unless-stopped -e NGROK_AUTHTOKEN="$token" \
    ngrok/ngrok:latest http ${dominio:+--url="$dominio"} --log stdout --log-format logfmt host.docker.internal:5173 >/dev/null
  url=""
  for _ in $(seq 1 20); do
    url=$(docker logs appfet-tunel 2>&1 | grep -oE 'url=https://[a-zA-Z0-9.-]+' | head -1 | cut -d= -f2)
    [ -n "$url" ] && break
    docker ps --filter name=appfet-tunel --filter status=running -q | grep -q . || break
    sleep 2
  done
  if [ -z "$url" ]; then
    docker logs appfet-tunel 2>&1 | grep -iE 'err|lvl=eror' | tail -3 >&2
    echo "ngrok no arrancó: revisa NGROK_AUTHTOKEN (y NGROK_DOMAIN, si lo pusiste) en backend/.env." >&2
    exit 1
  fi
else
  docker run -d --name appfet-tunel --restart unless-stopped cloudflare/cloudflared:latest \
    tunnel --no-autoupdate --url http://host.docker.internal:5173 >/dev/null
  url=""
  for _ in $(seq 1 30); do
    url=$(docker logs appfet-tunel 2>&1 | grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' | head -1)
    [ -n "$url" ] && break
    sleep 2
  done
  [ -n "$url" ] || { echo "No se obtuvo el enlace del túnel." >&2; exit 1; }
fi

if grep -q '^FRONTEND_URL=' backend/.env; then
  sed -i.bak "s#^FRONTEND_URL=.*#FRONTEND_URL=$url#" backend/.env && rm -f backend/.env.bak
else
  printf '\nFRONTEND_URL=%s\n' "$url" >> backend/.env
fi
docker compose exec -T api php artisan config:clear >/dev/null 2>&1 || true
# La cola conserva los valores de .env con los que arrancó: se reinicia para que los correos usen el enlace nuevo.
docker compose restart cola >/dev/null 2>&1 || true

echo "$url"
