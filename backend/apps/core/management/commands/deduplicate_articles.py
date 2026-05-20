"""Remove duplicate articles — keeps the oldest, soft-deletes the rest.

Duplicates are detected by exact title match (case-insensitive).

Usage:
    python manage.py deduplicate_articles
    python manage.py deduplicate_articles --dry-run
"""
from django.core.management.base import BaseCommand
from django.db.models import Count, Min
from django.db.models.functions import Lower
from django.utils import timezone

from apps.articles.models import Article


class Command(BaseCommand):
    help = 'Soft-delete duplicate articles (same title), keeping the oldest.'

    def add_arguments(self, parser):
        parser.add_argument('--dry-run', action='store_true', help='Show duplicates without deleting')

    def handle(self, *args, **options):
        dry_run = options['dry_run']

        # Find titles that appear more than once (case-insensitive)
        dupes = (
            Article.objects.filter(deleted_at__isnull=True)
            .annotate(title_lower=Lower('title'))
            .values('title_lower')
            .annotate(cnt=Count('id'), earliest=Min('published_at'))
            .filter(cnt__gt=1)
            .order_by('-cnt')
        )

        total_deleted = 0

        for group in dupes:
            title_lower = group['title_lower']
            articles = list(
                Article.objects.filter(deleted_at__isnull=True)
                .annotate(title_lower=Lower('title'))
                .filter(title_lower=title_lower)
                .order_by('published_at', 'created_at')
            )

            # Keep the first (oldest), soft-delete the rest
            keep = articles[0]
            to_delete = articles[1:]

            if dry_run:
                self.stdout.write(
                    f'  [{len(to_delete)} dupes] "{keep.title[:80]}" — '
                    f'keeping {keep.slug}'
                )
            else:
                now = timezone.now()
                for article in to_delete:
                    article.deleted_at = now
                    article.save(update_fields=['deleted_at'])

            total_deleted += len(to_delete)

        action = 'Would remove' if dry_run else 'Removed'
        self.stdout.write(self.style.SUCCESS(
            f'{action} {total_deleted} duplicate articles '
            f'({dupes.count()} duplicate groups found)'
        ))
