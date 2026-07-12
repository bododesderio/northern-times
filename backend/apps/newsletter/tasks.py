import logging

from celery import shared_task

logger = logging.getLogger(__name__)


@shared_task
def send_due():
    """Send due newsletters."""
    from apps.newsletter.models import NewsletterIssue, Subscriber
    from apps.newsletter.services.mailer import send_campaign
    from django.utils import timezone

    issues = NewsletterIssue.objects.filter(
        status='scheduled',
        scheduled_at__lte=timezone.now(),
    )

    sent_count = 0
    for issue in issues:
        subscribers = Subscriber.objects.filter(status='active')
        for sub in subscribers:
            send_campaign(sub, issue)

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
def notify_topic_followers(article_id):
    """Email everyone following this article's category or tags.

    Each follower gets at most one email per article (category preferred over
    tag), with a one-click unfollow link tied to their follow token.
    """
    from apps.articles.models import Article, TopicFollow
    from apps.newsletter.services.mailer import abs_url, send_topic_notification

    try:
        article = Article.objects.select_related('category').get(pk=article_id)
    except Article.DoesNotExist:
        return 'Article not found'
    if article.status != 'published':
        return 'Not published'

    cat_id = article.category_id
    tag_names = dict(article.tags.values_list('id', 'name'))
    follow_ids = ([cat_id] if cat_id else []) + list(tag_names.keys())
    if not follow_ids:
        return 'No topics to notify'

    # Dedup by email; prefer the category topic name (priority 0) over tags (1).
    best = {}
    for f in TopicFollow.objects.filter(follow_id__in=follow_ids):
        if f.follow_type == 'category' and f.follow_id == cat_id:
            priority, name = 0, article.category.name
        elif f.follow_type == 'tag' and f.follow_id in tag_names:
            priority, name = 1, tag_names[f.follow_id]
        else:
            continue
        if f.email not in best or priority < best[f.email][0]:
            best[f.email] = (priority, name, f)

    sent = 0
    for email, (_priority, name, follow) in best.items():
        send_topic_notification(
            email, name, article,
            abs_url(f'/topics/unfollow/{follow.unfollow_token}/'),
        )
        sent += 1
    logger.info("Topic notifications queued for article %s: %d", article_id, sent)
    return f'Notified {sent} topic followers'


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
