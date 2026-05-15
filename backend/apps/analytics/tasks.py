import logging

from celery import shared_task

logger = logging.getLogger(__name__)


@shared_task
def aggregate_stats():
    """Aggregate daily statistics."""
    from apps.analytics.services.aggregator import aggregate_today

    stats = aggregate_today()
    logger.info(
        f"Stats aggregated: {stats.total_views} views, "
        f"{stats.unique_visitors} unique visitors"
    )
    return (
        f"Stats: {stats.total_views} views, "
        f"{stats.unique_visitors} visitors"
    )
