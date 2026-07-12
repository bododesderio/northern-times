"""
Base settings for Northern Times Django project.
Shared across all environments (dev, production).
"""
import os
from pathlib import Path

# Build paths: backend/config/settings/base.py → backend/
BASE_DIR = Path(__file__).resolve().parent.parent.parent

# SECURITY WARNING: keep the secret key used in production secret!
SECRET_KEY = os.environ.get('DJANGO_SECRET_KEY', 'change-me-in-production')

# Application name and branding
APP_NAME = os.environ.get('APP_NAME', 'The Northern Times')
APP_VERSION = os.environ.get('APP_VERSION', '2.0.0')
APP_BOT_NAME = os.environ.get('APP_BOT_NAME', 'NewsCrawlerBot')
APP_URL = os.environ.get('APP_URL', 'http://localhost:8080')
APP_DOMAIN = os.environ.get('APP_DOMAIN', 'localhost')

# Application definition
INSTALLED_APPS = [
    # Django built-in
    'django.contrib.admin',
    'django.contrib.auth',
    'django.contrib.contenttypes',
    'django.contrib.sessions',
    'django.contrib.messages',
    'django.contrib.staticfiles',
    'django.contrib.postgres',
    'django.contrib.sitemaps',
    'django.contrib.sites',

    # Third-party
    'rest_framework',
    'drf_spectacular',
    'django_celery_beat',
    'django_filters',
    'corsheaders',

    # Project apps
    'apps.core',
    'apps.accounts',
    'apps.articles',
    'apps.crawler',
    'apps.enrichment',
    'apps.rewriter',
    'apps.media',
    'apps.newsletter',
    'apps.analytics',
    'apps.ads',
    'apps.seo',
    'apps.social',
    'apps.webhooks',
    'apps.system',
]

MIDDLEWARE = [
    'django.middleware.security.SecurityMiddleware',
    'corsheaders.middleware.CorsMiddleware',
    'django.contrib.sessions.middleware.SessionMiddleware',
    'django.middleware.common.CommonMiddleware',
    'django.middleware.csrf.CsrfViewMiddleware',
    'django.contrib.auth.middleware.AuthenticationMiddleware',
    'django.contrib.messages.middleware.MessageMiddleware',
    'django.middleware.clickjacking.XFrameOptionsMiddleware',
    # Custom middleware
    'apps.core.middleware.RequestIDMiddleware',
    'apps.core.middleware.SecurityHeadersMiddleware',
    'apps.analytics.middleware.VisitorTrackingMiddleware',
    'apps.accounts.middleware.ActiveSessionMiddleware',
]

ROOT_URLCONF = 'config.urls'

TEMPLATES = [
    {
        'BACKEND': 'django.template.backends.django.DjangoTemplates',
        'DIRS': [BASE_DIR / 'templates'],
        'APP_DIRS': True,
        'OPTIONS': {
            'context_processors': [
                'django.template.context_processors.debug',
                'django.template.context_processors.request',
                'django.contrib.auth.context_processors.auth',
                'django.contrib.messages.context_processors.messages',
                'apps.core.context_processors.site_settings',
                'apps.core.context_processors.branding',
                'apps.core.context_processors.ads',
                'apps.core.context_processors.navigation',
                'apps.core.context_processors.location',
            ],
        },
    },
]

WSGI_APPLICATION = 'config.wsgi.application'

# Database — PostgreSQL with pgvector
DATABASES = {
    'default': {
        'ENGINE': 'django.db.backends.postgresql',
        'NAME': os.environ.get('DB_NAME', 'northern_times'),
        'USER': os.environ.get('DB_USER', 'northern'),
        'PASSWORD': os.environ.get('DB_PASS', ''),
        'HOST': os.environ.get('DB_HOST', 'db'),
        'PORT': os.environ.get('DB_PORT', '5432'),
        'OPTIONS': {
            'connect_timeout': 5,
        },
    }
}

