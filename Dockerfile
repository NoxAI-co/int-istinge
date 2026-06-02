# syntax=docker/dockerfile:1.7

# ============================================================================
#  int-istinge — imagen única, compartida por TODAS las instancias de cliente.
#  Cada cliente corre un contenedor con esta misma imagen; lo único que cambia
#  es su archivo .env (BD, dominio, claves). Servidor web: Apache + mod_php.
#
#  Nota: NO se compilan assets en este Dockerfile. laravel-mix ^2.0 es del
#  2017 y requiere Node muy viejo; los assets ya viven compilados en public/
#  (app.js, app.css) y se versionan con el repo — clásico flujo cPanel.
# ============================================================================

FROM php:7.4-apache AS runtime

# ── Extensiones PHP necesarias para Laravel 7 + las deps del repo ───────────
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev \
        libzip-dev libicu-dev libonig-dev libxml2-dev \
        zip unzip git curl ca-certificates \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring bcmath gd zip intl exif opcache \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# ── Apache: mod_rewrite + DocumentRoot a /public + AllowOverride All ────────
# La app vive en /var/www/html/public (no en /var/www/html), y necesita el
# .htaccess de Laravel para que el front-controller funcione.
RUN a2enmod rewrite headers \
 && sed -ri 's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/sites-available/000-default.conf \
        /etc/apache2/apache2.conf \
 && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/laravel.conf \
 && a2enconf laravel

# ── Config PHP runtime: subir límites para evitar OOM en clientes con
#    catálogos/movimientos grandes. (php:7.4-apache trae 128M / 30s.)
RUN { \
      echo 'memory_limit = 512M'; \
      echo 'max_execution_time = 300'; \
      echo 'upload_max_filesize = 64M'; \
      echo 'post_max_size = 64M'; \
      echo 'date.timezone = America/Bogota'; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

# 1) Dependencias PHP (sin scripts: aún no está todo el código)
COPY composer.json composer.lock ./
# composer.json declara database/{seeds,factories} en el classmap; el dump del
# autoloader las escanea aunque estén vacías, así que las creamos para que el
# install no falle antes de copiar el resto del código.
RUN mkdir -p database/seeds database/factories \
 && composer install --no-dev --no-scripts --no-interaction --no-progress \
        --prefer-dist --optimize-autoloader

# 2) Código de la aplicación (vendor/ ya está, .env queda excluido)
COPY . .

# 3) Finaliza autoload + descubrimiento de paquetes.
#    APP_KEY dummy: solo para que artisan arranque durante el build (no se usa en runtime).
ENV APP_ENV=production \
    APP_KEY=base64:c2l4dGVlbmJ5dGVzMTIzNDU2Nzg5MGFiY2RlZg==
RUN composer dump-autoload --no-dev --optimize \
 && php artisan package:discover --ansi || true

# 4) Permisos para storage y cache de Laravel
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R ug+rwX storage bootstrap/cache

# Detrás del reverse proxy central: Apache escucha HTTP plano en :80
# (el proxy termina el TLS).
EXPOSE 80

# Script de arranque: cachea config/vistas y levanta Apache
COPY docker/start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start
CMD ["start"]
