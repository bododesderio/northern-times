"""Reclassify articles using AI + keyword geo-routing.

Reads every article and assigns the correct category based on content.
Also removes the 'National' category, redistributing its articles.

Usage:
    python manage.py reclassify_articles
    python manage.py reclassify_articles --dry-run
    python manage.py reclassify_articles --ai-only         # skip keyword pre-filter
    python manage.py reclassify_articles --remove-national  # only handle National articles
"""
import re

from django.core.management.base import BaseCommand
from django.utils.text import slugify

from apps.articles.models import Article, Category


class Command(BaseCommand):
    help = 'Reclassify articles using AI zero-shot classification + keyword geo-routing.'

    def add_arguments(self, parser):
        parser.add_argument('--dry-run', action='store_true', help='Show changes without saving')
        parser.add_argument('--ai-only', action='store_true', help='Use AI classifier for all (skip keyword pre-filter)')
        parser.add_argument('--remove-national', action='store_true', help='Only reclassify articles in National category')

    def handle(self, *args, **options):
        dry_run = options['dry_run']
        ai_only = options['ai_only']
        remove_national = options['remove_national']

        # Load categories
        categories = {c.slug: c for c in Category.objects.all()}
        national = categories.get('national')

        # Valid topic categories for AI classification (exclude meta-categories)
        topic_categories = [
            c for c in categories.values()
            if c.slug not in ('top-stories', 'national')
        ]
        topic_names = [c.name for c in topic_categories]
        topic_by_name = {c.name.lower(): c for c in topic_categories}
        topic_by_slug = {c.slug: c for c in topic_categories}

        # Load classifier
        classifier = None
        try:
            from apps.enrichment.category_classifier import CategoryClassifier
            classifier = CategoryClassifier()
            # Test it loads
            classifier.classify('test', 'test', ['Politics', 'Sports'])
            self.stdout.write('  AI classifier loaded successfully.')
        except Exception as e:
            self.stdout.write(self.style.WARNING(f'  AI classifier unavailable: {e}'))
            self.stdout.write('  Falling back to keyword-only classification.')

        # Load keyword hints from crawler engine
        from apps.crawler.engine import CrawlerEngine
        engine = CrawlerEngine.__new__(CrawlerEngine)

        # Determine which articles to process
        if remove_national:
            if not national:
                self.stderr.write('National category not found — nothing to do.')
                return
            queryset = Article.objects.filter(category=national, deleted_at__isnull=True)
            self.stdout.write(f'Processing {queryset.count()} National articles...')
        else:
            queryset = Article.objects.filter(deleted_at__isnull=True).select_related('category')
            self.stdout.write(f'Processing {queryset.count()} articles...')

        updated = 0
        for article in queryset.iterator():
            text_lower = f"{article.title} {(article.content or '')[:2000]}".lower()
            source_name = (article.source_name or '').lower()
            old_cat = article.category

            # Determine source type
            international_sources = ['bbc', 'al jazeera', 'reuters', 'associated press', 'afp',
                                     'cnn', 'guardian', 'new york times', 'washington post']
            east_african_sources = ['east african', 'nation africa', 'daily nation', 'citizen',
                                    'standard media']

            is_international = any(s in source_name for s in international_sources)
            is_east_african = any(s in source_name for s in east_african_sources)
            is_ugandan = not is_international and not is_east_african

            new_cat = None

            if not ai_only:
                # Step 1: Northern Uganda detection (Ugandan sources, 3+ keyword matches)
                if is_ugandan and 'northern-uganda' in topic_by_slug:
                    try:
                        northern_matches = engine._count_northern_matches(text_lower)
                        if northern_matches >= 3:
                            new_cat = topic_by_slug['northern-uganda']
                    except Exception:
                        pass

                # Step 2: International sources → World unless heavily about Uganda
                if not new_cat and (is_international or is_east_african):
                    uganda_matches = sum(
                        1 for kw in CrawlerEngine.UGANDA_KEYWORDS
                        if re.search(r'\b' + re.escape(kw) + r'\b', text_lower)
                    )
                    if uganda_matches < 2 and 'world' in topic_by_slug:
                        new_cat = topic_by_slug['world']

            # Step 3: Keyword topic hint (fast)
            if not new_cat:
                try:
                    hint = engine._keyword_topic_hint(text_lower)
                    if hint:
                        cat = topic_by_name.get(hint.lower())
                        if cat:
                            new_cat = cat
                except Exception:
                    pass

            # Step 4: AI classification (slow but accurate)
            if not new_cat and classifier:
                try:
                    cat_name, score = classifier.classify(
                        article.title,
                        (article.content or '')[:1000],
                        topic_names,
                    )
                    if cat_name and score >= 0.3:
                        cat = topic_by_name.get(cat_name.lower())
                        if cat:
                            new_cat = cat
                except Exception as e:
                    self.stderr.write(f'  AI error for "{article.title[:50]}": {e}')

            # Step 5: Fallback — Ugandan source → Politics, else → World
            if not new_cat:
                if is_ugandan:
                    new_cat = topic_by_slug.get('politics', topic_by_slug.get('world'))
                else:
                    new_cat = topic_by_slug.get('world')

            # Apply change if different from current
            if new_cat and new_cat != old_cat:
                old_name = old_cat.name if old_cat else 'None'
                if dry_run:
                    self.stdout.write(f'  [{old_name} -> {new_cat.name}] {article.title[:70]}')
                else:
                    article.category = new_cat
                    article.save(update_fields=['category'])
                updated += 1

        action = 'Would reclassify' if dry_run else 'Reclassified'
        self.stdout.write(self.style.SUCCESS(f'{action} {updated} articles'))

        # Remove National category if it exists and not dry-run
        if national and not dry_run:
            remaining = Article.objects.filter(category=national, deleted_at__isnull=True).count()
            if remaining == 0:
                national.delete()
                self.stdout.write(self.style.SUCCESS('Deleted "National" category (0 articles remaining).'))
            else:
                self.stdout.write(self.style.WARNING(
                    f'National category still has {remaining} articles — not deleted.'
                ))
