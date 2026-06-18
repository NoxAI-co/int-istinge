#!/usr/bin/env bash
#
# fix-btw-env.sh
# Agrega/corrige las variables BTW en el .env de todos los clientes multi-tenant.
# - Hace respaldo de cada .env antes de tocarlo.
# - Es idempotente: si la variable existe la actualiza al valor canónico,
#   si no existe la agrega. Volver a correrlo no genera duplicados.
# - Opcionalmente limpia config cache y reinicia el contenedor de cada cliente.
#
# Uso:
#   ./fix-btw-env.sh                 # solo edita los .env (dry-run de reinicio)
#   ./fix-btw-env.sh --restart       # además limpia config y reinicia contenedores
#
set -euo pipefail

# ── Rutas ──────────────────────────────────────────────────────────────────
BASE_DIR="/opt/integra/int-istinge"
CLIENTS_DIR="${BASE_DIR}/clientes"
COMPOSE_FILE="${BASE_DIR}/docker-compose.client.yml"

# ── Valores canónicos BTW ──────────────────────────────────────────────────
BTW_TEST_MODE="0"
BTW_TEST_CREDENTIAL="dRhYZzSovk1dv0eTgj8w2VCySgkmogoKMApJbrS79e6851d9"
BTW_URL_TEST="https://btw.gestorudesarrollo.com"
BTW_URL_PROD="https://btw.gestorudesarrollo.com"

RESTART=0
[ "${1:-}" = "--restart" ] && RESTART=1

TS="$(date +%Y%m%d-%H%M%S)"

# upsert_var <archivo> <CLAVE> <valor>
# Reemplaza la línea CLAVE=... si existe (incluida comentada/espacios), si no la agrega.
upsert_var() {
  local file="$1" key="$2" value="$3"
  # Quitar cualquier línea existente de esa clave (con o sin comillas/espacios)
  sed -i "/^[[:space:]]*${key}[[:space:]]*=/d" "$file"
  # Agregar la versión canónica
  printf '%s="%s"\n' "$key" "$value" >> "$file"
}

if [ ! -d "$CLIENTS_DIR" ]; then
  echo "ERROR: no existe $CLIENTS_DIR" >&2
  exit 1
fi

echo "==> Procesando clientes en $CLIENTS_DIR"
total=0; editados=0; sin_env=0

for dir in "$CLIENTS_DIR"/*/; do
  cliente="$(basename "$dir")"
  env_file="${dir}.env"
  total=$((total+1))

  if [ ! -f "$env_file" ]; then
    echo "  [$cliente] sin .env, salto"
    sin_env=$((sin_env+1))
    continue
  fi

  # Respaldo
  cp -p "$env_file" "${env_file}.bak-${TS}"

  # Asegurar salto de línea final antes de agregar
  [ -n "$(tail -c1 "$env_file")" ] && printf '\n' >> "$env_file"

  upsert_var "$env_file" "BTW_TEST_MODE"       "$BTW_TEST_MODE"
  upsert_var "$env_file" "BTW_TEST_CREDENTIAL" "$BTW_TEST_CREDENTIAL"
  upsert_var "$env_file" "BTW_URL_TEST"        "$BTW_URL_TEST"
  upsert_var "$env_file" "BTW_URL_PROD"        "$BTW_URL_PROD"

  editados=$((editados+1))
  echo "  [$cliente] .env actualizado (respaldo: .env.bak-${TS})"

  if [ "$RESTART" -eq 1 ]; then
    svc="${cliente}-app"
    echo "      -> config:clear + restart ($svc)"
    docker compose -f "$COMPOSE_FILE" exec -T "$svc" php artisan config:clear || \
      echo "      (aviso: no se pudo limpiar config en $svc)"
    docker compose -f "$COMPOSE_FILE" restart "$svc" || \
      echo "      (aviso: no se pudo reiniciar $svc)"
  fi
done

echo ""
echo "==> Resumen: $total clientes | $editados editados | $sin_env sin .env"
[ "$RESTART" -eq 0 ] && echo "==> No se reiniciaron contenedores. Corre con --restart para aplicar config:clear + restart."
echo "==> Respaldos creados con sufijo .bak-${TS}"
