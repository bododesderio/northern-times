#!/bin/bash
set -e

# ── Wait for PostgreSQL (max 60s) ────────────────────────────────
echo "[entrypoint] Waiting for PostgreSQL..."
pg_attempts=0
until python -c "
import psycopg2, os
psycopg2.connect(
    host=os.environ.get('DB_HOST', 'db'),
    port=os.environ.get('DB_PORT', '5432'),
    dbname=os.environ.get('DB_NAME', 'northern_times'),
    user=os.environ.get('DB_USER', 'northern'),
    password=os.environ.get('DB_PASS', ''),
)
" 2>/dev/null; do
    pg_attempts=$((pg_attempts + 1))
    if [ "$pg_attempts" -ge 30 ]; then
        echo "[entrypoint] ERROR: PostgreSQL not available after 60s — aborting"
        exit 1
    fi
    echo "[entrypoint] PostgreSQL not ready, retrying in 2s... ($pg_attempts/30)"
    sleep 2
done
echo "[entrypoint] PostgreSQL is ready."

# ── Wait for Redis (max 60s) ─────────────────────────────────────
echo "[entrypoint] Waiting for Redis..."
redis_attempts=0
until python -c "
import redis, os
r = redis.Redis(
    host=os.environ.get('REDIS_HOST', 'redis'),
    port=int(os.environ.get('REDIS_PORT', '6379')),
    password=os.environ.get('REDIS_PASSWORD', ''),
)
r.ping()
" 2>/dev/null; do
    redis_attempts=$((redis_attempts + 1))
    if [ "$redis_attempts" -ge 30 ]; then
        echo "[entrypoint] ERROR: Redis not available after 60s — aborting"
        exit 1
    fi
    echo "[entrypoint] Redis not ready, retrying in 2s... ($redis_attempts/30)"
    sleep 2
done
echo "[entrypoint] Redis is ready."

# ── Run migrations (only for web/gunicorn, not celery) ────────────
if echo "$@" | grep -q "gunicorn"; then
    # Enable pgvector extension BEFORE migrations (VectorField needs it)
    echo "[entrypoint] Ensuring pgvector extension..."
    python -c "
import psycopg2, os
conn = psycopg2.connect(
    host=os.environ.get('DB_HOST', 'db'),
    port=os.environ.get('DB_PORT', '5432'),
    dbname=os.environ.get('DB_NAME', 'northern_times'),
    user=os.environ.get('DB_USER', 'northern'),
    password=os.environ.get('DB_PASS', ''),
)
conn.autocommit = True
cur = conn.cursor()
cur.execute('CREATE EXTENSION IF NOT EXISTS vector')
cur.close()
conn.close()
print('[entrypoint] pgvector extension ensured.')
" || echo "[entrypoint] WARNING: Could not create pgvector extension (may need superuser)"

    echo "[entrypoint] Running migrations..."
    python manage.py migrate --noinput 2>&1 || {
        echo "[entrypoint] WARNING: Migrations failed — starting anyway for debugging"
    }

    echo "[entrypoint] Collecting static files..."
    python manage.py collectstatic --noinput 2>/dev/null || true

    echo "[entrypoint] Running seeder..."
    python manage.py seed_all 2>&1 || {
        echo "[entrypoint] WARNING: Seeder failed — starting anyway"
    }
fi

echo "[entrypoint] Starting: $@"
exec "$@"
