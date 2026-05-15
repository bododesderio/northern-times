import logging

from celery import shared_task

logger = logging.getLogger(__name__)


@shared_task
def send_due():
    """Send due newsletters."""
    from apps.newsletter.models import NewsletterIssue, Subscriber
    from apps.newsletter.services.mailer import queue_email
    from django.utils import timezone

    issues = NewsletterIssue.objects.filter(
        status='scheduled',
        scheduled_at__lte=timezone.now(),
    )

    sent_count = 0
    for issue in issues:
        subscribers = Subscriber.objects.filter(status='active')
        for sub in subscribers:
            queue_email(sub.email, issue.subject, issue.content)

        issue.status = 'sent'
        issue.sent_at = timezone.now()
        issue.recipient_count = subscribers.count()
        issue.save()
        sent_count += 1
        logger.info(
            f"Newsletter '{issue.subject}' queued for "
            f"{issue.recipient_count} subscribers"
        )

    return f"Processed {sent_count} newsletters"


@shared_task
def process_email_queue():
    """Process pending emails in the queue."""
    from apps.newsletter.services.mailer import process_queue

    result = process_queue(batch_size=50)
    return f"Processed {result['sent']} sent, {result['failed']} failed of {result['total']}"


@shared_task
def weekly_digest():
    """Generate and send weekly digest."""
    from apps.articles.models import Article
    from apps.newsletter.models import Subscriber
    from apps.newsletter.services.mailer import send_digest
    from django.utils import timezone
    from datetime import timedelta

    week_ago = timezone.now() - timedelta(days=7)
    top_articles = list(
        Article.objects.published()
        .filter(published_at__gte=week_ago)
        .order_by('-quality_score', '-published_at')[:10]
    )

    if not top_articles:
        return "No articles for digest"

    subscribers = Subscriber.objects.filter(status='active')
    count = 0
    for sub in subscribers:
        send_digest(sub, top_articles)
        count += 1

    logger.info(f"Weekly digest sent to {count} subscribers")
    return f"Digest sent to {count} subscribers"
