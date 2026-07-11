import logging

from celery import shared_task

logger = logging.getLogger(__name__)


@shared_task(bind=True, max_retries=1, time_limit=600)
def process_queue(self):
    """Process pending article rewrites using OpenAI."""
    from django.conf import settings
    from apps.articles.models import Article
    from apps.rewriter.service import ArticleRewriter, has_valid_openai_key

    if not settings.REWRITER_ENABLED:
        return "Rewriter disabled"

    # Skip cleanly when there's no usable API key, rather than 401-failing every
    # queued article each cycle. Articles remain published with original content.
    if not has_valid_openai_key():
        logger.warning(
            "Rewriter enabled but OPENAI_API_KEY is missing/placeholder — "
            "skipping rewrites (articles keep their original crawled content)."
        )
        return "Rewriter skipped: no valid OpenAI API key"

    batch_size = settings.REWRITER_BATCH_SIZE
    articles = list(
        Article.objects.filter(rewrite_status='queued')
        .order_by('created_at')[:batch_size]
    )

    if not articles:
        return "No articles queued"

    rewriter = ArticleRewriter()

    processed = 0
    for article in articles:
        article.rewrite_status = 'processing'
        article.save(update_fields=['rewrite_status'])

        result = rewriter.rewrite(article)
        if result:
            article.rewritten_title = result['title']
            article.rewritten_content = result['content']
            article.rewritten_excerpt = result['excerpt']
            article.rewrite_tokens_used = result.get('tokens_used', 0)
            article.rewrite_cost = result.get('cost', 0)
            article.rewrite_status = 'completed'
            processed += 1
        else:
            article.rewrite_status = 'failed'

        article.save(update_fields=[
            'rewritten_title', 'rewritten_content',
            'rewritten_excerpt', 'rewrite_status',
            'rewrite_tokens_used', 'rewrite_cost',
        ])

    logger.info(f"Rewriter: {processed}/{len(articles)} articles processed")
    return f"Processed {processed}/{len(articles)} articles"
