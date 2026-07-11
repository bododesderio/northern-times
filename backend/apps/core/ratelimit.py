"""Lightweight per-IP rate limiting for unauthenticated write endpoints.

Uses the Redis cache (fixed window) — no extra dependency. Guards public POST
endpoints (comments, topic follow/unfollow) against automated flooding.
"""
import logging
from functools import wraps

from django.core.cache import cache
from django.http import JsonResponse

from apps.core.helpers import get_client_ip

logger = logging.getLogger(__name__)


def rate_limit(key: str, limit: int = 5, window: int = 60):
    """Allow at most *limit* requests per *window* seconds per client IP.

    On exceed, returns HTTP 429 JSON without calling the view. Fixed-window via
    ``cache.add`` (sets the counter + TTL only if absent) then ``cache.incr``.
    Fails open if the cache is unavailable — availability beats strictness here.
    """
    def decorator(view):
        @wraps(view)
        def wrapper(request, *args, **kwargs):
            ip = get_client_ip(request) or 'unknown'
            bucket = f'rl:{key}:{ip}'
            try:
                cache.add(bucket, 0, window)   # first hit in window arms the TTL
                count = cache.incr(bucket)
            except Exception as exc:           # cache down → don't block the site
                logger.debug('rate_limit cache error (%s): %s', bucket, exc)
                return view(request, *args, **kwargs)
            if count > limit:
                return JsonResponse(
                    {'success': False, 'error': 'Too many requests. Please slow down.'},
                    status=429,
                )
            return view(request, *args, **kwargs)
        return wrapper
    return decorator
