#!/usr/bin/env bash
# ============================================================================
#  Garantiza los usuarios 'master' (rol 1) y 'desarrollo' (rol 2) con una
#  contraseña ESTÁNDAR en TODAS las BDs integra_* de AMBOS proyectos
#  (integra2.0 e int-istinge). Idempotente:
#    - Si el usuario ya existe (match por username) → actualiza password, rol,
#      email, nombres y user_status a los valores estándar.
#    - Si no existe → lo crea.
#
#  NO toca ningún otro usuario. NO es parte de fix-legacy (script aparte).
#
#  Obtiene el password de root de MySQL desde el propio contenedor
#  (printenv MYSQL_ROOT_PASSWORD), así funciona en cualquier servidor sin
#  depender de infra.env. Procesa los contenedores MySQL que estén CORRIENDO.
#
#  Uso:
#     ./ensure-usuarios-master-dev.sh                # aplica en ambos proyectos
#     ./ensure-usuarios-master-dev.sh --dry-run      # solo lista BDs, no modifica
#     MYSQL_CONTAINERS="integra20-mysql-1" ./ensure-usuarios-master-dev.sh   # uno solo
# ============================================================================
set -uo pipefail

DRY=0
[ "${1:-}" = "--dry-run" ] && DRY=1

# Contenedores MySQL a procesar (ambos proyectos). Overridable por env.
CONTAINERS="${MYSQL_CONTAINERS:-integra20-mysql-1 int-istinge-mysql-1}"

# Hashes bcrypt EXACTOS provistos (en comillas simples: el $ NO se interpola).
MASTER_HASH='$2y$10$3tl6vIgv4uwcx0x/KNVFMey8VhUZU3v.yUNMPNx3Jc4HaSwSTIIay'
DEV_HASH='$2y$10$WD8a1SPeWciClhLhOXrTPeL8ihvCRIfSPH1ol7c4HNfChtw8B4eYC'

total_db=0; total_ok=0; total_err=0

for C in $CONTAINERS; do
  if ! docker ps --format '{{.Names}}' | grep -qx "$C"; then
    echo "== $C : no está corriendo, salto"
    continue
  fi

  # Password root: del contenedor; fallback a infra.env en dirs candidatas.
  PASS="$(docker exec "$C" printenv MYSQL_ROOT_PASSWORD 2>/dev/null || true)"
  if [ -z "$PASS" ]; then
    for f in ./infra.env ../infra.env "./$(dirname "$C")/infra.env"; do
      [ -f "$f" ] || continue
      PASS="$(grep -E '^MYSQL_ROOT_PASSWORD=' "$f" | head -1 | cut -d= -f2- | tr -d '"'"'"'"')"
      [ -n "$PASS" ] && break
    done
  fi
  if [ -z "$PASS" ]; then
    echo "== $C : no pude obtener MYSQL_ROOT_PASSWORD, salto"
    continue
  fi

  dm(){ docker exec -i "$C" mysql --default-character-set=utf8mb4 -uroot -p"$PASS" "$@"; }

  mapfile -t DBS < <(dm -N -B -e "SHOW DATABASES LIKE 'integra\\_%';" 2>/dev/null)
  echo "════════════════════════════════════════════════════════════════════"
  echo "== $C : ${#DBS[@]} BDs integra_*"

  for DB in "${DBS[@]}"; do
    [ -z "$DB" ] && continue
    total_db=$((total_db+1))

    if [ "$DRY" = "1" ]; then
      echo "   • (dry-run) $DB"
      continue
    fi

    if dm "$DB" 2>/tmp/ensure_usr_err <<SQL
UPDATE usuarios SET password='${MASTER_HASH}', nombres='master', email='master@integra.com', rol=1, user_status=1 WHERE username='master';
INSERT INTO usuarios (nombres,email,username,password,rol,user_status,empresa,created_at,updated_at)
 SELECT 'master','master@integra.com','master','${MASTER_HASH}',1,1,1,NOW(),NOW()
 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE username='master');
UPDATE usuarios SET password='${DEV_HASH}', nombres='desarrollo', email='desarrollo@integra.com', rol=2, user_status=1 WHERE username='desarrollo';
INSERT INTO usuarios (nombres,email,username,password,rol,user_status,empresa,created_at,updated_at)
 SELECT 'desarrollo','desarrollo@integra.com','desarrollo','${DEV_HASH}',2,1,1,NOW(),NOW()
 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE username='desarrollo');
SQL
    then
      echo "   ✔ $DB"
      total_ok=$((total_ok+1))
    else
      echo "   ✖ $DB — $(head -1 /tmp/ensure_usr_err)"
      total_err=$((total_err+1))
    fi
  done
done

rm -f /tmp/ensure_usr_err
echo "════════════════════════════════════════════════════════════════════"
if [ "$DRY" = "1" ]; then
  echo "DRY-RUN: $total_db BDs detectadas (no se modificó nada)."
else
  echo "Total BDs: $total_db | OK: $total_ok | errores: $total_err"
fi
