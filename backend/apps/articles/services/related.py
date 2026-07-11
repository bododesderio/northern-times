"""Related-article selection.

Old behaviour: story-cluster members, then "same category, newest first" — which
often surfaced unrelated stories that merely shared a category. Every article
already carries a 384-d sentence-transformer embedding, so we can rank by actual
semantic closeness (pgvector cosine distance) and fall back to category only to
top up when there are too few embedded neighbours.

Order of precedence, de-duplicated, capped at ``limit``:
  1. Same story cluster (a genuinely continuing story).
  2. Nearest embedding neighbours (real topical similarity).
  3. Same-category recency (fallback / top-up).
"""
import logging

logger = logging.getLogger(__name__)

# Cosine distance beyond this is "not really related" — drop it rather than pad
# the list with a loosely-associated story.
_MAX_DISTANCE = 0.55


def related_articles(article, limit: int = 6) -> list:
    from apps.articles.models import Article

    picked: list = []
    seen = {article.id}

    def add(candidates):
        for a in candidates:
            if a.id not in seen:
                seen.add(a.id)
                picked.append(a)
                if len(picked) >= limit:
                    return True
        return False

    # 1. Continuing story (same cluster).
    if article.story_cluster_id:
        cluster = (
            Article.objects.published()
            .filter(story_cluster_id=article.story_cluster_id)
            .exclude(id=article.id)
            .order_by('-published_at')[:limit]
        )
        if add(cluster):
            return picked

    # 2. Semantic nearest neighbours over the embedding.
    if article.embedding is not None:
        try:
            from pgvector.django import CosineDistance

            neighbours = (
                Article.objects.published()
                .filter(embedding__isnull=False)
                .exclude(id__in=seen)
                .annotate(distance=CosineDistance('embedding', article.embedding))
                .filter(distance__lt=_MAX_DISTANCE)
                .order_by('distance')
                .select_related('category')[:limit]
            )
            if add(neighbours):
                return picked
        except Exception as exc:  # pragma: no cover - defensive
            logger.debug('semantic related failed: %s', exc)

    # 3. Category recency fallback / top-up.
    if article.category_id and len(picked) < limit:
        category_related = (
            Article.objects.published()
            .filter(category_id=article.category_id)
            .exclude(id__in=seen)
            .order_by('-published_at')
            .select_related('category')[:limit]
        )
        add(category_related)

    return picked
