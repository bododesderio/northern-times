"""Celery configuration for Northern Times."""
import os

from celery import Celery
from celery.schedules import crontab

os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'config.settings.production')

app = Celery('northern_times')
app.config_from_object('django.conf:settings', namespace='CELERY')
app.autodiscover_tasks()

# Periodic task schedule (replaces PHP cron jobs)
app.conf.beat_schedule = {
    # News crawler — every 5 minutes
    'crawl-news-sources': {
        'task': 'apps.crawler.tasks.run_crawler',
        'schedule': 300.0,
    },
    # Article rewriter — every 10 minutes
    'process-rewrite-queue': {
        'task': 'apps.rewriter.tasks.process_queue',
        'schedule': 600.0,
    },
    # Publish scheduled articles — every minute
    'publish-scheduled-articles': {
        'task': 'apps.articles.tasks.publish_scheduled',
        'schedule': 60.0,
    },
    # Send due newsletters — every minute
    'send-due-newsletters': {
        'task': 'apps.newsletter.tasks.send_due',
        'schedule': 60.0,
    },
    # Email queue processor — every 2 minutes
    'process-email-queue': {
        'task': 'apps.newsletter.tasks.process_email_queue',
        'schedule': 120.0,
    },
    # Daily maintenance — 2am
    'daily-maintenance': {
        'task': 'apps.system.tasks.daily_maintenance',
        'schedule': crontab(hour=2, minute=0),
    },
    # Weekly digest — Sunday 8am
    'weekly-digest': {
        'task': 'apps.newsletter.tasks.weekly_digest',
        'schedule': crontab(hour=8, minute=0, day_of_week=0),
    },
    # Image health check — daily 4am
    'image-health-check': {
        'task': 'apps.media.tasks.check_image_health',
        'schedule': crontab(hour=4, minute=0),
    },
    # Stats aggregation — every hour
    'aggregate-daily-stats': {
        'task': 'apps.analytics.tasks.aggregate_stats',
        'schedule': crontab(minute=0),
    },
    # SEO audit — Sunday 6am
    'run-seo-audit': {
        'task': 'apps.seo.tasks.run_scheduled_audit',
        'schedule': crontab(day_of_week='sunday', hour=6, minute=0),
    },
    # Social media monitoring — every 6 hours
    'scan-social-mentions': {
        'task': 'apps.social.tasks.scan_social_mentions',
        'schedule': crontab(hour='*/6'),
    },
    # Database backup — daily 3am
    'daily-db-backup': {
        'task': 'apps.system.tasks.run_db_backup',
        'schedule': crontab(hour=3, minute=0),
    },
    # AI reclassification — daily 5am
    'ai-reclassify-articles': {
        'task': 'apps.enrichment.tasks.ai_reclassify_articles',
        'schedule': crontab(hour=5, minute=0),
    },
    # Duplicate cleanup — every 12 hours
    'cleanup-duplicates': {
        'task': 'apps.enrichment.tasks.cleanup_duplicates',
        'schedule': crontab(hour='*/12', minute=30),
    },
    # Geocode any articles still missing coordinates — hourly
    'geocode-pending-articles': {
        'task': 'apps.articles.tasks.geocode_pending_articles',
        'schedule': crontab(minute=15),
    },
}