# Cache — Redis
_REDIS_BASE = 'redis://:{password}@{host}:{port}'.format(
    password=os.environ.get('REDIS_PASSWORD', ''),
    host=os.environ.get('REDIS_HOST', 'redis'),
    port=os.environ.get('REDIS_PORT', '6379'),
)
CACHES = {
    'default': {
        'BACKEND': 'django.core.cache.backends.redis.RedisCache',
        'LOCATION': f'{_REDIS_BASE}/0',
        'KEY_PREFIX': 'nt',
    },
    # Sessions live in a SEPARATE Redis DB so an admin "flush cache" (which
    # FLUSHDBs the default cache) can never wipe live sessions / log everyone
    # out. Django's RedisCache.clear() ignores KEY_PREFIX, so a distinct DB —
    # not just a distinct prefix — is required for isolation.
    'sessions': {
        'BACKEND': 'django.core.cache.backends.redis.RedisCache',
        'LOCATION': f'{_REDIS_BASE}/1',
        'KEY_PREFIX': 'nt_sess',
    },
}

# Sessions — Redis-backed (isolated from the flushable default cache)
SESSION_ENGINE = 'django.contrib.sessions.backends.cache'
SESSION_CACHE_ALIAS = 'sessions'
SESSION_COOKIE_NAME = os.environ.get('SESSION_NAME', 'northern_times_session')
SESSION_COOKIE_HTTPONLY = True
SESSION_COOKIE_SAMESITE = 'Lax'
SESSION_COOKIE_AGE = 86400  # 24 hours

# Password validation
AUTH_PASSWORD_VALIDATORS = [
    {'NAME': 'django.contrib.auth.password_validation.UserAttributeSimilarityValidator'},
    {'NAME': 'django.contrib.auth.password_validation.MinimumLengthValidator'},
    {'NAME': 'django.contrib.auth.password_validation.CommonPasswordValidator'},
    {'NAME': 'django.contrib.auth.password_validation.NumericPasswordValidator'},
]

# Django Sites framework (required by SEO audit engine)
SITE_ID = 1

# Custom user model
AUTH_USER_MODEL = 'accounts.User'

# Login URLs
LOGIN_URL = '/admin/login/'
LOGIN_REDIRECT_URL = '/admin/'
LOGOUT_REDIRECT_URL = '/admin/login/'

# Internationalization
LANGUAGE_CODE = 'en-us'
TIME_ZONE = 'Africa/Kampala'
USE_I18N = True
USE_TZ = True

# Static files (CSS, JavaScript, images)
STATIC_URL = '/static/'
STATIC_ROOT = BASE_DIR.parent / 'frontend' / 'staticfiles'
STATICFILES_DIRS = [
    BASE_DIR.parent / 'frontend' / 'static',
]

# Media files (user uploads)
MEDIA_URL = os.environ.get('MEDIA_PUBLIC_BASE', '/uploads') + '/'
MEDIA_ROOT = BASE_DIR / 'storage' / 'uploads'
MEDIA_UPLOAD_MAX_MB = int(os.environ.get('MEDIA_UPLOAD_MAX_MB', '100'))
MEDIA_DEFAULT_FOLDER = os.environ.get('MEDIA_DEFAULT_FOLDER', 'Articles')

# Storage backend (local or S3)
STORAGE_DRIVER = os.environ.get('STORAGE_DRIVER', 'local')
if STORAGE_DRIVER == 's3':
    DEFAULT_FILE_STORAGE = 'storages.backends.s3boto3.S3Boto3Storage'
    AWS_S3_ENDPOINT_URL = os.environ.get('S3_ENDPOINT')
    AWS_STORAGE_BUCKET_NAME = os.environ.get('S3_BUCKET', 'northern-times')
    AWS_S3_REGION_NAME = os.environ.get('S3_REGION', 'auto')
    AWS_ACCESS_KEY_ID = os.environ.get('S3_KEY')
    AWS_SECRET_ACCESS_KEY = os.environ.get('S3_SECRET')
    AWS_S3_CUSTOM_DOMAIN = os.environ.get('S3_URL', '').replace('https://', '').replace('http://', '')
    AWS_LOCATION = os.environ.get('S3_PATH_PREFIX', 'uploads')
    AWS_DEFAULT_ACL = None
    AWS_S3_FILE_OVERWRITE = False

