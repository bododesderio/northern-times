"""Reconcile crawl sources to a known-good, verified set.

Many inherited sources pointed at HTML pages (not RSS), paywalled endpoints, or
JS/Cloudflare-walled SPAs, so they returned 0 items. This command:

  * upserts a curated set of sources whose feeds were verified to return items,
  * fixes wrong URLs (e.g. Pulse) and enables working-but-idle ones (WHO),
  * adds a couple of allAfrica aggregator feeds (direct article links, bot-
    friendly) for Uganda + sport depth,
  * deactivates sources with no reachable feed, recording WHY in
    attribution_text so it is auditable rather than silently dropped.

Idempotent — safe to re-run. Matches sources by name.

Usage: python manage.py configure_sources [--dry-run]
"""
from django.core.management.base import BaseCommand
from django.utils import timezone

from apps.articles.models import Category
from apps.crawler.models import CrawlSource

# name -> config. category is a slug hint for the source's typical content; the
# GeoClassifier still classifies each article individually.
ACTIVE_SOURCES = [
    # --- International wire / topic feeds (direct RSS, verified) ---
    {'name': 'Al Jazeera Africa', 'url': 'https://www.aljazeera.com/xml/rss/all.xml', 'region': 'international', 'browser': False, 'cat': 'world'},
    {'name': 'BBC Africa', 'url': 'http://feeds.bbci.co.uk/news/world/africa/rss.xml', 'region': 'international', 'browser': False, 'cat': 'world'},
    {'name': 'BBC Sport', 'url': 'https://feeds.bbci.co.uk/sport/rss.xml', 'region': 'international', 'browser': False, 'cat': 'sports'},
    {'name': 'TechCrunch Africa', 'url': 'https://techcrunch.com/tag/africa/feed/', 'region': 'international', 'browser': False, 'cat': 'technology'},
    {'name': 'WHO Africa', 'url': 'https://www.afro.who.int/rss.xml', 'region': 'international', 'browser': False, 'cat': 'health'},
    # --- Ugandan / local (direct WordPress RSS, verified) ---
    {'name': 'The Independent', 'url': 'https://www.independent.co.ug/feed/', 'region': 'ugandan', 'browser': False, 'cat': 'politics'},
    {'name': 'The Observer', 'url': 'https://observer.ug/feed/', 'region': 'ugandan', 'browser': False, 'cat': 'politics'},
    {'name': 'Sqoop Entertainment', 'url': 'https://www.sqoop.co.ug/feed/', 'region': 'ugandan', 'browser': False, 'cat': 'entertainment'},
    {'name': 'Dokolo Post', 'url': 'https://dokolopost.com/feed/', 'region': 'ugandan', 'browser': False, 'cat': 'northern-uganda'},
    {'name': 'Pulse Uganda', 'url': 'https://www.pulse.ug/rss-articles.xml', 'region': 'ugandan', 'browser': False, 'cat': 'entertainment'},
    # --- allAfrica aggregators (direct article links, bot-friendly) ---
    {'name': 'allAfrica Uganda', 'url': 'https://allafrica.com/tools/headlines/rdf/uganda/headlines.rdf', 'region': 'ugandan', 'browser': False, 'cat': 'politics', 'interval': 90},
    {'name': 'allAfrica Sport', 'url': 'https://allafrica.com/tools/headlines/rdf/sport/headlines.rdf', 'region': 'international', 'browser': False, 'cat': 'sports', 'interval': 90},
]

# Sites with no usable RSS that ARE reachable via the stealth browser: render the
# index and scrape article links. content_selector = required path fragment(s).
WEB_SOURCES = [
    {'name': 'Nile Post', 'url': 'https://nilepost.co.ug/', 'region': 'ugandan',
     'cat': 'politics', 'selector': '/news/', 'interval': 90},
    {'name': 'Daily Monitor', 'url': 'https://www.monitor.co.ug/uganda/news', 'region': 'ugandan',
     'cat': 'politics', 'selector': '/uganda/news/', 'interval': 90},
]

