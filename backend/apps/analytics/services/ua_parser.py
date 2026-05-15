"""Lightweight user-agent parser using regex matching.

No external dependencies required.
"""

import re

# ---------------------------------------------------------------------------
# Browser patterns (order matters — check more specific first)
# ---------------------------------------------------------------------------

_BROWSER_PATTERNS = [
    (re.compile(r'Edg(?:e|A|iOS)?/(\S+)', re.I), 'Edge'),
    (re.compile(r'OPR/(\S+)', re.I), 'Opera'),
    (re.compile(r'Opera/(\S+)', re.I), 'Opera'),
    (re.compile(r'Vivaldi/(\S+)', re.I), 'Vivaldi'),
    (re.compile(r'Brave', re.I), 'Brave'),
    (re.compile(r'SamsungBrowser/(\S+)', re.I), 'Samsung Internet'),
    (re.compile(r'UCBrowser/(\S+)', re.I), 'UC Browser'),
    (re.compile(r'Firefox/(\S+)', re.I), 'Firefox'),
    (re.compile(r'FxiOS/(\S+)', re.I), 'Firefox'),
    (re.compile(r'CriOS/(\S+)', re.I), 'Chrome'),
    (re.compile(r'Chrome/(\S+)', re.I), 'Chrome'),
    (re.compile(r'Chromium/(\S+)', re.I), 'Chromium'),
    (re.compile(r'Safari/(\S+)', re.I), 'Safari'),
]

# ---------------------------------------------------------------------------
# OS patterns
# ---------------------------------------------------------------------------

_OS_PATTERNS = [
    (re.compile(r'iPhone|iPad|iPod', re.I), 'iOS'),
    (re.compile(r'Android', re.I), 'Android'),
    (re.compile(r'Windows NT', re.I), 'Windows'),
    (re.compile(r'Mac OS X|Macintosh', re.I), 'macOS'),
    (re.compile(r'CrOS', re.I), 'Chrome OS'),
    (re.compile(r'Linux', re.I), 'Linux'),
]

# ---------------------------------------------------------------------------
# Device type keywords
# ---------------------------------------------------------------------------

_TABLET_KEYWORDS = re.compile(
    r'iPad|tablet|Kindle|Silk|PlayBook|Nexus\s?(7|9|10)',
    re.I,
)

_MOBILE_KEYWORDS = re.compile(
    r'Mobile|Android.*Mobile|iPhone|iPod|Opera\sMini|IEMobile|'
    r'BlackBerry|webOS|Fennec|Windows\sPhone',
    re.I,
)

_BOT_KEYWORDS = re.compile(
    r'bot|crawler|spider|slurp|Mediapartners|Googlebot|Bingbot|Yahoo',
    re.I,
)


def parse(user_agent_string: str) -> dict:
    """Parse a user-agent string into device type, browser, and OS.

    Args:
        user_agent_string: The raw User-Agent header value.

    Returns:
        {
            'device_type': 'mobile' | 'tablet' | 'desktop' | 'bot' | 'unknown',
            'browser': str,
            'os': str,
        }
    """
    if not user_agent_string:
        return {'device_type': 'unknown', 'browser': '', 'os': ''}

    ua = user_agent_string

    # Detect device type
    device_type = _detect_device_type(ua)

    # Detect browser
    browser = _detect_browser(ua)

    # Detect OS
    os_name = _detect_os(ua)

    return {
        'device_type': device_type,
        'browser': browser,
        'os': os_name,
    }


def _detect_device_type(ua: str) -> str:
    if _BOT_KEYWORDS.search(ua):
        return 'bot'
    if _TABLET_KEYWORDS.search(ua):
        return 'tablet'
    if _MOBILE_KEYWORDS.search(ua):
        return 'mobile'
    return 'desktop'


def _detect_browser(ua: str) -> str:
    for pattern, name in _BROWSER_PATTERNS:
        if pattern.search(ua):
            return name
    return 'Other'


def _detect_os(ua: str) -> str:
    for pattern, name in _OS_PATTERNS:
        if pattern.search(ua):
            return name
    return 'Other'
