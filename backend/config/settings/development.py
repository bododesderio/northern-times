"""Development settings — debug mode, relaxed security."""
from .base import *  # noqa: F401,F403

DEBUG = True
ALLOWED_HOSTS = ['*']

# Use console email backend in dev
EMAIL_BACKEND = 'django.core.mail.backends.console.EmailBackend'

# Session cookie doesn't require HTTPS in dev
SESSION_COOKIE_SECURE = False
CSRF_COOKIE_SECURE = False

# Allow all origins in dev
CORS_ALLOW_ALL_ORIGINS = True

# Add debug toolbar if installed
try:
    import debug_toolbar  # noqa: F401
    INSTALLED_APPS += ['debug_toolbar']  # noqa: F405
    MIDDLEWARE.insert(0, 'debug_toolbar.middleware.DebugToolbarMiddleware')  # noqa: F405
    INTERNAL_IPS = ['127.0.0.1', '::1']
except ImportError:
    pass

# DRF browsable API in dev
REST_FRAMEWORK['DEFAULT_RENDERER_CLASSES'] = [  # noqa: F405
    'rest_framework.renderers.JSONRenderer',
    'rest_framework.renderers.BrowsableAPIRenderer',
]

# Shorter Celery timeouts for dev
CELERY_TASK_ALWAYS_EAGER = False

LOGGING['root']['level'] = 'DEBUG'  # noqa: F405
