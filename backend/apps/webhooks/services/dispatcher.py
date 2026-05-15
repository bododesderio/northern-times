"""
Webhook Dispatcher -- deliver events to registered webhook endpoints.

Signs payloads with HMAC-SHA256 and logs every delivery attempt.
"""
import hashlib
import hmac
import json
import logging
from datetime import datetime, timezone
from fnmatch import fnmatch

import httpx
from celery import shared_task

from apps.webhooks.models import Webhook, WebhookLog

logger = logging.getLogger(__name__)

HTTP_TIMEOUT = 10.0


class WebhookDispatcher:
    """Find matching webhooks for an event and deliver the payload."""

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    def dispatch(self, event: str, payload: dict) -> list[WebhookLog]:
        """Synchronously send *event* to every matching active webhook.

        Returns a list of WebhookLog records (one per delivery attempt).
        """
        webhooks = Webhook.objects.filter(is_active=True)
        logs: list[WebhookLog] = []
        for webhook in webhooks:
            if self._matches(webhook, event):
                log = self._send(webhook, event, payload)
                logs.append(log)
        return logs

    @staticmethod
    def dispatch_async(event: str, payload: dict) -> None:
        """Queue event delivery as a Celery background task."""
        _deliver_webhook_task.delay(event, payload)

    # ------------------------------------------------------------------
    # Matching
    # ------------------------------------------------------------------

    @staticmethod
    def _matches(webhook: Webhook, event: str) -> bool:
        """Return True if *webhook.events* contains a pattern matching
        *event*.

        Supports Unix-style glob patterns (e.g. ``article.*`` matches
        ``article.published``).
        """
        patterns = webhook.events or []
        for pattern in patterns:
            if fnmatch(event, pattern):
                return True
        return False

    # ------------------------------------------------------------------
    # Delivery
    # ------------------------------------------------------------------

    @staticmethod
    def _send(webhook: Webhook, event: str, payload: dict) -> WebhookLog:
        """POST the payload to *webhook.url*, sign it, and log the result."""
        body_dict = {
            'event': event,
            'payload': payload,
            'timestamp': datetime.now(timezone.utc).isoformat(),
        }
        body_bytes = json.dumps(body_dict, default=str).encode()

        # HMAC-SHA256 signature
        signature = hmac.new(
            webhook.secret.encode(),
            body_bytes,
            hashlib.sha256,
        ).hexdigest()

        headers = {
            'Content-Type': 'application/json',
            'X-Webhook-Signature': signature,
            'User-Agent': 'NorthernTimes-Webhook/1.0',
        }

        response_code = None
        response_body = ''

        try:
            with httpx.Client(timeout=HTTP_TIMEOUT) as client:
                resp = client.post(
                    str(webhook.url),
                    content=body_bytes,
                    headers=headers,
                )
                response_code = resp.status_code
                response_body = resp.text[:5000]  # cap stored body
        except httpx.TimeoutException:
            response_body = 'Request timed out'
            logger.warning(
                'Webhook %s timed out for event %s', webhook.url, event,
            )
        except httpx.HTTPError as exc:
            response_body = str(exc)[:5000]
            logger.error(
                'Webhook %s failed for event %s: %s',
                webhook.url, event, exc,
            )

        log = WebhookLog.objects.create(
            webhook=webhook,
            event=event,
            payload=body_dict,
            response_code=response_code,
            response_body=response_body,
        )

        if response_code and response_code < 300:
            logger.info(
                'Webhook delivered: %s -> %s [%d]',
                event, webhook.url, response_code,
            )
        else:
            logger.warning(
                'Webhook delivery issue: %s -> %s [%s]',
                event, webhook.url, response_code,
            )

        return log


# ---------------------------------------------------------------------------
# Celery task for async dispatch
# ---------------------------------------------------------------------------

@shared_task(name='webhooks.deliver', bind=True, max_retries=3,
             default_retry_delay=30)
def _deliver_webhook_task(self, event: str, payload: dict):
    """Background task that delivers webhooks via the dispatcher."""
    try:
        dispatcher = WebhookDispatcher()
        dispatcher.dispatch(event, payload)
    except Exception as exc:
        logger.error('Webhook async dispatch failed: %s', exc)
        raise self.retry(exc=exc)
