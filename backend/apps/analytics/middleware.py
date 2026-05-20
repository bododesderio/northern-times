"""Visitor tracking middleware — records site visitors for analytics."""
import logging
from datetime import date

from django.conf import settings

logger = logging.getLogger(__name__)


class VisitorTrackingMiddleware:
    """Tracks visitor IP, geo-location, and device info per request."""

    # Skip tracking for these path prefixes
    SKIP_PREFIXES = (
        '/static/', '/uploads/', '/favicon', '/robots.txt',
        '/sitemap', '/healthz', '/api/health', '/manifest.json',
        '/service-worker', '/django-admin/',
    )

    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        response = self.get_response(request)

        # Skip non-page requests
        if request.path.startswith(self.SKIP_PREFIXES):
            return response

        # Skip AJAX/API requests
        if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
            return response

        # Skip non-GET requests
        if request.method != 'GET':
            return response

        # Skip bots (basic check)
        ua = request.META.get('HTTP_USER_AGENT', '')
        if not ua or any(b in ua.lower() for b in ('bot', 'spider', 'crawl', 'curl', 'wget')):
            return response

        # Track visitor (errors must not break the response)
        try:
            self._track_visitor(request, ua)
        except Exception as e:
            logger.warning("Visitor tracking error: %s", e)

        return response

    def _track_visitor(self, request, ua: str):
        """Record visitor in database (deduped by IP + date)."""
        from apps.analytics.models import SiteVisitor
        from apps.core.helpers import get_client_ip

        ip = get_client_ip(request)
        if not ip:
            return
        today = date.today()

        # Skip if already tracked today (1 record per IP per day)
        if SiteVisitor.objects.filter(ip_address=ip, visit_date=today).exists():
            return

        # Parse user agent (safe — pure regex, no external deps)
        device_info = {}
        try:
            from apps.analytics.services.ua_parser import parse
            device_info = parse(ua)
        except Exception:
            pass

        # GeoIP lookup (optional — skip for local IPs, cached internally)
        geo = {}
        if ip not in ('127.0.0.1', '::1', '0.0.0.0'):
            try:
                from apps.analytics.services.geoip import lookup
                geo = lookup(ip)
            except Exception:
                pass

        SiteVisitor.objects.create(
            ip_address=ip,
            country=geo.get('country', ''),
            city=geo.get('city', ''),
            latitude=geo.get('latitude'),
            longitude=geo.get('longitude'),
            visit_date=today,
            device_type=device_info.get('device_type', ''),
            browser=device_info.get('browser', ''),
            os=device_info.get('os', ''),
        )
