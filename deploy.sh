#!/usr/bin/env bash
# ============================================================================
#  int-istinge — Rollout de cambios de código a TODOS los clientes.
#
#  Hace, en orden:
#    0) espera            -> si hay un barrido largo del scheduler en curso
#    1) git pull          -> trae el código nuevo
#    2) docker build      -> reconstruye la imagen integra-int:<sha> y :latest
#    3) canario           -> recrea UN cliente y verifica que responda
#    4) resto de clientes -> recrea cada uno con la imagen nueva
#
#  Uso:
#    ./deploy.sh                 # despliega a TODOS los clientes (con canario)
#    ./deploy.sh acme            # despliega solo a un cliente (sin canario)
#    SKIP_BUILD=1 ./deploy.sh    # salta el build (p. ej. solo cambió un .env)
#
#  Variables opcionales:
#    ROLLOUT_PARALLEL=5    clientes desplegados a la vez (default: 5)
#    CANARY_CLIENT=acme    cliente a usar como canario (default: el primero)
#    CANARY_CLIENT=none    desactiva el canario
#    IMAGE_TAG=abc1234     despliega una imagen ya construida (ROLLBACK)
#    WAIT_SCHEDULER=0      no esperar al scheduler
#    FORCE=1               desplegar aunque el scheduler siga ocupado
#    DRY_RUN=1             muestra qué haría (no toca git, ni imágenes, ni contenedores)
#
#  Rollback:
#    IMAGE_TAG=<sha_anterior> SKIP_BUILD=1 ./deploy.sh
#    (los tags disponibles: docker images integra-int)
#
#  El DOMAIN de cada cliente se deduce de APP_URL en su clientes/<x>/.env.
# ============================================================================
set -euo pipefail

# Bash NO lee el script entero de una: lo va leyendo del disco por offsets a
# medida que lo ejecuta. El `git pull` de más abajo reescribe este mismo
# archivo, así que bash seguiría leyendo desde una posición que ya no
# corresponde y ejecutaría líneas cortadas a la mitad. Nos copiamos a un
# temporal y seguimos la corrida desde ahí, que nadie va a tocar.
REPO_DIR="${DEPLOY_REPO_DIR:-$(cd "$(dirname "$0")" && pwd)}"
if [ "${DEPLOY_REEXEC:-0}" != "1" ]; then
  _self="$(mktemp)"
  cat "$0" > "$_self"
  DEPLOY_REEXEC=1 DEPLOY_REPO_DIR="$REPO_DIR" exec bash "$_self" "$@"
fi
# Ya corriendo desde la copia: la borramos al salir, pase lo que pase.
trap 'rm -f "$0"' EXIT

cd "$REPO_DIR"

# ----------------------------------------------------------------------------
# Lock: UN SOLO deploy a la vez.
#
# El 06-08-2026 corrieron a la vez el workflow de GitHub y un rollout manual:
# ambos recreando contenedores del mismo cliente -> "Conflict. The container
# name is already in use" y flota con imágenes mezcladas. flock lo hace
# imposible: el segundo deploy se rehúsa a arrancar (o espera, si se pide).
#
#   WAIT_LOCK=600 ./deploy.sh   espera hasta 600s a que el otro termine
#   DEPLOY_LOCK=/otra/ruta.lock cambia el archivo de lock
#
# El lock lo libera el kernel al morir el proceso: no quedan locks huérfanos.
# ----------------------------------------------------------------------------
LOCK_FILE="${DEPLOY_LOCK:-/var/lock/int-istinge-deploy.lock}"
if [ "${DEPLOY_LOCK_HELD:-0}" != "1" ] && command -v flock >/dev/null 2>&1; then
  if [ -n "${WAIT_LOCK:-}" ]; then
    _flock_opts=(-w "$WAIT_LOCK")
  else
    _flock_opts=(-n)
  fi

  set +e
  DEPLOY_LOCK_HELD=1 DEPLOY_REEXEC=1 DEPLOY_REPO_DIR="$REPO_DIR" \
    flock -E 99 "${_flock_opts[@]}" "$LOCK_FILE" bash "$0" "$@"
  _rc=$?
  set -e

  if [ "$_rc" = "99" ]; then
    echo "ERROR: ya hay un deploy en curso en este servidor."
    echo "       $(cat "$LOCK_FILE" 2>/dev/null || echo 'sin datos del proceso que lo tomó')"
    echo "       Esperá a que termine, o relanzá con WAIT_LOCK=900 ./deploy.sh"
  fi
  exit "$_rc"
