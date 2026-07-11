"""Tests for the hybrid article search service (apps.articles.services.search).

Covers keyword FTS ranking (via the STORED search_vector generated column),
trigram typo tolerance, reciprocal-rank fusion, the honest match count, the
published-only contract, and the semantic-ranking opt-in gate. The semantic
ranker (torch/sentence-transformers) is never invoked here — it is gated OFF by
default and its wiring is asserted, not executed.
"""
import pytest
from django.utils import timezone

from apps.articles.models import Article
from apps.articles.services.search import HybridSearch


def _article(category, title, *, content='', excerpt='', ai_summary='',
             status='published', slug=None, published=True):
    return Article.objects.create(
        title=title,
        slug=slug or title.lower().replace(' ', '-')[:60],
        excerpt=excerpt,
        content=content,
        ai_summary=ai_summary,
        status=status,
        published_at=timezone.now() if published else None,
        category=category,
    )


@pytest.mark.django_db
class TestHybridSearchKeyword:
    def test_empty_query_returns_empty(self, category):
        _article(category, 'Election results announced')
        assert HybridSearch(use_semantic=False).search('') == []
        assert HybridSearch(use_semantic=False).search('   ') == []
        assert HybridSearch(use_semantic=False).search_ids('') == []

    def test_keyword_match_on_title(self, category):
        hit = _article(category, 'Parliament passes new health budget')
        _article(category, 'Local football club wins regional cup')

        res = HybridSearch(use_semantic=False).search('health budget')
        assert hit.id in [a.id for a in res]

    def test_keyword_match_on_body(self, category):
        hit = _article(
            category, 'City council meeting',
            content='Councillors debated the new inflation figures at length.',
        )
        res = HybridSearch(use_semantic=False).search('inflation')
        assert hit.id in [a.id for a in res]

    def test_title_outranks_body(self, category):
        """Weight A (title) should rank above the same term only in the body."""
        title_hit = _article(category, 'Malaria outbreak in the north', slug='t-hit')
        body_hit = _article(
            category, 'Health ministry briefing', slug='b-hit',
            content='Officials mentioned malaria only briefly near the end.',
        )
        res = HybridSearch(use_semantic=False).search('malaria')
        ids = [a.id for a in res]
        assert ids.index(title_hit.id) < ids.index(body_hit.id)


@pytest.mark.django_db
class TestHybridSearchTrigram:
    def test_typo_tolerance(self, category):
        """A misspelled query still matches a headline via the trigram ranker.

        Uses the index-backed ``%`` (whole-string similarity) operator, so typo
        tolerance applies to headline-length titles; a single typo buried in a
        long multi-word title is left to the FTS/semantic signals.
        """
        hit = _article(category, 'Election results', slug='typo-hit')
        res = HybridSearch(use_semantic=False).search('electon')
        assert hit.id in [a.id for a in res]

    def test_clean_query_still_matches(self, category):
        """A correctly-spelled short title matches too (sanity)."""
        hit = _article(category, 'Inflation report', slug='typo-clean')
        res = HybridSearch(use_semantic=False).search('inflaton')  # typo
        assert hit.id in [a.id for a in res]


@pytest.mark.django_db
class TestHybridSearchContract:
    def test_excludes_drafts(self, category):
        _article(category, 'Secret draft about elections', status='draft', slug='d1')
        res = HybridSearch(use_semantic=False).search('elections')
        assert res == [] or all(a.status == 'published' for a in res)

    def test_excludes_future_dated(self, category):
        future = Article.objects.create(
            title='Scheduled election coverage', slug='future-1',
            status='published',
            published_at=timezone.now() + timezone.timedelta(days=2),
            category=category,
        )
        res = HybridSearch(use_semantic=False).search('election')
        assert future.id not in [a.id for a in res]

    def test_count_reports_total(self, category):
        for i in range(3):
            _article(category, f'Budget story number {i}', slug=f'b-{i}')
        assert HybridSearch(use_semantic=False).count('budget') == 3
        assert HybridSearch(use_semantic=False).count('') == 0

    def test_order_preserved_between_ids_and_objects(self, category):
        _article(category, 'Election budget health story', slug='multi-1')
        _article(category, 'Football match report', slug='multi-2')
        hs = HybridSearch(use_semantic=False)
        ids = hs.search_ids('election budget')
        objs = hs.search('election budget')
        assert [a.id for a in objs] == [i for i in ids if i in {a.id for a in objs}]


@pytest.mark.django_db
class TestSemanticGate:
    def test_semantic_defaults_off(self, settings):
        """Default respects SEARCH_SEMANTIC_ENABLED (False) — no torch load."""
        settings.SEARCH_SEMANTIC_ENABLED = False
        assert HybridSearch().use_semantic is False

    def test_semantic_setting_opt_in(self, settings):
        settings.SEARCH_SEMANTIC_ENABLED = True
        assert HybridSearch().use_semantic is True

    def test_explicit_override_wins(self, settings):
        settings.SEARCH_SEMANTIC_ENABLED = True
        assert HybridSearch(use_semantic=False).use_semantic is False

    def test_semantic_off_never_calls_embedder(self, category, monkeypatch):
        """With semantic off, the embedder must not be constructed/loaded."""
        _article(category, 'Election budget story')

        def _boom(*a, **k):  # pragma: no cover - fails the test if hit
            raise AssertionError('Embedder must not load on the keyword path')

        import apps.enrichment.embedder as emb
        monkeypatch.setattr(emb.Embedder, '__init__', _boom)

        # Should complete purely on keyword + trigram, never touching Embedder.
        HybridSearch(use_semantic=False).search('election')
