"""Production settings — security hardened."""
import os
from .base import *  # noqa: F401,F403

DEBUG = False
ALLOWED_HOSTS = [
    os.environ.get('APP_DOMAIN', 'localhost'),
    'www.' + os.environ.get('APP_DOMAIN', 'localhost'),
]

# Security
SECRET_KEY = os.environ.get('DJANGO_SECRET_KEY', '')
if not SECRET_KEY:
    import warnings
    warnings.warn(
        'DJANGO_SECRET_KEY not set! Generate one with: '
        'python -c "from django.core.management.utils import get_random_secret_key; print(get_random_secret_key())"'
    )
    # Use base.py fallback so the container can at least start for debugging
    SECRET_KEY = globals().get('SECRET_KEY', 'INSECURE-CHANGE-ME-IN-PRODUCTION')
_use_ssl = os.environ.get('SECURE_SSL_REDIRECT', 'false').lower() == 'true'
SESSION_COOKIE_SECURE = _use_ssl
CSRF_COOKIE_SECURE = _use_ssl
SECURE_SSL_REDIRECT = _use_ssl
SECURE_HSTS_SECONDS = 31536000 if _use_ssl else 0
SECURE_HSTS_INCLUDE_SUBDOMAINS = _use_ssl
SECURE_HSTS_PRELOAD = _use_ssl
SECURE_PROXY_SSL_HEADER = ('HTTP_X_FORWARDED_PROTO', 'https') if _use_ssl else None
SECURE_CONTENT_TYPE_NOSNIFF = True
SECURE_BROWSER_XSS_FILTER = True
X_FRAME_OPTIONS = 'SAMEORIGIN'

# CORS — restrict to own domain
_scheme = 'https' if _use_ssl else 'http'
_port = '' if _use_ssl else ':8080'
CORS_ALLOWED_ORIGINS = [
    f'{_scheme}://{APP_DOMAIN}{_port}',  # noqa: F405
    f'{_scheme}://www.{APP_DOMAIN}{_port}',  # noqa: F405
]

# CSRF trusted origins (required when behind a reverse proxy)
CSRF_TRUSTED_ORIGINS = [
    f'{_scheme}://{APP_DOMAIN}{_port}',  # noqa: F405
    f'{_scheme}://www.{APP_DOMAIN}{_port}',  # noqa: F405
]

# Static files — served by nginx in production, with content-hash cache busting
STATIC_URL = '/static/'
STORAGES = {
    'default': {
        'BACKEND': 'storages.backends.s3boto3.S3Boto3Storage',
    } if STORAGE_DRIVER == 's3' else {  # noqa: F405
        'BACKEND': 'django.core.files.storage.FileSystemStorage',
    },
    'staticfiles': {
        'BACKEND': 'django.contrib.staticfiles.storage.ManifestStaticFilesStorage',
    },
}

# Cache timeout
CACHES['default']['TIMEOUT'] = 300  # noqa: F405

# Production logging — less verbose, JSON format for log aggregation
LOGGING['root']['level'] = 'WARNING'  # noqa: F405
LOGGING['loggers']['apps.crawler']['level'] = 'INFO'  # noqa: F405
LOGGING['handlers']['console']['formatter'] = 'json'  # noqa: F405
