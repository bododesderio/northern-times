"""Integration tests for the CrawlerEngine."""
import hashlib
from datetime import timedelta
from unittest.mock import patch, MagicMock

import pytest
from django.utils import timezone


@pytest.mark.integration
class TestCrawlerEngineUrlHash:
    """Test URL hashing (no DB needed)."""

    def test_url_hash_deterministic(self):
        from apps.crawler.engine import CrawlerEngine
        engine = CrawlerEngine()
        h1 = engine._url_hash('https://example.com/article/1')
        h2 = engine._url_hash('https://example.com/article/1')
        assert h1 == h2
        assert len(h1) == 64  # SHA256 hex

    def test_url_hash_different_for_different_urls(self):
        from apps.crawler.engine import CrawlerEngine
        engine = CrawlerEngine()
        h1 = engine._url_hash('https://example.com/1')
        h2 = engine._url_hash('https://example.com/2')
        assert h1 != h2

    def test_url_hash_matches_sha256(self):
        from apps.crawler.engine import CrawlerEngine
        engine = CrawlerEngine()
        url = 'https://example.com/test'
        expected = hashlib.sha256(url.encode()).hexdigest()
        assert engine._url_hash(url) == expected


@pytest.mark.django_db
@pytest.mark.integration
class TestCrawlerEngineScheduling:
    def test_crawl_all_no_due_sources(self, crawl_source):
        """Sources not due should not be crawled."""
        from apps.crawler.engine import CrawlerEngine

        crawl_source.next_crawl_at = timezone.now() + timedelta(hours=1)
        crawl_source.save()

        engine = CrawlerEngine()
        result = engine.crawl_all()
        assert result['sources'] == 0
        assert result['new_articles'] == 0

    def test_update_source_schedule_with_new_articles(self, crawl_source):
        """Schedule should reset empty counter when new articles found."""
        from apps.crawler import scheduling

        crawl_source.consecutive_empty = 3
        crawl_source.save()

        scheduling.update_after_crawl(crawl_source, new_articles=5)
        crawl_source.refresh_from_db()

        assert crawl_source.consecutive_empty == 0
        assert crawl_source.consecutive_failures == 0
        assert crawl_source.last_crawled_at is not None
        assert crawl_source.next_crawl_at is not None

    def test_update_source_schedule_no_articles(self, crawl_source):
        """Empty counter should increment when no articles found."""
        from apps.crawler import scheduling

        crawl_source.consecutive_empty = 2
        crawl_source.save()

        scheduling.update_after_crawl(crawl_source, new_articles=0)
        crawl_source.refresh_from_db()

        assert crawl_source.consecutive_empty == 3

    def test_update_source_schedule_adaptive_backoff(self, crawl_source):
        """Interval should increase after 5+ consecutive empty crawls."""
        from apps.crawler import scheduling

        crawl_source.consecutive_empty = 5
        crawl_source.crawl_interval = 30
        crawl_source.save()

        scheduling.update_after_crawl(crawl_source, new_articles=0)
        crawl_source.refresh_from_db()

        # After 6 consecutive empty, interval should be doubled (60 min)
        assert crawl_source.consecutive_empty == 6
        # next_crawl_at should be at least 60 min from now (doubled interval)
        expected_min = timezone.now() + timedelta(minutes=59)
        assert crawl_source.next_crawl_at >= expected_min

    def test_increment_failures_backoff(self, crawl_source):
        """Failure counter should increment with backoff."""
        from apps.crawler import scheduling

        scheduling.increment_failures(crawl_source)
        crawl_source.refresh_from_db()

        assert crawl_source.consecutive_failures == 1
        assert crawl_source.last_crawled_at is not None
        assert crawl_source.next_crawl_at is not None

    def test_increment_failures_exponential_backoff(self, crawl_source):
        """Multiple failures should produce exponential backoff."""
        from apps.crawler import scheduling

        crawl_source.consecutive_failures = 4
        crawl_source.crawl_interval = 10
        crawl_source.save()

        scheduling.increment_failures(crawl_source)
        crawl_source.refresh_from_db()

        assert crawl_source.consecutive_failures == 5
        # backoff = 2^(5-2) = 8 min, but crawl_interval is 10, so uses 10
        # Actually backoff = min(2^3, 480) = 8, max(10, 8) = 10
        expected_min = timezone.now() + timedelta(minutes=9)
        assert crawl_source.next_crawl_at >= expected_min

    def test_increment_failures_caps_at_8_hours(self, crawl_source):
        """Backoff should be capped at 480 minutes (8 hours)."""
        from apps.crawler import scheduling

        crawl_source.consecutive_failures = 20
        crawl_source.crawl_interval = 10
        crawl_source.save()

        scheduling.increment_failures(crawl_source)
        crawl_source.refresh_from_db()

        assert crawl_source.consecutive_failures == 21
        # backoff capped at 480
        expected_max = timezone.now() + timedelta(minutes=481)
        assert crawl_source.next_crawl_at <= expected_max


@pytest.mark.django_db
@pytest.mark.integration
class TestCrawlerEngineLogging:
    def test_log_crawl_creates_entry(self, crawl_source):
        """_log_crawl should create a CrawlLog entry."""
        from apps.crawler.engine import CrawlerEngine
        from apps.crawler.models import CrawlLog

        log = CrawlerEngine._log_crawl(
            crawl_source,
            'success',
            found_count=10,
            new_count=5,
            duplicate_count=5,
        )

        assert log.pk is not None
        assert log.source == crawl_source
        assert log.status == 'success'
        assert log.found_count == 10
        assert log.new_count == 5
        assert log.duplicate_count == 5

    def test_log_crawl_error(self, crawl_source):
        """_log_crawl should store error messages."""
        from apps.crawler.engine import CrawlerEngine

        log = CrawlerEngine._log_crawl(
            crawl_source,
            'error',
            error_message='Connection timeout',
        )

        assert log.status == 'error'
        assert log.error_message == 'Connection timeout'


@pytest.mark.django_db
@pytest.mark.integration
class TestCrawlerSourceDueLogic:
    def test_source_due_when_never_crawled(self, crawl_source):
        """A source that has never been crawled should be due."""
        crawl_source.next_crawl_at = None
        crawl_source.save()
        assert crawl_source.due_for_crawl() is True

    def test_source_due_when_past_next_crawl(self, crawl_source):
        """A source past its next_crawl_at should be due."""
        crawl_source.next_crawl_at = timezone.now() - timedelta(minutes=5)
        crawl_source.save()
        assert crawl_source.due_for_crawl() is True

    def test_source_not_due_when_future(self, crawl_source):
        """A source with future next_crawl_at should not be due."""
        crawl_source.next_crawl_at = timezone.now() + timedelta(hours=1)
        crawl_source.save()
        assert crawl_source.due_for_crawl() is False
