#!/usr/bin/env bash
# ============================================================================
#  int-istinge — Migración multi-tenant de la carpeta legacy "documentos"
#  hacia ADJUNTOS_FOLDER (por defecto "adjuntos") en el bucket de Contabo.
#
#  Contexto: históricamente los adjuntos de contratos/radicados se subían a la
#  carpeta "documentos", pero el resto del sistema usa env('ADJUNTOS_FOLDER').
#  Tras unificar el código en 'adjuntos', este script mueve los archivos que
#  quedaron en "documentos" para que el ojito los siga encontrando.
#
#  Igual que scheduler-all.sh: descubre TODOS los contenedores 'app' (uno por
#  cliente) y corre el comando Artisan DENTRO de cada uno, así usa el .env y las
#  credenciales de Contabo de ese cliente. No hay que pasar el cliente a mano.
#
#  Uso:
#     ./migrar-documentos-adjuntos-all.sh              # DRY-RUN (solo lista, no toca nada)
#     ./migrar-documentos-adjuntos-all.sh --run        # copia documentos -> adjuntos
#     ./migrar-documentos-adjuntos-all.sh --run --delete   # copia y borra el origen
#
#  Recomendado: correr primero SIN flags (dry-run), revisar el log, y recién
#  entonces con --run. Usar --delete solo cuando confirmes que todo copió bien.
#
#  El comando es idempotente: si el archivo ya existe en destino, lo omite.
# ============================================================================
set -uo pipefail
# No usamos 'set -e': si un cliente falla, los demás deben seguir.

# --- Flags que se pasan tal cual al comando Artisan --------------------------
ARTISAN_FLAGS=""
for arg in "$@"; do
  case "$arg" in
    --run|--delete) ARTISAN_FLAGS="$ARTISAN_FLAGS $arg" ;;
    *) echo "Flag desconocido: $arg (permitidos: --run, --delete)"; exit 2 ;;
  esac
done

LOG=/var/log/int-istinge-migrar-adjuntos.log
touch "$LOG" 2>/dev/null || LOG=/dev/stderr

MODE="DRY-RUN"
case "$ARTISAN_FLAGS" in *--run*) MODE="EJECUCIÓN";; esac

echo "===== $(date '+%Y-%m-%d %H:%M:%S %z') :: migrar-documentos-adjuntos ($MODE)$ARTISAN_FLAGS =====" | tee -a "$LOG"

# Auto-descubre TODOS los contenedores 'app' en ejecución (uno por cliente).
containers="$(docker ps \
              --filter "label=com.docker.compose.service=app" \
              --filter "status=running" \
              --format '{{.Names}}')"

if [ -z "$containers" ]; then
  echo "  (sin contenedores 'app' en ejecución)" | tee -a "$LOG"
  exit 0
fi

# Secuencial (no en paralelo) para que el log quede legible por cliente.
rc_total=0
for c in $containers; do
  echo "  -> ${c}" | tee -a "$LOG"
  docker exec "$c" php artisan contabo:migrar-documentos-adjuntos $ARTISAN_FLAGS --no-interaction 2>&1 | tee -a "$LOG"
  rc=${PIPESTATUS[0]}
  if [ "$rc" -ne 0 ]; then
    echo "     [!] ${c} terminó con código $rc (hubo fallos, revisar arriba)" | tee -a "$LOG"
    rc_total=1
  fi
done

echo "===== fin migración ($MODE). Log: $LOG =====" | tee -a "$LOG"
exit $rc_total
