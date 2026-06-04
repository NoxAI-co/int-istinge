#!/usr/bin/env bash
# ============================================================================
#  int-istinge — Remontar SOLO la BD de un cliente YA existente.
#
#  Úsalo cuando el cliente ya está bien desplegado (contenedor + .env con su
#  APP_KEY, credenciales y dominio) pero se importó el dump equivocado.
#  NO toca el .env, ni el APP_KEY, ni el dominio, ni recrea el contenedor:
#  solo vacía la BD, reimporta el dump correcto, reinyecta las tablas de
#  framework (sessions/cache/jobs/failed_jobs) y limpia la caché de la app.
#
#  Conserva el MISMO usuario/password de la BD (los del .env del cliente),
#  así que la app sigue conectando sin cambios.
#
#  Uso:
#     ./reset-client-db.sh <cliente> <nombre-del-dump.sql>
#  Ej:
#     ./reset-client-db.sh infinitylan bd_infinitylan.sql
#     ./reset-client-db.sh acme acme_2026-06.sql
#
#  Pasás solo el NOMBRE del archivo (2º argumento); el script siempre lo
#  busca dentro de la carpeta dumps/ (ajusta DUMP_DIR si está en otra ruta).
#
#  Requiere: stack infra arriba (MySQL) e infra.env con MYSQL_ROOT_PASSWORD.
# ============================================================================
set -euo pipefail

cd "$(dirname "$0")"

CLIENT="${1:-}"
DUMP_NAME="${2:-}"
DUMP_DIR="${DUMP_DIR:-dumps}"
[ -n "$CLIENT" ]    || { echo "ERROR: falta el cliente. Uso: ./reset-client-db.sh <cliente> <nombre-del-dump.sql>"; exit 1; }
[ -n "$DUMP_NAME" ] || { echo "ERROR: falta el nombre del dump. Uso: ./reset-client-db.sh <cliente> <nombre-del-dump.sql>"; exit 1; }
DUMP_RAW="${DUMP_DIR}/$(basename "$DUMP_NAME")"
[ -f "$DUMP_RAW" ]  || { echo "ERROR: no encontré el dump '$DUMP_RAW' en la carpeta ${DUMP_DIR}/"; exit 1; }
DUMP="$(readlink -f "$DUMP_RAW")"

DB="integra_${CLIENT}"
MYSQL_CONTAINER="${MYSQL_CONTAINER:-int-istinge-mysql-1}"
ENVFILE="clientes/${CLIENT}/.env"

# --- Validaciones ---
[ -f "$DUMP" ]    || { echo "ERROR: no existe el dump: $DUMP"; exit 1; }
[ -f "$ENVFILE" ] || { echo "ERROR: el cliente '$CLIENT' no existe ($ENVFILE). Usa new-client.sh para darlo de alta."; exit 1; }
[ -f infra.env ]  || { echo "ERROR: falta infra.env con MYSQL_ROOT_PASSWORD"; exit 1; }

ROOT_PASS="$(grep -E '^MYSQL_ROOT_PASSWORD=' infra.env | head -1 | cut -d= -f2- | tr -d '"'"'"'"')"
[ -n "$ROOT_PASS" ] || { echo "ERROR: no encontré MYSQL_ROOT_PASSWORD en infra.env"; exit 1; }
dm(){ docker exec -i "$MYSQL_CONTAINER" mysql -uroot -p"$ROOT_PASS" "$@"; }

# --- Gate del dump: trae 'usuarios' y no está corrupto ---
echo "==> Verificando el dump"
grep -qi 'CREATE TABLE `usuarios`' "$DUMP" || { echo "ERROR: el dump no trae la tabla 'usuarios'"; exit 1; }
grep -q 'alert-danger' "$DUMP" && { echo "ERROR: el dump trae HTML de error (export corrupto). Re-exporta con mysqldump."; exit 1; }

# --- Confirmación: esto BORRA la BD actual del cliente ---
echo "==> Cliente: $CLIENT   BD: $DB   Dump: $DUMP"
read -r -p "Esto BORRA la BD '$DB' y la reimporta. ¿Continuar? [y/N] " ans
[[ "$ans" =~ ^[yY]$ ]] || { echo "Cancelado."; exit 0; }

# --- 1) Vaciar BD (drop + create), conservando el usuario/credenciales ---
echo "==> Vaciando BD: $DB"
dm -e "DROP DATABASE IF EXISTS ${DB};
CREATE DATABASE ${DB} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# --- 2) Importar dump correcto (quita CREATE DATABASE/USE internos) ---
echo "==> Importando dump correcto (puede tardar varios minutos)"
sed -E '/^CREATE DATABASE/d; /^USE /d' "$DUMP" | dm "$DB"

# --- 3) Tablas de framework (sesión/caché/colas, driver database) ---
echo "==> Inyectando tablas de framework (Laravel 7)"
dm "$DB" <<'SQL'
CREATE TABLE IF NOT EXISTS sessions (
  id VARCHAR(255) NOT NULL PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  payload TEXT NOT NULL,
  last_activity INT NOT NULL,
  INDEX sessions_user_id_index (user_id),
  INDEX sessions_last_activity_index (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cache (
  `key` VARCHAR(255) NOT NULL PRIMARY KEY,
  value MEDIUMTEXT NOT NULL,
  expiration INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  queue VARCHAR(255) NOT NULL,
  payload LONGTEXT NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL,
  reserved_at INT UNSIGNED NULL,
  available_at INT UNSIGNED NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  INDEX jobs_queue_index (queue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS failed_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uuid VARCHAR(255) NOT NULL UNIQUE,
  connection TEXT NOT NULL,
  queue TEXT NOT NULL,
  payload LONGTEXT NOT NULL,
  exception LONGTEXT NOT NULL,
  failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL

# --- 4) Limpiar caché de la app (la sesión/config vieja apuntaba a la BD anterior) ---
echo "==> Limpiando caché de la app"
docker compose -p "$CLIENT" -f docker-compose.client.yml exec -T app php artisan config:clear || true
docker compose -p "$CLIENT" -f docker-compose.client.yml exec -T app php artisan cache:clear  || true

echo ""
echo "==> Listo. BD de '$CLIENT' remontada con el dump correcto."
echo "    BD: ${DB}  (credenciales SIN cambios, en ${ENVFILE})"
