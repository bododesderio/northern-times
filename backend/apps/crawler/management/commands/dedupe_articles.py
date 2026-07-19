"""One-time sweep that collapses EXISTING duplicate articles to one per story.

The live pipeline now keeps-best on ingest, but rows created before that landed
may still contain same-story-different-site duplicates. This command clusters
live articles by embedding cosine (+ headline Jaccard confirmation), keeps the
highest-quality article per cluster, and HARD-deletes the losers.

Dry-run by default — pass ``--apply`` to actually delete.

Usage:
    python manage.py dedupe_articles              # report only
    python manage.py dedupe_articles --apply      # collapse duplicates
    python manage.py dedupe_articles --threshold 0.85 --window-days 7
"""
from django.core.management.base import BaseCommand
from django.utils import timezone
from datetime import timedelta

from apps.articles.models import Article
from apps.enrichment.dedup import DuplicateChecker
from apps.crawler.services import resolve


class Command(BaseCommand):
    help = 'Collapse existing duplicate articles, keeping the best of each cluster.'

    def add_arguments(self, parser):
        parser.add_argument('--apply', action='store_true',
                            help='Actually hard-delete losers (default: dry-run).')
        parser.add_argument('--threshold', type=float, default=None,
                            help='Cosine similarity threshold (default: settings).')
        parser.add_argument('--window-days', type=int, default=30,
                            help='Only consider articles created within N days (default 30).')

    def handle(self, *args, **opts):
        apply = opts['apply']
        checker = DuplicateChecker()
        window_start = timezone.now() - timedelta(days=opts['window_days'])

        articles = list(
            Article.objects.with_deleted()
            .filter(deleted_at__isnull=True, embedding__isnull=False,
                    created_at__gte=window_start)
            .order_by('created_at')
        )
        self.stdout.write(f'Scanning {len(articles)} live articles for duplicates...')

        seen = set()           # ids already assigned to a kept cluster or purged
        purged = 0
        clusters = 0

        for art in articles:
            if art.id in seen:
                continue
            # Find the best current match for this article among the rest.
            match = checker.find_duplicate(
                art.embedding, title=art.title,
                window_days=opts['window_days'], threshold=opts['threshold'],
            )
            if match is None or match.id == art.id or match.id in seen:
                continue

            clusters += 1
            q_a = resolve.score_existing(art)
            q_b = resolve.score_existing(match)
            winner, loser = (art, match) if q_a >= q_b else (match, art)
            seen.add(winner.id)
            seen.add(loser.id)

            self.stdout.write(
                f"  DUP: keep '{winner.title[:60]}' ({winner.source_name}) "
                f"drop '{loser.title[:60]}' ({loser.source_name})"
            )
            if apply:
                resolve.purge_duplicate(loser)
                purged += 1

        verb = 'Hard-deleted' if apply else 'Would delete'
        self.stdout.write(self.style.SUCCESS(
            f'{clusters} duplicate cluster(s) found. {verb} {purged if apply else clusters} loser(s).'
        ))
        if not apply and clusters:
            self.stdout.write('Re-run with --apply to collapse them.')
