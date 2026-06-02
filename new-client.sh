#!/usr/bin/env bash
# ============================================================================
#  int-istinge — Alta de un cliente nuevo (todo en un comando).
#
#  Hace: crea BD+usuario, importa el dump, inyecta las tablas de framework,
#        arma el clientes/<cliente>/.env (APP_KEY, APP_URL, DB_*) y levanta
#        el contenedor.
#
#  Uso:
#     ./new-client.sh <cliente> <dominio> <ruta-al-dump.sql>
#  Ej:
#     ./new-client.sh acme acme.com.co dumps/acme.sql
#
#  Requiere: imagen integra-int:latest construida, stack infra arriba (MySQL),
#            e infra.env con MYSQL_ROOT_PASSWORD.
# ============================================================================
set -euo pipefail

CLIENT="${1:?Falta el nombre corto del cliente (ej: acme)}"
DOMAIN="${2:?Falta el dominio (ej: acme.com.co)}"
DUMP="$(readlink -f "${3:?Falta la ruta al dump .sql}")"

cd "$(dirname "$0")"

DB="integra_${CLIENT}"
# El proyecto compose del stack infra se llama por defecto como el dir donde
# vive docker-compose.infra.yml. Si lo arrancás desde este repo (int-istinge),
# el contenedor será 'int-istinge-mysql-1'. Ajustá si usás otro -p.
MYSQL_CONTAINER="${MYSQL_CONTAINER:-int-istinge-mysql-1}"
ENVFILE="clientes/${CLIENT}/.env"

# --- Validaciones ---
[ -f "$DUMP" ] || { echo "ERROR: no existe el dump: $DUMP"; exit 1; }
[ -f "$ENVFILE" ] && { echo "ERROR: el cliente '$CLIENT' ya existe ($ENVFILE)"; exit 1; }
[ -f infra.env ] || { echo "ERROR: falta infra.env con MYSQL_ROOT_PASSWORD"; exit 1; }

ROOT_PASS="$(grep -E '^MYSQL_ROOT_PASSWORD=' infra.env | head -1 | cut -d= -f2- | tr -d '"'"'"'"')"
[ -n "$ROOT_PASS" ] || { echo "ERROR: no encontré MYSQL_ROOT_PASSWORD en infra.env"; exit 1; }
dm(){ docker exec -i "$MYSQL_CONTAINER" mysql -uroot -p"$ROOT_PASS" "$@"; }

# --- Gate del dump: trae 'usuarios' y no está corrupto ---
echo "==> Verificando el dump"
grep -qi 'CREATE TABLE `usuarios`' "$DUMP" || { echo "ERROR: el dump no trae la tabla 'usuarios'"; exit 1; }
grep -q 'alert-danger' "$DUMP" && { echo "ERROR: el dump trae HTML de error (export corrupto). Re-exporta con mysqldump."; exit 1; }

# --- 1) BD + usuario ---
DB_PASS="$(openssl rand -hex 24)"
echo "==> Creando BD y usuario: $DB"
dm -e "CREATE DATABASE IF NOT EXISTS ${DB} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB}'@'%' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB}.* TO '${DB}'@'%';
FLUSH PRIVILEGES;"

# --- 2) Importar dump (quita CREATE DATABASE/USE internos) ---
echo "==> Importando dump (puede tardar varios minutos)"
sed -E '/^CREATE DATABASE/d; /^USE /d' "$DUMP" | dm "$DB"

# --- 3) Tablas de framework (sesión/caché/colas, driver database) ---
# Schemas de Laravel 7 (sin cache_locks — esa tabla la introdujeron en L11).
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

# --- 3b) Deltas de schema sobre el dump ---
# Columnas agregadas después de que se exportó el dump fuente. Tolerante a que
# ya existan (ALTER falla -> seguimos) o a que la tabla no esté en el dump.
echo "==> Aplicando deltas de schema sobre el dump"
dm "$DB" -e "ALTER TABLE instances ADD COLUMN access_token TEXT NULL;" 2>/dev/null \
  || echo "    (instances.access_token ya existía o tabla no presente)"

# --- 4) .env del cliente ---
echo "==> Generando $ENVFILE"
mkdir -p "clientes/${CLIENT}"
cp .env.docker.example "$ENVFILE"
APP_KEY="$(docker run --rm integra-int:latest php artisan key:generate --show)"
# La app vive bajo /software (Caddy hace handle_path: /software*).
# APP_URL y ASSET_URL DEBEN incluir /software, si no Laravel genera URLs
# fuera del path enrutado y el cliente ve pantalla en blanco / 404.
APP_URL_FULL="https://${DOMAIN}/software"
sed -i.bak -E \
  -e "s#^APP_KEY=.*#APP_KEY=${APP_KEY}#" \
  -e "s#^APP_URL=.*#APP_URL=${APP_URL_FULL}#" \
  -e "s#^CLIENTE=.*#CLIENTE=${CLIENT}#" \
  -e "s#^DB_DATABASE=.*#DB_DATABASE=${DB}#" \
  -e "s#^DB_USERNAME=.*#DB_USERNAME=${DB}#" \
  -e "s#^DB_PASSWORD=.*#DB_PASSWORD=${DB_PASS}#" \
  "$ENVFILE"
rm -f "${ENVFILE}.bak"
# ASSET_URL: añadir solo si no vino ya del template, para no duplicar.
if ! grep -qE '^[[:space:]]*ASSET_URL=' "$ENVFILE"; then
  echo "ASSET_URL=${APP_URL_FULL}" >> "$ENVFILE"
else
  sed -i.bak -E "s#^ASSET_URL=.*#ASSET_URL=${APP_URL_FULL}#" "$ENVFILE"
  rm -f "${ENVFILE}.bak"
fi

# --- 5) Levantar el contenedor ---
echo "==> Levantando contenedor"
CLIENT="$CLIENT" DOMAIN="$DOMAIN" ASSET_URL="$APP_URL_FULL" \
  docker compose -p "$CLIENT" -f docker-compose.client.yml up -d

VPS_IP="$(curl -s ifconfig.me 2>/dev/null || echo 'IP-DEL-VPS')"
echo ""
echo "==> Cliente '$CLIENT' desplegado."
echo "    URL:  ${APP_URL_FULL}"
echo "    BD:   ${DB}  (credenciales en ${ENVFILE})"
echo ""
echo "    PASO MANUAL: apunta el DNS (registro A) de ${DOMAIN} -> ${VPS_IP}"
echo "    Caddy emitirá el HTTPS automático cuando el DNS resuelva al VPS."
