#!/bin/sh
set -e

cd /var/www/html

# Symlink public/storage -> storage/app/public (idempotente)
php artisan storage:link 2>/dev/null || true

# Cachear configuración y vistas para producción.
#
# OJO: NO usamos `route:cache` porque algunas rutas de la app pueden definirse
# con Closures (no cacheables) — falla con "Unable to prepare route ... Uses
# Closure". Si en el futuro se eliminan las Closures, se puede habilitar.
php artisan config:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true

# Arranca Apache en foreground (sirve /var/www/html/public)
exec apache2-foreground
