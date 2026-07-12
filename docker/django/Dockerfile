###############################################################################
# Northern Times — Django Application Container
# Serves: gunicorn (web), celery worker, celery beat
###############################################################################

# ---------- Stage 1: Build dependencies ----------
FROM python:3.12-slim AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential libpq-dev libxml2-dev libxslt1-dev \
    libjpeg62-turbo-dev libwebp-dev zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

COPY backend/requirements/ /tmp/requirements/

# Install all dependencies — use CPU-only PyTorch index to avoid ~2.5GB CUDA download.
# The --extra-index-url ensures non-torch packages still come from PyPI.
RUN pip install --no-cache-dir --prefix=/install \
    --default-timeout=300 --retries=5 \
    --extra-index-url https://download.pytorch.org/whl/cpu \
    -r /tmp/requirements/production.txt

# Optionally bake the dev/test deps (pytest, pytest-django, etc.) into the image so
# they survive container recreation. Off by default (prod/VPS); enabled by the
# mailpit dev overlay via --build-arg INSTALL_DEV=true.
ARG INSTALL_DEV=false
RUN if [ "$INSTALL_DEV" = "true" ]; then \
      pip install --no-cache-dir --prefix=/install \
        --default-timeout=300 --retries=5 \
        --extra-index-url https://download.pytorch.org/whl/cpu \
        -r /tmp/requirements/development.txt ; \
    fi

# ---------- Stage 2: Runtime ----------
FROM python:3.12-slim

# System libs needed at runtime (no build tools)
# Note: chromium/chromedriver NOT installed here — Selenium container handles browser work
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq5 libxml2 libxslt1.1 libjpeg62-turbo libwebp7 \
    curl postgresql-client \
    && rm -rf /var/lib/apt/lists/*

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

# Copy installed packages from builder
COPY --from=builder /install /usr/local

# Download spaCy model
RUN python -m spacy download en_core_web_sm

WORKDIR /app

# Copy project files
COPY backend/ /app/backend/
COPY frontend/ /app/frontend/

WORKDIR /app/backend

# Create storage dirs
RUN mkdir -p storage/logs storage/uploads storage/backups storage/geoip storage/cache \
    && groupadd -r -g 1000 appuser \
    && useradd -r -u 1000 -g 1000 -s /bin/false appuser \
    && chown -R appuser:appuser /app/backend/storage

# Collect static files at build time
RUN DJANGO_SETTINGS_MODULE=config.settings.production \
    DJANGO_SECRET_KEY=build-placeholder \
    DB_HOST=localhost \
    python manage.py collectstatic --noinput 2>/dev/null || true

COPY docker/django/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["gunicorn", "config.wsgi:application", "--config", "/app/backend/gunicorn.conf.py"]