fi

# Dejamos rastro de quién tiene el lock (lo lee el que se topa con él).
_lock_from="${SSH_CLIENT:-}"; _lock_from="${_lock_from%% *}"; [ -n "$_lock_from" ] || _lock_from="local"
echo "tomado por PID $$ el $(date '+%d-%m-%Y %H:%M:%S') desde $_lock_from" > "$LOCK_FILE" 2>/dev/null || true

ONLY_CLIENT="${1:-}"

# Con DRY_RUN=1 las acciones que modifican algo se imprimen en vez de correr.
run() {
  if [ "${DRY_RUN:-0}" = "1" ]; then
    echo "     [dry-run] $*"
  else
    "$@"
  fi
}

# ID del contenedor 'app' de un cliente.
#
# NO usamos `docker compose ps`: el compose file interpola ${CLIENT:?} y
# ${ASSET_URL:?}, así que sin esas variables exportadas el comando falla y
# devuelve vacío. Por eso la preservación de logs de más abajo nunca llegaba a
# encontrar el contenedor viejo. Los labels que pone compose son la vía directa
# (son los mismos que usa scheduler-all.sh para descubrir la flota).
client_cid() {
  docker ps -q \
    --filter "label=com.docker.compose.project=$1" \
    --filter "label=com.docker.compose.service=app" \
    2>/dev/null | head -1
}

# ----------------------------------------------------------------------------
# No recrear el contenedor de un cliente a mitad de su barrido del scheduler.
#
# El cron de corte (CronController::CortarFacturas) recorre cientos de contratos
# y puede tardar más de media hora. Si matamos el contenedor a la mitad, quedan
# contratos metidos en la lista 'morosos' del MikroTik pero sin state='disabled'
# en la BD (o al revés): el cliente se queda sin internet y el sistema cree que
# está habilitado.
#
# El scheduler del host lanza `docker exec <cliente>-app-1 php artisan
# schedule:run`, así que filtramos por el nombre del contenedor: que otro cliente
# esté cortando no es razón para frenar el rollout de éste. Y un `schedule:run`
# normal dura segundos, así que solo esperamos por los que llevan mucho tiempo
# vivos, que son los barridos de verdad.
# ----------------------------------------------------------------------------
scheduler_busy_for() {
  local client="$1" line pid et
  while read -r line; do
    pid="${line%% *}"
    et="$(ps -o etimes= -p "$pid" 2>/dev/null | tr -d ' ')"
    if [ -n "$et" ] && [ "$et" -gt "${SCHEDULER_BUSY_SECS:-120}" ]; then
      return 0
    fi
  done < <(pgrep -af "schedule:run" 2>/dev/null | grep -- "${client}-app-1" || true)
  return 1
}

# Espera a que el cliente quede libre. Devuelve 1 si se agotó el tiempo.
wait_for_client_scheduler() {
  local client="$1"
  local waited=0
  local timeout="${SCHEDULER_TIMEOUT:-900}"

  [ "${WAIT_SCHEDULER:-1}" != "1" ] && return 0

  while scheduler_busy_for "$client"; do
    if [ "$waited" -ge "$timeout" ]; then
      if [ "${FORCE:-0}" = "1" ]; then
        echo "     ! sigue ocupado tras ${waited}s — FORCE=1, lo recreo igual"
        return 0
      fi
      echo "     ! ${client}: barrido del scheduler activo hace más de ${waited}s, se omite."
      echo "       Recrearlo ahora puede dejar contratos a medio cortar (en 'morosos'"
      echo "       dentro del MikroTik pero sin state='disabled' en la BD)."
      return 1
    fi
    [ "$waited" -eq 0 ] && echo "     esperando a que termine el barrido de ${client}..."
    sleep 10
    waited=$((waited + 10))
    # Latido cada 30s: sin esto la espera es MUDA hasta 15 minutos y la sesión
    # SSH del deploy por GitHub Actions muere con "broken pipe" a mitad del
    # rollout (pasó el 06-08-2026: 5 de 35 clientes desplegados). Además le
    # dice al operador que el deploy sigue vivo, no colgado.
    if [ $((waited % 30)) -eq 0 ]; then
      echo "     ... ${client} sigue en barrido (${waited}s de ${timeout}s)"
    fi
  done
  [ "$waited" -gt 0 ] && echo "     libre tras ${waited}s"
  return 0
}

