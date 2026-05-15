"""Download external featured images locally and clear broken ones."""
from django.core.management.base import BaseCommand
from apps.articles.models import Article


class Command(BaseCommand):
    help = 'Download external featured images and clear broken ones'

    def add_arguments(self, parser):
        parser.add_argument('--dry-run', action='store_true')
        parser.add_argument('--limit', type=int, default=500)

    def handle(self, *args, **options):
        from apps.media.services.downloader import download_image

        articles = Article.objects.filter(
            status='published',
            deleted_at__isnull=True,
            featured_image__startswith='http',  # Only external URLs
        ).order_by('-created_at')[:options['limit']]

        fixed = 0
        cleared = 0
        for article in articles:
            url = article.featured_image
            # Try to download locally
            try:
                local_path = download_image(url)
                if local_path:
                    if not options['dry_run']:
                        article.featured_image = local_path
                        article.save(update_fields=['featured_image'])
                    fixed += 1
                    self.stdout.write(f'  Downloaded: {url[:60]}...')
                else:
                    # Download failed -- clear the broken URL
                    if not options['dry_run']:
                        article.featured_image = ''
                        article.save(update_fields=['featured_image'])
                    cleared += 1
                    self.stdout.write(f'  Cleared broken: {url[:60]}...')
            except Exception as e:
                if not options['dry_run']:
                    article.featured_image = ''
                    article.save(update_fields=['featured_image'])
                cleared += 1
                self.stdout.write(f'  Error ({e}): {url[:60]}...')

        self.stdout.write(f'\nFixed: {fixed}, Cleared: {cleared}, Total: {articles.count()}')
