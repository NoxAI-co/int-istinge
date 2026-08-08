#!/usr/bin/env bash
# ============================================================================
#  int-istinge — Watchdog de schedule:run colgados.
#
#  Un `php artisan schedule:run` normal dura segundos; un barrido de corte
#  pesado, media hora como mucho. Si uno lleva más de MAX_SECS vivo es que
#  quedó colgado (p. ej. una conexión a MikroTik que nunca respondió) y, como
#  las tareas corren como closures dentro del propio proceso, no hay hijo que
#  matar: el schedule:run entero queda zombi. Eso además frena los deploys,
#  que esperan a que el "barrido" termine (pasó el 08-08-2026: CortarFacturas
#  colgado 16 horas en un cliente demoró/omitió todos los rollouts del día).
#
#  Este script mata, dentro de cada contenedor *-app-1, los schedule:run con
#  más de MAX_SECS de vida. El cliente docker exec del host muere solo al caer
#  su proceso. El mutex de withoutOverlapping expira a los 60 min (Kernel.php),
#  así que la tarea vuelve a correr sola en la siguiente ventana.
#
#  Instalación (cron del host, cada 15 min):
#    */15 * * * * /opt/integra/int-istinge/watchdog-scheduler.sh >> /var/log/integra-scheduler-watchdog.log 2>&1
# ============================================================================
set -uo pipefail

MAX_SECS="${MAX_SECS:-7200}"   # 2 horas

matados=0
for c in $(docker ps --format '{{.Names}}' | grep -- '-app-1$'); do
  # PIDs de schedule:run con más de MAX_SECS dentro del contenedor
  pids="$(docker exec "$c" sh -c "ps -eo pid,etimes,cmd 2>/dev/null" 2>/dev/null \
          | awk -v max="$MAX_SECS" '/schedule:run/ && !/awk/ && $2 > max {print $1":"$2}')"
  [ -z "$pids" ] && continue

  for entry in $pids; do
    pid="${entry%%:*}"
    secs="${entry##*:}"
    echo "$(date '+%F %T') ${c}: schedule:run PID ${pid} colgado hace ${secs}s (> ${MAX_SECS}s), matando"
    docker exec "$c" kill -9 "$pid" 2>/dev/null
    matados=$((matados + 1))
  done
done

[ "$matados" -gt 0 ] && echo "$(date '+%F %T') total procesos matados: ${matados}"
exit 0
