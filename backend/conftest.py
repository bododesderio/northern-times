"""Root conftest — shared fixtures for all tests."""
import uuid

import pytest
from django.utils import timezone


@pytest.fixture(autouse=True)
def _clear_default_cache():
    """Isolate tests from Redis cache state.

    Django rolls back the DB between tests but NOT the cache, so values cached
    by ``Setting.get`` (and similar) leak across tests and cause order-dependent
    flakiness. Clear the default cache before each test. The ``sessions`` cache
    is left alone so logged-in test clients keep their session.
    """
    from django.core.cache import cache
    cache.clear()
    yield


@pytest.fixture
def role_author(db):
    from apps.accounts.models import Role
    role, _ = Role.objects.get_or_create(
        name='author',
        defaults={'level': 1, 'permissions': ['articles.own', 'media.upload']},
    )
    return role


@pytest.fixture
def role_editor(db):
    from apps.accounts.models import Role
    role, _ = Role.objects.get_or_create(
        name='editor',
        defaults={'level': 2, 'permissions': ['articles.*', 'media.*', 'comments.moderate']},
    )
    return role


@pytest.fixture
def role_super_admin(db):
    from apps.accounts.models import Role
    role, _ = Role.objects.get_or_create(
        name='super_admin',
        defaults={'level': 3, 'permissions': ['*']},
    )
    return role


@pytest.fixture
def user_author(db, role_author):
    from apps.accounts.models import User
    return User.objects.create_user(
        username='author1',
        email='author@example.com',
        password='testpass123',
        display_name='Test Author',
        role=role_author,
    )


@pytest.fixture
def user_editor(db, role_editor):
    from apps.accounts.models import User
    return User.objects.create_user(
        username='editor1',
        email='editor@example.com',
        password='testpass123',
        display_name='Test Editor',
        role=role_editor,
    )


@pytest.fixture
def user_admin(db, role_super_admin):
    from apps.accounts.models import User
    return User.objects.create_user(
        username='admin1',
        email='admin@example.com',
        password='testpass123',
        display_name='Test Admin',
        role=role_super_admin,
    )


@pytest.fixture
def category(db):
    from apps.articles.models import Category
    return Category.objects.create(
        name='Politics',
        slug='politics',
        color='#cc0000',
        show_in_nav=True,
        show_in_sidebar=True,
    )


@pytest.fixture
def category_sports(db):
    from apps.articles.models import Category
    return Category.objects.create(
        name='Sports',
        slug='sports',
        color='#1a6bbf',
    )


@pytest.fixture
def published_article(db, category, user_author):
    from apps.articles.models import Article
    return Article.objects.create(
        title='Test Article Title',
        slug='test-article-title',
        excerpt='This is a test article excerpt.',
        content='<p>This is the full content of the test article. ' * 20 + '</p>',
        status='published',
        published_at=timezone.now(),
        category=category,
        author=user_author,
        display_author='Test Author',
        quality_score=75,
    )


@pytest.fixture
def draft_article(db, category, user_author):
    from apps.articles.models import Article
    return Article.objects.create(
        title='Draft Article',
        slug='draft-article',
        content='<p>Draft content</p>',
        status='draft',
        category=category,
        author=user_author,
    )


@pytest.fixture
def crawled_article(db, category):
    from apps.articles.models import Article
    return Article.objects.create(
        title='Crawled News Article',
        slug='crawled-news-article',
        excerpt='Crawled article excerpt.',
        content='<p>Crawled content. ' * 30 + '</p>',
        status='published',
        published_at=timezone.now(),
        is_crawled=True,
        source_url='https://example.com/article/123',
        source_name='Example News',
        source_hash='abc123def456',
        category=category,
        quality_score=60,
    )


@pytest.fixture
def tag(db):
    from apps.articles.models import Tag
    return Tag.objects.create(name='Uganda', slug='uganda', type='location')


@pytest.fixture
def crawl_source(db, category):
    from apps.crawler.models import CrawlSource
    return CrawlSource.objects.create(
        name='Test Source',
        url='https://example.com/feed.xml',
        category=category,
        feed_type='rss',
        crawl_interval=60,
        is_active=True,
    )


@pytest.fixture
def subscriber(db):
    from apps.newsletter.models import Subscriber
    return Subscriber.objects.create(
        email='subscriber@example.com',
        unsub_token='unsub-token-123',
        status='active',
    )


@pytest.fixture
def admin_client(client, user_admin):
    """Django test client logged in as super_admin."""
    client.login(email='admin@example.com', password='testpass123')
    return client


@pytest.fixture
def editor_client(client, user_editor):
    """Django test client logged in as editor."""
    client.login(email='editor@example.com', password='testpass123')
    return client
