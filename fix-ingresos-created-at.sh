#!/usr/bin/env bash
# ============================================================================
#  ingresos.created_at / updated_at — quitar ON UPDATE CURRENT_TIMESTAMP.
#
#  Problema:
#    `ingresos.created_at` está declarada como
#        timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
#    así que CUALQUIER update de la fila reescribe la fecha de creación del pago.
#    `CronController::refreshCorteIntertTV()` corre cada 15 minutos y hace
#    `$ingreso->save()` sobre todos los pagos del día que siguen pendientes de
#    revalidación, de modo que la hora del recibo se corre sola durante todo el
#    día (la tirilla imprime `date('H:i', ingresos.created_at)`).
#
#  Operaciones (idempotentes):
#    1) ingresos.created_at -> quita ON UPDATE (conserva el DEFAULT)
#    2) ingresos.updated_at -> conserva ON UPDATE (ahí sí corresponde)
#    3) --repair            -> OPCIONAL: restaura los created_at ya corrompidos
#                             usando ingresos_factura.created_at, que guarda el
#                             momento real en que se asentó el pago.
#
#  Uso:
#     ./fix-ingresos-created-at.sh                        # esquema, todas las BDs
#     ./fix-ingresos-created-at.sh integra_interfibrasas  # esquema, una BD
#     ./fix-ingresos-created-at.sh --dry-run              # solo reporta
#     ./fix-ingresos-created-at.sh --repair               # esquema + reparar datos
# ============================================================================
set -euo pipefail

cd "$(dirname "$0")"

MYSQL_CONTAINER="${MYSQL_CONTAINER:-int-istinge-mysql-1}"
ROOT_PASS="$(grep -E '^MYSQL_ROOT_PASSWORD=' infra.env | head -1 | cut -d= -f2- | tr -d '"'"'"'"')"
[ -n "$ROOT_PASS" ] || { echo "ERROR: no encontré MYSQL_ROOT_PASSWORD en infra.env"; exit 1; }

DRY_RUN=0
REPAIR=0
ARGS=()
for a in "$@"; do
  case "$a" in
    --dry-run) DRY_RUN=1 ;;
    --repair)  REPAIR=1 ;;
    *)         ARGS+=("$a") ;;
  esac
done

dm(){ docker exec -i "$MYSQL_CONTAINER" mysql -uroot -p"$ROOT_PASS" "$@"; }

table_exists(){ [ "$(dm -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$1' AND table_name='$2';")" = "1" ]; }
tiene_on_update(){
  [ "$(dm -N -B -e "SELECT COUNT(*) FROM information_schema.columns
       WHERE table_schema='$1' AND table_name='ingresos' AND column_name='$2'
       AND EXTRA LIKE '%on update CURRENT_TIMESTAMP%';")" = "1" ]
}

if [ "${#ARGS[@]}" -gt 0 ]; then
  DBS=("${ARGS[@]}")
else
  mapfile -t DBS < <(dm -N -B -e "SHOW DATABASES LIKE 'integra\\_%';")
fi

[ "${#DBS[@]}" -gt 0 ] || { echo "No hay BDs integra_* para procesar."; exit 0; }

for DB in "${DBS[@]}"; do
  echo "==> $DB"

  if ! table_exists "$DB" "ingresos"; then
    echo "    (sin tabla ingresos)"
    continue
  fi

  # --- 1) quitar ON UPDATE de created_at ------------------------------------
  if tiene_on_update "$DB" "created_at"; then
    if [ "$DRY_RUN" = "1" ]; then
      echo "    [created_at] tiene ON UPDATE -> se quitaría"
    else
      dm "$DB" -e "ALTER TABLE ingresos MODIFY COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;"
      echo "    [created_at] ON UPDATE removido"
    fi
  else
    echo "    [created_at] ya está correcta, salto"
  fi

  # --- 2) reparar los created_at ya corrompidos -----------------------------
  # ingresos_factura.created_at guarda el momento real del asiento: esa tabla no
  # la toca refreshCorteIntertTV(). Solo tocamos pagos de tipo 1 (contra factura)
  # con más de 5 minutos de desfase.
  AFECTADOS="$(dm -N -B "$DB" -e "
    SELECT COUNT(*) FROM ingresos i
    JOIN (SELECT ingreso, MIN(created_at) det FROM ingresos_factura GROUP BY ingreso) d
      ON d.ingreso = i.id
    WHERE i.tipo = 1 AND ABS(TIMESTAMPDIFF(MINUTE, i.created_at, d.det)) > 5;")"

  if [ "$AFECTADOS" = "0" ]; then
    echo "    [datos] sin pagos desfasados"
  elif [ "$REPAIR" != "1" ] || [ "$DRY_RUN" = "1" ]; then
    echo "    [datos] $AFECTADOS pagos con created_at desfasado (usá --repair para corregirlos)"
  else
    dm "$DB" -e "
      UPDATE ingresos i
      JOIN (SELECT ingreso, MIN(created_at) det FROM ingresos_factura GROUP BY ingreso) d
        ON d.ingreso = i.id
      SET i.created_at = d.det
      WHERE i.tipo = 1 AND ABS(TIMESTAMPDIFF(MINUTE, i.created_at, d.det)) > 5;"
    echo "    [datos] $AFECTADOS pagos con created_at restaurado"
  fi
done

echo "Listo."