if [ "${SKIP_BUILD:-0}" != "1" ]; then
  echo "==> 1/4  git pull"
  # Si el servidor tiene commits propios sin subir, --ff-only aborta acá.
  # Es a propósito: mejor detenerse que mezclar código a ciegas en producción.
  if ! run git pull --ff-only; then
    echo "ERROR: el pull no pudo avanzar en fast-forward."
    echo "       Suele ser que este servidor tiene commits locales sin subir:"
    git log --oneline @{u}..HEAD 2>/dev/null | sed 's/^/         /' || true
    echo "       Subilos (git push) o descartalos (git reset --hard @{u}) y reintentá."
    exit 1
  fi

  # Estamos corriendo desde una copia hecha ANTES del pull, así que si el pull
  # trajo una versión nueva de este script hay que relanzarla: de lo contrario
  # cada cambio a deploy.sh recién se aplicaría en el deploy siguiente, y el
  # rollout de hoy usaría la lógica de ayer.
  # DEPLOY_SELFUPDATED corta cualquier posibilidad de relanzarse en bucle.
  if [ "${DEPLOY_SELFUPDATED:-0}" != "1" ] && ! cmp -s "$0" "$REPO_DIR/deploy.sh"; then
    echo "==> deploy.sh se actualizó con el pull; relanzando con la versión nueva"
    _nuevo="$(mktemp)"
    cat "$REPO_DIR/deploy.sh" > "$_nuevo"
    rm -f "$0"
    DEPLOY_SELFUPDATED=1 DEPLOY_REEXEC=1 DEPLOY_REPO_DIR="$REPO_DIR" \
      exec bash "$_nuevo" "$@"
  fi

  # Etiquetamos la imagen con el commit desplegado, además de 'latest'. Sin esto
  # 'latest' se sobrescribe y no queda ninguna imagen a la cual volver.
  IMAGE_TAG="${IMAGE_TAG:-$(git rev-parse --short HEAD)}"

  echo "==> 2/4  docker build -t integra-int:${IMAGE_TAG}"
  run docker build -t "integra-int:${IMAGE_TAG}" -t integra-int:latest .
else
  IMAGE_TAG="${IMAGE_TAG:-latest}"
  echo "==> build saltado (SKIP_BUILD=1) — usando integra-int:${IMAGE_TAG}"
fi

export IMAGE_TAG

