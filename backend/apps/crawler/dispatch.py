"""
Post-publish side-effects for freshly crawled articles.

Isolates network/queue side-effects (webhook fan-out + push notification) from
the persistence step of the crawl pipeline, so "store an article" no longer
implies "make outbound calls". Every failure is swallowed — a side-effect must
never break the crawl. Behaviour matches the former engine ``_dispatch_webhook``
plus the inline push block in ``_process_item``.
"""
import logging

logger = logging.getLogger(__name__)


def dispatch_article_published(article) -> None:
    """Fire the ``article.published`` webhook asynchronously."""
    try:
        from apps.webhooks.services.dispatcher import WebhookDispatcher
        WebhookDispatcher.dispatch_async('article.published', {
            'id': str(article.id),
            'title': article.title,
            'slug': article.slug,
            'source_name': article.source_name,
            'source_url': article.source_url,
        })
    except Exception:
        pass  # Webhook failure must never block the crawl


def emit_published(article) -> None:
    """Run all post-publish side-effects for a published article.

    Webhook dispatch + new-article push notification. Both are best-effort.
    """
    dispatch_article_published(article)
    try:
        from apps.articles.tasks import notify_new_article
        notify_new_article.delay(str(article.id))
    except Exception:
        pass  # Non-critical — don't break crawl pipeline
    try:
        from apps.newsletter.tasks import notify_topic_followers
        notify_topic_followers.delay(str(article.id))
    except Exception:
        pass  # Non-critical — don't break crawl pipeline
