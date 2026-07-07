#!/usr/bin/env bash
# ============================================================================
#  int-istinge — Reconciliación multi-tenant del estatus de facturas.
#
#  Reimpone la invariante  estatus=0 (Cerrada) <=> porpagar()<=0  en todos los
#  clientes. Repara facturas que quedaron "Cerradas" sin pago real (Pagado $0 /
#  Por Pagar > 0), síntoma del bug del cierre en bloque (IngresosController).
#
#  Igual que scheduler-all.sh: descubre los contenedores 'app' (uno por cliente)
#  y corre el comando Artisan DENTRO de cada uno, con su propia BD.
#
#  Uso:
#     ./reconciliar-estatus-facturas-all.sh                # DRY-RUN (solo reporta)
#     ./reconciliar-estatus-facturas-all.sh --run          # reabre las mal cerradas
#     ./reconciliar-estatus-facturas-all.sh --run --cerrar # además cierra pagadas abiertas
#
#  Recomendado: correr primero SIN flags (dry-run) y revisar el log. La opción
#  --cerrar es más agresiva (cambia facturas a Cerrada); usarla solo si se quiere
#  reconciliar en ambos sentidos.
# ============================================================================
set -uo pipefail
# No usamos 'set -e': si un cliente falla, los demás deben seguir.

# --- Flags que se pasan tal cual al comando Artisan --------------------------
ARTISAN_FLAGS=""
for arg in "$@"; do
  case "$arg" in
    --run|--cerrar) ARTISAN_FLAGS="$ARTISAN_FLAGS $arg" ;;
    --desde=*|--empresa=*) ARTISAN_FLAGS="$ARTISAN_FLAGS $arg" ;;
    *) echo "Flag desconocido: $arg (permitidos: --run, --cerrar, --desde=YYYY-MM-DD, --empresa=ID)"; exit 2 ;;
  esac
done

LOG=/var/log/int-istinge-reconciliar-facturas.log
touch "$LOG" 2>/dev/null || LOG=/dev/stderr

MODE="DRY-RUN"
case "$ARTISAN_FLAGS" in *--run*) MODE="EJECUCIÓN";; esac

echo "===== $(date '+%Y-%m-%d %H:%M:%S %z') :: reconciliar-estatus-facturas ($MODE)$ARTISAN_FLAGS =====" | tee -a "$LOG"

containers="$(docker ps \
              --filter "label=com.docker.compose.service=app" \
              --filter "status=running" \
              --format '{{.Names}}')"

if [ -z "$containers" ]; then
  echo "  (sin contenedores 'app' en ejecución)" | tee -a "$LOG"
  exit 0
fi

for c in $containers; do
  echo "  -> ${c}" | tee -a "$LOG"
  docker exec "$c" php artisan facturas:reconciliar-estatus $ARTISAN_FLAGS --no-interaction 2>&1 | tee -a "$LOG"
done

echo "===== fin reconciliación ($MODE). Log: $LOG =====" | tee -a "$LOG"