# ----------------------------------------------------------------------------
# Despliegue de UN cliente. Devuelve 1 si el cliente debe omitirse.
#
# Segundo argumento (modo frente a un barrido del scheduler en curso):
#   wait (default) -> espera hasta SCHEDULER_TIMEOUT a que el barrido termine
#                     (canario, cliente único y fase de reintentos).
#   fast           -> chequeo instantáneo: si está en barrido devuelve 2 sin
#                     esperar. Lo usa la primera pasada del rollout para que
#                     un cliente ocupado no frene la fila; se reintenta al
#                     final, cuando su barrido normalmente ya terminó.
# ----------------------------------------------------------------------------
rollout_client() {
  local client="$1"
  local sched_mode="${2:-wait}"
  local dir="clientes/${client}/"
  local envfile="${dir}.env"

  if [ ! -f "$envfile" ]; then
    echo "  ! ${client}: sin .env, se omite"
    return 1
  fi

  # APP_URL crudo, limpiado de comillas/espacios/comentarios inline/\r.
  local app_url
  app_url="$(grep -E '^[[:space:]]*APP_URL=' "$envfile" | head -1 \
            | sed -E 's/\r$//; s/^[[:space:]]*APP_URL=//; s/[[:space:]]*#.*$//; s/["'"'"']//g; s/[[:space:]]//g')"
  if [ -z "$app_url" ]; then
    echo "  ! ${client}: APP_URL vacío en .env, se omite"
    return 1
  fi

  # Dominio = host de APP_URL.   https://acme.com.co/software -> acme.com.co
  local domain
  domain="$(echo "$app_url" | sed -E 's#^https?://##; s#[/:].*$##')"

  # Toda la flota vive bajo /software (Caddy hace handle_path: /software*).
  # Si APP_URL no termina en /software, Laravel arma URLs absolutas fuera
  # del path que Caddy enruta -> pantalla en blanco / 404 para el cliente.
  case "$app_url" in
    */software|*/software/) ;;
    *)
      echo "  ! ${client}: APP_URL='${app_url}' no termina en /software, se omite"
      echo "    Esperado: https://${domain}/software   (corregí ${envfile} y reintentá)"
      return 1
      ;;
  esac

  # ASSET_URL: por defecto, igual a APP_URL (todos los assets viven en el mismo
  # host bajo /software). Si el .env del cliente lo declara, gana el del .env
  # (caso típico: CDN). Validamos también que termine en /software.
  local asset_url
  asset_url="$(grep -E '^[[:space:]]*ASSET_URL=' "$envfile" | head -1 \
              | sed -E 's/\r$//; s/^[[:space:]]*ASSET_URL=//; s/[[:space:]]*#.*$//; s/["'"'"']//g; s/[[:space:]]//g' || true)"
  [ -z "$asset_url" ] && asset_url="$app_url"
  case "$asset_url" in
    */software|*/software/) ;;
    *)
      echo "  ! ${client}: ASSET_URL='${asset_url}' no termina en /software, se omite"
      echo "    Quitalo de ${envfile} (se deriva de APP_URL) o corregilo y reintentá"
      return 1
      ;;
  esac

  # HESTIA_IP: IP del servidor Hestia que sirve la raíz del dominio (la página
  # web del cliente). Caddy proxea ahí todo lo que NO sea /software*. Default
  # global; se puede sobreescribir por cliente declarándolo en su .env.
  local hestia_ip
  hestia_ip="$(grep -E '^[[:space:]]*HESTIA_IP=' "$envfile" | head -1 \
              | sed -E 's/\r$//; s/^[[:space:]]*HESTIA_IP=//; s/[[:space:]]*#.*$//; s/["'"'"']//g; s/[[:space:]]//g' || true)"
  [ -z "$hestia_ip" ] && hestia_ip="13.140.155.227"

  echo "  -> ${client}  (${domain}, raíz -> hestia ${hestia_ip})"

  # Código 2 = omitido a propósito por tener un barrido en curso, distinto de un
  # error de configuración (1). El loop de arriba los reporta por separado.
  if [ "$sched_mode" = "fast" ]; then
    if scheduler_busy_for "$client"; then
      echo "     barrido del scheduler en curso — diferido para el final del rollout"
      return 2
    fi
  else
    if ! wait_for_client_scheduler "$client"; then
      return 2
    fi
  fi

  # Preservar los logs del contenedor anterior: hasta ahora storage/logs vivía
  # en la capa efímera y se perdía al recrear. Los copiamos a un tmp y, tras
  # levantar el contenedor nuevo (que ya monta el volumen 'logs' persistente),
  # los restauramos para que el módulo de Logs no aparezca vacío tras el deploy.
  local old_cid tmp_logs new_cid
  old_cid="$(client_cid "$client")"
  tmp_logs=""
  if [ -n "$old_cid" ] && [ "${DRY_RUN:-0}" != "1" ]; then
    tmp_logs="$(mktemp -d)"
    docker cp "${old_cid}:/var/www/html/storage/logs/." "$tmp_logs/" 2>/dev/null || true
  fi

  run env CLIENT="$client" DOMAIN="$domain" ASSET_URL="$asset_url" HESTIA_IP="$hestia_ip" \
    docker compose -p "$client" -f docker-compose.client.yml up -d --force-recreate

  if [ -n "$tmp_logs" ] && [ -n "$(ls -A "$tmp_logs" 2>/dev/null)" ]; then
    new_cid="$(client_cid "$client")"
    if [ -n "$new_cid" ]; then
      docker cp "$tmp_logs/." "${new_cid}:/var/www/html/storage/logs/" 2>/dev/null || true
      docker exec "$new_cid" chown -R www-data:www-data /var/www/html/storage/logs 2>/dev/null || true
      echo "     logs preservados del contenedor anterior"
    fi
  fi
  [ -n "$tmp_logs" ] && rm -rf "$tmp_logs"

  LAST_DOMAIN="$domain"
  return 0
}

