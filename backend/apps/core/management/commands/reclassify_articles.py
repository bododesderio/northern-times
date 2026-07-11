"""Reclassify existing articles with the current GeoClassifier.

Uses the SAME topic-primary classifier the crawl pipeline uses
(apps.crawler.classification.GeoClassifier) so there is one source of truth for
routing rules. Region is inferred from each article's source_name.

Usage:
    python manage.py reclassify_articles
    python manage.py reclassify_articles --dry-run
"""
from dataclasses import dataclass

from django.core.management.base import BaseCommand

from apps.articles.models import Article, Category


@dataclass
class _StubSource:
    """Minimal stand-in for a CrawlSource — GeoClassifier only reads .region."""
    region: str


# Substrings used to infer a stored article's origin region from its source_name.
_INTERNATIONAL = ('bbc', 'al jazeera', 'aljazeera', 'reuters', 'associated press',
                  'ap news', 'afp', 'cnn', 'guardian', 'new york times', 'techcrunch',
                  'espn', 'voa', 'who', 'goal', 'kickoff', 'supersport')
_EAST_AFRICAN = ('east african', 'nation africa', 'daily nation', 'the citizen',
                 'standard media', 'business daily', 'allafrica')


def _infer_region(source_name: str) -> str:
    name = (source_name or '').lower()
    if any(s in name for s in _INTERNATIONAL):
        return 'international'
    if any(s in name for s in _EAST_AFRICAN):
        return 'east_african'
    return 'ugandan'


class Command(BaseCommand):
    help = 'Reclassify articles using the crawl pipeline GeoClassifier (topic-primary).'

    def add_arguments(self, parser):
        parser.add_argument('--dry-run', action='store_true', help='Show changes without saving')

    def handle(self, *args, **options):
        dry_run = options['dry_run']

        from apps.crawler.classification import GeoClassifier

        # Load the AI zero-shot classifier (shared by the GeoClassifier).
        try:
            from apps.enrichment.category_classifier import CategoryClassifier
            classifier = CategoryClassifier()
            classifier.classify('test', 'test', ['Politics', 'Sports'])
            self.stdout.write('  AI classifier loaded.')
        except Exception as e:
            self.stdout.write(self.style.WARNING(f'  AI classifier unavailable: {e} — keyword-only.'))

            class _NullClassifier:
                def classify(self, *a, **k):
                    return None, 0.0

            classifier = _NullClassifier()

        geo = GeoClassifier(classifier)
        category_names = list(
            Category.objects.exclude(slug__in=['top-stories']).values_list('name', flat=True)
        )

        queryset = Article.objects.filter(deleted_at__isnull=True).select_related('category')
        total = queryset.count()
        self.stdout.write(f'Reclassifying {total} articles...')

        updated = 0
        dist = {}
        for article in queryset.iterator():
            source = _StubSource(region=_infer_region(article.source_name))
            new_cat = geo.classify(
                source, article.title, article.content or '', category_names,
            )
            if new_cat:
                dist[new_cat.name] = dist.get(new_cat.name, 0) + 1
            if new_cat and new_cat != article.category:
                old = article.category.name if article.category else 'None'
                if dry_run:
                    self.stdout.write(f'  [{old} -> {new_cat.name}] {article.title[:65]}')
                else:
                    article.category = new_cat
                    article.save(update_fields=['category'])
                updated += 1

        action = 'Would reclassify' if dry_run else 'Reclassified'
        self.stdout.write(self.style.SUCCESS(f'{action} {updated}/{total} articles'))
        self.stdout.write('  Resulting distribution:')
        for name, n in sorted(dist.items(), key=lambda kv: -kv[1]):
            self.stdout.write(f'    {n:4}  {name}')