# name -> reason. Deactivated because no reachable/parseable feed exists.
DEACTIVATE = {
    'New Vision': 'HTML page / no public RSS; JS-rendered',
    'Uganda Radio Network': 'SPA — /feed/ returns app shell',
    'Monitor Lifestyle': 'Cloudflare-walled; covered via allAfrica',
    'Reuters Africa': 'Public RSS discontinued (401 paywall)',
    'AP News Africa': 'Hub is HTML, no RSS',
    'VOA Africa': 'RSS 403 bot-blocked',
    'Nation Africa': 'JS-rendered, no reachable RSS',
    'Business Daily Africa': 'JS-rendered, no reachable RSS',
    'The East African': 'JS-rendered, no reachable RSS',
    'Goal.com': 'HTML page, no RSS; covered via BBC Sport / allAfrica Sport',
    'KickOff': 'HTML page, no RSS',
    'SuperSport': 'HTML page, no RSS; covered via BBC Sport / allAfrica Sport',
    'ESPN': 'Feed returns 0 items',
    'BBC Culture': 'Feed returns 0 items',
    'BBC Travel': 'Feed returns 0 items',
}

_DEFAULT_STRIP = '.ads,.sidebar,.social-share,.related-content,.newsletter-signup,.sharedaddy'


class Command(BaseCommand):
    help = 'Reconcile crawl sources to a verified working set.'

    def add_arguments(self, parser):
        parser.add_argument('--dry-run', action='store_true')

    def handle(self, *args, **options):
        dry = options['dry_run']
        cats = {c.slug: c for c in Category.objects.all()}

        self.stdout.write(self.style.MIGRATE_HEADING('Configuring crawl sources...'))
        for cfg in ACTIVE_SOURCES:
            src = CrawlSource.objects.filter(name=cfg['name']).first()
            fields = dict(
                url=cfg['url'], region=cfg['region'], use_browser=cfg['browser'],
                feed_type='rss', is_active=True,
                crawl_interval=cfg.get('interval', 60),
                category=cats.get(cfg['cat']),
                strip_selectors=_DEFAULT_STRIP,
                # Re-arm: clear failure state and make it due immediately.
                consecutive_failures=0, consecutive_empty=0,
                next_crawl_at=timezone.now(),
                attribution_text=f"Source: {cfg['name']}",
            )
            action = 'update' if src else 'create'
            if not dry:
                if src:
                    for k, v in fields.items():
                        setattr(src, k, v)
                    src.save()
                else:
                    CrawlSource.objects.create(name=cfg['name'], max_articles=25, **fields)
            self.stdout.write(f'  [{action}] {cfg["name"]:22} -> {cfg["url"]}')

        self.stdout.write(self.style.MIGRATE_HEADING('\nConfiguring stealth-browser (web) sources...'))
        for cfg in WEB_SOURCES:
            src = CrawlSource.objects.filter(name=cfg['name']).first()
            fields = dict(
                url=cfg['url'], region=cfg['region'], use_browser=True,
                feed_type='web', is_active=True,
                crawl_interval=cfg.get('interval', 90),
                category=cats.get(cfg['cat']),
                content_selector=cfg['selector'],
                consecutive_failures=0, consecutive_empty=0,
                next_crawl_at=timezone.now(),
                attribution_text=f"Source: {cfg['name']} (stealth browser)",
            )
            if not dry:
                if src:
                    for k, v in fields.items():
                        setattr(src, k, v)
                    src.save()
                else:
                    CrawlSource.objects.create(name=cfg['name'], max_articles=15, **fields)
            self.stdout.write(f'  [web] {cfg["name"]:22} -> {cfg["url"]} (links: {cfg["selector"]})')

        self.stdout.write(self.style.MIGRATE_HEADING('\nDeactivating unreachable sources...'))
        for name, reason in DEACTIVATE.items():
            src = CrawlSource.objects.filter(name=name).first()
            if not src:
                continue
            if not dry:
                src.is_active = False
                src.attribution_text = f'DISABLED: {reason}'
                src.save(update_fields=['is_active', 'attribution_text'])
            self.stdout.write(f'  [disable] {name:22} — {reason}')

        active = CrawlSource.objects.filter(is_active=True).count()
        total = CrawlSource.objects.count()
        self.stdout.write(self.style.SUCCESS(f'\nDone. {active}/{total} sources active.'))
