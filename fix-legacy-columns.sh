#!/usr/bin/env bash
# ============================================================================
#  Ajustes a tablas legacy para BDs `integra_*` existentes.
#
#  Operaciones (todas idempotentes):
#    1) instances.access_token       -> agrega TEXT NULL si no existe
#    2) radicados.firma, adjunto_4   -> hace cada columna nullable si está NOT NULL
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

  # --- 2) radicados: columnas a hacer nullable ------------------------------
  if table_exists "$DB" "radicados"; then
    for COL in firma adjunto_4; do
      if ! column_exists "$DB" "radicados" "$COL"; then
        echo "    (radicados.${COL} no existe, salto)"
      elif column_is_nullable "$DB" "radicados" "$COL"; then
        echo "    [radicados.${COL}] ya es nullable, salto"
      else
        TYPE="$(column_type "$DB" "radicados" "$COL")"
        dm "$DB" -e "SET SESSION sql_mode=''; ALTER TABLE radicados MODIFY ${COL} ${TYPE} NULL DEFAULT NULL;"
        echo "    [radicados.${COL}] ahora ${TYPE} NULL DEFAULT NULL"
      fi
    done
  else
    echo "    (sin tabla radicados)"
  fi
  # --- 3) factura.onepay_idempotency_key ------------------------------------
  if table_exists "$DB" "factura"; then
    if column_exists "$DB" "factura" "onepay_idempotency_key"; then
      echo "    [factura.onepay_idempotency_key] ya existe, salto"
    else
      dm "$DB" -e "ALTER TABLE factura ADD COLUMN onepay_idempotency_key VARCHAR(255) NULL;"
      echo "    [factura.onepay_idempotency_key] agregada"
    fi
  else
    echo "    (sin tabla factura)"
  fi

  # --- 4) cron_cortes_logs --------------------------------------------------
  if table_exists "$DB" "cron_cortes_logs"; then
    echo "    [cron_cortes_logs] ya existe, salto"
  else
    dm "$DB" -e "
      CREATE TABLE cron_cortes_logs (
        id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        tipo              VARCHAR(20) NOT NULL COMMENT 'internet | tv',
        empresa           INT UNSIGNED NULL,
        grupo_corte_id    BIGINT UNSIGNED NULL,
        total_procesados  INT UNSIGNED NOT NULL DEFAULT 0,
        total_cortados    INT UNSIGNED NOT NULL DEFAULT 0,
        total_omitidos    INT UNSIGNED NOT NULL DEFAULT 0,
        total_errores     INT UNSIGNED NOT NULL DEFAULT 0,
        duracion_ms       INT UNSIGNED NOT NULL DEFAULT 0,
        ejecutado_por     INT UNSIGNED NULL COMMENT 'NULL = CRON automatico',
        contexto          JSON NULL,
        created_at        TIMESTAMP NULL,
        updated_at        TIMESTAMP NULL,
        INDEX idx_grupo_tipo (grupo_corte_id, tipo),
        INDEX idx_created_at (created_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    "
    echo "    [cron_cortes_logs] creada"
  fi

  # --- 5) cron_cortes_detalle -----------------------------------------------
  if table_exists "$DB" "cron_cortes_detalle"; then
    echo "    [cron_cortes_detalle] ya existe, salto"
  else
    dm "$DB" -e "
      CREATE TABLE cron_cortes_detalle (
        id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        log_id           BIGINT UNSIGNED NOT NULL,
        contrato_id      BIGINT UNSIGNED NULL,
        factura_id       BIGINT UNSIGNED NULL,
        cliente_id       BIGINT UNSIGNED NULL,
        grupo_corte_id   BIGINT UNSIGNED NULL,
        tipo             VARCHAR(20) NULL COMMENT 'internet | tv',
        resultado        VARCHAR(30) NULL COMMENT 'cortado | omitido | error',
        metodo           VARCHAR(50) NULL COMMENT 'mikrotik | olt | pppoe | db_only',
        descripcion      TEXT NULL,
        ip               VARCHAR(50) NULL,
        serial_onu       VARCHAR(100) NULL,
        mikrotik_id      INT UNSIGNED NULL,
        error_detalle    TEXT NULL,
        created_at       TIMESTAMP NULL,
        INDEX idx_log_id (log_id),
        INDEX idx_log_resultado (log_id, resultado),
        CONSTRAINT fk_ccd_log FOREIGN KEY (log_id) REFERENCES cron_cortes_logs(id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    "
    echo "    [cron_cortes_detalle] creada"
  fi

  # --- 6) factura.estatus: default 1 + normalizar estados inválidos ----------
  #  Estados válidos: 0=Cerrada, 1=Abierta(pendiente), 2=Anulada.
  #  En BDs legacy la columna tenía DEFAULT 3 y el cron de facturación crea
  #  facturas SIN asignar estatus -> quedaban en 3 (inválido) y el portal de
  #  pagos (filtra estatus=1) no las mostraba. Se corrige el default y se
  #  reabren las facturas de venta (tipo 1,2) que quedaron en un estado inválido.
  if table_exists "$DB" "factura" && column_exists "$DB" "factura" "estatus"; then
    DEF="$(dm -N -B -e "SELECT COLUMN_DEFAULT FROM information_schema.columns WHERE table_schema='$DB' AND table_name='factura' AND column_name='estatus';")"
    if [ "$DEF" = "1" ]; then
      echo "    [factura.estatus] default ya es 1, salto ALTER"
    else
      TYPE="$(column_type "$DB" "factura" "estatus")"
      dm "$DB" -e "ALTER TABLE factura MODIFY estatus ${TYPE} NOT NULL DEFAULT 1;"
      echo "    [factura.estatus] default ajustado a 1 (antes: ${DEF:-NULL})"
    fi

    INVAL="$(dm -N -B -e "SELECT COUNT(*) FROM factura WHERE estatus NOT IN (0,1,2) AND tipo IN (1,2);" "$DB")"
    if [ "${INVAL:-0}" -gt 0 ]; then
      dm "$DB" -e "UPDATE factura SET estatus=1 WHERE estatus NOT IN (0,1,2) AND tipo IN (1,2);"
      echo "    [factura.estatus] ${INVAL} factura(s) con estado inválido reabiertas (estatus=1)"
    else
      echo "    [factura.estatus] sin facturas en estado inválido, salto UPDATE"
    fi
  else
    echo "    (sin tabla/columna factura.estatus)"
  fi
done

echo "==> Listo."
