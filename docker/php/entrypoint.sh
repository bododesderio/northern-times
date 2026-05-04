#!/bin/sh
# ═══════════════════════════════════════════════════════════════
#  THE NORTHERN TIMES — PHP-FPM Container Entrypoint
#
#  Lifecycle:
#    1. Ensure storage directories exist with correct ownership
#    2. Install Composer dependencies if vendor/ is missing
#    3. Wait for PostgreSQL to accept connections (max 30s)
#    4. Run pending database migrations
#    5. Install all cron jobs (crawler, scheduler, email queue)
#    6. Start crond in background
#    7. Exec php-fpm as PID 1
#
#  This script runs on every container start. Every step is
#  idempotent — safe to restart without side effects.
# ═══════════════════════════════════════════════════════════════
set -e

APP_ROOT="/var/www/html"
LOG_DIR="${APP_ROOT}/storage/logs"

# ── 1. Storage directories ─────────────────────────────────────
echo "[entrypoint] Ensuring storage directories..."
mkdir -p \
  "${APP_ROOT}/storage/uploads" \
  "${APP_ROOT}/storage/cache" \
  "${APP_ROOT}/storage/cache/sessions" \
  "${APP_ROOT}/storage/cache/geoip" \
  "${APP_ROOT}/storage/logs" \
  "${APP_ROOT}/storage/backups"

# www-data (UID 82 on Alpine) must own storage for writes
chown -R www-data:www-data "${APP_ROOT}/storage" 2>/dev/null || true

# ── 2. Composer install ────────────────────────────────────────
if [ ! -d "${APP_ROOT}/vendor" ]; then
  echo "[entrypoint] vendor/ missing — running composer install..."
  cd "${APP_ROOT}"
  composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
else
  echo "[entrypoint] vendor/ present — skipping composer install"
fi

# ── 3. Wait for PostgreSQL ─────────────────────────────────────
# Docker Compose depends_on only waits for container start, not
# TCP readiness. We poll pg_isready to avoid migration failures.
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-5432}"
DB_USER="${DB_USER:-northern}"

echo "[entrypoint] Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT}..."
MAX_WAIT=30
WAITED=0
while ! pg_isready -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -q 2>/dev/null; do
  WAITED=$((WAITED + 1))
  if [ "${WAITED}" -ge "${MAX_WAIT}" ]; then
    echo "[entrypoint] WARNING: PostgreSQL not ready after ${MAX_WAIT}s — skipping migrations"
    break
  fi
  sleep 1
done

# ── 4. Database migrations ─────────────────────────────────────
if [ "${WAITED}" -lt "${MAX_WAIT}" ]; then
  echo "[entrypoint] Running database migrations..."
  cd "${APP_ROOT}"
  php database/migrate.php 2>&1 || echo "[entrypoint] WARNING: Migration runner returned non-zero"

  echo "[entrypoint] Running database seeder..."
  php database/seed.php 2>&1 || echo "[entrypoint] WARNING: Seeder returned non-zero"
fi

# ── 5. Install cron jobs ───────────────────────────────────────
# All three scheduled tasks in a single crontab. Each script has
# its own lock-file mechanism to prevent overlapping runs.
echo "[entrypoint] Installing crontab..."
cat <<'CRONTAB' | crontab -
# ── Northern Times Scheduled Tasks ──────────────────────────
# Crawler: fetch RSS feeds, scrape articles (every 5 min)
*/5 * * * * cd /var/www/html && php cron/crawl.php >> /var/www/html/storage/logs/crawler.log 2>&1

# Scheduler: publish scheduled articles (every minute)
* * * * * cd /var/www/html && php schedule.php >> /var/www/html/storage/logs/schedule.log 2>&1

# Email queue: process pending newsletter/notification emails (every 2 min)
*/2 * * * * cd /var/www/html && php bin/process-queue.php >> /var/www/html/storage/logs/queue.log 2>&1

# Daily maintenance: stats aggregation, automated backup, cleanup (daily at 2am)
0 2 * * * cd /var/www/html && php cron/daily_maintenance.php >> /var/www/html/storage/logs/maintenance.log 2>&1

# Weekly digest: send top articles email (Sunday 8am)
0 8 * * 0 cd /var/www/html && php cron/weekly_digest.php >> /var/www/html/storage/logs/digest.log 2>&1

# AI Rewriter: process queued article rewrites (every 10 min)
*/10 * * * * cd /var/www/html && php cron/rewrite.php >> /var/www/html/storage/logs/rewriter.log 2>&1

# Log rotation: truncate logs > 50MB (daily at 3am)
0 3 * * * find /var/www/html/storage/logs -name "*.log" -size +50M -exec truncate -s 0 {} \;
CRONTAB

# ── 6. Start crond ─────────────────────────────────────────────
# -b = background, -l 8 = log level (errors only)
if pgrep -x crond > /dev/null 2>&1; then
  echo "[entrypoint] crond already running"
else
  crond -b -l 8
  echo "[entrypoint] crond started"
fi

# ── 7. Exec PHP-FPM ───────────────────────────────────────────
# exec replaces this shell with php-fpm so it becomes PID 1
# and receives SIGTERM/SIGINT for graceful shutdown.
echo "[entrypoint] Starting PHP-FPM..."
exec php-fpm