# Default primary key field type (all models define explicit UUID PKs)
DEFAULT_AUTO_FIELD = 'django.db.models.BigAutoField'

# Email
EMAIL_BACKEND = 'django.core.mail.backends.smtp.EmailBackend'
EMAIL_HOST = os.environ.get('MAIL_HOST', 'mail')
EMAIL_PORT = int(os.environ.get('MAIL_PORT', '587'))
EMAIL_HOST_USER = os.environ.get('MAIL_USERNAME', '')
EMAIL_HOST_PASSWORD = os.environ.get('MAIL_PASSWORD', '')
EMAIL_USE_TLS = os.environ.get('MAIL_ENCRYPTION', 'none') == 'tls'
DEFAULT_FROM_EMAIL = '{name} <{addr}>'.format(
    name=os.environ.get('MAIL_FROM_NAME', APP_NAME),
    addr=os.environ.get('MAIL_FROM_ADDRESS', f'noreply@{APP_DOMAIN}'),
)

# Celery
CELERY_BROKER_URL = 'redis://:{password}@{host}:{port}/1'.format(
    password=os.environ.get('REDIS_PASSWORD', ''),
    host=os.environ.get('REDIS_HOST', 'redis'),
    port=os.environ.get('REDIS_PORT', '6379'),
)
CELERY_RESULT_BACKEND = CELERY_BROKER_URL
CELERY_ACCEPT_CONTENT = ['json']
CELERY_TASK_SERIALIZER = 'json'
CELERY_RESULT_SERIALIZER = 'json'
CELERY_TIMEZONE = TIME_ZONE
CELERY_TASK_TRACK_STARTED = True
CELERY_TASK_TIME_LIMIT = 600  # 10 minutes hard limit
CELERY_TASK_SOFT_TIME_LIMIT = 540  # 9 minutes soft limit
CELERY_WORKER_MAX_TASKS_PER_CHILD = 100  # Prevent memory leaks

# Django REST Framework
REST_FRAMEWORK = {
    'DEFAULT_PAGINATION_CLASS': 'rest_framework.pagination.PageNumberPagination',
    'PAGE_SIZE': 20,
    'DEFAULT_FILTER_BACKENDS': [
        'django_filters.rest_framework.DjangoFilterBackend',
        'rest_framework.filters.SearchFilter',
        'rest_framework.filters.OrderingFilter',
    ],
    'DEFAULT_THROTTLE_CLASSES': [
        'rest_framework.throttling.AnonRateThrottle',
    ],
    'DEFAULT_THROTTLE_RATES': {
        'anon': '60/minute',
    },
    'DEFAULT_RENDERER_CLASSES': [
        'rest_framework.renderers.JSONRenderer',
    ],
    'DEFAULT_SCHEMA_CLASS': 'drf_spectacular.openapi.AutoSchema',
}

SPECTACULAR_SETTINGS = {
    'TITLE': 'Northern Times API',
    'DESCRIPTION': 'Public API for Northern Times news platform',
    'VERSION': '1.0.0',
    'SERVE_INCLUDE_SCHEMA': False,
}

# OpenAI — for article rewriting
OPENAI_API_KEY = os.environ.get('OPENAI_API_KEY', '')
OPENAI_MODEL = os.environ.get('OPENAI_MODEL', 'gpt-4o-mini')
REWRITER_ENABLED = os.environ.get('REWRITER_ENABLED', 'false').lower() == 'true'
REWRITER_WORD_TOLERANCE = int(os.environ.get('REWRITER_WORD_TOLERANCE', '50'))
REWRITER_BATCH_SIZE = int(os.environ.get('REWRITER_BATCH_SIZE', '3'))

