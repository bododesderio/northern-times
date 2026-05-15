"""Authentication-related middleware."""
import logging
from django.utils import timezone

logger = logging.getLogger(__name__)


class ActiveSessionMiddleware:
    """Tracks active admin sessions — updates last_activity_at every 60 seconds."""

    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        response = self.get_response(request)

        # Only track authenticated admin users
        if (
            request.user.is_authenticated
            and request.path.startswith('/admin/')
            and request.session.session_key
        ):
            self._touch_session(request)

        return response

    def _touch_session(self, request):
        """Update or create ActiveSession record (throttled to 60s intervals)."""
        from apps.accounts.models import ActiveSession
        from apps.core.helpers import get_client_ip

        session_key = request.session.session_key
        now = timezone.now()

        # Check if we recently updated (avoid DB hit every request)
        last_touch = request.session.get('_session_touched')
        if last_touch:
            try:
                from datetime import datetime
                last_dt = datetime.fromisoformat(last_touch)
                from django.utils.timezone import make_aware, is_naive
                if is_naive(last_dt):
                    last_dt = make_aware(last_dt)
                if (now - last_dt).total_seconds() < 60:
                    return
            except (ValueError, TypeError):
                pass

        try:
            session, created = ActiveSession.objects.update_or_create(
                session_id=session_key,
                defaults={
                    'user': request.user,
                    'ip_address': get_client_ip(request),
                    'user_agent': request.META.get('HTTP_USER_AGENT', '')[:500],
                },
            )
            request.session['_session_touched'] = now.isoformat()
        except Exception as e:
            logger.debug(f"Session tracking error: {e}")
