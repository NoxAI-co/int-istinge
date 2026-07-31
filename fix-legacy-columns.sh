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
    # Tabla creada por una versión anterior podía no tener updated_at/created_at;
    # el código las escribe (insertGetId/update) → 1054 Unknown column. Añadir si faltan.
    if column_exists "$DB" "cron_cortes_logs" "created_at"; then
      echo "    [cron_cortes_logs.created_at] ya existe, salto"
    else
      dm "$DB" -e "ALTER TABLE cron_cortes_logs ADD COLUMN created_at TIMESTAMP NULL;"
      echo "    [cron_cortes_logs.created_at] agregada"
    fi
    if column_exists "$DB" "cron_cortes_logs" "updated_at"; then
      echo "    [cron_cortes_logs.updated_at] ya existe, salto"
    else
      dm "$DB" -e "ALTER TABLE cron_cortes_logs ADD COLUMN updated_at TIMESTAMP NULL;"
      echo "    [cron_cortes_logs.updated_at] agregada"
    fi
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
    # 1) Normalizar existentes ANTES de forzar NOT NULL: estados inválidos (3, u
    #    otros) o NULL -> 1 (Abierta). 'estatus NOT IN (0,1,2)' NO captura NULL en
    #    SQL, de ahí el 'OR estatus IS NULL'. Una factura en estado inválido nunca
    #    pasó por el flujo de pago (que la cerraría en 0), así que reabrir en 1 es
    #    correcto.
    INVAL="$(dm -N -B -e "SELECT COUNT(*) FROM factura WHERE (estatus NOT IN (0,1,2) OR estatus IS NULL) AND tipo IN (1,2);" "$DB")"
    if [ "${INVAL:-0}" -gt 0 ]; then
      dm "$DB" -e "UPDATE factura SET estatus=1 WHERE (estatus NOT IN (0,1,2) OR estatus IS NULL) AND tipo IN (1,2);"
      echo "    [factura.estatus] ${INVAL} factura(s) con estado inválido reabiertas (estatus=1)"
    else
      echo "    [factura.estatus] sin facturas en estado inválido, salto UPDATE"
    fi

    # 2) Default de la columna -> 1 (protege a CUALQUIER inserción que no asigne
    #    estatus: SQL crudo, un deploy viejo del app sin $attributes, etc.).
    DEF="$(dm -N -B -e "SELECT COLUMN_DEFAULT FROM information_schema.columns WHERE table_schema='$DB' AND table_name='factura' AND column_name='estatus';")"
    if [ "$DEF" = "1" ]; then
      echo "    [factura.estatus] default ya es 1, salto ALTER"
    else
      TYPE="$(column_type "$DB" "factura" "estatus")"
      dm "$DB" -e "SET SESSION sql_mode=''; ALTER TABLE factura MODIFY estatus ${TYPE} NOT NULL DEFAULT 1;"
      echo "    [factura.estatus] default ajustado a 1 (antes: ${DEF:-NULL})"
    fi
  else
    echo "    (sin tabla/columna factura.estatus)"
  fi

  # --- 6b) factura.tipo: alinear a la numeración DIAN ------------------------
  #  Caso: facturas que quedaron como ESTÁNDAR (factura.tipo=1) pero con una
  #  numeración DIAN (numeraciones_facturas.tipo=2) -> consumieron un consecutivo
  #  DIAN pero aparecían en la tabla de facturas estándar. Como el consecutivo
  #  ya se consumió, lo correcto es tratarlas como electrónicas (tipo=2). El fix
  #  de código (CronController) ya evita que se generen nuevas así; esto corrige
  #  las existentes. Idempotente.
  if table_exists "$DB" "factura" && table_exists "$DB" "numeraciones_facturas" \
     && column_exists "$DB" "factura" "tipo" && column_exists "$DB" "factura" "numeracion" \
     && column_exists "$DB" "numeraciones_facturas" "tipo"; then
    MISM="$(dm -N -B -e "SELECT COUNT(*) FROM factura f JOIN numeraciones_facturas n ON n.id = f.numeracion WHERE f.tipo = 1 AND n.tipo = 2;" "$DB")"
    if [ "${MISM:-0}" -gt 0 ]; then
      dm "$DB" -e "UPDATE factura f JOIN numeraciones_facturas n ON n.id = f.numeracion SET f.tipo = 2 WHERE f.tipo = 1 AND n.tipo = 2;"
      echo "    [factura.tipo] ${MISM} factura(s) estándar con numeración DIAN pasadas a electrónicas (tipo=2)"
    else
      echo "    [factura.tipo] sin facturas estándar con numeración DIAN, salto UPDATE"
    fi
  else
    echo "    (sin tabla/columna para alinear factura.tipo con numeración DIAN)"
  fi

  # --- 7) empresa: flags de configuración (análisis de ciclo) ---------------
  #  El panel grupos-corte/analisis-ciclo lee y togglea estos flags sobre la
  #  tabla `empresa` (GruposCorteController::updateEmpresaConfig). En BDs legacy
  #  faltaban -> al guardar daba 1054 Unknown column y el front mostraba
  #  "Error desconocido". Se agregan como TINYINT(1) NOT NULL DEFAULT 0
  #  (apagado = comportamiento actual, ya que la columna ausente se leía null/falsy).
  if table_exists "$DB" "empresa"; then
    for COL in factura_auto aplicar_saldofavor cron_fact_abiertas factura_contrato_off contrato_factura_pro prorrateo; do
      if column_exists "$DB" "empresa" "$COL"; then
        echo "    [empresa.${COL}] ya existe, salto"
      else
        dm "$DB" -e "ALTER TABLE empresa ADD COLUMN ${COL} TINYINT(1) NOT NULL DEFAULT 0;"
        echo "    [empresa.${COL}] agregada (TINYINT(1) DEFAULT 0)"
      fi
    done
  else
    echo "    (sin tabla empresa)"
  fi

  # --- 8) etiqueta_id en tablas que lo referencian --------------------------
  #  El módulo de etiquetas filtra/asigna por `etiqueta_id` (FK lógica a
  #  etiquetas.id). En BDs legacy faltaba en algunas tablas -> 1054 Unknown
  #  column 'etiqueta_id' (p.ej. listado de contactos). Se agrega nullable.
  #  Sin FK constraint para no exigir tipos exactos entre BDs.
  for TBL in contactos contracts crm radicados factura_proveedores; do
    if ! table_exists "$DB" "$TBL"; then
      echo "    (sin tabla ${TBL}, salto etiqueta_id)"
    elif column_exists "$DB" "$TBL" "etiqueta_id"; then
      echo "    [${TBL}.etiqueta_id] ya existe, salto"
    else
      dm "$DB" -e "ALTER TABLE ${TBL} ADD COLUMN etiqueta_id BIGINT UNSIGNED NULL DEFAULT NULL;"
      echo "    [${TBL}.etiqueta_id] agregada (BIGINT UNSIGNED NULL)"
    fi
  done

  # --- 9) inventario.nro_serial ---------------------------------------------
  if table_exists "$DB" "inventario"; then
    if column_exists "$DB" "inventario" "nro_serial"; then
      echo "    [inventario.nro_serial] ya existe, salto"
    else
      dm "$DB" -e "ALTER TABLE inventario ADD COLUMN nro_serial VARCHAR(255) NULL AFTER linea;"
      echo "    [inventario.nro_serial] agregada"
    fi
  else
    echo "    (sin tabla inventario)"
  fi

  # --- 10) whatsapp_messages: type a VARCHAR + metadata (ubicación/contactos) -
  #  El ENUM legacy de `type` no incluye 'location'/'contacts'; MySQL guarda ''
  #  y el chat muestra burbujas vacías. VARCHAR(32) acepta cualquier tipo.
  #  Además se agrega `metadata` JSON si falta y se reparan filas con type=''.
  if table_exists "$DB" "whatsapp_messages"; then
    TYPE="$(column_type "$DB" "whatsapp_messages" "type")"
    case "$TYPE" in
      varchar*)
        echo "    [whatsapp_messages.type] ya es $TYPE, salto"
        ;;
      *)
        dm "$DB" -e "SET SESSION sql_mode=''; ALTER TABLE whatsapp_messages MODIFY \`type\` VARCHAR(32) NOT NULL DEFAULT 'text';"
        echo "    [whatsapp_messages.type] convertida a VARCHAR(32) (era $TYPE)"
        ;;
    esac
    if column_exists "$DB" "whatsapp_messages" "metadata"; then
      echo "    [whatsapp_messages.metadata] ya existe, salto"
    else
      dm "$DB" -e "SET SESSION sql_mode=''; ALTER TABLE whatsapp_messages ADD COLUMN \`metadata\` JSON NULL;"
      echo "    [whatsapp_messages.metadata] agregada"
    fi
    # Repara mensajes guardados con type vacío usando el metadata almacenado.
    dm "$DB" -e "SET SESSION sql_mode='';
      UPDATE whatsapp_messages SET type='location' WHERE type='' AND metadata IS NOT NULL AND JSON_VALID(metadata) AND JSON_EXTRACT(metadata, '\$.location') IS NOT NULL;
      UPDATE whatsapp_messages SET type='contacts' WHERE type='' AND metadata IS NOT NULL AND JSON_VALID(metadata) AND JSON_EXTRACT(metadata, '\$.contacts') IS NOT NULL;
      UPDATE whatsapp_messages SET type='text' WHERE type='';" || echo "    (aviso: no se pudieron reparar filas con type vacío)"
    echo "    [whatsapp_messages] filas con type vacío reparadas"
  else
    echo "    (sin tabla whatsapp_messages)"
  fi

  # --- 11) permiso "Descuento desde recibo de caja" (módulo Facturación) -----
  #  Controla la visibilidad del campo "Descuento %" al pagar una factura
  #  pendiente (recibo de caja). Se crea dentro del módulo Facturación
  #  (permisos_botones). Idempotente: no duplica si ya existe. El id lo resuelve
  #  la app por NOMBRE, así que no importa qué autoincrement reciba en cada BD.
  if table_exists "$DB" "permisos_botones" && table_exists "$DB" "permisos_modulo"; then
    PERM_EXISTS="$(dm -N -B -e "SELECT COUNT(*) FROM permisos_botones WHERE nombre_permiso='Descuento desde recibo de caja';" "$DB")"
    if [ "${PERM_EXISTS:-0}" -gt 0 ]; then
      echo "    [permiso Descuento desde recibo de caja] ya existe, salto"
    else
      # Módulo Facturación: LIKE 'Facturaci%n' -> "Facturación" (NO "Facturación
      # Electrónica" que termina en 'a', ni "Facturación POS"); si hubiera varios
      # que terminan en 'n', prefiere el nombre más corto.
      MOD_ID="$(dm -N -B -e "SELECT id FROM permisos_modulo WHERE nombre_modulo LIKE 'Facturaci%n' ORDER BY CHAR_LENGTH(nombre_modulo) ASC LIMIT 1;" "$DB")"
      if [ -n "$MOD_ID" ]; then
        # `id` en permisos_botones NO es AUTO_INCREMENT -> se pasa MAX(id)+1. El
        # derived table (SELECT n FROM (SELECT ... ) t) evita el error 1093 de MySQL
        # al referenciar la tabla destino dentro del subquery del mismo INSERT.
        # Un solo statement (sin @vars ni tabla temporal) -> funciona igual por
        # docker exec o por cualquier cliente SQL. El id puede diferir por cliente:
        # la app resuelve el permiso por NOMBRE, así que no importa.
        if dm "$DB" -e "INSERT INTO permisos_botones (id, id_modulo, nombre_permiso) VALUES ((SELECT n FROM (SELECT IFNULL(MAX(id),0)+1 AS n FROM permisos_botones) t), ${MOD_ID}, 'Descuento desde recibo de caja');"; then
          NEWID="$(dm -N -B -e "SELECT id FROM permisos_botones WHERE nombre_permiso='Descuento desde recibo de caja' LIMIT 1;" "$DB")"
          echo "    [permiso Descuento desde recibo de caja] creado (id ${NEWID:-?}, módulo ${MOD_ID})"
        else
          echo "    (aviso: no se pudo insertar el permiso en ${DB} — ejecutar: DESCRIBE permisos_botones)"
        fi
      else
        echo "    (no se encontró el módulo Facturación, salto permiso)"
      fi
    fi
  else
    echo "    (sin permisos_botones/permisos_modulo, salto permiso)"
  fi

  # --- 12) notas_retenciones.tipo -------------------------------------------
  #  Distingue retenciones de nota crédito (tipo=1) vs nota débito (tipo=2).
  #  El código lo escribe (Notascredito/Notasdebito Controller) y filtra
  #  (WHERE notas_retenciones.tipo = 1/2). En BDs legacy faltaba -> 1054 Unknown
  #  column 'tipo'. Se agrega INT NOT NULL DEFAULT 1 (crédito, el caso más común;
  #  las filas viejas correspondían a notas crédito).
  if table_exists "$DB" "notas_retenciones"; then
    if column_exists "$DB" "notas_retenciones" "tipo"; then
      echo "    [notas_retenciones.tipo] ya existe, salto"
    else
      dm "$DB" -e "ALTER TABLE notas_retenciones ADD COLUMN tipo INT NOT NULL DEFAULT 1;"
      echo "    [notas_retenciones.tipo] agregada (INT NOT NULL DEFAULT 1)"
    fi
  else
    echo "    (sin tabla notas_retenciones)"
  fi

  # --- 13) notas_credito.correo ---------------------------------------------
  #  Flag que marca si la nota crédito ya se envió por correo (se setea en 1).
  #  Mismo patrón que factura.correo. En BDs legacy faltaba -> 1054 Unknown
  #  column 'correo' al hacer UPDATE notas_credito SET correo=1. Se agrega
  #  TINYINT(1) NOT NULL DEFAULT 0 (0 = no enviado, comportamiento previo).
  if table_exists "$DB" "notas_credito"; then
    if column_exists "$DB" "notas_credito" "correo"; then
      echo "    [notas_credito.correo] ya existe, salto"
    else
      dm "$DB" -e "ALTER TABLE notas_credito ADD COLUMN correo TINYINT(1) NOT NULL DEFAULT 0;"
      echo "    [notas_credito.correo] agregada (TINYINT(1) DEFAULT 0)"
    fi
  else
    echo "    (sin tabla notas_credito)"
  fi

  # --- 14) mk_sync_lotes / mk_sync_items ------------------------------------
  #  Módulo Cron Jobs > "Sincronización MikroTik": envía en lotes los contratos
  #  de una MikroTik reutilizando UNA conexión por tanda. `mk_sync_lotes` es la
  #  cabecera de la orden (progreso + lock anti-concurrencia entre el navegador
  #  y el cron) y `mk_sync_items` el renglón por contrato con su resultado.
  #  En BDs legacy no existen (la migración solo corre en instalaciones nuevas)
  #  -> 1146 Table doesn't exist al abrir el módulo. Idempotente.
  if table_exists "$DB" "mk_sync_lotes"; then
    echo "    [mk_sync_lotes] ya existe, salto"
  else
    dm "$DB" -e "
      CREATE TABLE mk_sync_lotes (
        id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        empresa       INT NOT NULL,
        mikrotik_id   INT NOT NULL,
        estado        VARCHAR(20) NOT NULL DEFAULT 'pendiente' COMMENT 'pendiente|ejecutando|completado|cancelado',
        total         INT NOT NULL DEFAULT 0,
        procesados    INT NOT NULL DEFAULT 0,
        correctos     INT NOT NULL DEFAULT 0,
        fallidos      INT NOT NULL DEFAULT 0,
        filtros       TEXT NULL,
        lock_token    VARCHAR(40) NULL,
        lock_expires  DATETIME NULL,
        created_by    INT NULL,
        inicio        DATETIME NULL,
        fin           DATETIME NULL,
        created_at    TIMESTAMP NULL,
        updated_at    TIMESTAMP NULL,
        INDEX idx_empresa (empresa),
        INDEX idx_empresa_estado (empresa, estado)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    "
    echo "    [mk_sync_lotes] creada"
  fi

  if table_exists "$DB" "mk_sync_items"; then
    echo "    [mk_sync_items] ya existe, salto"
  else
    dm "$DB" -e "
      CREATE TABLE mk_sync_items (
        id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        lote_id       BIGINT UNSIGNED NOT NULL,
        contrato_id   INT NOT NULL,
        contrato_nro  INT NULL,
        ip            VARCHAR(64) NULL,
        estado        VARCHAR(12) NOT NULL DEFAULT 'pendiente' COMMENT 'pendiente|ok|error',
        mensaje       VARCHAR(255) NULL,
        intentos      INT NOT NULL DEFAULT 0,
        created_at    TIMESTAMP NULL,
        updated_at    TIMESTAMP NULL,
        INDEX idx_lote (lote_id),
        INDEX idx_lote_estado (lote_id, estado)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    "
    echo "    [mk_sync_items] creada"
  fi

  # --- 15) mikrotik_conexion_logs -------------------------------------------
  #  Bitácora de la acción "Probar conexión" del módulo Mikrotik: guarda cada
  #  sondeo (ok/fallo, latencia, motivo, board/versión) para poder darle
  #  seguimiento a una MikroTik intermitente. Idempotente.
  if table_exists "$DB" "mikrotik_conexion_logs"; then
    echo "    [mikrotik_conexion_logs] ya existe, salto"
  else
    dm "$DB" -e "
      CREATE TABLE mikrotik_conexion_logs (
        id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        mikrotik_id  INT NOT NULL,
        empresa      INT NULL,
        ok           TINYINT(1) NOT NULL DEFAULT 0,
        latencia_ms  INT NOT NULL DEFAULT 0,
        mensaje      VARCHAR(255) NULL,
        board        VARCHAR(100) NULL,
        version      VARCHAR(50) NULL,
        uptime       VARCHAR(50) NULL,
        cpu_load     VARCHAR(20) NULL,
        origen       VARCHAR(20) NOT NULL DEFAULT 'manual' COMMENT 'manual | cron',
        created_by   INT NULL,
        created_at   TIMESTAMP NULL,
        INDEX idx_mikrotik (mikrotik_id),
        INDEX idx_empresa (empresa),
        INDEX idx_mikrotik_fecha (mikrotik_id, created_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    "
    echo "    [mikrotik_conexion_logs] creada"
  fi

  # --- 16) cont_envio_fallido en factura e ingresos --------------------------
  #  WhatsappFailureClassifier separa los fallos del destinatario de los de la
  #  cuenta emisora; el contador de estos últimos es cont_envio_fallido, y
  #  CronController::envioFacturaWpp lo usa en el WHERE. Sin la columna, el cron
  #  de envío por WhatsApp aborta con "Unknown column" en cada corrida (pasaba
  #  cada 15 minutos en toda la flota). Idempotente.
  for TBL in factura ingresos; do
    if ! table_exists "$DB" "$TBL"; then
      echo "    (sin tabla ${TBL})"
    elif column_exists "$DB" "$TBL" "cont_envio_fallido"; then
      echo "    [${TBL}.cont_envio_fallido] ya existe, salto"
    else
      dm "$DB" -e "ALTER TABLE ${TBL} ADD COLUMN cont_envio_fallido INT NOT NULL DEFAULT 0 COMMENT 'reintentos por fallos de la cuenta emisora (pago, token, rate-limit), no del destinatario';"
      echo "    [${TBL}.cont_envio_fallido] agregada"
    fi
  done

  # --- 17) factura.registrado_en --------------------------------------------
  #  created_at NO dice cuándo se creó la factura: FacturasController::store lo
  #  pisa con la fecha que el usuario escribe en el formulario. Eso dejó sin
  #  rastro el origen de las facturas duplicadas 52769/52770 (se pudo reconstruir
  #  de casualidad, por el created_at de facturas_contratos).
  #  No se cambia la semántica de created_at porque el cron de cortes la usa para
  #  elegir la última factura del cliente; se guarda la hora real aparte.
  if table_exists "$DB" "factura"; then
    if column_exists "$DB" "factura" "registrado_en"; then
      echo "    [factura.registrado_en] ya existe, salto"
    else
      dm "$DB" -e "ALTER TABLE factura ADD COLUMN registrado_en TIMESTAMP NULL COMMENT 'momento real en que se guardo la factura; created_at lleva la fecha del documento';"
      echo "    [factura.registrado_en] agregada"
    fi
  fi
  # --- 18) factura.form_token + índice único ---------------------------------
  #  Idempotencia de facturas manuales: cada render del formulario lleva un token
  #  único que se guarda con la factura. El bloqueo JS del botón no impide que el
  #  navegador repita el POST (F5 + "volver a enviar"): así salieron duplicadas en
  #  enternet con 3-4 s de diferencia y el fix del consecutivo ya desplegado.
  #  FacturasController::store descarta el POST cuyo token ya existe; el índice
  #  único cubre el caso concurrente. UNIQUE sobre columna NULL admite múltiples
  #  NULL en MySQL, así que las facturas del cron (sin token) no chocan.
  if table_exists "$DB" "factura"; then
    if column_exists "$DB" "factura" "form_token"; then
      echo "    [factura.form_token] ya existe, salto"
    else
      dm "$DB" -e "ALTER TABLE factura ADD COLUMN form_token VARCHAR(64) NULL DEFAULT NULL COMMENT 'token de idempotencia del formulario de creacion manual';"
      echo "    [factura.form_token] agregada"
    fi
    IDX="$(dm -N -B -e "SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema='$DB' AND table_name='factura' AND index_name='uniq_factura_form_token';")"
    if [ "${IDX:-0}" = "1" ]; then
      echo "    [factura.uniq_factura_form_token] ya existe, salto"
    else
      dm "$DB" -e "ALTER TABLE factura ADD UNIQUE KEY uniq_factura_form_token (form_token);"
      echo "    [factura.uniq_factura_form_token] creado"
    fi
  else
    echo "    (sin tabla factura, salto form_token)"
  fi
done

echo "==> Listo."
