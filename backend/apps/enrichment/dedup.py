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
        """Check if embedding is semantically similar to recent articles via pgvector."""
        from apps.articles.models import Article
        from pgvector.django import CosineDistance
        from django.utils import timezone
        from datetime import timedelta

        if threshold is None:
            threshold = getattr(settings, 'CRAWLER_SEMANTIC_DEDUP_THRESHOLD', 0.82)

        three_days_ago = timezone.now() - timedelta(days=3)

        # Find most similar article in last 3 days
        similar = (
            Article.objects.with_deleted()
            .filter(
                embedding__isnull=False,
                created_at__gte=three_days_ago,
            )
            .annotate(distance=CosineDistance('embedding', embedding))
            .filter(distance__lt=(1 - threshold))
            .order_by('distance')
            .first()
        )

        return similar is not None
