"""Tests for keep-best cross-source dedup + anti-stale first-run wipe."""
from datetime import timedelta
from types import SimpleNamespace

import pytest
from django.utils import timezone

from apps.articles.models import Article, ArticleEntity, Category
from apps.crawler.models import CrawlSource, CrawlLog
from apps.crawler.services import resolve
from apps.enrichment.dedup import DuplicateChecker
from apps.core.models import Setting


def _emb(lead=1.0):
    """A 384-dim unit-ish embedding; identical `lead` → identical vector."""
    return [lead] + [0.0] * 383


def _orthogonal():
    return [0.0] * 383 + [1.0]


def _mk_article(**kw):
    defaults = dict(
        title='Uganda president signs new education bill',
        slug=None,
        status='published',
        published_at=timezone.now(),
        is_crawled=True,
        embedding=_emb(),
        word_count=500,
        quality_score=60,
    )
    defaults.update(kw)
    if defaults['slug'] is None:
        import uuid
        defaults['slug'] = f'a-{uuid.uuid4().hex[:10]}'
    return Article.objects.create(**defaults)


@pytest.mark.django_db
class TestFindDuplicate:
    def test_semantic_match_returned(self):
        existing = _mk_article(title='Uganda leader approves education reform')
        checker = DuplicateChecker()
        match = checker.find_duplicate(_emb(), title='Totally different headline')
        assert match is not None
        assert match.id == existing.id

    def test_no_match_when_orthogonal(self):
        _mk_article(embedding=_orthogonal())
        checker = DuplicateChecker()
        assert checker.find_duplicate(_emb(), title='Uganda education bill') is None

    def test_ignores_soft_deleted(self):
        art = _mk_article()
        art.soft_delete()
        checker = DuplicateChecker()
        assert checker.find_duplicate(_emb(), title=art.title) is None

    def test_none_embedding_safe(self):
        assert DuplicateChecker().find_duplicate(None, title='x') is None


@pytest.mark.django_db
class TestResolveKeepBest:
    def _ctx(self, **kw):
        src = SimpleNamespace(name='New Outlet', region=kw.pop('region', 'ugandan'))
        base = dict(
            word_count=1200, image_url='https://img/x.jpg', entities=['a', 'b', 'c'],
            pull_quotes=['q1', 'q2'], published_at=timezone.now(), plain_text='body',
            title='New version', metadata={}, source=src,
        )
        base.update(kw)
        return SimpleNamespace(**base)

    def test_new_wins_when_richer(self):
        existing = _mk_article(word_count=200, featured_image='', quality_score=30,
                               source_name='Weak Source')
        ctx = self._ctx(word_count=1500)
        assert resolve.resolve_duplicate(ctx, existing) == 'new'

    def test_existing_wins_when_richer(self):
        existing = _mk_article(word_count=1400, featured_image='https://img/y.jpg',
                               source_name='Test Source')
        ctx = self._ctx(word_count=120, image_url='', entities=[], pull_quotes=[],
                        region='international')
        assert resolve.resolve_duplicate(ctx, existing) == 'existing'

    def test_purge_hard_deletes_and_cascades(self):
        art = _mk_article()
        ArticleEntity.objects.create(article=art, entity_text='Museveni',
                                     entity_type='person', salience=0.9)
        aid = art.id
        resolve.purge_duplicate(art)
        # Row is GONE (not soft-deleted) and entities cascaded.
        assert not Article.objects.with_deleted().filter(pk=aid).exists()
        assert not ArticleEntity.objects.filter(article_id=aid).exists()


@pytest.mark.django_db
class TestFirstRunWipe:
    def test_wipe_happens_once_then_flag_set(self):
        _mk_article(title='Old stale one')
        _mk_article(title='Old stale two')
        CrawlLog.objects.create(
            source=CrawlSource.objects.create(name='S', url='http://x/f'),
            status='ok',
        )
        assert Setting.get('crawler_initialized', False) is False

        from apps.crawler.engine import CrawlerEngine
        eng = CrawlerEngine()
        eng._maybe_first_run_wipe()

        assert Article.objects.with_deleted().count() == 0
        assert CrawlLog.objects.count() == 0
        assert Setting.get('crawler_initialized', False) is True

    def test_no_wipe_when_flag_present(self):
        Setting.set('crawler_initialized', '1', type='bool')
        _mk_article(title='Should survive')
        from apps.crawler.engine import CrawlerEngine
        CrawlerEngine()._maybe_first_run_wipe()
        assert Article.objects.with_deleted().count() == 1


@pytest.mark.django_db
class TestDedupeArticlesCommand:
    def test_collapses_seeded_duplicate_pair(self):
        Setting.set('crawler_initialized', '1', type='bool')  # avoid wipe side-effects
        keep = _mk_article(title='Flood hits Lira district hard',
                           word_count=1500, featured_image='https://img/a.jpg',
                           source_name='Daily Monitor')
        drop = _mk_article(title='Flood hits Lira district',
                           word_count=150, featured_image='',
                           source_name='Random Blog')
        from django.core.management import call_command
        from io import StringIO
        out = StringIO()
        call_command('dedupe_articles', '--apply', stdout=out)

        remaining = set(Article.objects.with_deleted().values_list('id', flat=True))
        assert keep.id in remaining
        assert drop.id not in remaining
