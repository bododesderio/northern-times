import logging

from celery import shared_task

logger = logging.getLogger(__name__)


@shared_task
def publish_scheduled():
    """Publish articles whose published_at has passed."""
    from apps.articles.models import Article
    from django.utils import timezone

    now = timezone.now()
    scheduled = Article.objects.with_deleted().filter(
        status='scheduled',
        published_at__lte=now,
        deleted_at__isnull=True,
    )

    # Collect IDs before update so we can dispatch webhooks
    article_ids = list(scheduled.values_list('id', flat=True))
    count = scheduled.update(status='published')

    if count:
        logger.info(f"Published {count} scheduled articles")

        # Dispatch webhooks + push notifications for each newly published article
        from apps.webhooks.services.dispatcher import WebhookDispatcher
        published = Article.objects.filter(id__in=article_ids)
        for article in published:
            WebhookDispatcher.dispatch_async('article.published', {
                'id': str(article.id),
                'title': article.title,
                'slug': article.slug,
            })

        # Send push notification for first article (avoid spamming)
        if article_ids:
            notify_new_article.delay(str(article_ids[0]))

        # Email topic followers for each newly published article (opt-in only).
        from apps.newsletter.tasks import notify_topic_followers
        for aid in article_ids:
            notify_topic_followers.delay(str(aid))

    return f"Published {count} articles"


@shared_task
def geocode_pending_articles(limit=500):
    """Backfill coordinates for articles that don't have them yet.

    The crawler geocodes at ingest, but this catches anything missed (entities
    added later, gazetteer expanded, imported rows). Cheap gazetteer lookups.
    """
    from apps.articles.models import Article
    from apps.articles.services.geo import apply_geocode

    qs = (Article.objects.filter(latitude__isnull=True)
          .prefetch_related('entities')[:limit])
    done = 0
    for article in qs:
        try:
            if apply_geocode(article):
                done += 1
        except Exception:
            logger.exception('geocode_pending_articles: failed for %s', article.id)
    logger.info('geocode_pending_articles: geocoded %d article(s)', done)
    return f'Geocoded {done} articles'


@shared_task
def notify_new_article(article_id):
    """Send Web Push notification for a newly published article."""
    from apps.articles.models import Article

    try:
        article = Article.objects.get(pk=article_id)
    except Article.DoesNotExist:
        return "Article not found"

    # Build notification with summary
    title = article.title
    body = article.ai_summary or article.excerpt or title
    if len(body) > 120:
        body = body[:117] + '...'

    url = f'/article/{article.slug}/'

    try:
        from apps.newsletter.services.webpush import send_bulk_notification
        result = send_bulk_notification(title=title, body=body, url=url)
        logger.info(f"Push notification sent for '{title}': {result}")
        return f"Sent: {result.get('sent', 0)}, Failed: {result.get('failed', 0)}"
    except Exception as e:
        logger.error(f"Push notification failed: {e}")
        return f"Failed: {e}"
