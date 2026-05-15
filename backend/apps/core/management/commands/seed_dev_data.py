"""
Development data seeder — creates realistic sample data for testing.

Seeds: test users, sample articles, tags, comments, subscribers,
popups, social keywords, and sample analytics.  Idempotent.

Usage:
    python manage.py seed_dev_data
    python manage.py seed_dev_data --articles=20
"""
import random
from datetime import timedelta

from django.core.management.base import BaseCommand
from django.utils import timezone
from django.utils.text import slugify


class Command(BaseCommand):
    help = 'Seed development/test data for Northern Times.'

    def add_arguments(self, parser):
        parser.add_argument(
            '--articles', type=int, default=12,
            help='Number of sample articles to create (default: 12)',
        )

    def handle(self, *args, **options):
        self.stdout.write(self.style.MIGRATE_HEADING('Seeding development data...'))

        self._seed_test_users()
        self._seed_tags()
        self._seed_articles(options['articles'])
        self._seed_comments()
        self._seed_subscribers()
        self._seed_popups()
        self._seed_social_keywords()
        self._seed_sample_analytics()

        self.stdout.write(self.style.SUCCESS('\nDev data seeding complete.'))

    def _seed_test_users(self):
        from apps.accounts.models import Role, User

        self.stdout.write('\n  Seeding test users...')
        editor_role = Role.objects.filter(name='editor').first()
        author_role = Role.objects.filter(name='author').first()

        users = [
            {'username': 'editor1', 'email': 'editor1@northerntimes.local', 'first_name': 'Sarah', 'last_name': 'Achieng', 'role': editor_role},
            {'username': 'editor2', 'email': 'editor2@northerntimes.local', 'first_name': 'James', 'last_name': 'Okello', 'role': editor_role},
            {'username': 'reporter1', 'email': 'reporter1@northerntimes.local', 'first_name': 'Grace', 'last_name': 'Lakica', 'role': author_role},
            {'username': 'reporter2', 'email': 'reporter2@northerntimes.local', 'first_name': 'David', 'last_name': 'Otim', 'role': author_role},
            {'username': 'reporter3', 'email': 'reporter3@northerntimes.local', 'first_name': 'Agnes', 'last_name': 'Aciro', 'role': author_role},
        ]
        created = 0
        for u in users:
            if not User.objects.filter(username=u['username']).exists():
                user = User.objects.create_user(
                    username=u['username'],
                    email=u['email'],
                    password='testpass123',
                    first_name=u['first_name'],
                    last_name=u['last_name'],
                )
                if u['role']:
                    user.role = u['role']
                    user.save(update_fields=['role'])
                created += 1
        self.stdout.write(f'    Users: {created} created, {len(users) - created} already existed.')

    def _seed_tags(self):
        from apps.articles.models import Tag

        self.stdout.write('\n  Seeding tags...')
        tag_names = [
            'Uganda', 'Gulu', 'Lira', 'Kampala', 'Elections', 'Parliament',
            'Agriculture', 'Education', 'Health', 'Infrastructure',
            'Climate Change', 'Security', 'Economy', 'East Africa',
            'South Sudan', 'DRC', 'Football', 'Music', 'Technology', 'Tourism',
        ]
        created = 0
        for name in tag_names:
            _, was_created = Tag.objects.get_or_create(
                slug=slugify(name),
                defaults={'name': name},
            )
            if was_created:
                created += 1
        self.stdout.write(f'    Tags: {created} created, {len(tag_names) - created} already existed.')

    def _seed_articles(self, count):
        from apps.accounts.models import User
        from apps.articles.models import Article, Category, Tag

        self.stdout.write(f'\n  Seeding {count} sample articles...')

        if Article.objects.filter(source_name='dev-seeder').exists():
            existing = Article.objects.filter(source_name='dev-seeder').count()
            self.stdout.write(f'    {existing} dev articles already exist — skipping.')
            return

        categories = list(Category.objects.all())
        tags = list(Tag.objects.all())
        authors = list(User.objects.filter(role__isnull=False))
        if not categories:
            self.stdout.write('    No categories found — run seed_all first.')
            return

        now = timezone.now()

        headlines = [
            ('Gulu University Launches New Science Faculty Building', 'northern-uganda'),
            ('Uganda Parliament Passes Landmark Climate Bill', 'politics'),
            ('Northern Uganda Farmers Report Record Harvest Season', 'northern-uganda'),
            ('Kampala-Gulu Highway Expansion Reaches 80% Completion', 'national'),
            ('East African Community Summit Opens in Nairobi', 'world'),
            ('Uganda Cranes Qualify for Africa Cup of Nations', 'sports'),
            ('New Hospital Opens in Lira District', 'health'),
            ('Tech Startups in Northern Uganda Attract Investor Interest', 'technology'),
            ('Oil Production Set to Begin in Albertine Graben Region', 'business'),
            ('Acholi Cultural Festival Draws Thousands to Gulu', 'entertainment'),
            ('Climate Change Threatens Lango Sub-Region Crop Yields', 'environment'),
            ('Uganda Shilling Strengthens Against Dollar', 'business'),
            ('New Education Policy to Boost Rural School Enrollment', 'education'),
            ('Security Forces Conduct Joint Operations in Karamoja', 'crime-security'),
            ('Opinion: The Future of Journalism in Northern Uganda', 'opinion'),
            ('South Sudan Peace Talks Enter Critical Phase', 'world'),
            ('Organic Farming Movement Grows in Acholi Region', 'lifestyle'),
            ('Northern Uganda Entrepreneurship Summit Announced', 'business'),
            ('Malaria Prevention Campaign Reaches 2 Million Children', 'health'),
            ('Traditional Dance Competition Held in Kitgum', 'entertainment'),
        ]

        statuses = ['published'] * 7 + ['draft'] * 2 + ['pending_review'] * 2 + ['scheduled'] * 1
        rewrite_statuses = [None] * 5 + ['queued'] * 2 + ['completed'] * 3 + ['failed'] * 1 + ['approved'] * 1

        created = 0
        for i in range(min(count, len(headlines))):
            title, cat_slug = headlines[i]
            category = next((c for c in categories if c.slug == cat_slug), random.choice(categories))
            status = statuses[i % len(statuses)]
            rewrite_status = rewrite_statuses[i % len(rewrite_statuses)]

            days_ago = random.randint(0, 14)
            pub_date = now - timedelta(days=days_ago, hours=random.randint(0, 12))
            if status == 'scheduled':
                pub_date = now + timedelta(days=random.randint(1, 5))

            slug = slugify(title)[:300]
            if Article.objects.filter(slug=slug).exists():
                slug = f'{slug}-dev'

            content = self._generate_article_body(title)
            excerpt = f'This is a sample article about {title.lower()}. Generated for development testing.'

            article = Article.objects.create(
                title=title,
                slug=slug,
                excerpt=excerpt,
                content=content,
                status=status,
                published_at=pub_date,
                category=category,
                author=random.choice(authors) if authors else None,
                display_author=random.choice(authors).get_full_name() if authors else 'Staff Reporter',
                source_name='dev-seeder',
                source_url=f'https://example.com/article/{slug}',
                source_hash=f'dev-{slug}'[:64],
                is_crawled=False,
                quality_score=random.uniform(40, 95),
                sentiment=random.choice(['positive', 'negative', 'neutral']),
                sentiment_score=random.uniform(0.3, 0.95),
                rewrite_status=rewrite_status,
            )

            # Add 2-4 random tags
            article_tags = random.sample(tags, min(random.randint(2, 4), len(tags)))
            for tag in article_tags:
                article.tags.add(tag)

            created += 1

        self.stdout.write(f'    Articles: {created} created.')

    def _generate_article_body(self, title):
        paragraphs = [
            f'<p>In a significant development for the region, {title.lower()}. '
            f'Officials confirmed the news during a press briefing held earlier today, '
            f'marking a milestone that has been years in the making.</p>',

            '<p>Local leaders and community members have expressed optimism about the '
            'impact this development will have on the lives of ordinary citizens. '
            '"This is a transformative moment for our people," said one district official '
            'who spoke on condition of anonymity.</p>',

            '<p>The initiative comes amid growing calls for greater investment in '
            'infrastructure, education, and healthcare across Northern Uganda. '
            'International development partners have also pledged their support for '
            'similar projects in the region.</p>',

            '<h2>Background</h2>'
            '<p>The project was first proposed three years ago and has undergone '
            'extensive planning and consultation with local stakeholders. Environmental '
            'impact assessments were completed last year, and construction began in '
            'early 2025.</p>',

            '<p>Experts say the project could serve as a model for similar initiatives '
            'across East Africa. "What we are seeing here is a blueprint for '
            'sustainable development," noted a senior researcher at Makerere University.</p>',

            '<h2>What This Means</h2>'
            '<p>For residents of the affected areas, the development promises improved '
            'access to essential services and economic opportunities. Young people, in '
            'particular, stand to benefit from the new employment prospects that will '
            'be created.</p>',

            '<p>The government has committed to providing ongoing support and monitoring '
            'to ensure the project delivers on its promises. A review is scheduled for '
            'the end of the fiscal year.</p>',
        ]
        return '\n'.join(paragraphs)

    def _seed_comments(self):
        from apps.articles.models import Article, Comment

        self.stdout.write('\n  Seeding comments...')
        articles = Article.objects.filter(source_name='dev-seeder', status='published')[:5]
        if Comment.objects.filter(author_name='Dev Commenter').exists():
            self.stdout.write('    Comments already exist — skipping.')
            return

        comments_data = [
            ('John Opio', 'Great reporting on this story. Keep up the good work!', 'approved'),
            ('Mary Atieno', 'I hope the government follows through on these promises.', 'approved'),
            ('Peter Okot', 'This is exactly what Northern Uganda needs right now.', 'approved'),
            ('Susan Achola', 'Can you provide more details on the timeline?', 'approved'),
            ('Anonymous', 'This is fake news and propaganda!', 'pending'),
        ]

        created = 0
        for article in articles:
            comment_data = random.choice(comments_data)
            Comment.objects.create(
                article=article,
                author_name=comment_data[0],
                author_email=f'{slugify(comment_data[0])}@example.com',
                content=comment_data[1],
                status=comment_data[2],
            )
            created += 1
        self.stdout.write(f'    Comments: {created} created.')

    def _seed_subscribers(self):
        from apps.newsletter.models import Subscriber

        self.stdout.write('\n  Seeding subscribers...')
        subs = [
            ('reader1@example.com', 'active'),
            ('reader2@example.com', 'active'),
            ('reader3@example.com', 'active'),
            ('reader4@example.com', 'active'),
            ('unsubbed@example.com', 'unsubscribed'),
        ]
        created = 0
        for email, status in subs:
            _, was_created = Subscriber.objects.get_or_create(
                email=email,
                defaults={'status': status},
            )
            if was_created:
                created += 1
        self.stdout.write(f'    Subscribers: {created} created, {len(subs) - created} already existed.')

    def _seed_popups(self):
        from apps.ads.models import Popup

        self.stdout.write('\n  Seeding sample popups...')
        popups = [
            {
                'name': 'Welcome Popup',
                'type': 'modal',
                'title': 'Welcome to The Northern Times',
                'body': 'Stay informed with independent journalism from Northern Uganda and East Africa.',
                'button_text': 'Start Reading',
                'button_url': '/',
                'frequency': 'once_ever',
                'trigger_type': 'page_load',
                'show_delay': 3,
                'is_active': False,
            },
            {
                'name': 'Newsletter Signup',
                'type': 'modal',
                'title': 'Get the Latest News',
                'body': 'Subscribe to our newsletter for weekly updates delivered to your inbox.',
                'button_text': 'Subscribe',
                'has_email_field': True,
                'frequency': 'once_session',
                'trigger_type': 'scroll',
                'trigger_value': '50',
                'show_delay': 0,
                'is_active': False,
            },
        ]
        created = 0
        for p in popups:
            _, was_created = Popup.objects.get_or_create(
                name=p['name'],
                defaults=p,
            )
            if was_created:
                created += 1
        self.stdout.write(f'    Popups: {created} created, {len(popups) - created} already existed.')

    def _seed_social_keywords(self):
        from apps.social.models import SocialKeyword

        self.stdout.write('\n  Seeding social keywords...')
        keywords = ['Northern Times', 'Uganda News', 'Northern Uganda']
        created = 0
        for kw in keywords:
            _, was_created = SocialKeyword.objects.get_or_create(
                keyword=kw,
                defaults={'is_active': True},
            )
            if was_created:
                created += 1
        self.stdout.write(f'    Social keywords: {created} created, {len(keywords) - created} already existed.')

    def _seed_sample_analytics(self):
        from apps.analytics.models import DailyStats

        self.stdout.write('\n  Seeding sample analytics...')
        if DailyStats.objects.exists():
            self.stdout.write('    Analytics data already exists — skipping.')
            return

        today = timezone.now().date()
        created = 0
        for i in range(30):
            day = today - timedelta(days=i)
            base_views = random.randint(50, 500)
            DailyStats.objects.get_or_create(
                date=day,
                defaults={
                    'total_views': base_views,
                    'unique_visitors': int(base_views * random.uniform(0.4, 0.7)),
                    'new_articles': random.randint(0, 8),
                    'new_comments': random.randint(0, 5),
                    'new_subscribers': random.randint(0, 3),
                },
            )
            created += 1
        self.stdout.write(f'    Daily stats: {created} days seeded (last 30 days).')
