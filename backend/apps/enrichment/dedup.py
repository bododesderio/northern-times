import logging
import re
from django.conf import settings

logger = logging.getLogger(__name__)


class DuplicateChecker:
    """Cross-source duplicate detection using Jaccard similarity + pgvector."""

    STOP_WORDS = frozenset([
        'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
        'should', 'may', 'might', 'shall', 'can', 'to', 'of', 'in', 'for',
        'on', 'with', 'at', 'by', 'from', 'as', 'into', 'through', 'during',
        'before', 'after', 'above', 'below', 'between', 'and', 'but', 'or',
        'not', 'no', 'so', 'if', 'than', 'that', 'this', 'it', 'its',
    ])

    def _tokenize(self, text: str) -> set[str]:
        """Tokenize and normalize text for comparison."""
        words = re.findall(r'\w+', text.lower())
        return {w for w in words if w not in self.STOP_WORDS and len(w) > 2}

    def jaccard_similarity(self, text1: str, text2: str) -> float:
        """Calculate Jaccard similarity between two texts."""
        set1 = self._tokenize(text1)
        set2 = self._tokenize(text2)
        if not set1 or not set2:
            return 0.0
        intersection = set1 & set2
        union = set1 | set2
        return len(intersection) / len(union)

    def is_duplicate(self, title: str, existing_titles: list[str], threshold: float = 0.55) -> bool:
        """Check if title is a fuzzy duplicate of any existing title."""
        for existing in existing_titles:
            if self.jaccard_similarity(title, existing) >= threshold:
                return True
        return False

    def semantic_check(self, embedding: list[float], threshold: float = None) -> bool:
        """Check if embedding is semantically similar to recent articles via pgvector.

        Thin back-compat wrapper — returns True when a live match exists. New code
        should use ``find_duplicate`` (returns the matching Article for keep-best).
        """
        return self.find_duplicate(embedding, title='', threshold=threshold) is not None

    def find_duplicate(
        self,
        embedding: list[float],
        title: str = '',
        entities: list = None,
        window_days: int = 4,
        threshold: float = None,
    ):
        """Return the best-matching LIVE Article for keep-best resolution, or None.

        Same-story-different-site is caught by combining semantic cosine (pgvector)
        with headline Jaccard: an exact-topic embedding match, or a strong semantic
        near-miss backed by a shared headline. The window is widened to 4 days so a
        story that breaks late on one outlet still resolves against an earlier one.
        Only non-deleted rows are candidates — we never "keep" a purged loser.
        """
        from apps.articles.models import Article
        from pgvector.django import CosineDistance
        from django.utils import timezone
        from datetime import timedelta

        if embedding is None:
            return None
        if threshold is None:
            threshold = getattr(settings, 'CRAWLER_SEMANTIC_DEDUP_THRESHOLD', 0.82)

        window_start = timezone.now() - timedelta(days=window_days)
        # Headline-overlap floor for a semantic near-miss to still count as the
        # same story (different outlets reword the same facts).
        near_miss = max(0.0, threshold - 0.12)

        candidates = (
            Article.objects.with_deleted()
            .filter(
                deleted_at__isnull=True,
                embedding__isnull=False,
                created_at__gte=window_start,
            )
            .annotate(distance=CosineDistance('embedding', embedding))
            .order_by('distance')[:5]
        )
        for cand in candidates:
            cosine_sim = 1.0 - float(cand.distance)
            if cosine_sim >= threshold:
                return cand
            if (
                title
                and cosine_sim >= near_miss
                and self.jaccard_similarity(title, cand.title) >= 0.5
            ):
                return cand
        return None
