import logging
from typing import Optional

from django.conf import settings
from django.core.mail import send_mail
from django.template.loader import render_to_string
from django.utils import timezone

logger = logging.getLogger(__name__)

MAX_ATTEMPTS = 3


def site_context() -> dict:
    """Common context (site name, absolute base URL, location) for all emails."""
    from apps.core.models import Setting

    domain = getattr(settings, 'APP_DOMAIN', 'localhost')
    base = (getattr(settings, 'APP_URL', '') or f'https://{domain}').rstrip('/')
    return {
        'site_name': Setting.get('site_name', getattr(settings, 'APP_NAME', 'Northern Times')),
        'site_url': base,
        'site_location': Setting.get('contact_location', ''),
    }


def abs_url(path: str) -> str:
    """Build an absolute site URL for a root-relative path."""
    return f"{site_context()['site_url']}{path}"


def _unsub_url(subscriber) -> str:
    return abs_url(f'/newsletter/unsubscribe/{subscriber.unsub_token}/')


def send_confirmation(subscriber) -> 'EmailQueue':
    """Double opt-in: queue the confirmation email with the confirm link."""
    ctx = site_context()
    ctx.update({
        'subscriber': subscriber,
        'confirm_url': abs_url(f'/newsletter/confirm/{subscriber.confirm_token}/'),
        'unsub_url': _unsub_url(subscriber),
    })
    html = render_to_string('newsletter/confirm_email.html', ctx)
    return queue_email(subscriber.email, f"Confirm your subscription — {ctx['site_name']}", html)


def send_welcome(subscriber, articles=None) -> 'EmailQueue':
    """Queue the welcome email once a subscription is confirmed."""
    ctx = site_context()
    ctx.update({
        'subscriber': subscriber,
        'articles': articles or [],
        'unsub_url': _unsub_url(subscriber),
    })
    html = render_to_string('newsletter/welcome.html', ctx)
    return queue_email(subscriber.email, f"Welcome to {ctx['site_name']}", html)


def send_email(
    to: str,
    subject: str,
    html_body: str,
    from_email: Optional[str] = None,
) -> bool:
    """Send a single email via Django's built-in email framework."""
    sender = from_email or settings.DEFAULT_FROM_EMAIL
    try:
        send_mail(
            subject=subject,
            message='',  # plain-text fallback (empty; HTML is primary)
            from_email=sender,
            recipient_list=[to],
            html_message=html_body,
            fail_silently=False,
        )
        logger.info('Email sent to %s: %s', to, subject)
        return True
    except Exception:
        logger.exception('Failed to send email to %s: %s', to, subject)
        return False


def queue_email(to: str, subject: str, html_body: str) -> 'EmailQueue':
    """Create an EmailQueue record for asynchronous sending."""
    from apps.newsletter.models import EmailQueue

    item = EmailQueue.objects.create(
        to_email=to,
        subject=subject,
        body_html=html_body,
        status='pending',
    )
    logger.info('Queued email %s to %s: %s', item.pk, to, subject)
    return item


def process_queue(batch_size: int = 50) -> dict:
    """Process pending EmailQueue items in batches.

    Returns a summary dict with counts of sent and failed items.
    """
    from apps.newsletter.models import EmailQueue

    pending = (
        EmailQueue.objects
        .filter(status='pending', attempts__lt=MAX_ATTEMPTS)
        .order_by('created_at')[:batch_size]
    )

    sent_count = 0
    failed_count = 0

    for item in pending:
        item.attempts += 1
        success = send_email(item.to_email, item.subject, item.body_html)

        if success:
            item.status = 'sent'
            item.sent_at = timezone.now()
            sent_count += 1
        else:
            if item.attempts >= MAX_ATTEMPTS:
                item.status = 'failed'
                item.error_message = f'Permanently failed after {MAX_ATTEMPTS} attempts'
                failed_count += 1
                logger.warning(
                    'Email %s permanently failed after %d attempts',
                    item.pk,
                    MAX_ATTEMPTS,
                )
            # else remains 'pending' for retry on next run

        item.save(update_fields=['status', 'attempts', 'sent_at', 'error_message'])

    logger.info(
        'Queue processed: %d sent, %d failed out of %d',
        sent_count,
        failed_count,
        len(pending),
    )
    return {'sent': sent_count, 'failed': failed_count, 'total': len(pending)}


def send_digest(subscriber: 'Subscriber', articles) -> 'EmailQueue':
    """Render a digest email from the template and queue it for sending.

    Args:
        subscriber: A Subscriber model instance.
        articles: A queryset or list of Article instances to include.

    Returns:
        The created EmailQueue record.
    """
    ctx = site_context()
    ctx.update({
        'subscriber': subscriber,
        'articles': articles,
        'unsub_url': _unsub_url(subscriber),
    })
    html_body = render_to_string('newsletter/digest.html', ctx)

    subject = f"Your Digest — {ctx['site_name']}"
    return queue_email(subscriber.email, subject, html_body)
