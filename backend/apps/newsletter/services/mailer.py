import logging
from typing import Optional

from django.conf import settings
from django.core.mail import send_mail
from django.template.loader import render_to_string
from django.utils import timezone

logger = logging.getLogger(__name__)

MAX_ATTEMPTS = 3


def site_context() -> dict:
    """CMS-driven context injected into every email template.

    All editor-editable branding/contact/social values come from the `Setting`
    key/value store (this project has no separate SiteSettings model) so the
    editorial team can change them without a developer. `current_year` is auto.
    """
    from datetime import date

    from apps.core.models import Setting

    domain = getattr(settings, 'APP_DOMAIN', 'localhost')
    base = (getattr(settings, 'APP_URL', '') or f'https://{domain}').rstrip('/')
    name = Setting.get('site_name', getattr(settings, 'APP_NAME', 'Northern Times'))
    settings_ctx = {
        'publication_name': name,
        'logo_url': Setting.get('email_logo_url', '') or Setting.get('site_logo', ''),
        'brand_primary_color': Setting.get('email_brand_primary', '') or Setting.get('theme_accent', '#D32F2F'),
        'brand_accent_color': Setting.get('email_brand_accent', '') or Setting.get('theme_accent', '#D32F2F'),
        'site_url': base,
        'address': Setting.get('contact_location', '') or Setting.get('contact_address', ''),
        'editorial_email': Setting.get('contact_email', '') or Setting.get('editorial_email', ''),
        'footer_tagline': Setting.get('footer_tagline', '') or Setting.get('site_tagline', ''),
        'social_facebook': Setting.get('social_facebook', ''),
        'social_whatsapp': Setting.get('social_whatsapp', ''),
        'social_x': Setting.get('social_twitter', '') or Setting.get('social_x', ''),
        'social_instagram': Setting.get('social_instagram', ''),
        'social_youtube': Setting.get('social_youtube', ''),
        'social_tiktok': Setting.get('social_tiktok', ''),
    }
    return {
        'site_name': name,
        'site_url': base,
        'site_location': settings_ctx['address'],
        'settings': settings_ctx,
        'current_year': date.today().year,
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


def send_campaign(subscriber, issue) -> 'EmailQueue':
    """Queue a newsletter issue wrapped in the branded shell + unsubscribe footer."""
    ctx = site_context()
    ctx.update({
        'subscriber': subscriber,
        'issue': issue,
        'content': issue.content,
        'unsub_url': _unsub_url(subscriber),
    })
    html = render_to_string('newsletter/campaign.html', ctx)
    return queue_email(subscriber.email, issue.subject, html)


def send_topic_notification(email, topic_name, article, unfollow_url) -> 'EmailQueue':
    """Queue a 'new in <topic>' notification to a topic follower."""
    ctx = site_context()
    ctx.update({
        'article': article,
        'topic_name': topic_name,
        'unsub_url': unfollow_url,
    })
    html = render_to_string('newsletter/topic_notify.html', ctx)
    return queue_email(email, f"New in {topic_name} — {ctx['site_name']}", html)


def send_contact_alert(message):
    """Queue an internal alert to staff when a contact/tips message arrives.

    Recipient: the `contact_alert_email` Setting, else the first superuser's email.
    Returns the EmailQueue item, or None if no recipient is configured.
    """
    from email.utils import parseaddr

    from django.db.models import Q

    from apps.accounts.models import User
    from apps.core.models import Setting

    to = (Setting.get('contact_alert_email', '') or '').strip()
    if not to:
        # This project authorises via a role FK (level), not Django's is_superuser
        # flag — try both, preferring the highest-level staffer.
        admin = (User.objects.exclude(email='')
                 .filter(Q(is_superuser=True) | Q(is_staff=True) | Q(role__level__gte=2))
                 .order_by('-role__level', 'id').first())
        to = admin.email if admin else ''
    if not to:
        # Last resort: the site's own from-address inbox.
        to = parseaddr(settings.DEFAULT_FROM_EMAIL)[1]
    if not to:
        logger.warning('Contact alert not sent: no recipient configured')
        return None

    ctx = site_context()
    ctx.update({'msg': message})
    html = render_to_string('newsletter/contact_alert.html', ctx)
    subject = message.subject or 'New message'
    return queue_email(to, f"[{ctx['site_name']}] Contact: {subject}", html)


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
