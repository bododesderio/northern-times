"""
Breaking News Engine -- 4-tier scoring system.

Scores articles on a 0-100 scale to determine breaking-news prominence.

Tier 1: Manual override (100 pts)
Tier 2: Velocity detection (0-30 pts)
Tier 3: Urgency keywords (0-25 pts)
Tier 4: Multi-source clustering (0-20 pts)
Bonus:  Recency (0-25 pts, linear decay over 6 hours)
"""
import re
from datetime import timedelta

from django.db.models import Q, QuerySet
from django.utils import timezone


class BreakingNewsEngine:
    """Score articles and retrieve the current breaking-news set."""

    # -- Tier constants ----------------------------------------------------

    MANUAL_OVERRIDE_SCORE = 100

    # Velocity: similar titles in the last N hours
    VELOCITY_WINDOW_HOURS = 2
    VELOCITY_MAX_POINTS = 30
    # Each additional similar article adds this many points (capped)
    VELOCITY_POINTS_PER_MATCH = 10

    # Urgency keywords that signal breaking news
    URGENCY_KEYWORDS = [
        'BREAKING',
        'URGENT',
        'JUST IN',
        'DEVELOPING',
        'FLASH',
        'ALERT',
        'HAPPENING NOW',
        'UPDATE',
    ]
    URGENCY_MAX_POINTS = 25
    URGENCY_POINTS_PER_KEYWORD = 15  # first hit gets full points; capped

    # Multi-source clustering
    CLUSTER_WINDOW_HOURS = 6
    CLUSTER_MAX_POINTS = 20
    CLUSTER_POINTS_PER_SOURCE = 7

    # Recency bonus (linear decay)
    RECENCY_MAX_POINTS = 25
    RECENCY_WINDOW_HOURS = 6

    # get_breaking_articles lookback
    BREAKING_LOOKBACK_HOURS = 12
    BREAKING_MIN_SCORE = 30

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    def score_article(self, article) -> int:
        """
        Compute the breaking-news score for *article* (0-100).

        The article should be an ``Article`` model instance with at least
        ``is_breaking_manual``, ``title``, ``published_at``, and
        ``story_cluster_id`` populated.
        """
        # Tier 1: manual override
        if getattr(article, 'is_breaking_manual', False):
            return self.MANUAL_OVERRIDE_SCORE

        score = 0
        score += self._velocity_score(article)
        score += self._urgency_score(article)
        score += self._cluster_score(article)
        score += self._recency_bonus(article)

        return min(score, 100)

    def get_breaking_articles(self, limit: int = 5) -> QuerySet:
        """
        Return the top *limit* breaking articles from the last 12 hours,
        ordered by computed score descending.

        This performs an in-Python score computation over a small candidate
        set (published in the lookback window) and returns the qualifying
        articles as a queryset filtered to the winning IDs.
        """
        from apps.articles.models import Article

        cutoff = timezone.now() - timedelta(hours=self.BREAKING_LOOKBACK_HOURS)

        candidates = (
            Article.objects
            .published()
            .filter(published_at__gte=cutoff)
            .select_related('category')
        )

        scored = []
        for article in candidates:
            s = self.score_article(article)
            if s >= self.BREAKING_MIN_SCORE:
                scored.append((article.pk, s))

        # Sort by score desc, take top N
        scored.sort(key=lambda x: x[1], reverse=True)
        top_ids = [pk for pk, _ in scored[:limit]]

        if not top_ids:
            return Article.objects.none()

        # Preserve the computed score order via FIELD()
        preserved_order = {pk: idx for idx, pk in enumerate(top_ids)}
        qs = Article.objects.filter(pk__in=top_ids)
        return sorted(qs, key=lambda a: preserved_order.get(a.pk, 0))

    # ------------------------------------------------------------------
    # Private scoring tiers
    # ------------------------------------------------------------------

    def _velocity_score(self, article) -> int:
        """Tier 2: count articles with similar titles in the velocity window."""
        from apps.articles.models import Article

        if not article.title or not article.published_at:
            return 0

        cutoff = timezone.now() - timedelta(hours=self.VELOCITY_WINDOW_HOURS)

        # Build keyword tokens from the title (words >= 4 chars)
        words = [w.lower() for w in re.findall(r'[A-Za-z]{4,}', article.title)]
        if not words:
            return 0

        # Match articles that share at least one significant keyword
        q = Q()
        for word in words[:8]:  # cap to avoid huge OR chains
            q |= Q(title__icontains=word)

        similar_count = (
            Article.objects
            .published()
            .filter(published_at__gte=cutoff)
            .filter(q)
            .exclude(pk=article.pk)
            .values('pk')
            .count()
        )

        return min(similar_count * self.VELOCITY_POINTS_PER_MATCH,
                    self.VELOCITY_MAX_POINTS)

    def _urgency_score(self, article) -> int:
        """Tier 3: check title for urgency keywords."""
        if not article.title:
            return 0

        title_upper = article.title.upper()
        hits = sum(1 for kw in self.URGENCY_KEYWORDS if kw in title_upper)

        if hits == 0:
            return 0

        # First keyword gets full points, additional ones add diminishing value
        return min(self.URGENCY_POINTS_PER_KEYWORD + (hits - 1) * 5,
                    self.URGENCY_MAX_POINTS)

    def _cluster_score(self, article) -> int:
        """Tier 4: articles in the same story cluster within the window."""
        from apps.articles.models import Article

        if not article.story_cluster_id:
            return 0

        cutoff = timezone.now() - timedelta(hours=self.CLUSTER_WINDOW_HOURS)

        cluster_count = (
            Article.objects
            .published()
            .filter(
                story_cluster_id=article.story_cluster_id,
                published_at__gte=cutoff,
            )
            .exclude(pk=article.pk)
            .values('source_name')
            .distinct()
            .count()
        )

        return min(cluster_count * self.CLUSTER_POINTS_PER_SOURCE,
                    self.CLUSTER_MAX_POINTS)

    def _recency_bonus(self, article) -> int:
        """Linear decay bonus: 25 pts at publish time, 0 at 6 hours."""
        if not article.published_at:
            return 0

        now = timezone.now()
        age = now - article.published_at

        window = timedelta(hours=self.RECENCY_WINDOW_HOURS)
        if age >= window:
            return 0
        if age.total_seconds() <= 0:
            return self.RECENCY_MAX_POINTS

        fraction_remaining = 1 - (age / window)
        # round(), not int(): a just-published article has a few ms of age, and
        # truncation would drop its 24.999... to 24 instead of the full 25.
        return round(self.RECENCY_MAX_POINTS * fraction_remaining)
