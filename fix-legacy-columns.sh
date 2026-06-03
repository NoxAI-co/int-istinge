#!/usr/bin/env bash
# ============================================================================
#  Ajustes a tablas legacy para BDs `integra_*` existentes.
#
#  Operaciones (todas idempotentes):
#    1) instances.access_token -> agrega TEXT NULL si no existe
#    2) radicados.firma        -> hace la columna nullable si está NOT NULL
#
#  Uso:
#     ./fix-legacy-columns.sh                 # todas las BDs integra_*
#     ./fix-legacy-columns.sh integra_acme    # solo una BD
# ============================================================================
set -euo pipefail

cd "$(dirname "$0")"

MYSQL_CONTAINER="${MYSQL_CONTAINER:-int-istinge-mysql-1}"
ROOT_PASS="$(grep -E '^MYSQL_ROOT_PASSWORD=' infra.env | head -1 | cut -d= -f2- | tr -d '"'"'"'"')"
[ -n "$ROOT_PASS" ] || { echo "ERROR: no encontré MYSQL_ROOT_PASSWORD en infra.env"; exit 1; }

dm(){ docker exec -i "$MYSQL_CONTAINER" mysql -uroot -p"$ROOT_PASS" "$@"; }

table_exists(){ [ "$(dm -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$1' AND table_name='$2';")" = "1" ]; }
column_exists(){ [ "$(dm -N -B -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$1' AND table_name='$2' AND column_name='$3';")" = "1" ]; }
column_is_nullable(){ [ "$(dm -N -B -e "SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema='$1' AND table_name='$2' AND column_name='$3';")" = "YES" ]; }
column_type(){ dm -N -B -e "SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema='$1' AND table_name='$2' AND column_name='$3';"; }

# Lista de BDs a procesar: argumento explícito o todas las integra_*
if [ $# -gt 0 ]; then
  DBS=("$@")
else
  mapfile -t DBS < <(dm -N -B -e "SHOW DATABASES LIKE 'integra\\_%';")
fi

[ "${#DBS[@]}" -gt 0 ] || { echo "No hay BDs integra_* para procesar."; exit 0; }

for DB in "${DBS[@]}"; do
  echo "==> $DB"

  # --- 1) instances.access_token --------------------------------------------
  if table_exists "$DB" "instances"; then
    if column_exists "$DB" "instances" "access_token"; then
      echo "    [instances.access_token] ya existe, salto"
    else
      dm "$DB" -e "ALTER TABLE instances ADD COLUMN access_token TEXT NULL AFTER uuid_whatsapp;"
      echo "    [instances.access_token] agregada"
    fi
  else
    echo "    (sin tabla instances)"
  fi

  # --- 2) radicados.firma nullable ------------------------------------------
  if table_exists "$DB" "radicados"; then
    if ! column_exists "$DB" "radicados" "firma"; then
      echo "    (radicados.firma no existe, salto)"
    elif column_is_nullable "$DB" "radicados" "firma"; then
      echo "    [radicados.firma] ya es nullable, salto"
    else
      TYPE="$(column_type "$DB" "radicados" "firma")"
      dm "$DB" -e "SET SESSION sql_mode=''; ALTER TABLE radicados MODIFY firma ${TYPE} NULL DEFAULT NULL;"
      echo "    [radicados.firma] ahora ${TYPE} NULL DEFAULT NULL"
    fi
  else
    echo "    (sin tabla radicados)"
  fi
done

echo "==> Listo."
