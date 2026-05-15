import logging
from typing import Optional

import httpx
from django.conf import settings
from django.core.cache import cache

logger = logging.getLogger(__name__)

CACHE_TTL = 60 * 60 * 24  # 24 hours
CACHE_PREFIX = 'geoip:'

# Sentinel for "lookup attempted but no result"
_EMPTY_RESULT = {
    'country': '',
    'city': '',
    'latitude': None,
    'longitude': None,
    'country_code': '',
}


def lookup(ip_address: str) -> dict:
    """Look up geographic information for an IP address.

    Returns a dict with keys: country, city, latitude, longitude, country_code.
    Results are cached in Redis for 24 hours.
    """
    if not ip_address or ip_address in ('127.0.0.1', '::1'):
        return dict(_EMPTY_RESULT)

    cache_key = f'{CACHE_PREFIX}{ip_address}'
    cached = cache.get(cache_key)
    if cached is not None:
        return cached

    # Try MaxMind first, then fall back to ip-api.com
    result = _lookup_maxmind(ip_address)
    if result is None:
        result = _lookup_ip_api(ip_address)
    if result is None:
        result = dict(_EMPTY_RESULT)

    cache.set(cache_key, result, CACHE_TTL)
    return result


def get_client_ip(request) -> str:
    """Extract the real client IP from a Django request.

    Parses X-Forwarded-For header (first non-private IP in the chain),
    falling back to REMOTE_ADDR.
    """
    forwarded_for = request.META.get('HTTP_X_FORWARDED_FOR', '')
    if forwarded_for:
        # X-Forwarded-For may contain: client, proxy1, proxy2
        # The leftmost is the original client IP
        ips = [ip.strip() for ip in forwarded_for.split(',')]
        for ip in ips:
            if ip and not _is_private_ip(ip):
                return ip
        # All are private — return the first one anyway
        return ips[0] if ips else request.META.get('REMOTE_ADDR', '')

    return request.META.get('REMOTE_ADDR', '')


# ---------------------------------------------------------------------------
# MaxMind GeoLite2 lookup
# ---------------------------------------------------------------------------

def _lookup_maxmind(ip_address: str) -> Optional[dict]:
    """Attempt a MaxMind GeoLite2-City database lookup."""
    geoip_path = getattr(settings, 'GEOIP_PATH', None)
    if not geoip_path:
        return None

    try:
        import geoip2.database
    except ImportError:
        logger.debug('geoip2 library not installed; skipping MaxMind lookup')
        return None

    db_path = f'{geoip_path}/GeoLite2-City.mmdb'
    try:
        with geoip2.database.Reader(db_path) as reader:
            response = reader.city(ip_address)
            return {
                'country': response.country.name or '',
                'city': response.city.name or '',
                'latitude': response.location.latitude,
                'longitude': response.location.longitude,
                'country_code': response.country.iso_code or '',
            }
    except FileNotFoundError:
        logger.warning('MaxMind database not found at %s', db_path)
        return None
    except Exception:
        logger.debug('MaxMind lookup failed for %s', ip_address, exc_info=True)
        return None


# ---------------------------------------------------------------------------
# ip-api.com fallback
# ---------------------------------------------------------------------------

def _lookup_ip_api(ip_address: str) -> Optional[dict]:
    """Fallback lookup via the free ip-api.com JSON endpoint."""
    url = f'http://ip-api.com/json/{ip_address}?fields=status,country,city,lat,lon,countryCode'
    try:
        resp = httpx.get(url, timeout=5.0)
        resp.raise_for_status()
        data = resp.json()
        if data.get('status') != 'success':
            return None
        return {
            'country': data.get('country', ''),
            'city': data.get('city', ''),
            'latitude': data.get('lat'),
            'longitude': data.get('lon'),
            'country_code': data.get('countryCode', ''),
        }
    except Exception:
        logger.debug('ip-api.com lookup failed for %s', ip_address, exc_info=True)
        return None


# ---------------------------------------------------------------------------
# Private IP check
# ---------------------------------------------------------------------------

def _is_private_ip(ip: str) -> bool:
    """Return True if the IP address is in a private/reserved range."""
    import ipaddress

    try:
        addr = ipaddress.ip_address(ip)
        return addr.is_private or addr.is_loopback or addr.is_reserved
    except ValueError:
        return False
