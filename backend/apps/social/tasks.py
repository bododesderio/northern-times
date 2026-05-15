from celery import shared_task


@shared_task
def scan_social_mentions():
    """Scan social media for tracked keywords."""
    try:
        from apps.social.services.monitor import SocialMonitor
        monitor = SocialMonitor()
        results = monitor.monitor_all_keywords()
        return f'Social scan complete: {results}'
    except Exception as e:
        return f'Social scan failed: {e}'
