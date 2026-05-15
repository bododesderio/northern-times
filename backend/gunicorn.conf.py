"""Gunicorn configuration for Northern Times."""
import multiprocessing
import os

# Bind
bind = '0.0.0.0:8000'

# Workers — 2-4x CPU cores, capped for memory
workers = min(int(os.environ.get('GUNICORN_WORKERS', '4')), multiprocessing.cpu_count() * 2 + 1)
worker_class = 'gthread'
threads = 2

# Timeouts
timeout = 120
graceful_timeout = 30
keepalive = 5

# Limits
max_requests = 1000
max_requests_jitter = 50

# Logging
accesslog = '-'
errorlog = '-'
loglevel = os.environ.get('GUNICORN_LOG_LEVEL', 'info')

# Process naming
proc_name = 'northern_times'

# Preload app for faster worker startup (loads ML models once)
preload_app = True
