#!/usr/bin/env bash
  # Agrega columna access_token (TEXT NULL) a `instances` en todas las BDs.
  set -euo pipefail
  cd "$(dirname "$0")"

  MYSQL_CONTAINER="${MYSQL_CONTAINER:-int-istinge-mysql-1}"

  [ -f infra.env ] || { echo "ERROR: falta infra.env con MYSQL_ROOT_PASSWORD"; exit 1; }
  # shellcheck disable=SC1091
  . ./infra.env
  : "${MYSQL_ROOT_PASSWORD:?ERROR: MYSQL_ROOT_PASSWORD vacío en infra.env}"

  for dir in clientes/*/; do
    client="$(basename "$dir")"
    envfile="${dir}.env"
    [ -f "$envfile" ] || { echo "  ! ${client}: sin .env, se omite"; continue; }

    DB="$(grep -E '^[[:space:]]*DB_DATABASE=' "$envfile" | head -1 | cut -d= -f2- | tr -d 
  '[:space:]')"
    [ -n "$DB" ] || { echo "  ! ${client}: DB_DATABASE vacío"; continue; }

    echo "  -> ${client}  (${DB})"
    if docker exec -i "$MYSQL_CONTAINER" mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$DB" \
         -e "ALTER TABLE instances ADD COLUMN access_token TEXT NULL;" 2>/dev/null; then
      echo "     columna agregada"
    else
      echo "     ya existía (o tabla instances no presente)"
    fi
  done

  echo "==> Listo."
