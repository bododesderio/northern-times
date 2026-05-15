from datetime import timedelta

import pytest
from django.utils import timezone

from apps.crawler.models import CrawlLog, CrawlSource


class TestCrawlSource:
    def test_defaults(self, crawl_source):
        assert crawl_source.is_active is True
        assert crawl_source.crawl_interval == 60
        assert crawl_source.consecutive_empty == 0
        assert crawl_source.consecutive_failures == 0
        assert crawl_source.avg_articles_per_day == 0.0
        assert crawl_source.feed_type == 'rss'
        assert crawl_source.region == 'international'
        assert crawl_source.download_images is True
        assert crawl_source.nofollow is True
        assert crawl_source.max_articles == 50

    def test_str(self, crawl_source):
        assert str(crawl_source) == 'Test Source'

    def test_due_for_crawl_inactive(self, crawl_source):
        crawl_source.is_active = False
        crawl_source.save(update_fields=['is_active'])
        assert crawl_source.due_for_crawl() is False

    def test_due_for_crawl_no_next_crawl(self, crawl_source):
        assert crawl_source.next_crawl_at is None
        assert crawl_source.due_for_crawl() is True

    def test_due_for_crawl_past_next_crawl(self, crawl_source):
        crawl_source.next_crawl_at = timezone.now() - timedelta(minutes=5)
        crawl_source.save(update_fields=['next_crawl_at'])
        assert crawl_source.due_for_crawl() is True

    def test_due_for_crawl_future_next_crawl(self, crawl_source):
        crawl_source.next_crawl_at = timezone.now() + timedelta(hours=1)
        crawl_source.save(update_fields=['next_crawl_at'])
        assert crawl_source.due_for_crawl() is False

    def test_category_fk(self, crawl_source, category):
        assert crawl_source.category == category

    def test_region_choices(self, db, category):
        for region in ('ugandan', 'east_african', 'international'):
            src = CrawlSource.objects.create(
                name=f'{region} Source',
                url=f'https://{region}.example.com/feed',
                category=category,
                region=region,
            )
            assert src.region == region


class TestCrawlLog:
    def test_creation(self, crawl_source, db):
        log = CrawlLog.objects.create(
            source=crawl_source,
            status='ok',
            found_count=10,
            new_count=5,
            duplicate_count=3,
            error_count=2,
            duration_ms=1500,
        )
        assert log.source == crawl_source
        assert log.found_count == 10
        assert log.new_count == 5
        assert log.duplicate_count == 3
        assert log.error_count == 2
        assert log.duration_ms == 1500
        assert str(log).startswith('Test Source')

    def test_defaults(self, crawl_source, db):
        log = CrawlLog.objects.create(source=crawl_source)
        assert log.status == 'ok'
        assert log.found_count == 0
        assert log.new_count == 0
        assert log.error_message == ''
        assert log.details == {}

    def test_error_log(self, crawl_source, db):
        log = CrawlLog.objects.create(
            source=crawl_source,
            status='error',
            error_message='Connection timeout',
        )
        assert log.status == 'error'
        assert log.error_message == 'Connection timeout'

    def test_cascade_delete(self, crawl_source, db):
        CrawlLog.objects.create(source=crawl_source)
        CrawlLog.objects.create(source=crawl_source)
        assert CrawlLog.objects.filter(source=crawl_source).count() == 2
        crawl_source.delete()
        assert CrawlLog.objects.count() == 0

    def test_ordering(self, crawl_source, db):
        log1 = CrawlLog.objects.create(source=crawl_source)
        log2 = CrawlLog.objects.create(source=crawl_source)
        logs = list(CrawlLog.objects.all())
        # Ordering is -created_at, so newest first
        assert logs[0].pk == log2.pk
