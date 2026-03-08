FROM php:8.2.27-fpm-alpine3.21

# ═══════════════════════════════════════════════════════════════
#  THE NORTHERN TIMES — PHP-FPM Application Container
#
#  Layers (ordered by change frequency for cache efficiency):
#    1. System packages (rarely changes)
#    2. PHP extensions (rarely changes)
#    3. Redis PECL extension (rarely changes)
#    4. Composer binary (rarely changes)
#    5. PHP config (occasionally changes)
#    6. Entrypoint script (occasionally changes)
#    7. Storage directories (build-time scaffold)
# ═══════════════════════════════════════════════════════════════

# ── 1. System dependencies ─────────────────────────────────────
# postgresql-client: pg_isready for DB health check in entrypoint
# dcron: Alpine cron daemon for scheduled tasks
# imagemagick*: Full-format image processing (HEIC, WebP, SVG, AVIF)
# ca-certificates: For SSL verification in crawler (security fix)
RUN apk add --no-cache \
  bash \
  git \
  curl \
  unzip \
  icu-dev \
  libzip-dev \
  oniguruma-dev \
  postgresql-dev \
  zlib-dev \
  freetype-dev \
  libpng-dev \
  libjpeg-turbo-dev \
  libwebp-dev \
  linux-headers \
  postgresql-client \
  dcron \
  imagemagick \
  imagemagick-heic \
  imagemagick-svg \
  imagemagick-webp \
  libavif \
  libheif \
  ca-certificates

# ── 2. PHP extensions ──────────────────────────────────────────
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
  && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    zip \
    intl \
    opcache \
    gd

# ── 3. Redis extension (session handling + caching) ────────────
RUN apk add --no-cache --virtual .build-deps autoconf gcc g++ make \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && apk del .build-deps

# ── 4. Composer ────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── 5. PHP configuration ──────────────────────────────────────
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-northern-times.ini

# ── 6. Entrypoint script ──────────────────────────────────────
# Handles: storage dirs, composer install, DB wait, migrations,
# cron installation (crawler + scheduler + email queue), php-fpm
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# ── 7. Storage directory scaffold ──────────────────────────────
# Created at build time; entrypoint re-ensures + chowns at runtime
RUN mkdir -p \
  /var/www/html/storage/uploads \
  /var/www/html/storage/cache \
  /var/www/html/storage/cache/sessions \
  /var/www/html/storage/cache/geoip \
  /var/www/html/storage/logs \
  /var/www/html/storage/backups \
  && chown -R www-data:www-data /var/www/html/storage

# ── Container lifecycle ────────────────────────────────────────
# ENTRYPOINT runs entrypoint.sh which:
#   1. Creates/chowns storage dirs
#   2. Runs composer install if vendor/ missing
#   3. Waits for PostgreSQL (pg_isready, 30s timeout)
#   4. Runs database migrations
#   5. Installs 3 cron jobs + log rotation
#   6. Starts crond in background
#   7. exec php-fpm as PID 1
ENTRYPOINT ["entrypoint.sh"]
