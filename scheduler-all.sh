#!/usr/bin/env bash
# ============================================================================
#  int-istinge — Disparador multi-tenant del scheduler de Laravel.
#
#  El cron REAL vive en el HOST (el VPS), NO dentro de los contenedores.
#  Dentro de cada contenedor solo corre Apache/PHP; este script descubre
#  todos los contenedores 'app' (uno por cliente) y en cada uno ejecuta
#  `php artisan schedule:run`. Laravel decide internamente qué tarea toca
#  según la hora (ver el método schedule() en app/Console/Kernel.php).
#
#  Como el comando corre DENTRO del contenedor, usa automáticamente el .env
#  y la BD de ese cliente: no hay que pasar el cliente por parámetro.
#
#  Instalación (1 sola vez, en el crontab del HOST):
#     chmod +x /ruta/al/int-istinge/scheduler-all.sh
#     crontab -e
#     # agregar (corre cada minuto; Laravel decide la hora real de cada tarea):
#     * * * * * /ruta/al/int-istinge/scheduler-all.sh >/dev/null 2>&1
#
#  Requisitos:
#   - El usuario del crontab debe poder hacer `docker exec` (grupo 'docker').
#   - El servicio de compose debe llamarse 'app' (label que filtramos abajo).
#
#  Ventaja: levantás un cliente nuevo con ./deploy.sh y queda incluido solo.
#  Nunca hay que tocar este script ni el crontab al agregar clientes/tareas.
# ============================================================================
set -uo pipefail

# No usamos 'set -e': si un cliente falla, los demás deben seguir corriendo.

LOG=/var/log/int-istinge-scheduler.log

# Aseguramos que el log exista y sea escribible (si no, caemos a stderr).
touch "$LOG" 2>/dev/null || LOG=/dev/stderr

echo "===== $(date '+%Y-%m-%d %H:%M:%S %z') :: scheduler-all run =====" >> "$LOG"

# Auto-descubre TODOS los contenedores 'app' en ejecución (uno por cliente).
containers="$(docker ps \
              --filter "label=com.docker.compose.service=app" \
              --filter "status=running" \
              --format '{{.Names}}')"

if [ -z "$containers" ]; then
  echo "  (sin contenedores 'app' en ejecución)" >> "$LOG"
  exit 0
fi

# Por cada cliente, dispara schedule:run en paralelo (&) y al final espera.
for c in $containers; do
  {
    echo "  -> ${c}" >> "$LOG"
    docker exec "$c" php artisan schedule:run --no-interaction >> "$LOG" 2>&1
  } &
done

wait
