import logging
from datetime import timedelta

from celery import shared_task
from django.utils import timezone

logger = logging.getLogger(__name__)


@shared_task
def ai_reclassify_articles():
    """Daily AI reclassification of recent articles."""
    from apps.articles.models import Article, Category
    from apps.enrichment.category_classifier import CategoryClassifier

    cutoff = timezone.now() - timedelta(hours=24)
    articles = Article.objects.published().filter(
        published_at__gte=cutoff,
    ).select_related('category')

    classifier = CategoryClassifier()
    categories = {c.slug: c for c in Category.objects.all()}
    reclassified = 0

    for article in articles:
        try:
            text = f"{article.title} {article.excerpt or ''} {(article.content or '')[:1000]}"
            result = classifier.classify(text)
            if not result:
                continue

            predicted_slug, confidence = result
            if confidence > 0.5 and predicted_slug in categories:
                new_cat = categories[predicted_slug]
                if article.category != new_cat:
                    article.category = new_cat
                    article.save(update_fields=['category'])
                    reclassified += 1
                    logger.info(
                        f"Reclassified article {article.id} "
                        f"from {article.category} to {new_cat.name} "
                        f"(confidence: {confidence:.2f})"
                    )
        except Exception as e:
            logger.warning(f"Reclassify failed for article {article.id}: {e}")

    logger.info(f"AI reclassification complete: {reclassified} articles updated.")
    return reclassified


@shared_task
def cleanup_duplicates():
    """Archive duplicate articles using pgvector cosine similarity > 0.90."""
    from apps.articles.models import Article

    cutoff = timezone.now() - timedelta(hours=48)
    recent = list(
        Article.objects.published()
        .filter(published_at__gte=cutoff)
        .exclude(embedding__isnull=True)
        .order_by('published_at')
    )

    archived = 0
    seen_ids = set()

    for i, article in enumerate(recent):
        if article.id in seen_ids:
            continue
        if not article.embedding:
            continue

        # Find near-duplicates among remaining articles
        for j in range(i + 1, len(recent)):
            other = recent[j]
            if other.id in seen_ids:
                continue
            if not other.embedding:
                continue

            try:
                # Compute cosine similarity
                from pgvector.django import CosineDistance
                distance = Article.objects.filter(
                    id=other.id,
                ).annotate(
                    dist=CosineDistance('embedding', article.embedding),
                ).values_list('dist', flat=True).first()

                if distance is not None and (1 - distance) > 0.90:
                    # Archive the newer article
                    other.status = 'archived'
                    other.save(update_fields=['status'])
                    seen_ids.add(other.id)
                    archived += 1
                    logger.info(
                        f"Archived duplicate: {other.id} "
                        f"(similar to {article.id}, similarity: {1 - distance:.3f})"
                    )
            except Exception as e:
                logger.warning(f"Duplicate check failed for {other.id}: {e}")

    logger.info(f"Duplicate cleanup complete: {archived} articles archived.")
    return archived
