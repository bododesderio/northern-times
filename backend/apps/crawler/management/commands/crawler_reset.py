"""Explicit crawler reset — deliberate clean slate for a controlled first deploy.

The engine auto-wipes once on its first crawl (the safety net). This command
lets ops trigger that wipe deliberately: hard-delete ALL articles + crawl history
and set the ``crawler_initialized`` flag so the auto-wipe never fires afterward.

Usage:
    python manage.py crawler_reset --first-run          # wipe + set flag
    python manage.py crawler_reset --first-run --yes     # skip confirmation
    python manage.py crawler_reset --clear-flag          # re-arm the auto-wipe
"""
from django.core.management.base import BaseCommand, CommandError
from django.db import transaction

from apps.articles.models import Article, StoryCluster
from apps.crawler.models import CrawlLog
from apps.core.models import Setting


class Command(BaseCommand):
    help = 'Wipe all articles + crawl history and mark the crawler initialized.'

    def add_arguments(self, parser):
        parser.add_argument('--first-run', action='store_true',
                            help='Hard-delete all articles + logs, set the initialized flag.')
        parser.add_argument('--clear-flag', action='store_true',
                            help='Clear crawler_initialized so the auto-wipe re-arms.')
        parser.add_argument('--yes', action='store_true',
                            help='Skip the interactive confirmation.')

    def handle(self, *args, **opts):
        if opts['clear_flag']:
            Setting.set('crawler_initialized', '', type='bool')
            self.stdout.write(self.style.WARNING(
                'crawler_initialized cleared — the next crawl will wipe once.'))
            return

        if not opts['first_run']:
            raise CommandError('Pass --first-run (to wipe) or --clear-flag (to re-arm).')

        art_count = Article.objects.with_deleted().count()
        log_count = CrawlLog.objects.count()

        if not opts['yes']:
            confirm = input(
                f'This HARD-deletes {art_count} articles + {log_count} crawl logs. '
                f'Type "wipe" to proceed: '
            )
            if confirm.strip().lower() != 'wipe':
                self.stdout.write('Aborted.')
                return

        with transaction.atomic():
            Article.objects.with_deleted().all().delete()
            CrawlLog.objects.all().delete()
            StoryCluster.objects.all().delete()
            Setting.set('crawler_initialized', '1', type='bool')

        self.stdout.write(self.style.SUCCESS(
            f'Wiped {art_count} articles + {log_count} logs. Crawler initialized; '
            f'freshness gate now governs new content.'
        ))
