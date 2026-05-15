"""
General-purpose utility functions used across the project.

Replaces the key helpers from the legacy PHP ``helpers.php``.
"""
import math
import re
from datetime import datetime, timedelta

from django.conf import settings
from django.utils import timezone

from apps.core.models import Setting


# ---------------------------------------------------------------------------
# Site settings shortcut
# ---------------------------------------------------------------------------

def get_site_setting(key: str, default=None):
    """
    Retrieve a site setting by *key*, falling back to *default*.

    Wraps ``Setting.get()`` so callers don't need to import the model.
    """
    return Setting.get(key, default)


# ---------------------------------------------------------------------------
# Request helpers
# ---------------------------------------------------------------------------

def get_client_ip(request) -> str:
    """
    Extract the real client IP address from the Django request.

    Checks (in order):
    1. ``X-Forwarded-For`` (first IP in the chain)
    2. ``X-Real-IP``
    3. ``REMOTE_ADDR``
    """
    forwarded_for = request.META.get('HTTP_X_FORWARDED_FOR', '')
    if forwarded_for:
        # X-Forwarded-For may contain a comma-separated list; first is client
        return forwarded_for.split(',')[0].strip()

    real_ip = request.META.get('HTTP_X_REAL_IP', '')
    if real_ip:
        return real_ip.strip()

    return request.META.get('REMOTE_ADDR', '127.0.0.1')


# ---------------------------------------------------------------------------
# Date / time helpers
# ---------------------------------------------------------------------------

_TIME_UNITS = [
    (timedelta(days=365), 'year'),
    (timedelta(days=30), 'month'),
    (timedelta(days=7), 'week'),
    (timedelta(days=1), 'day'),
    (timedelta(hours=1), 'hour'),
    (timedelta(minutes=1), 'minute'),
]


def relative_time(dt: datetime) -> str:
    """
    Return a human-friendly relative time string such as
    ``"2 hours ago"`` or ``"just now"``.

    *dt* should be a timezone-aware ``datetime``.  If it is naive it is
    assumed to be in the current Django timezone.
    """
    if dt is None:
        return ''

    now = timezone.now()

    if timezone.is_naive(dt):
        dt = timezone.make_aware(dt)

    diff = now - dt

    if diff.total_seconds() < 0:
        return 'just now'

    for threshold, unit in _TIME_UNITS:
        count = int(diff / threshold)
        if count >= 1:
            plural = 's' if count != 1 else ''
            return f'{count} {unit}{plural} ago'

    return 'just now'


# ---------------------------------------------------------------------------
# Text helpers
# ---------------------------------------------------------------------------

_STRIP_HTML_RE = re.compile(r'<[^>]+>')


def reading_time(text: str) -> int:
    """
    Estimate reading time in minutes for *text* (assumed ~200 wpm).

    Returns at least 1.
    """
    if not text:
        return 1
    # Strip any HTML so tags don't inflate the word count
    plain = _STRIP_HTML_RE.sub('', text)
    word_count = len(plain.split())
    return max(1, math.ceil(word_count / 200))


def generate_excerpt(text: str, max_length: int = 160) -> str:
    """
    Generate a plain-text excerpt from *text*.

    1. Strips HTML tags.
    2. Collapses whitespace.
    3. Truncates at a word boundary to fit within *max_length* characters.
    """
    if not text:
        return ''

    plain = _STRIP_HTML_RE.sub(' ', text)
    plain = re.sub(r'\s+', ' ', plain).strip()

    if len(plain) <= max_length:
        return plain

    # Truncate at the last space before max_length
    truncated = plain[:max_length]
    last_space = truncated.rfind(' ')
    if last_space > 0:
        truncated = truncated[:last_space]

    return truncated.rstrip('.,;:!? ') + '...'


# ---------------------------------------------------------------------------
# Branding helpers
# ---------------------------------------------------------------------------

def bot_name() -> str:
    """Return the configured bot user-agent name."""
    return getattr(settings, 'APP_BOT_NAME', 'NewsCrawlerBot')


# ---------------------------------------------------------------------------
# Formatting helpers
# ---------------------------------------------------------------------------

def format_file_size(size_bytes: int | float) -> str:
    """
    Format a byte count as a human-readable string.

    Examples: ``"1.2 MB"``, ``"345 KB"``, ``"0 B"``.
    """
    if size_bytes is None or size_bytes < 0:
        return '0 B'

    size_bytes = float(size_bytes)

    for unit in ('B', 'KB', 'MB', 'GB', 'TB'):
        if size_bytes < 1024:
            # Drop decimal for bytes
            if unit == 'B':
                return f'{int(size_bytes)} B'
            return f'{size_bytes:.1f} {unit}'
        size_bytes /= 1024

    return f'{size_bytes:.1f} PB'
