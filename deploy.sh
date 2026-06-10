#!/usr/bin/env bash
# ============================================================================
#  int-istinge — Rollout de cambios de código a TODOS los clientes.
#
#  Hace, en orden:
#    1) git pull           -> trae el código nuevo
#    2) docker build       -> reconstruye la imagen única integra-int:latest
#    3) por cada cliente    -> recrea su contenedor con la imagen nueva
#
#  Uso:
#    ./deploy.sh                 # despliega a TODOS los clientes
#    ./deploy.sh acme            # despliega solo a un cliente
#    SKIP_BUILD=1 ./deploy.sh    # salta el build (p. ej. solo cambió un .env)
#
#  El DOMAIN de cada cliente se deduce de APP_URL en su clientes/<x>/.env.
# ============================================================================
set -euo pipefail

cd "$(dirname "$0")"

ONLY_CLIENT="${1:-}"

if [ "${SKIP_BUILD:-0}" != "1" ]; then
  echo "==> 1/3  git pull"
  git pull --ff-only

  echo "==> 2/3  docker build -t integra-int:latest ."
  docker build -t integra-int:latest .
else
  echo "==> build saltado (SKIP_BUILD=1)"
fi

echo "==> 3/3  rollout"
for dir in clientes/*/; do
  client="$(basename "$dir")"
  [ -n "$ONLY_CLIENT" ] && [ "$client" != "$ONLY_CLIENT" ] && continue

  envfile="${dir}.env"
  if [ ! -f "$envfile" ]; then
    echo "  ! ${client}: sin .env, se omite"
    continue
  fi

  # APP_URL crudo, limpiado de comillas/espacios/comentarios inline/\r.
  app_url="$(grep -E '^[[:space:]]*APP_URL=' "$envfile" | head -1 \
            | sed -E 's/\r$//; s/^[[:space:]]*APP_URL=//; s/[[:space:]]*#.*$//; s/["'"'"']//g; s/[[:space:]]//g')"
  if [ -z "$app_url" ]; then
    echo "  ! ${client}: APP_URL vacío en .env, se omite"
    continue
  fi

  # Dominio = host de APP_URL.   https://acme.com.co/software -> acme.com.co
  domain="$(echo "$app_url" | sed -E 's#^https?://##; s#[/:].*$##')"

  # Toda la flota vive bajo /software (Caddy hace handle_path: /software*).
  # Si APP_URL no termina en /software, Laravel arma URLs absolutas fuera
  # del path que Caddy enruta -> pantalla en blanco / 404 para el cliente.
  case "$app_url" in
    */software|*/software/) ;;
    *)
      echo "  ! ${client}: APP_URL='${app_url}' no termina en /software, se omite"
      echo "    Esperado: https://${domain}/software   (corregí ${envfile} y reintentá)"
      continue
      ;;
  esac

  # ASSET_URL: por defecto, igual a APP_URL (todos los assets viven en el mismo
  # host bajo /software). Si el .env del cliente lo declara, gana el del .env
  # (caso típico: CDN). Validamos también que termine en /software.
  asset_url="$(grep -E '^[[:space:]]*ASSET_URL=' "$envfile" | head -1 \
              | sed -E 's/\r$//; s/^[[:space:]]*ASSET_URL=//; s/[[:space:]]*#.*$//; s/["'"'"']//g; s/[[:space:]]//g' || true)"
  [ -z "$asset_url" ] && asset_url="$app_url"
  case "$asset_url" in
    */software|*/software/) ;;
    *)
      echo "  ! ${client}: ASSET_URL='${asset_url}' no termina en /software, se omite"
      echo "    Quitalo de ${envfile} (se deriva de APP_URL) o corregilo y reintentá"
      continue
      ;;
  esac

  # HESTIA_IP: IP del servidor Hestia que sirve la raíz del dominio (la página
  # web del cliente). Caddy proxea ahí todo lo que NO sea /software*. Default
  # global; se puede sobreescribir por cliente declarándolo en su .env.
  hestia_ip="$(grep -E '^[[:space:]]*HESTIA_IP=' "$envfile" | head -1 \
              | sed -E 's/\r$//; s/^[[:space:]]*HESTIA_IP=//; s/[[:space:]]*#.*$//; s/["'"'"']//g; s/[[:space:]]//g' || true)"
  [ -z "$hestia_ip" ] && hestia_ip="13.140.155.227"

  echo "  -> ${client}  (${domain}, raíz -> hestia ${hestia_ip})"

  # Preservar los logs del contenedor anterior: hasta ahora storage/logs vivía
  # en la capa efímera y se perdía al recrear. Los copiamos a un tmp y, tras
  # levantar el contenedor nuevo (que ya monta el volumen 'logs' persistente),
  # los restauramos para que el módulo de Logs no aparezca vacío tras el deploy.
  old_cid="$(docker compose -p "$client" -f docker-compose.client.yml ps -q app 2>/dev/null || true)"
  tmp_logs=""
  if [ -n "$old_cid" ]; then
    tmp_logs="$(mktemp -d)"
    docker cp "${old_cid}:/var/www/html/storage/logs/." "$tmp_logs/" 2>/dev/null || true
  fi

  CLIENT="$client" DOMAIN="$domain" ASSET_URL="$asset_url" HESTIA_IP="$hestia_ip" \
    docker compose -p "$client" -f docker-compose.client.yml up -d --force-recreate

  if [ -n "$tmp_logs" ] && [ -n "$(ls -A "$tmp_logs" 2>/dev/null)" ]; then
    new_cid="$(docker compose -p "$client" -f docker-compose.client.yml ps -q app)"
    if [ -n "$new_cid" ]; then
      docker cp "$tmp_logs/." "${new_cid}:/var/www/html/storage/logs/" 2>/dev/null || true
      docker compose -p "$client" -f docker-compose.client.yml exec -T app \
        chown -R www-data:www-data /var/www/html/storage/logs 2>/dev/null || true
      echo "     logs preservados del contenedor anterior"
    fi
  fi
  [ -n "$tmp_logs" ] && rm -rf "$tmp_logs"
done

echo "==> Listo. Clientes corriendo:"
docker compose ls
