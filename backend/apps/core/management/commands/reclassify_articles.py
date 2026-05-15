"""Reclassify existing articles using geo-routing rules.

Fast — uses keyword matching only, no AI models needed.
"""
import re

from django.core.management.base import BaseCommand

from apps.articles.models import Article, Category
from apps.crawler.engine import CrawlerEngine


class Command(BaseCommand):
    help = 'Reclassify articles using geo-routing (keywords only, no AI).'

    def add_arguments(self, parser):
        parser.add_argument('--dry-run', action='store_true', help='Show changes without saving')

    def handle(self, *args, **options):
        dry_run = options['dry_run']
        northern = Category.objects.filter(slug='northern-uganda').first()
        world = Category.objects.filter(slug='world').first()
        national = Category.objects.filter(slug='national').first()

        if not all([northern, world, national]):
            self.stderr.write('Missing required categories')
            return

        engine = CrawlerEngine.__new__(CrawlerEngine)  # just for keyword access
        uk = CrawlerEngine.UGANDA_KEYWORDS

        international_sources = ['bbc', 'al jazeera', 'reuters', 'associated press', 'afp']
        east_african_sources = ['east african', 'nation africa', 'daily nation']

        updated = 0
        for a in Article.objects.select_related('category').all():
            text_lower = f"{a.title} {(a.content or '')[:2000]}".lower()
            source_name = (a.source_name or '').lower()
            old_cat = a.category

            is_international = any(s in source_name for s in international_sources)
            is_east_african = any(s in source_name for s in east_african_sources)
            is_ugandan = not is_international and not is_east_african

            new_cat = None

            # Northern Uganda — only for Ugandan sources, 3+ keyword matches
            if is_ugandan:
                northern_matches = engine._count_northern_matches(text_lower)
                if northern_matches >= 3:
                    new_cat = northern

            # International/East African → World unless about Uganda
            if not new_cat and (is_international or is_east_african):
                uganda_matches = sum(
                    1 for kw in uk
                    if re.search(r'\b' + re.escape(kw) + r'\b', text_lower)
                )
                if uganda_matches >= 2:
                    if old_cat and old_cat.slug not in ('top-stories', 'world'):
                        new_cat = old_cat  # keep existing topic
                    else:
                        new_cat = national
                else:
                    new_cat = world

            # Keyword topic hint for better classification
            if not new_cat or (new_cat and new_cat.slug in ('national', 'top-stories')):
                hint = engine._keyword_topic_hint(text_lower)
                if hint:
                    topic_cat = Category.objects.filter(name=hint).first()
                    if topic_cat:
                        new_cat = topic_cat

            # Ugandan source still in Top Stories/World → National
            if not new_cat and is_ugandan and old_cat and old_cat.slug in ('top-stories', 'world'):
                new_cat = national

            if new_cat and new_cat != old_cat:
                if dry_run:
                    self.stdout.write(f'  [{old_cat} -> {new_cat}] {a.title[:60]}')
                else:
                    a.category = new_cat
                    a.save(update_fields=['category'])
                updated += 1

        action = 'Would reclassify' if dry_run else 'Reclassified'
        self.stdout.write(self.style.SUCCESS(f'{action} {updated} articles'))