# Crawler settings
CRAWLER_FETCH_TIMEOUT = int(os.environ.get('CRAWLER_FETCH_TIMEOUT', '15'))
CRAWLER_BATCH_WORKERS = int(os.environ.get('CRAWLER_BATCH_WORKERS', '4'))
CRAWLER_STRATEGY_WORKERS = int(os.environ.get('CRAWLER_STRATEGY_WORKERS', '4'))
CRAWLER_MIN_CONTENT_LENGTH = int(os.environ.get('CRAWLER_MIN_CONTENT_LENGTH', '400'))
CRAWLER_SEMANTIC_DEDUP_THRESHOLD = float(os.environ.get('CRAWLER_DEDUP_THRESHOLD', '0.82'))
CRAWLER_STORY_CLUSTER_THRESHOLD = float(os.environ.get('CRAWLER_CLUSTER_THRESHOLD', '0.65'))
# Skip feed items whose published date is older than this many hours.
CRAWLER_MAX_ARTICLE_AGE_HOURS = int(os.environ.get('CRAWLER_MAX_ARTICLE_AGE_HOURS', '48'))

# Search — semantic (pgvector) ranking cold-loads the sentence-transformer into
# the calling process, so it is OFF by default on the synchronous web path.
# Enable only where the ML model is already resident (or latency is acceptable).
SEARCH_SEMANTIC_ENABLED = os.environ.get('SEARCH_SEMANTIC_ENABLED', 'false').lower() == 'true'

# Selenium WebDriver
SELENIUM_REMOTE_URL = os.environ.get('SELENIUM_REMOTE_URL', 'http://selenium:4444/wd/hub')

# SSL verification for outbound HTTP
VERIFY_SSL = os.environ.get('VERIFY_SSL', 'true').lower() == 'true'

# MaxMind GeoIP
MAXMIND_LICENSE_KEY = os.environ.get('MAXMIND_LICENSE_KEY', '')
GEOIP_PATH = BASE_DIR / 'storage' / 'geoip'

# Sentry error tracking
SENTRY_DSN = os.environ.get('SENTRY_DSN', '')
if SENTRY_DSN:
    import sentry_sdk
    sentry_sdk.init(dsn=SENTRY_DSN, traces_sample_rate=0.1)

# Logging
LOGGING = {
    'version': 1,
    'disable_existing_loggers': False,
    'formatters': {
        'verbose': {
            'format': '[{asctime}] {levelname} {name} [{request_id}] {message}',
            'style': '{',
            'defaults': {'request_id': '-'},
        },
        'json': {
            'format': '{{"timestamp":"{asctime}","level":"{levelname}","logger":"{name}","request_id":"{request_id}","message":"{message}"}}',
            'style': '{',
            'defaults': {'request_id': '-'},
        },
    },
    'handlers': {
        'console': {
            'class': 'logging.StreamHandler',
            'formatter': 'verbose',
        },
        'file': {
            'class': 'logging.handlers.RotatingFileHandler',
            'filename': BASE_DIR / 'storage' / 'logs' / 'django.log',
            'maxBytes': 10 * 1024 * 1024,  # 10 MB
            'backupCount': 5,
            'formatter': 'verbose',
        },
    },
    'root': {
        'handlers': ['console', 'file'],
        'level': 'INFO',
    },
    'loggers': {
        'django': {'handlers': ['console', 'file'], 'level': 'WARNING', 'propagate': False},
        'apps.crawler': {'handlers': ['console', 'file'], 'level': 'INFO', 'propagate': False},
        'apps.rewriter': {'handlers': ['console', 'file'], 'level': 'INFO', 'propagate': False},
        'apps.enrichment': {'handlers': ['console', 'file'], 'level': 'INFO', 'propagate': False},
        'celery': {'handlers': ['console', 'file'], 'level': 'INFO', 'propagate': False},
    },
}
