"""
Story Thread Detector -- cluster related articles across sources.

Uses two similarity strategies:
1. pgvector cosine similarity on article embeddings (primary)
2. Jaccard keyword overlap on titles (fallback)
"""
import logging
import re
from datetime import timedelta

from django.conf import settings
from django.db.models import F
from django.utils import timezone

logger = logging.getLogger(__name__)

# Stop words excluded from Jaccard title comparison
_STOP_WORDS = frozenset({
    'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
    'of', 'with', 'by', 'from', 'is', 'was', 'are', 'were', 'be', 'been',
    'has', 'have', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
    'should', 'may', 'might', 'this', 'that', 'it', 'its', 'not', 'no',
    'as', 'if', 'so', 'up', 'out', 'about', 'into', 'over', 'after',
    'than', 'also', 'just', 'more', 'some', 'all', 'any', 'each', 'very',
    'can', 'he', 'she', 'they', 'we', 'you', 'who', 'what', 'when',
    'where', 'how', 'which', 'their', 'them', 'his', 'her', 'our',
    'says', 'said',
})


def _tokenize_title(title: str) -> set[str]:
    """
    Lowercase the title, extract alphabetic tokens of 3+ chars,
    and remove stop words.
    """
    words = re.findall(r'[a-z]{3,}', title.lower())
    return {w for w in words if w not in _STOP_WORDS}


def _jaccard_similarity(set_a: set, set_b: set) -> float:
    """Jaccard index: |A & B| / |A | B|."""
    if not set_a or not set_b:
        return 0.0
    intersection = set_a & set_b
    union = set_a | set_b
    return len(intersection) / len(union)


class StoryThreadDetector:
    """Find or create story clusters for incoming articles."""

    def __init__(
        self,
        embedding_threshold: float | None = None,
        jaccard_threshold: float = 0.4,
        candidate_window_days: int = 3,
    ):
        self.embedding_threshold = (
            embedding_threshold
            if embedding_threshold is not None
            else getattr(settings, 'CRAWLER_STORY_CLUSTER_THRESHOLD', 0.65)
        )
        self.jaccard_threshold = jaccard_threshold
        self.candidate_window_days = candidate_window_days

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    def find_cluster(self, article):
        """
        Find an existing ``StoryCluster`` that matches *article*.

        Returns the ``StoryCluster`` instance or ``None``.

        Strategy order:
        1. Cosine similarity on pgvector embeddings (if article has one).
        2. Jaccard keyword overlap on titles.
        """
        from apps.articles.models import Article, StoryCluster

        cutoff = timezone.now() - timedelta(days=self.candidate_window_days)

        # ----- Strategy 1: embedding cosine similarity --------------------
        cluster = self._find_by_embedding(article, cutoff)
        if cluster is not None:
            return cluster

        # ----- Strategy 2: Jaccard title similarity -----------------------
        cluster = self._find_by_title(article, cutoff)
        if cluster is not None:
            return cluster

        return None

    def create_or_assign_cluster(self, article) -> 'StoryCluster':
        """
        Assign *article* to a matching cluster, or create a new one.

        Updates ``article.story_cluster_id`` in the database.
        """
        from apps.articles.models import StoryCluster

        cluster = self.find_cluster(article)

        if cluster is None:
            cluster = StoryCluster.objects.create(
                title=article.title,
                canonical_article=article,
            )
            logger.info(
                'Created new story cluster %s for article %s',
                cluster.pk, article.pk,
            )
        else:
            logger.info(
                'Assigned article %s to existing cluster %s',
                article.pk, cluster.pk,
            )

        if article.story_cluster_id != cluster.pk:
            article.story_cluster_id = cluster.pk
            article.save(update_fields=['story_cluster_id'])

        return cluster

    # ------------------------------------------------------------------
    # Private strategies
    # ------------------------------------------------------------------

    def _find_by_embedding(self, article, cutoff):
        """
        Use pgvector cosine distance to find the nearest cluster.

        The cosine distance operator ``<=>`` returns values in [0, 2] where
        0 means identical.  We convert to similarity = 1 - distance and
        compare against the threshold.
        """
        from apps.articles.models import Article, StoryCluster
        from pgvector.django import CosineDistance

        if article.embedding is None:
            return None

        # Find the closest published article with a cluster assignment
        nearest = (
            Article.objects
            .published()
            .filter(
                published_at__gte=cutoff,
                embedding__isnull=False,
                story_cluster_id__isnull=False,
            )
            .exclude(pk=article.pk)
            .annotate(distance=CosineDistance('embedding', article.embedding))
            .order_by('distance')
            .values('story_cluster_id', 'distance')
            .first()
        )

        if nearest is None:
            return None

        similarity = 1.0 - nearest['distance']
        if similarity < self.embedding_threshold:
            return None

        try:
            return StoryCluster.objects.get(pk=nearest['story_cluster_id'])
        except StoryCluster.DoesNotExist:
            return None

    def _find_by_title(self, article, cutoff):
        """
        Fall back to Jaccard keyword similarity over recent article titles.
        """
        from apps.articles.models import Article, StoryCluster

        if not article.title:
            return None

        target_tokens = _tokenize_title(article.title)
        if not target_tokens:
            return None

        candidates = (
            Article.objects
            .published()
            .filter(
                published_at__gte=cutoff,
                story_cluster_id__isnull=False,
            )
            .exclude(pk=article.pk)
            .values_list('title', 'story_cluster_id')
            [:500]  # cap candidates to avoid scanning entire table
        )

        best_cluster_id = None
        best_score = 0.0

        for title, cluster_id in candidates:
            cand_tokens = _tokenize_title(title)
            score = _jaccard_similarity(target_tokens, cand_tokens)
            if score > best_score:
                best_score = score
                best_cluster_id = cluster_id

        if best_score < self.jaccard_threshold or best_cluster_id is None:
            return None

        try:
            return StoryCluster.objects.get(pk=best_cluster_id)
        except StoryCluster.DoesNotExist:
            return None
