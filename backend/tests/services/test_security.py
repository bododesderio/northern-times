"""Regression tests for the audit security fixes."""
import pytest


class TestSSRFGuard:
    """apps.crawler.fetchers.url_guard.is_safe_public_url"""

    @pytest.mark.parametrize('url', [
        'http://169.254.169.254/latest/meta-data/',  # cloud metadata
        'http://localhost:6379',                     # local Redis
        'http://127.0.0.1/',                         # loopback
        'http://10.0.0.5/x',                         # RFC1918
        'http://192.168.1.1/',                       # RFC1918
        'file:///etc/passwd',                        # non-http scheme
        'gopher://evil/',                            # non-http scheme
        'ftp://internal/',                           # non-http scheme
        'not-a-url',
        '',
    ])
    def test_blocks_unsafe(self, url):
        from apps.crawler.fetchers.url_guard import is_safe_public_url
        assert is_safe_public_url(url) is False

    @pytest.mark.parametrize('url', [
        'https://www.bbc.co.uk/',
        'https://nilepost.co.ug/',
    ])
    def test_allows_public(self, url):
        from apps.crawler.fetchers.url_guard import is_safe_public_url
        assert is_safe_public_url(url) is True


class TestLdjsonEscaping:
    """apps.articles.views._ldjson_safe — no <script> breakout from crawled data."""

    def test_escapes_script_breakout(self):
        from apps.articles.views import _ldjson_safe
        out = _ldjson_safe('{"headline": "</script><img src=x onerror=alert(1)>"}')
        assert '</script>' not in out
        assert '<' not in out and '>' not in out
        assert '\\u003c' in out and '\\u003e' in out

    def test_escapes_ampersand(self):
        from apps.articles.views import _ldjson_safe
        out = _ldjson_safe('Tom & Jerry')
        assert '&' not in out
        assert '\\u0026' in out

    def test_escapes_js_line_separators(self):
        from apps.articles.views import _ldjson_safe
        # U+2028 / U+2029 are valid in JSON strings but break JS parsing.
        out = _ldjson_safe('a' + chr(0x2028) + 'b' + chr(0x2029) + 'c')
        assert chr(0x2028) not in out and chr(0x2029) not in out
        assert '\\u2028' in out and '\\u2029' in out


@pytest.mark.django_db
class TestRateLimit:
    def test_comment_post_is_rate_limited(self, client, published_article):
        from django.core.cache import cache
        cache.clear()
        payload = {
            'article_id': str(published_article.id),
            'name': 'Bob', 'email': 'bob@e.com', 'content': 'nice article',
        }
        codes = [client.post('/api/comment/', payload).status_code for _ in range(7)]
        # limit=5/min → first 5 pass (200), the rest are throttled (429).
        assert codes[:5] == [200] * 5
        assert 429 in codes[5:]


@pytest.mark.django_db
class TestCommentParentValidation:
    def test_reply_to_foreign_comment_is_dropped(self, client, published_article, category, user_author):
        from apps.articles.models import Article, Comment
        other = Article.objects.create(
            title='Other', slug='other-x', status='published', category=category,
            author=user_author,
        )
        foreign = Comment.objects.create(
            article=other, name='X', email='x@e.com', content='hi', status='approved',
        )
        resp = client.post('/api/comment/', {
            'article_id': str(published_article.id),
            'name': 'Bob', 'email': 'bob@e.com', 'content': 'nice',
            'parent_id': str(foreign.id),
        })
        assert resp.status_code == 200
        created = Comment.objects.filter(article=published_article, name='Bob').first()
        assert created is not None
        # The cross-article parent must have been dropped, not threaded.
        assert created.parent_id is None