# ----------------------------------------------------------------------------
# Verifica que la app quedó levantada tras recrear el contenedor.
#
# La autoridad es el CONTENEDOR, no el dominio público: es lo único que el deploy
# controla. Hay clientes cuyo dominio apunta a otro servidor o directamente no
# resuelve (DNS vencido, alta en trámite); si midiéramos por ahí, el deploy
# quedaría en rojo para siempre por algo que ningún rollback arregla.
# El dominio se chequea igual, pero solo como aviso.
#
# Reintenta un rato: tras recrear, Apache tarda unos segundos en levantar.
# ----------------------------------------------------------------------------
check_client_app() {
  local client="$1"
  local domain="$2"
  local tries="${3:-10}"
  local cid ip code="" pub i ok=1

  cid="$(client_cid "$client")"
  if [ -z "$cid" ]; then
    echo "     FALLA: ${client} no tiene contenedor 'app' corriendo"
    return 1
  fi

  ip="$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}} {{end}}' "$cid" 2>/dev/null | awk '{print $1}')"
  if [ -z "$ip" ]; then
    echo "     FALLA: ${client} sin IP en la red docker"
    return 1
  fi

  for i in $(seq 1 "$tries"); do
    code="$(curl -s -o /dev/null -w '%{http_code}' -m 10 "http://${ip}/login" || echo 000)"
    case "$code" in
      200|302) ok=0; break ;;
    esac
    sleep 5
  done

  if [ "$ok" != "0" ]; then
    echo "     FALLA: la app de ${client} no responde dentro del contenedor (HTTP ${code})"
    return 1
  fi

  pub="$(curl -s -o /dev/null -w '%{http_code}' -m 15 "https://${domain}/software/login" || echo 000)"
  case "$pub" in
    200|302) echo "     OK (app ${code}, público ${pub})" ;;
    *)       echo "     OK (app ${code}) — aviso: https://${domain}/software/login devolvió ${pub}, revisar DNS del dominio" ;;
  esac
  return 0
}

