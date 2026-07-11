"""
Adaptive per-source crawl scheduling.

Extracted verbatim from ``CrawlerEngine`` so the back-off / next-crawl policy is
a pure, unit-testable unit independent of the pipeline. Behaviour is identical
to the former ``_update_source_schedule`` / ``_increment_failures`` methods.
"""
from datetime import timedelta

from django.utils import timezone


def update_after_crawl(source, new_articles: int) -> None:
    """Update source scheduling fields after a crawl."""
    source.last_crawled_at = timezone.now()

    if new_articles > 0:
        source.last_new_content_at = timezone.now()
        source.consecutive_empty = 0
        source.consecutive_failures = 0
    else:
        source.consecutive_empty += 1

    # Adaptive interval: back off if source keeps returning empty
    interval = source.crawl_interval
    if source.consecutive_empty >= 5:
        # Double the interval, but cap at 2 hours
        interval = min(interval * 2, 120)

    source.next_crawl_at = timezone.now() + timedelta(minutes=interval)
    source.save(update_fields=[
        'last_crawled_at',
        'last_new_content_at',
        'consecutive_empty',
        'consecutive_failures',
        'next_crawl_at',
    ])


def increment_failures(source) -> None:
    """Increment failure counter with exponential backoff."""
    source.consecutive_failures += 1
    source.last_crawled_at = timezone.now()

    # Exponential backoff: 2^(failures-2) minutes, max 480 min (8 hours)
    backoff = min(2 ** max(0, source.consecutive_failures - 2), 480)
    source.next_crawl_at = timezone.now() + timedelta(
        minutes=max(source.crawl_interval, backoff),
    )
    source.save(update_fields=[
        'consecutive_failures',
        'last_crawled_at',
        'next_crawl_at',
    ])
