"""
Django management command: python manage.py crawl

Runs the news crawler for all due sources or a specific source by name/ID.
"""
import logging

from django.core.management.base import BaseCommand, CommandError

from apps.crawler.engine import CrawlerEngine

logger = logging.getLogger(__name__)


class Command(BaseCommand):
    help = 'Run the news crawler for all due sources'

    def add_arguments(self, parser):
        parser.add_argument(
            '--source',
            type=str,
            help='Crawl a specific source by name',
        )
        parser.add_argument(
            '--source-id',
            type=str,
            help='Crawl a specific source by UUID',
        )
        parser.add_argument(
            '--force',
            action='store_true',
            help='Crawl even if source is not due yet',
        )
        parser.add_argument(
            '--dry-run',
            action='store_true',
            help='List sources that would be crawled without actually crawling',
        )

    def handle(self, *args, **options):
        from apps.crawler.models import CrawlSource

        # Dry-run mode: just list due sources
        if options['dry_run']:
            sources = CrawlSource.objects.filter(is_active=True)
            due = [s for s in sources if s.due_for_crawl()]
            self.stdout.write(f"Active sources: {sources.count()}")
            self.stdout.write(f"Due for crawl: {len(due)}")
            for s in due:
                self.stdout.write(f"  - {s.name} (interval={s.crawl_interval}m)")
            return

        engine = CrawlerEngine()
        try:
            if options['source'] or options['source_id']:
                # Single source mode
                source = self._get_source(options)
                if not options['force'] and not source.due_for_crawl():
                    self.stdout.write(self.style.WARNING(
                        f"Source '{source.name}' is not due yet. "
                        f"Use --force to crawl anyway."
                    ))
                    return

                result = engine.crawl_source(source)
                self.stdout.write(self.style.SUCCESS(
                    f"Source '{source.name}': "
                    f"{result['new_count']} new articles, "
                    f"{result.get('dupe_count', 0)} duplicates, "
                    f"{result.get('error_count', 0)} errors"
                ))
            else:
                # Crawl all sources (force=True skips schedule check)
                summary = engine.crawl_all(force=options['force'])
                self.stdout.write(self.style.SUCCESS(
                    f"Crawled {summary['sources']} sources: "
                    f"{summary['new_articles']} new, "
                    f"{summary['errors']} errors"
                ))
        except Exception as e:
            raise CommandError(f"Crawler failed: {e}") from e
        finally:
            engine.close()

    @staticmethod
    def _get_source(options):
        """Resolve source from --source or --source-id."""
        from apps.crawler.models import CrawlSource

        if options['source_id']:
            try:
                return CrawlSource.objects.get(id=options['source_id'])
            except CrawlSource.DoesNotExist:
                raise CommandError(
                    f"Source with ID '{options['source_id']}' not found"
                )

        try:
            return CrawlSource.objects.get(name=options['source'])
        except CrawlSource.DoesNotExist:
            # Try case-insensitive match
            matches = CrawlSource.objects.filter(
                name__icontains=options['source']
            )
            if matches.count() == 1:
                return matches.first()
            elif matches.count() > 1:
                names = ', '.join(m.name for m in matches[:5])
                raise CommandError(
                    f"Multiple sources match '{options['source']}': {names}"
                )
            raise CommandError(
                f"Source '{options['source']}' not found"
            )
