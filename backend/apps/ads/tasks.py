import logging

from celery import shared_task

logger = logging.getLogger(__name__)


@shared_task
def log_ad_stats():
    """Log daily ad/popup performance stats for monitoring."""
    from apps.ads.models import AdSlot, Popup

    active_ads = AdSlot.objects.filter(is_active=True)
    for ad in active_ads:
        if ad.impressions > 0:
            ctr = (ad.clicks / ad.impressions * 100) if ad.impressions else 0
            logger.info(
                f"Ad stats: slot={ad.slot_name} "
                f"impressions={ad.impressions} clicks={ad.clicks} ctr={ctr:.1f}%"
            )

    active_popups = Popup.objects.filter(is_active=True)
    for popup in active_popups:
        if popup.impressions > 0:
            ctr = (popup.clicks / popup.impressions * 100) if popup.impressions else 0
            logger.info(
                f"Popup stats: name={popup.name} "
                f"impressions={popup.impressions} clicks={popup.clicks} ctr={ctr:.1f}%"
            )
