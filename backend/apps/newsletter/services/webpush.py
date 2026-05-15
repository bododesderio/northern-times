import json
import logging

from django.conf import settings

from apps.newsletter.models import PushSubscription

logger = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Helper to build VAPID claims
# ---------------------------------------------------------------------------

def _vapid_claims() -> dict:
    email = getattr(settings, 'VAPID_EMAIL', '')
    return {'sub': f'mailto:{email}'}


def _vapid_private_key() -> str:
    return getattr(settings, 'VAPID_PRIVATE_KEY', '')


# ---------------------------------------------------------------------------
# Public API
# ---------------------------------------------------------------------------

def send_notification(
    subscription_info: dict,
    title: str,
    body: str,
    url: str | None = None,
) -> bool:
    """Send a single Web Push notification.

    Args:
        subscription_info: Dict with keys ``endpoint``, ``keys`` (containing
            ``p256dh`` and ``auth``).
        title: Notification title.
        body: Notification body text.
        url: Optional click-through URL.

    Returns:
        True on success, False on failure.
    """
    from pywebpush import webpush, WebPushException

    payload = {'title': title, 'body': body}
    if url:
        payload['url'] = url

    try:
        webpush(
            subscription_info=subscription_info,
            data=json.dumps(payload),
            vapid_private_key=_vapid_private_key(),
            vapid_claims=_vapid_claims(),
        )
        return True
    except WebPushException as exc:
        logger.warning('WebPush failed for %s: %s', subscription_info.get('endpoint', '?'), exc)
        # If the subscription is gone (HTTP 404/410), remove it
        status_code = getattr(exc, 'response', None) and exc.response.status_code
        if status_code in (404, 410):
            endpoint = subscription_info.get('endpoint', '')
            PushSubscription.objects.filter(endpoint=endpoint).delete()
            logger.info('Removed stale subscription: %s', endpoint[:80])
        return False


def send_bulk_notification(
    title: str,
    body: str,
    url: str | None = None,
) -> dict:
    """Send a Web Push notification to all active subscribers.

    Returns:
        Dict with ``sent`` and ``failed`` counts.
    """
    subscriptions = PushSubscription.objects.all()
    sent = 0
    failed = 0

    for sub in subscriptions.iterator():
        info = {
            'endpoint': sub.endpoint,
            'keys': {
                'p256dh': sub.p256dh,
                'auth': sub.auth,
            },
        }
        if send_notification(info, title, body, url):
            sent += 1
        else:
            failed += 1

    logger.info('Bulk push: %d sent, %d failed out of %d', sent, failed, sent + failed)
    return {'sent': sent, 'failed': failed}


def subscribe(subscription_json: dict | str) -> PushSubscription:
    """Store a new push subscription.

    Args:
        subscription_json: The PushSubscription JSON from the browser, either
            as a dict or a JSON string.  Expected shape::

                {
                    "endpoint": "https://...",
                    "keys": {"p256dh": "...", "auth": "..."}
                }

    Returns:
        The created or existing PushSubscription instance.
    """
    if isinstance(subscription_json, str):
        subscription_json = json.loads(subscription_json)

    endpoint = subscription_json['endpoint']
    keys = subscription_json.get('keys', {})

    sub, created = PushSubscription.objects.update_or_create(
        endpoint=endpoint,
        defaults={
            'p256dh': keys.get('p256dh', ''),
            'auth': keys.get('auth', ''),
        },
    )
    if created:
        logger.info('New push subscription: %s', endpoint[:80])
    return sub


def unsubscribe(endpoint: str) -> bool:
    """Remove a push subscription by its endpoint URL.

    Returns:
        True if a subscription was deleted, False if it was not found.
    """
    deleted, _ = PushSubscription.objects.filter(endpoint=endpoint).delete()
    if deleted:
        logger.info('Unsubscribed push endpoint: %s', endpoint[:80])
    return deleted > 0
