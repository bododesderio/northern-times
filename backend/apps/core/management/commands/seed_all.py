"""
Comprehensive database seeder for fresh Django installations.

Seeds: settings, categories, crawl sources, ad slots, policy pages, login
quotes, and an admin superuser.  100% idempotent — safe to run repeatedly.

Usage:
    python manage.py seed_all
    python manage.py seed_all --skip-sources
"""
import os

from django.core.management import call_command
from django.core.management.base import BaseCommand
from django.utils.text import slugify


class Command(BaseCommand):
    help = 'Seed all essential data for a fresh Northern Times installation.'

    def add_arguments(self, parser):
        parser.add_argument(
            '--skip-sources', action='store_true',
            help='Skip crawl source seeding',
        )
        parser.add_argument(
            '--level', choices=['minimal', 'dev', 'full'], default='minimal',
            help='Seed level: minimal (essential only), dev (+ test data), full (+ all optionals)',
        )

    def handle(self, *args, **options):
        level = options['level']
        self.stdout.write(self.style.MIGRATE_HEADING(f'Running full seeder (level={level})...'))

        # Essential seeds (always run)
        self._seed_roles()
        self._seed_admin_user()
        self._seed_settings()
        categories = self._seed_categories()
        if not options['skip_sources']:
            self._seed_crawl_sources(categories)
        self._seed_ad_slots()
        self._seed_policy_pages()
        self._seed_login_quotes()
        self._reset_stale_crawl_state()

        # Development data (level=dev or full)
        if level in ('dev', 'full'):
            self.stdout.write(self.style.MIGRATE_HEADING('\nSeeding development data...'))
            call_command('seed_dev_data', stdout=self.stdout)

        self.stdout.write(self.style.SUCCESS('\nSeeder complete.'))

    # ------------------------------------------------------------------
    # 0. Roles (delegates to existing command)
    # ------------------------------------------------------------------
    def _seed_roles(self):
        self.stdout.write('\n  Seeding roles...')
        call_command('seed_roles', stdout=self.stdout)

    # ------------------------------------------------------------------
    # 1. Admin superuser
    # ------------------------------------------------------------------
    # Fixed superuser credentials — identical in dev and prod by rule.
    ADMIN_EMAIL = 'admin@northerntimesug.com'
    ADMIN_USERNAME = 'admin'
    ADMIN_PASSWORD = 'Admin1234'

    def _seed_admin_user(self):
        from apps.accounts.models import Role, User

        self.stdout.write('\n  Seeding admin user...')
        role = Role.objects.filter(name='super_admin').first()

        # Upsert so re-seeding always enforces the canonical credentials, even if
        # a legacy admin (e.g. admin@northerntimes.local) already exists.
        user = (User.objects.filter(email=self.ADMIN_EMAIL).first()
                or User.objects.filter(username=self.ADMIN_USERNAME).first())
        if user:
            user.username = self.ADMIN_USERNAME
            user.email = self.ADMIN_EMAIL
            user.is_superuser = True
            user.is_staff = True
            if role:
                user.role = role
            user.set_password(self.ADMIN_PASSWORD)
            user.save()
            self.stdout.write(self.style.SUCCESS(f'    Admin ensured ({self.ADMIN_EMAIL}).'))
        else:
            user = User.objects.create_superuser(
                username=self.ADMIN_USERNAME,
                email=self.ADMIN_EMAIL,
                password=self.ADMIN_PASSWORD,
            )
            if role:
                user.role = role
                user.save(update_fields=['role'])
            self.stdout.write(self.style.SUCCESS(f'    Created admin ({self.ADMIN_EMAIL}).'))

    # ------------------------------------------------------------------
    # 2. Site settings
    # ------------------------------------------------------------------
    def _seed_settings(self):
        from apps.core.models import Setting

        self.stdout.write('\n  Seeding site settings...')
        defaults = {
            'crawler_enabled': ('true', 'bool'),
            'crawler_auto_publish': ('true', 'bool'),
            'site_name': (os.environ.get('APP_NAME', 'The Northern Times'), 'string'),
            'site_tagline': ('Independent journalism from Northern Uganda and beyond.', 'string'),
            'site_description': (
                'The Northern Times delivers independent news, analysis and in-depth '
                'reporting from Northern Uganda and across East Africa.',
                'string',
            ),
            'site_abbreviation': ('NT', 'string'),
            'theme_mode': ('light', 'string'),
            'theme_accent': ('#cc0000', 'string'),
        }
        created = 0
        for key, (value, stype) in defaults.items():
            _, was_created = Setting.objects.get_or_create(
                key=key,
                defaults={'value': str(value), 'type': stype},
            )
            if was_created:
                created += 1
        self.stdout.write(f'    Settings: {created} created, {len(defaults) - created} already existed.')

    # ------------------------------------------------------------------
    # 3. Categories (15)
    # ------------------------------------------------------------------
    def _seed_categories(self):
        from apps.articles.models import Category

        self.stdout.write('\n  Seeding categories...')
        cats = [
            # (name, slug, description, sort_order, show_in_nav, show_in_sidebar)
            ('Top Stories', 'top-stories', 'Lead stories and editor picks from across all sections.', 1, True, True),
            ('Local News', 'local-news', 'Community news, events, and reporting from your part of the country.', 2, True, True),
            ('Politics', 'politics', 'Politics, government, elections, and policy analysis.', 3, True, True),
            ('Business', 'business', 'Business, economy, finance, and market analysis.', 4, True, True),
            ('Sports', 'sports', 'Football, basketball, athletics, and East African sports.', 5, True, True),
            ('Health', 'health', 'Health, medicine, public health, and wellness reporting.', 6, True, True),
            ('World', 'world', 'World news, international affairs, and global analysis.', 7, True, True),
            ('Opinion', 'opinion', 'Opinion columns, editorials, and expert commentary.', 8, True, True),
            ('Technology', 'technology', 'Technology, software, AI, innovation, and digital trends.', 9, True, True),
            ('Crime & Security', 'crime-security', 'Crime, law enforcement, security, and justice reporting.', 10, True, True),
            ('Education', 'education', 'Education news, policy, and academic developments.', 11, True, True),
            ('Entertainment', 'entertainment', 'Entertainment, celebrity news, music, film, and culture.', 12, True, True),
            ('Environment', 'environment', 'Environment, climate change, conservation, and sustainability.', 13, True, True),
            ('Lifestyle', 'lifestyle', 'Lifestyle, wellness, travel, and modern living.', 14, True, True),
        ]

        lookup = {}
        created = 0
        updated = 0
        for name, slug, desc, sort, nav, sidebar in cats:
            obj, was_created = Category.objects.get_or_create(
                slug=slug,
                defaults={
                    'name': name,
                    'description': desc,
                    'sort_order': sort,
                    'show_in_nav': nav,
                    'show_in_sidebar': sidebar,
                },
            )
            if was_created:
                created += 1
            else:
                # Update existing categories to match current config
                changed = False
                if obj.sort_order != sort:
                    obj.sort_order = sort
                    changed = True
                if obj.show_in_nav != nav:
                    obj.show_in_nav = nav
                    changed = True
                if obj.show_in_sidebar != sidebar:
                    obj.show_in_sidebar = sidebar
                    changed = True
                if changed:
                    obj.save(update_fields=['sort_order', 'show_in_nav', 'show_in_sidebar'])
                    updated += 1
            lookup[slug] = obj

        # Remove deprecated "National" category (reclassify its articles first)
        national = Category.objects.filter(slug='national').first()
        if national:
            politics = lookup.get('politics')
            if politics:
                moved = national.articles.filter(deleted_at__isnull=True).update(category=politics)
                self.stdout.write(f'    Moved {moved} articles from National to Politics.')
            national.delete()
            self.stdout.write('    Deleted deprecated "National" category.')

        self.stdout.write(f'    Categories: {created} created, {updated} updated, {len(cats) - created - updated} unchanged.')
        return lookup

    # ------------------------------------------------------------------
    # 4. Crawl sources (12)
    # ------------------------------------------------------------------
    def _seed_crawl_sources(self, categories):
        from apps.crawler.models import CrawlSource

        self.stdout.write('\n  Seeding crawl sources...')

        sources = [
            # Ugandan
            {
                'name': 'Daily Monitor',
                'url': 'https://www.monitor.co.ug/uganda/news',
                'region': 'ugandan',
                'category': 'national',
                'use_browser': True,
                'content_selector': '.article-body,.story-body,.entry-content',
                'strip_selectors': '.ads,.sidebar,#related-articles,.social-share,.newsletter-signup',
                'attribution_text': 'Source: Daily Monitor',
            },
            {
                'name': 'New Vision',
                'url': 'https://www.newvision.co.ug/category/news',
                'region': 'ugandan',
                'category': 'national',
                'use_browser': True,
                'content_selector': '.article-body,.story-body,.entry-content',
                'strip_selectors': '.ads,.sidebar,.social-share,.newsletter-signup',
                'attribution_text': 'Source: New Vision',
            },
            {
                'name': 'Nile Post',
                'url': 'https://nilepost.co.ug/feed/',
                'region': 'ugandan',
                'category': 'national',
                'content_selector': '.entry-content,.post-content',
                'strip_selectors': '.ads,.sidebar,.social-share,.sharedaddy,#related-articles,.wp-block-newspack-blocks-homepage-articles',
                'attribution_text': 'Source: Nile Post',
            },
            {
                'name': 'The Observer',
                'url': 'https://observer.ug/feed/',
                'region': 'ugandan',
                'category': 'national',
                'content_selector': '.entry-content,.post-content',
                'strip_selectors': '.ads,.sidebar,.social-share,.sharedaddy,#related-articles',
                'attribution_text': 'Source: The Observer',
            },
            {
                'name': 'The Independent',
                'url': 'https://www.independent.co.ug/feed/',
                'region': 'ugandan',
                'category': 'politics',
                'content_selector': '.entry-content,.post-content',
                'strip_selectors': '.ads,.sidebar,.social-share,.sharedaddy,#related-articles',
                'attribution_text': 'Source: The Independent',
            },
            {
                'name': 'Uganda Radio Network',
                'url': 'https://ugandaradionetwork.net/feed/',
                'region': 'ugandan',
                'category': 'national',
                'content_selector': '.entry-content,.post-content',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: Uganda Radio Network',
            },
            {
                'name': 'Dokolo Post',
                'url': 'https://dokolopost.com/feed/',
                'region': 'ugandan',
                'category': 'local-news',
                'content_selector': '.entry-content,.post-content',
                'strip_selectors': '.ads,.sidebar,.social-share,.wp-block-newspack-blocks-homepage-articles',
                'attribution_text': 'Source: Dokolo Post',
            },
            # East African
            {
                'name': 'The East African',
                'url': 'https://www.theeastafrican.co.ke/tea/news',
                'region': 'east_african',
                'category': 'world',
                'use_browser': True,
                'content_selector': '.article-body,.story-body',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: The East African',
            },
            {
                'name': 'Nation Africa',
                'url': 'https://nation.africa/kenya/news',
                'region': 'east_african',
                'category': 'world',
                'use_browser': True,
                'content_selector': '.article-body,.story-body',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: Nation Africa',
            },
            # International
            {
                'name': 'BBC Africa',
                'url': 'http://feeds.bbci.co.uk/news/world/africa/rss.xml',
                'region': 'international',
                'category': 'world',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: BBC Africa',
            },
            {
                'name': 'Al Jazeera Africa',
                'url': 'https://www.aljazeera.com/xml/rss/all.xml',
                'region': 'international',
                'category': 'world',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: Al Jazeera',
            },
            {
                'name': 'Reuters Africa',
                'url': 'https://www.reuters.com/world/africa/',
                'region': 'international',
                'category': 'world',
                'use_browser': True,
                'content_selector': '.article-body__content',
                'strip_selectors': '.ads,.sidebar',
                'attribution_text': 'Source: Reuters',
            },
            {
                'name': 'AP News Africa',
                'url': 'https://apnews.com/hub/africa',
                'region': 'international',
                'category': 'world',
                'use_browser': True,
                'content_selector': '.Article,.RichTextStoryBody',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: AP News',
            },
            {
                'name': 'VOA Africa',
                'url': 'https://www.voanews.com/rss/africa.xml',
                'region': 'international',
                'category': 'world',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: VOA Africa',
            },
            # Sports
            {
                'name': 'BBC Sport Africa',
                'url': 'https://feeds.bbci.co.uk/sport/rss.xml',
                'region': 'international',
                'category': 'sports',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: BBC Sport Africa',
            },
            {
                'name': 'ESPN',
                'url': 'https://www.espn.com/espn/rss/soccer/news',
                'region': 'international',
                'category': 'sports',
                'strip_selectors': '.ads,.sidebar,.social-share,.related-content',
                'attribution_text': 'Source: ESPN',
            },
            {
                'name': 'Goal.com',
                'url': 'https://www.goal.com/en/news',
                'region': 'international',
                'category': 'sports',
                'use_browser': True,
                'content_selector': '.article-body,.body-article',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: Goal.com',
            },
            {
                'name': 'KickOff',
                'url': 'https://www.kickoff.com/news',
                'region': 'international',
                'category': 'sports',
                'use_browser': True,
                'content_selector': '.article-body,.article-content',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: KickOff',
            },
            {
                'name': 'SuperSport',
                'url': 'https://supersport.com/football',
                'region': 'international',
                'category': 'sports',
                'use_browser': True,
                'content_selector': '.article-body,.article-content',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: SuperSport',
            },
            # Entertainment
            {
                'name': 'Pulse Uganda',
                'url': 'https://www.pulse.ug/news',
                'region': 'ugandan',
                'category': 'entertainment',
                'use_browser': True,
                'content_selector': '.article-body,.entry-content,.post-content',
                'strip_selectors': '.ads,.sidebar,.social-share,.sharedaddy',
                'attribution_text': 'Source: Pulse Uganda',
            },
            {
                'name': 'Sqoop Entertainment',
                'url': 'https://www.sqoop.co.ug/feed/',
                'region': 'ugandan',
                'category': 'entertainment',
                'content_selector': '.entry-content,.post-content',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: Sqoop',
            },
            {
                'name': 'BBC Culture',
                'url': 'http://feeds.bbci.co.uk/culture/rss.xml',
                'region': 'international',
                'category': 'entertainment',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: BBC Culture',
            },
            # Lifestyle
            {
                'name': 'Monitor Lifestyle',
                'url': 'https://www.monitor.co.ug/uganda/magazines/rss',
                'region': 'ugandan',
                'category': 'lifestyle',
                'strip_selectors': '.ads,.sidebar,.social-share,.newsletter-signup',
                'attribution_text': 'Source: Monitor Lifestyle',
            },
            {
                'name': 'BBC Travel',
                'url': 'http://feeds.bbci.co.uk/travel/rss.xml',
                'region': 'international',
                'category': 'lifestyle',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: BBC Travel',
            },
            # Business
            {
                'name': 'Business Daily Africa',
                'url': 'https://www.businessdailyafrica.com/bd/markets',
                'region': 'east_african',
                'category': 'business',
                'use_browser': True,
                'content_selector': '.article-body,.story-body',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: Business Daily Africa',
            },
            # Health
            {
                'name': 'WHO Africa',
                'url': 'https://www.afro.who.int/rss.xml',
                'region': 'international',
                'category': 'health',
                'strip_selectors': '.ads,.sidebar,.social-share',
                'attribution_text': 'Source: WHO Africa',
            },
            # Technology
            {
                'name': 'TechCrunch Africa',
                'url': 'https://techcrunch.com/tag/africa/feed/',
                'region': 'international',
                'category': 'technology',
                'strip_selectors': '.ads,.sidebar,.social-share,.related-content',
                'attribution_text': 'Source: TechCrunch',
            },
        ]

        created = 0
        for src in sources:
            cat = categories.get(src.get('category', 'national'))
            _, was_created = CrawlSource.objects.get_or_create(
                name=src['name'],
                defaults={
                    'url': src['url'],
                    'category': cat,
                    'feed_type': 'rss',
                    'region': src['region'],
                    'content_selector': src.get('content_selector', ''),
                    'strip_selectors': src.get('strip_selectors', ''),
                    'attribution_text': src.get('attribution_text', ''),
                    'use_browser': src.get('use_browser', False),
                    'download_images': True,
                    'crawl_interval': 30,
                    'max_articles': 20,
                    'nofollow': True,
                    'auto_rewrite': True,
                    'is_active': True,
                },
            )
            if was_created:
                created += 1

        # Enforce auto_rewrite on all sources
        updated = CrawlSource.objects.filter(auto_rewrite=False).update(auto_rewrite=True)
        if updated:
            self.stdout.write(f'    Auto-rewrite enabled on {updated} source(s).')

        # Reassign any existing sources still on top-stories to national
        top_stories_cat = categories.get('top-stories')
        national_cat = categories.get('national')
        if top_stories_cat and national_cat:
            reassigned = CrawlSource.objects.filter(category=top_stories_cat).update(category=national_cat)
            if reassigned:
                self.stdout.write(f'    Reassigned {reassigned} source(s) from top-stories to national.')

        self.stdout.write(f'    Sources: {created} created, {len(sources) - created} already existed.')

    # ------------------------------------------------------------------
    # 5. Ad slots (6 standard positions)
    # ------------------------------------------------------------------
    def _seed_ad_slots(self):
        from apps.ads.models import AdSlot

        self.stdout.write('\n  Seeding ad slots...')
        slots = [
            ('Top Banner', 'top-banner', 'all'),
            ('Hero Banner', 'hero-banner', 'all'),
            ('Sidebar', 'sidebar', 'desktop'),
            ('In-Feed', 'in-feed', 'all'),
            ('In-Article', 'in-article', 'all'),
            ('Below Article', 'below-article', 'all'),
            ('Article Sidebar', 'article-sidebar', 'desktop'),
            ('Footer', 'footer', 'all'),
        ]
        created = 0
        for name, slot_name, device in slots:
            _, was_created = AdSlot.objects.get_or_create(
                slot_name=slot_name,
                defaults={
                    'name': name,
                    'device_targeting': device,
                    'is_active': False,  # inactive until ad content is added
                    'html_content': '',
                },
            )
            if was_created:
                created += 1
        self.stdout.write(f'    Ad slots: {created} created, {len(slots) - created} already existed.')

    # ------------------------------------------------------------------
    # 6. Policy pages
    # ------------------------------------------------------------------
    def _seed_policy_pages(self):
        from apps.system.models import PolicyPage

        self.stdout.write('\n  Seeding policy pages...')
        pages = [
            {
                'title': 'Privacy Policy',
                'slug': 'privacy',
                'content': (
                    '<h2>Privacy Policy</h2>'
                    '<p>This privacy policy outlines how The Northern Times collects, uses, '
                    'and protects your personal information when you visit our website.</p>'
                    '<h3>Information We Collect</h3>'
                    '<p>We may collect non-personal identification information about visitors, '
                    'including browser name, device type, and technical information about '
                    'your means of connection to our site.</p>'
                    '<h3>Cookies</h3>'
                    '<p>Our site may use cookies to enhance the user experience. You may '
                    'choose to set your web browser to refuse cookies.</p>'
                    '<h3>Contact</h3>'
                    '<p>If you have questions about this privacy policy, please contact us.</p>'
                ),
                'sort_order': 1,
            },
            {
                'title': 'Terms of Service',
                'slug': 'terms',
                'content': (
                    '<h2>Terms of Service</h2>'
                    '<p>By accessing this website, you agree to be bound by these terms of '
                    'service. If you do not agree with any part of these terms, please do '
                    'not use our website.</p>'
                    '<h3>Content</h3>'
                    '<p>All content published on The Northern Times is protected by copyright. '
                    'Reproduction without permission is prohibited.</p>'
                    '<h3>User Conduct</h3>'
                    '<p>Users must not post defamatory, abusive, or illegal content in '
                    'comments or any interactive features.</p>'
                ),
                'sort_order': 2,
            },
            {
                'title': 'Editorial Standards',
                'slug': 'editorial-standards',
                'content': (
                    '<h2>Editorial Standards</h2>'
                    '<p>The Northern Times is committed to the highest standards of '
                    'journalistic integrity. Our editorial guidelines ensure accuracy, '
                    'fairness, and accountability in all our reporting.</p>'
                    '<h3>Accuracy</h3>'
                    '<p>We verify all facts before publication and promptly correct errors.</p>'
                    '<h3>Independence</h3>'
                    '<p>Our editorial decisions are independent of commercial or political influence.</p>'
                    '<h3>Fairness</h3>'
                    '<p>We present all sides of a story and give subjects the right of reply.</p>'
                ),
                'sort_order': 3,
            },
            {
                'title': 'About Us',
                'slug': 'about',
                'content': (
                    '<h2>About The Northern Times</h2>'
                    '<p>The Northern Times is an independent digital news platform dedicated '
                    'to providing timely, accurate, and in-depth reporting from Northern '
                    'Uganda and across East Africa.</p>'
                    '<h3>Our Mission</h3>'
                    '<p>We believe in the power of independent journalism to inform, educate, '
                    'and empower communities. Our mission is to amplify voices from Northern '
                    'Uganda and deliver news that matters to our readers.</p>'
                ),
                'sort_order': 3,
            },
        ]
        created = 0
        for page in pages:
            _, was_created = PolicyPage.objects.get_or_create(
                slug=page['slug'],
                defaults={
                    'title': page['title'],
                    'content': page['content'],
                    'is_published': True,
                    'show_in_footer': True,
                    'sort_order': page['sort_order'],
                },
            )
            if was_created:
                created += 1
        self.stdout.write(f'    Policy pages: {created} created, {len(pages) - created} already existed.')

    # ------------------------------------------------------------------
    # 7. Login quotes
    # ------------------------------------------------------------------
    def _seed_login_quotes(self):
        from apps.system.models import LoginQuote

        self.stdout.write('\n  Seeding login quotes...')
        quotes = [
            ('A free press can, of course, be good or bad, but, most certainly without freedom, the press will never be anything but bad.', 'Albert Camus'),
            ('The media is the most powerful entity on earth.', 'Malcolm X'),
            ('Journalism is printing what someone else does not want printed; everything else is public relations.', 'George Orwell'),
            ('News is what somebody somewhere wants to suppress; all the rest is advertising.', 'Lord Northcliffe'),
            ('The duty of a journalist is to write the truth.', 'Horst Faas'),
        ]
        created = 0
        for quote, author in quotes:
            _, was_created = LoginQuote.objects.get_or_create(
                quote=quote,
                defaults={'author': author, 'is_active': True},
            )
            if was_created:
                created += 1
        self.stdout.write(f'    Login quotes: {created} created, {len(quotes) - created} already existed.')

    # ------------------------------------------------------------------
    # 8. Reset stale crawl state (force re-crawl on empty DB)
    # ------------------------------------------------------------------
    def _reset_stale_crawl_state(self):
        from apps.articles.models import Article
        from apps.crawler.models import CrawlSource

        article_count = Article.objects.count()
        if article_count == 0:
            updated = CrawlSource.objects.filter(is_active=True).update(
                last_crawled_at=None,
                consecutive_failures=0,
                next_crawl_at=None,
            )
            self.stdout.write(f'\n    No articles found — reset crawl state on {updated} source(s) for immediate re-crawl.')
        else:
            self.stdout.write(f'\n    Articles in DB: {article_count}')
