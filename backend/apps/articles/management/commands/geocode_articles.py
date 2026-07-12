"""Backfill article coordinates from stored GPE entities via the gazetteer.

Usage:
    python manage.py geocode_articles          # only ungeocoded published articles
    python manage.py geocode_articles --all    # re-geocode everything
"""
from django.core.management.base import BaseCommand

from apps.articles.models import Article
from apps.articles.services.geo import apply_geocode


class Command(BaseCommand):
    help = 'Geocode articles from their named entities (gazetteer lookup).'

    def add_arguments(self, parser):
        parser.add_argument('--all', action='store_true',
                            help='Re-geocode all articles, not just ungeocoded ones.')

    def handle(self, *args, **opts):
        qs = Article.objects.all()
        if not opts['all']:
            qs = qs.filter(latitude__isnull=True)
        qs = qs.prefetch_related('entities')

        total = geocoded = 0
        for article in qs.iterator(chunk_size=200):
            total += 1
            if apply_geocode(article):
                geocoded += 1
        self.stdout.write(self.style.SUCCESS(
            f'Geocoded {geocoded} of {total} article(s).'))