# ----------------------------------------------------------------------------
# 3) y 4) rollout
# ----------------------------------------------------------------------------
clients=()
for dir in clientes/*/; do
  client="$(basename "$dir")"
  [ -n "$ONLY_CLIENT" ] && [ "$client" != "$ONLY_CLIENT" ] && continue
  clients+=("$client")
done

if [ "${#clients[@]}" -eq 0 ]; then
  echo "ERROR: no hay clientes para desplegar${ONLY_CLIENT:+ (filtro: $ONLY_CLIENT)}"
  exit 1
fi

# El canario solo aplica al rollout completo: con un cliente explícito no hay
# nada que proteger, y con 'none' se desactiva a propósito.
canary=""
if [ -z "$ONLY_CLIENT" ] && [ "${CANARY_CLIENT:-}" != "none" ]; then
  canary="${CANARY_CLIENT:-${clients[0]}}"
fi

if [ -n "$canary" ]; then
  echo "==> 3/4  canario: ${canary}"
  LAST_DOMAIN=""
  if ! rollout_client "$canary"; then
    echo "ERROR: el canario ${canary} no se pudo desplegar. No sigo con el resto."
    exit 1
  fi
  if ! check_client_app "$canary" "$LAST_DOMAIN"; then
    echo "ERROR: el canario ${canary} no responde con la imagen nueva."
    echo "       El resto de los clientes NO fue tocado y sigue con la imagen anterior."
    echo "       Rollback del canario:  IMAGE_TAG=<sha_anterior> SKIP_BUILD=1 ./deploy.sh ${canary}"
    exit 1
  fi
else
  echo "==> 3/4  sin canario"
fi

# Los clientes se despliegan en paralelo (ROLLOUT_PARALLEL a la vez, default 5):
# el recreate + health check de cada uno es independiente, y en serie 35
# clientes tomaban 12-15 minutos. Cada cliente corre en un subshell con su log
# y su código de salida en archivos propios: los logs no se entremezclan y el
# rc sobrevive al subshell. Los que están a mitad de un barrido del cron no
# esperan ni frenan la fila: se difieren y se reintentan al final (modo wait),
# cuando su barrido normalmente ya terminó.
PARALLEL="${ROLLOUT_PARALLEL:-5}"
[ "${DRY_RUN:-0}" = "1" ] && PARALLEL=1   # dry-run: salida secuencial legible

echo "==> 4/4  rollout (${#clients[@]} cliente(s), imagen integra-int:${IMAGE_TAG}, ${PARALLEL} en paralelo)"
failed=()
skipped=()
deferred=()

tmp_roll="$(mktemp -d)"
# Reemplaza el trap EXIT de arriba (borrar la copia temporal del script), así
# que acá se hacen las dos limpiezas.
trap 'rm -f "$0"; [ -n "${tmp_roll:-}" ] && rm -rf "$tmp_roll"' EXIT

# Corre en subshell (background). rc: 0 ok, 1 config, 2 barrido en curso,
# 3 no respondió el health check. Siempre termina en 0 para no tumbar el
# script (set -e) — el rc real viaja por archivo.
deploy_one() {
  local client="$1" rc=0
  LAST_DOMAIN=""
  rollout_client "$client" fast || rc=$?
  if [ "$rc" -eq 0 ]; then
    # Acá no abortamos: si un cliente puntual no levanta, queremos terminar el
    # rollout y reportarlo al final, no dejar la flota a medio desplegar.
    check_client_app "$client" "$LAST_DOMAIN" 6 || rc=3
  fi
  echo "$rc" > "$tmp_roll/$client.rc"
  return 0
}

pending=()
for client in "${clients[@]}"; do
  [ "$client" = "$canary" ] && continue
  pending+=("$client")
done

running=0
for client in "${pending[@]}"; do
  deploy_one "$client" > "$tmp_roll/$client.log" 2>&1 &
  running=$((running + 1))
  if [ "$running" -ge "$PARALLEL" ]; then
    wait -n || true
    running=$((running - 1))
  fi
done
wait || true

for client in "${pending[@]}"; do
  cat "$tmp_roll/$client.log" 2>/dev/null || true
  rc="$(cat "$tmp_roll/$client.rc" 2>/dev/null || echo 1)"
  case "$rc" in
    0) ;;
    2) deferred+=("$client") ;;
    3) failed+=("$client") ;;
  esac
done

if [ "${#deferred[@]}" -gt 0 ]; then
  echo "==> Reintento de ${#deferred[@]} cliente(s) diferido(s) por barrido en curso: ${deferred[*]}"
  for client in "${deferred[@]}"; do
    LAST_DOMAIN=""
    rc=0
    rollout_client "$client" wait || rc=$?
    if [ "$rc" -eq 2 ]; then
      skipped+=("$client")
      continue
    fi
    [ "$rc" -ne 0 ] && continue
    check_client_app "$client" "$LAST_DOMAIN" 6 || failed+=("$client")
  done
fi

echo "==> Listo. Imagen desplegada: integra-int:${IMAGE_TAG}"
if [ "${#skipped[@]}" -gt 0 ]; then
  echo "!!! ${#skipped[@]} cliente(s) omitido(s) por tener un barrido del cron en curso: ${skipped[*]}"
  echo "    Quedaron con la imagen anterior. Reintentá luego:  ./deploy.sh <cliente>"
fi
if [ "${#failed[@]}" -gt 0 ]; then
  echo "!!! ${#failed[@]} cliente(s) no respondieron tras el deploy: ${failed[*]}"
  echo "    Revisá con: docker compose -p <cliente> -f docker-compose.client.yml logs --tail 50 app"
  exit 1
fi

# Un rollout parcial deja la flota con versiones mezcladas. Salimos en error a
# propósito para que no pase inadvertido, aunque nada esté roto.
[ "${#skipped[@]}" -gt 0 ] && exit 1

docker compose ls
