"""SSRF guard for crawler fetches.

Crawled URLs originate from untrusted content — RSS ``<link>`` targets and,
now, scraped anchor ``href``s (html_listing). Before the server fetches one we
require a *public* http(s) URL: an allowed scheme whose host does not resolve to
a private / loopback / link-local / reserved address. This blocks the classic
SSRF targets — cloud metadata (169.254.169.254), the stack's own Redis/DB on
localhost, ``file://``/``gopher://`` schemes — and every redirect hop is
re-checked, so a benign-looking URL can't 302 into internal space.
"""
import ipaddress
import logging
import socket
from urllib.parse import urlparse

logger = logging.getLogger(__name__)

_ALLOWED_SCHEMES = {'http', 'https'}
_MAX_REDIRECTS = 5


def is_safe_public_url(url: str) -> bool:
    """True only if *url* is http(s) and every resolved IP is publicly routable."""
    try:
        parts = urlparse(url)
    except Exception:
        return False
    if parts.scheme not in _ALLOWED_SCHEMES or not parts.hostname:
        return False

    port = parts.port or (443 if parts.scheme == 'https' else 80)
    try:
        infos = socket.getaddrinfo(parts.hostname, port, proto=socket.IPPROTO_TCP)
    except Exception:
        return False

    for info in infos:
        try:
            ip = ipaddress.ip_address(info[4][0])
        except ValueError:
            return False
        # is_global is False for private/loopback/link-local/reserved ranges.
        if not ip.is_global or ip.is_multicast:
            logger.warning("SSRF guard blocked %s (resolves to %s)", url, ip)
            return False
    return True


def safe_get(client, url, headers=None, max_redirects=_MAX_REDIRECTS):
    """httpx GET that validates the URL and every redirect hop against the guard.

    ``client`` must have ``follow_redirects=False``. Returns the final Response,
    or None if the URL / any hop is unsafe or the chain is too long.
    """
    current = url
    for _ in range(max_redirects + 1):
        if not is_safe_public_url(current):
            return None
        resp = client.get(current, headers=headers)
        if resp.is_redirect and 'location' in resp.headers:
            current = str(resp.next_request.url) if resp.next_request else resp.headers['location']
            continue
        return resp
    logger.warning("SSRF guard: too many redirects from %s", url)
    return None
