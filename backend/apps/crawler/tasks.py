import logging

from celery import shared_task

logger = logging.getLogger(__name__)


@shared_task(bind=True, max_retries=0, time_limit=300)
def run_crawler(self, source_id=None):
    """Main crawler task -- runs every 5 minutes via Celery Beat.

    Args:
        source_id: Optional UUID string. If provided, crawls only that source.
    """
    from apps.system.models import CronRun
    from django.utils import timezone

    started = timezone.now()
    label = 'crawler' if not source_id else f'crawler:{source_id}'
    cron = CronRun.objects.create(name=label, started_at=started)

    try:
        from apps.crawler.engine import CrawlerEngine

        engine = CrawlerEngine()
        try:
            if source_id:
                from apps.crawler.models import CrawlSource
                source = CrawlSource.objects.get(pk=source_id)
                result = engine.crawl_source(source)
                total_new = result.get('new_articles', 0)
                errors = 1 if result.get('error') else 0
                sources_crawled = 1
            else:
                summary = engine.crawl_all()
                total_new = summary['new_articles']
                errors = summary['errors']
                sources_crawled = summary['sources']
        finally:
            engine.close()

        cron.status = 'ok' if errors == 0 else 'error'
        cron.items_processed = total_new
        cron.notes = (
            f"{sources_crawled} sources crawled, "
            f"{total_new} new, {errors} errors"
        )
        cron.finished_at = timezone.now()
        cron.save()

        return cron.notes
    except Exception as e:
        cron.status = 'error'
        cron.notes = str(e)[:2000]
        cron.finished_at = timezone.now()
        cron.save()
        raise
