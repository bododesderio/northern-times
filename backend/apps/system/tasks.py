import logging
import os

from celery import shared_task

logger = logging.getLogger(__name__)


@shared_task
def daily_maintenance():
    """Daily maintenance tasks — runs at 2am via Celery Beat."""
    from apps.system.models import CronRun, SystemLog
    from apps.newsletter.models import EmailQueue
    from apps.webhooks.models import WebhookLog
    from apps.analytics.services.aggregator import aggregate_date
    from django.utils import timezone
    from datetime import timedelta

    now = timezone.now()
    results = []

    # 1. Aggregate yesterday's stats
    yesterday = (now - timedelta(days=1)).date()
    aggregate_date(yesterday)
    results.append("Stats aggregated")

    # 2. Clean old cron runs (>30 days)
    old = now - timedelta(days=30)
    deleted = CronRun.objects.filter(started_at__lt=old).delete()[0]
    results.append(f"Cleaned {deleted} old cron runs")

    # 3. Clean old email queue (sent >7 days ago)
    old_emails = now - timedelta(days=7)
    deleted = EmailQueue.objects.filter(
        status='sent', sent_at__lt=old_emails,
    ).delete()[0]
    results.append(f"Cleaned {deleted} old emails")

    # 4. Clean old system logs (>90 days)
    old_logs = now - timedelta(days=90)
    deleted = SystemLog.objects.filter(created_at__lt=old_logs).delete()[0]
    results.append(f"Cleaned {deleted} old system logs")

    # 5. Clean old webhook logs (>30 days)
    deleted = WebhookLog.objects.filter(created_at__lt=old).delete()[0]
    results.append(f"Cleaned {deleted} old webhook logs")

    summary = "; ".join(results)
    logger.info(f"Daily maintenance: {summary}")

    # Log this run
    CronRun.objects.create(
        name='daily_maintenance',
        status='ok',
        started_at=now,
        finished_at=timezone.now(),
        notes=summary,
    )

    return summary


@shared_task(time_limit=600, soft_time_limit=540)
def run_db_backup():
    """Create a database backup with optional S3 upload — runs daily at 3am."""
    from django.core.management import call_command
    import io

    out = io.StringIO()
    upload = bool(os.environ.get('S3_KEY'))
    args = ['--upload-s3'] if upload else []

    try:
        call_command('db_backup', *args, stdout=out, stderr=out)
        result = out.getvalue()
        logger.info(f"DB backup completed: {result}")
        return result
    except Exception as e:
        logger.error(f"DB backup failed: {e}")
        raise
