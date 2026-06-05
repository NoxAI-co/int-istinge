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

# Los volúmenes nombrados (storage/logs, storage/app, public/adjuntos) se montan
# como root y TAPAN el chown que hizo el build, dejando a www-data sin permiso
# de escritura (falla al abrir laravel.log -> 500 en cada request). Re-aseguramos
# permisos en runtime, ya con los volúmenes montados. Va al final para corregir
# también lo que artisan haya creado como root arriba (config.php, laravel.log).
chown -R www-data:www-data storage bootstrap/cache public/adjuntos 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache public/adjuntos 2>/dev/null || true

# Arranca Apache en foreground (sirve /var/www/html/public)
exec apache2-foreground
