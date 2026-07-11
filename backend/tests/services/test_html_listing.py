"""Tests for HTMLListingFetcher article-link filtering.

The Selenium browser is stubbed via monkeypatch, so these validate the pure
link-selection heuristic: keep same-host article slugs, drop nav/social/short
links, require a headline-length anchor text, de-duplicate, honour
required_fragments and max_items.
"""
import pytest

from apps.crawler.fetchers.html_listing import HTMLListingFetcher


class _StubBrowser:
    def __init__(self, pairs):
        self._pairs = pairs

    def fetch_links(self, url, wait_seconds=8):
        return self._pairs

    def close(self):
        pass


@pytest.fixture
def patched(monkeypatch):
    def _install(pairs):
        import apps.crawler.fetchers.html_listing as mod
        monkeypatch.setattr(
            'apps.crawler.fetchers.browser.BrowserFetcher',
            lambda: _StubBrowser(pairs),
        )
        return HTMLListingFetcher()
    return _install


def test_keeps_article_links_drops_nav(patched):
    pairs = [
        ('https://nilepost.co.ug/news/355485/wangadya-resigns-as-uhrc-chair', 'Wangadya resigns as UHRC chairperson today'),
        ('https://nilepost.co.ug/login/google', 'Login'),
        ('https://nilepost.co.ug/tags/nrm', 'NRM'),
        ('https://twitter.com/nilepostnews', 'Follow us'),
        ('https://nilepost.co.ug/news/355383/museveni-pushes-integration', 'Museveni pushes East African integration deal'),
    ]
    fetcher = patched(pairs)
    items = fetcher.fetch('https://nilepost.co.ug/', max_items=10)
    urls = [i.url for i in items]
    assert any('wangadya' in u for u in urls)
    assert any('museveni' in u for u in urls)
    assert not any('/login' in u or '/tags/' in u or 'twitter' in u for u in urls)


def test_requires_headline_length_anchor(patched):
    pairs = [
        ('https://x.co/news/1/some-real-headline-slug', 'news'),          # anchor too short
        ('https://x.co/news/2/another-real-headline-here', 'A proper four word headline'),
    ]
    fetcher = patched(pairs)
    items = fetcher.fetch('https://x.co/', max_items=10)
    assert len(items) == 1
    assert items[0].title == 'A proper four word headline'


def test_required_fragments_filter(patched):
    pairs = [
        ('https://monitor.co.ug/uganda/news/national/govt-signs-road-deal', 'Govt signs shs481b road deal'),
        ('https://monitor.co.ug/uganda/lifestyle/heart-to-heart-column-piece', 'Heart to heart column advice piece'),
    ]
    fetcher = patched(pairs)
    items = fetcher.fetch('https://monitor.co.ug/uganda/news', max_items=10,
                          required_fragments=['/uganda/news/'])
    assert len(items) == 1
    assert '/uganda/news/' in items[0].url


def test_dedup_and_limit(patched):
    pairs = [
        ('https://x.co/news/1/headline-one-two-three', 'Headline one two three four'),
        ('https://x.co/news/1/headline-one-two-three#comments', 'Headline one two three four'),  # dup
        ('https://x.co/news/2/headline-two-two-three', 'Second headline two three four'),
        ('https://x.co/news/3/headline-three-two-three', 'Third headline two three four'),
    ]
    fetcher = patched(pairs)
    items = fetcher.fetch('https://x.co/', max_items=2)
    assert len(items) == 2  # limit honoured
    assert len({i.url for i in items}) == 2  # deduped
