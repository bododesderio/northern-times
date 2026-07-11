"""
Hybrid article search — Postgres full-text + trigram + pgvector semantic.

The legacy search was a 4-field ``icontains`` OR: a leading-wildcard sequential
scan with no ranking, and it ignored the 384-dim embedding already stored on
every article. This service fuses three signals with Reciprocal Rank Fusion:

    1. Full-text search  — to_tsvector / websearch_to_tsquery, weighted
                           (title > excerpt/summary > body), ``SearchRank``.
    2. Trigram similarity — catches typos / partial tokens the FTS lexer misses.
    3. Semantic search    — cosine distance over the sentence-transformer
                           embedding, so "election violence" also matches
                           "clashes at the polls".

Every stage degrades gracefully: if pg_trgm, the FTS index, or the embedder is
unavailable the search still returns keyword results, and if *everything* fails
the caller falls back to the original ``icontains`` query. Behaviour for an
empty query is unchanged (no results).

Indexes backing this live in migration ``0003_search_indexes``.
"""
from __future__ import annotations

import logging

from django.conf import settings
from django.db.models import F

logger = logging.getLogger(__name__)

# RRF constant — dampens the influence of any single ranker's top hit.
_RRF_K = 60
# How many candidates each ranker contributes before fusion.
_CANDIDATE_LIMIT = 100
# Max cosine distance (0=identical, 2=opposite) for a semantic candidate.
_SEMANTIC_MAX_DISTANCE = 0.60


class HybridSearch:
    """Blend keyword + semantic relevance into a single ranked id list."""

    def __init__(self, *, use_semantic: bool | None = None):
        # Semantic ranking cold-loads the sentence-transformer (torch, ~hundreds
        # of MB, ~25s first call) into the calling process. That is fine in a
        # worker but a footgun in a gunicorn web worker, so it defaults OFF and
        # is opt-in via SEARCH_SEMANTIC_ENABLED. Callers on a warm/ML process may
        # still force it on explicitly.
        if use_semantic is None:
            use_semantic = getattr(settings, "SEARCH_SEMANTIC_ENABLED", False)
        self.use_semantic = use_semantic

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    def search_ids(self, query: str, limit: int = _CANDIDATE_LIMIT) -> list:
        """Return article ids ranked by fused relevance (best first)."""
        query = (query or "").strip()
        if not query:
            return []

        ranked_lists = []

        kw = self._keyword_rank(query, limit)
        if kw:
            ranked_lists.append(kw)

        tri = self._trigram_rank(query, limit)
        if tri:
            ranked_lists.append(tri)

        if self.use_semantic:
            sem = self._semantic_rank(query, limit)
            if sem:
                ranked_lists.append(sem)

        if not ranked_lists:
            return []
        return self._reciprocal_rank_fusion(ranked_lists, limit)

    def search(self, query: str, limit: int = _CANDIDATE_LIMIT):
        """Return a list of published Articles in fused-relevance order.

        Preserves ranking order via a CASE/position map, and eager-loads the
        category to avoid an N+1 in result rendering.
        """
        from apps.articles.models import Article

        ids = self.search_ids(query, limit)
        if not ids:
            return []

        by_id = {
            a.id: a
            for a in Article.objects.published()
            .filter(id__in=ids)
            .select_related("category")
        }
        # Re-apply fused order; drop any id that fell out of the published set.
        return [by_id[i] for i in ids if i in by_id]

    def count(self, query: str) -> int:
        """Total number of published articles matching ``query``.

        Cheap boolean count over the FTS ∪ trigram predicates — no fusion, no
        embedder. Lets callers report an honest total (e.g. "1,204 found") even
        though the browsable/fused result set is capped at ``_CANDIDATE_LIMIT``.
        """
        query = (query or "").strip()
        if not query:
            return 0
        try:
            from django.contrib.postgres.search import SearchQuery
            from django.db.models import Q

            sq = SearchQuery(query, search_type="websearch", config="english")
            return (
                self._base_qs()
                .filter(Q(search_vector=sq) | Q(title__trigram_similar=query))
                .count()
            )
        except Exception as exc:  # pragma: no cover - defensive
            logger.debug("search count failed: %s", exc)
            return 0

    # ------------------------------------------------------------------
    # Individual rankers — each returns an ordered list of ids or [].
    # ------------------------------------------------------------------

    def _base_qs(self):
        from apps.articles.models import Article

        return Article.objects.published()

    def _keyword_rank(self, query: str, limit: int) -> list:
        try:
            from django.contrib.postgres.search import SearchQuery, SearchRank

            search_query = SearchQuery(query, search_type="websearch", config="english")
            rows = (
                self._base_qs()
                # ``search_vector`` is the STORED weighted tsvector generated
                # column (see Article.search_vector). Filtering on it emits
                # ``search_vector @@ query`` which the article_fts_gin index
                # serves; F() ranks the stored vector directly (no re-vectorize).
                .filter(search_vector=search_query)
                .annotate(rank=SearchRank(F("search_vector"), search_query))
                .order_by("-rank")
                .values_list("id", flat=True)[:limit]
            )
            return list(rows)
        except Exception as exc:  # pragma: no cover - defensive
            logger.warning("keyword_rank failed: %s", exc)
            return []

    def _trigram_rank(self, query: str, limit: int) -> list:
        try:
            from django.contrib.postgres.search import TrigramWordSimilarity
            from django.db import connection, transaction

            # Word-similarity (`<%`) matches the query against the closest WORD in
            # the title, so a one-word typo ("musveni") matches a long headline
            # ("Museveni deploys new Cabinet"), which the whole-string `%` operator
            # cannot. Lower the word-similarity threshold for this query only
            # (SET LOCAL, scoped to the transaction) to admit reasonable typos.
            with transaction.atomic():
                with connection.cursor() as cur:
                    cur.execute("SET LOCAL pg_trgm.word_similarity_threshold = 0.35")
                rows = (
                    self._base_qs()
                    .filter(title__trigram_word_similar=query)
                    .annotate(sim=TrigramWordSimilarity(query, "title"))
                    .order_by("-sim")
                    .values_list("id", flat=True)[:limit]
                )
                return list(rows)
        except Exception as exc:
            # pg_trgm extension not installed — non-fatal.
            logger.debug("trigram_rank unavailable: %s", exc)
            return []

    def _semantic_rank(self, query: str, limit: int) -> list:
        try:
            from pgvector.django import CosineDistance

            from apps.enrichment.embedder import Embedder

            embedding = Embedder().embed(query)
            if not embedding:
                return []
            rows = (
                self._base_qs()
                .filter(embedding__isnull=False)
                .annotate(distance=CosineDistance("embedding", embedding))
                .filter(distance__lt=_SEMANTIC_MAX_DISTANCE)
                .order_by("distance")
                .values_list("id", flat=True)[:limit]
            )
            return list(rows)
        except Exception as exc:
            # Embedder model not loaded (e.g. web dyno without torch) — non-fatal.
            logger.debug("semantic_rank unavailable: %s", exc)
            return []

    # ------------------------------------------------------------------
    # Fusion
    # ------------------------------------------------------------------

    @staticmethod
    def _reciprocal_rank_fusion(ranked_lists: list, limit: int) -> list:
        """Combine several ranked id lists into one. Order-only, scale-free."""
        scores: dict = {}
        for ranked in ranked_lists:
            for position, item_id in enumerate(ranked):
                scores[item_id] = scores.get(item_id, 0.0) + 1.0 / (_RRF_K + position)
        ordered = sorted(scores.items(), key=lambda kv: kv[1], reverse=True)
        return [item_id for item_id, _ in ordered[:limit]]
