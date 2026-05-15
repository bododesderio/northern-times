import logging

from celery import shared_task

logger = logging.getLogger(__name__)


@shared_task(time_limit=3600)
def check_image_health():
    """Check health of images in recent articles."""
    from apps.media.services.health_checker import check_all_articles

    summary = check_all_articles(days=7)
    logger.info(f"Image health: {summary}")
    return str(summary)
