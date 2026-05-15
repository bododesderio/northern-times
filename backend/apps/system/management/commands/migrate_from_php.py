"""
Management command: python manage.py migrate_from_php

Migrates data from the existing PHP schema tables to Django-managed tables.
Both schemas coexist in the same PostgreSQL database — PHP tables use different
names than Django tables.

PHP tables → Django tables:
  users → accounts_user
  roles → accounts_role
  categories → articles_category
  articles → articles_article
  tags → articles_tag
  article_tags → articles_articletag
  comments → articles_comment
  article_revisions → articles_articlerevision
  story_clusters → articles_storycluster (if exists)
  article_entities → articles_articleentity
  topic_follows → articles_topicfollow
  crawl_sources → crawler_crawlsource
  crawl_logs → crawler_crawllog
  media_library → media_mediaitem
  newsletter_subscribers → newsletter_subscriber
  newsletter_issues → newsletter_newsletterissue
  email_queue → newsletter_emailqueue
  site_visitors → analytics_sitevisitor
  article_views → analytics_articleview
  daily_stats → analytics_dailystats
  ad_slots → ads_adslot
  popups → ads_popup
  seo_audits → seo_seoaudit
  seo_issues → seo_seoissue
  social_keywords → social_socialkeyword
  social_mentions → social_socialmention
  webhooks → webhooks_webhook
  webhook_logs → webhooks_webhooklog
  system_log → system_systemlog
  cron_runs → system_cronrun
  db_backups → system_dbbackup
  policy_pages → system_policypage
  login_quotes → system_loginquote
  notifications → system_notification
  site_settings → core_setting (NOTE: Django Setting model uses db_table='site_settings',
                                so this table is SHARED — skip migration, data is already there)
  contact_messages → system_contactmessage
  password_resets → system_passwordreset
  image_health_logs → system_imagehealthlog
"""

import logging
import uuid

from django.core.management.base import BaseCommand
from django.db import connection, transaction
from django.utils import timezone

logger = logging.getLogger(__name__)

# Batch size for bulk_create to avoid memory issues on large tables
BATCH_SIZE = 500


def _rows_as_dicts(cursor):
    """Convert cursor results to list of dicts keyed by column name."""
    columns = [col.name for col in cursor.description]
    return [dict(zip(columns, row)) for row in cursor.fetchall()]


def _existing_pks(model):
    """Return a set of existing primary keys for the given model."""
    return set(model.objects.values_list('pk', flat=True))


class Command(BaseCommand):
    help = 'Migrate data from PHP schema tables to Django schema tables'

    def add_arguments(self, parser):
        parser.add_argument(
            '--dry-run', action='store_true',
            help='Show what would be migrated without writing',
        )
        parser.add_argument(
            '--table', type=str,
            help='Migrate only a specific table (e.g. "articles", "users")',
        )
        parser.add_argument(
            '--skip-users', action='store_true',
            help='Skip user and role migration (if already done)',
        )

    def handle(self, *args, **options):
        self.dry_run = options['dry_run']
        specific_table = options.get('table')

        self.stdout.write(self.style.NOTICE('Starting PHP -> Django data migration...'))

        if self.dry_run:
            self.stdout.write(self.style.WARNING('DRY RUN -- no data will be written'))

        # Check which PHP tables exist
        with connection.cursor() as cursor:
            cursor.execute("""
                SELECT table_name FROM information_schema.tables
                WHERE table_schema = 'public'
                ORDER BY table_name
            """)
            existing_tables = {row[0] for row in cursor.fetchall()}

        self.stdout.write(f'Found {len(existing_tables)} tables in database')

        # Migration order respects foreign key dependencies
        migrations = [
            ('roles', self._migrate_roles),
            ('users', self._migrate_users),
            ('categories', self._migrate_categories),
            ('crawl_sources', self._migrate_crawl_sources),
            ('story_clusters', self._migrate_story_clusters),
            ('articles', self._migrate_articles),
            ('tags', self._migrate_tags),
            ('article_tags', self._migrate_article_tags),
            ('article_entities', self._migrate_article_entities),
            ('comments', self._migrate_comments),
            ('article_revisions', self._migrate_article_revisions),
            ('topic_follows', self._migrate_topic_follows),
            ('crawl_logs', self._migrate_crawl_logs),
            ('media_library', self._migrate_media),
            ('newsletter_subscribers', self._migrate_subscribers),
            ('newsletter_issues', self._migrate_newsletter_issues),
            ('email_queue', self._migrate_email_queue),
            ('site_visitors', self._migrate_visitors),
            ('article_views', self._migrate_article_views),
            ('daily_stats', self._migrate_daily_stats),
            ('ad_slots', self._migrate_ad_slots),
            ('popups', self._migrate_popups),
            ('seo_audits', self._migrate_seo_audits),
            ('seo_issues', self._migrate_seo_issues),
            ('social_keywords', self._migrate_social_keywords),
            ('social_mentions', self._migrate_social_mentions),
            ('webhooks', self._migrate_webhooks),
            ('webhook_logs', self._migrate_webhook_logs),
            ('system_log', self._migrate_system_log),
            ('cron_runs', self._migrate_cron_runs),
            ('db_backups', self._migrate_db_backups),
            ('policy_pages', self._migrate_policies),
            ('login_quotes', self._migrate_login_quotes),
            ('notifications', self._migrate_notifications),
            ('contact_messages', self._migrate_contact_messages),
            ('password_resets', self._migrate_password_resets),
            ('image_health_logs', self._migrate_image_health_logs),
        ]

        total_migrated = 0

        for table_name, migrate_fn in migrations:
            if specific_table and specific_table != table_name:
                continue
            if options['skip_users'] and table_name in ('users', 'roles'):
                continue
            if table_name not in existing_tables:
                self.stdout.write(f'  SKIP {table_name} (not found in database)')
                continue

            try:
                count = migrate_fn()
                total_migrated += count
                if count > 0:
                    self.stdout.write(self.style.SUCCESS(f'  OK   {table_name}: {count} rows migrated'))
                else:
                    self.stdout.write(f'  OK   {table_name}: 0 new rows (all already exist)')
            except Exception as e:
                self.stdout.write(self.style.ERROR(f'  FAIL {table_name}: {e}'))
                logger.error(f'Migration failed for {table_name}', exc_info=True)

        self.stdout.write(self.style.SUCCESS(f'\nMigration complete! {total_migrated} total rows migrated.'))

    # ─── Helper ─────────────────────────────────────────────────────

    def _read_php_table(self, sql):
        """Execute raw SQL and return rows as list of dicts."""
        with connection.cursor() as cursor:
            cursor.execute(sql)
            return _rows_as_dicts(cursor)

    def _bulk_insert(self, model, instances):
        """Bulk create model instances, respecting dry-run mode."""
        if not instances:
            return 0
        if self.dry_run:
            return len(instances)
        # Use ignore_conflicts=False so we get an error if something truly
        # conflicts beyond what we pre-filtered. We pre-filter by PK above.
        for i in range(0, len(instances), BATCH_SIZE):
            batch = instances[i:i + BATCH_SIZE]
            model.objects.bulk_create(batch, ignore_conflicts=True)
        return len(instances)

    # ─── Roles ──────────────────────────────────────────────────────

    def _migrate_roles(self):
        from apps.accounts.models import Role

        rows = self._read_php_table("""
            SELECT id, slug, label, permissions, sort_order
            FROM roles
        """)

        existing = _existing_pks(Role)
        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            permissions = r['permissions'] if isinstance(r['permissions'], list) else []
            instances.append(Role(
                id=r['id'],
                name=r['slug'],
                level=r['sort_order'] or 1,
                permissions=permissions,
            ))

        return self._bulk_insert(Role, instances)

    # ─── Users ──────────────────────────────────────────────────────

    def _migrate_users(self):
        from apps.accounts.models import Role, User

        rows = self._read_php_table("""
            SELECT id, username, email, password_hash, role,
                   bio, avatar_url, is_active, created_at, updated_at, last_login
            FROM users
        """)

        existing = _existing_pks(User)

        # Build role slug -> Role PK mapping
        role_map = {}
        for role in Role.objects.all():
            role_map[role.name] = role.pk

        instances = []
        for r in rows:
            if r['id'] in existing:
                continue

            # Convert PHP bcrypt hash to Django format:
            # PHP stores "$2y$..." or "$2b$...", Django expects "bcrypt$$2b$..."
            password = r['password_hash'] or ''
            if password.startswith('$2y$') or password.startswith('$2b$') or password.startswith('$2a$'):
                # Normalize to $2b$ variant which bcrypt lib understands
                normalized = '$2b$' + password[4:]
                password = f'bcrypt${normalized}'

            role_id = role_map.get(r['role'])

            user = User(
                id=r['id'],
                username=r['username'],
                email=r['email'],
                password=password,
                display_name=r['username'],
                bio=r['bio'] or '',
                avatar_url=r['avatar_url'] or '',
                role_id=role_id,
                is_active=r['is_active'] if r['is_active'] is not None else True,
                is_staff=r['role'] in ('super_admin', 'editor'),
                is_superuser=r['role'] == 'super_admin',
                last_login=r['last_login'],
                date_joined=r['created_at'] or timezone.now(),
            )
            instances.append(user)

        return self._bulk_insert(User, instances)

    # ─── Categories ─────────────────────────────────────────────────

    def _migrate_categories(self):
        from apps.articles.models import Category

        rows = self._read_php_table("""
            SELECT id, name, slug, description, sort_order,
                   show_in_nav, show_in_sidebar, created_at
            FROM categories
        """)

        existing = _existing_pks(Category)
        # Also check existing slugs to avoid unique constraint violations
        existing_slugs = set(Category.objects.values_list('slug', flat=True))

        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            slug = r['slug']
            if slug in existing_slugs:
                continue
            instances.append(Category(
                id=r['id'],
                name=r['name'],
                slug=slug,
                description=r['description'] or '',
                sort_order=r['sort_order'] or 0,
                show_in_nav=r['show_in_nav'] if r['show_in_nav'] is not None else True,
                show_in_sidebar=r.get('show_in_sidebar', True) if r.get('show_in_sidebar') is not None else True,
            ))
            existing_slugs.add(slug)

        return self._bulk_insert(Category, instances)

    # ─── Story Clusters ─────────────────────────────────────────────

    def _migrate_story_clusters(self):
        from apps.articles.models import StoryCluster

        rows = self._read_php_table("""
            SELECT id, title, canonical_article_id, created_at
            FROM story_clusters
        """)

        existing = _existing_pks(StoryCluster)
        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            instances.append(StoryCluster(
                id=r['id'],
                title=r['title'] or 'Untitled Cluster',
                # canonical_article FK set later after articles are migrated
                canonical_article_id=None,
            ))

        count = self._bulk_insert(StoryCluster, instances)

        # Second pass: update canonical_article_id references after articles exist
        # (deferred to _migrate_articles or a post-step)
        self._story_cluster_canonical = {
            r['id']: r['canonical_article_id']
            for r in rows if r['canonical_article_id']
        }

        return count

    # ─── Articles ───────────────────────────────────────────────────

    def _migrate_articles(self):
        from apps.articles.models import Article, StoryCluster

        rows = self._read_php_table("""
            SELECT id, title, slug, content, excerpt, featured_image,
                   status, published_at, created_at, updated_at, deleted_at,
                   is_crawled, source_url, source_name, source_hash,
                   author_id, category_id, story_cluster_id,
                   embedding, ai_summary, sentiment, sentiment_score, quality_score,
                   rewrite_status, rewritten_title, rewritten_content, rewritten_excerpt
            FROM articles
        """)

        existing = _existing_pks(Article)
        existing_slugs = set(
            Article.objects.with_deleted().values_list('slug', flat=True)
        )

        # Valid FKs
        from apps.accounts.models import User
        valid_user_ids = set(User.objects.values_list('pk', flat=True))
        from apps.articles.models import Category
        valid_category_ids = set(Category.objects.values_list('pk', flat=True))

        # Map PHP article_status enum to Django choices
        status_map = {
            'draft': 'draft',
            'published': 'published',
            'archived': 'archived',
            'pending_review': 'pending_review',
            'scheduled': 'scheduled',
        }

        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            slug = r['slug']
            if slug in existing_slugs:
                # Append a suffix to avoid unique violation
                slug = f"{slug}-{str(r['id'])[:8]}"
            if slug in existing_slugs:
                continue

            status = status_map.get(r['status'], 'draft')
            author_id = r['author_id'] if r['author_id'] in valid_user_ids else None
            category_id = r['category_id'] if r['category_id'] in valid_category_ids else None

            instances.append(Article(
                id=r['id'],
                title=r['title'] or '',
                slug=slug,
                content=r['content'] or '',
                excerpt=r['excerpt'] or '',
                featured_image=r['featured_image'] or '',
                status=status,
                published_at=r['published_at'],
                deleted_at=r.get('deleted_at'),
                is_crawled=r['is_crawled'] or False,
                source_url=r['source_url'] or '',
                source_name=r['source_name'] or '',
                source_hash=r['source_hash'] or '',
                display_author='',
                author_id=author_id,
                category_id=category_id,
                story_cluster_id=r.get('story_cluster_id'),
                embedding=r.get('embedding'),
                ai_summary=r.get('ai_summary') or '',
                sentiment=r.get('sentiment') or '',
                sentiment_score=r.get('sentiment_score'),
                quality_score=r.get('quality_score'),
                rewrite_status=r.get('rewrite_status'),
                rewritten_title=r.get('rewritten_title') or '',
                rewritten_content=r.get('rewritten_content') or '',
                rewritten_excerpt=r.get('rewritten_excerpt') or '',
            ))
            existing_slugs.add(slug)

        count = self._bulk_insert(Article, instances)

        # Update story cluster canonical article references
        if hasattr(self, '_story_cluster_canonical') and not self.dry_run:
            valid_article_ids = set(
                Article.objects.with_deleted().values_list('pk', flat=True)
            )
            for cluster_id, article_id in self._story_cluster_canonical.items():
                if article_id in valid_article_ids:
                    StoryCluster.objects.filter(pk=cluster_id).update(
                        canonical_article_id=article_id
                    )

        return count

    # ─── Tags ───────────────────────────────────────────────────────

    def _migrate_tags(self):
        from apps.articles.models import Tag

        rows = self._read_php_table("""
            SELECT id, name, slug, type, created_at
            FROM tags
        """)

        existing = _existing_pks(Tag)
        existing_slugs = set(Tag.objects.values_list('slug', flat=True))

        # PHP tags use BIGSERIAL ids; Django Tag uses UUID.
        # We need a mapping for article_tags later.
        self._tag_id_map = {}

        instances = []
        for r in rows:
            slug = r['slug']
            if slug in existing_slugs:
                # Map old id to existing Django tag with same slug
                existing_tag = Tag.objects.filter(slug=slug).first()
                if existing_tag:
                    self._tag_id_map[r['id']] = existing_tag.pk
                continue

            new_id = uuid.uuid4()
            self._tag_id_map[r['id']] = new_id

            instances.append(Tag(
                id=new_id,
                name=r['name'],
                slug=slug,
                type=r['type'] or 'topic',
            ))
            existing_slugs.add(slug)

        return self._bulk_insert(Tag, instances)

    # ─── Article Tags ───────────────────────────────────────────────

    def _migrate_article_tags(self):
        from apps.articles.models import Article, ArticleTag, Tag

        rows = self._read_php_table("""
            SELECT article_id, tag_id
            FROM article_tags
        """)

        valid_article_ids = set(
            Article.objects.with_deleted().values_list('pk', flat=True)
        )
        valid_tag_ids = set(Tag.objects.values_list('pk', flat=True))

        # Existing article-tag pairs to avoid duplicates
        existing_pairs = set(
            ArticleTag.objects.values_list('article_id', 'tag_id')
        )

        tag_id_map = getattr(self, '_tag_id_map', {})

        instances = []
        for r in rows:
            article_id = r['article_id']
            # Map old BIGSERIAL tag id to new UUID
            tag_id = tag_id_map.get(r['tag_id'])

            if article_id not in valid_article_ids:
                continue
            if tag_id is None or tag_id not in valid_tag_ids:
                continue
            if (article_id, tag_id) in existing_pairs:
                continue

            instances.append(ArticleTag(
                id=uuid.uuid4(),
                article_id=article_id,
                tag_id=tag_id,
            ))
            existing_pairs.add((article_id, tag_id))

        return self._bulk_insert(ArticleTag, instances)

    # ─── Article Entities ───────────────────────────────────────────

    def _migrate_article_entities(self):
        from apps.articles.models import Article, ArticleEntity

        rows = self._read_php_table("""
            SELECT id, article_id, entity_text, entity_type, salience
            FROM article_entities
        """)

        valid_article_ids = set(
            Article.objects.with_deleted().values_list('pk', flat=True)
        )
        existing = _existing_pks(ArticleEntity)

        instances = []
        for r in rows:
            if r['article_id'] not in valid_article_ids:
                continue
            # PHP uses BIGSERIAL; Django uses UUID. Generate new UUID.
            new_id = uuid.uuid4()
            instances.append(ArticleEntity(
                id=new_id,
                article_id=r['article_id'],
                entity_text=r['entity_text'] or '',
                entity_type=r['entity_type'] or 'person',
                salience=r['salience'] or 0.0,
            ))

        return self._bulk_insert(ArticleEntity, instances)

    # ─── Comments ───────────────────────────────────────────────────

    def _migrate_comments(self):
        from apps.articles.models import Article, Comment

        rows = self._read_php_table("""
            SELECT id, article_id, parent_id, author_name, author_email,
                   content, status, ip_address, created_at
            FROM comments
        """)

        valid_article_ids = set(
            Article.objects.with_deleted().values_list('pk', flat=True)
        )
        existing = _existing_pks(Comment)

        # Map status values
        status_map = {
            'pending': 'pending',
            'approved': 'approved',
            'rejected': 'rejected',
            'spam': 'rejected',
        }

        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            if r['article_id'] not in valid_article_ids:
                continue

            ip = r['ip_address']
            if ip:
                ip = str(ip).split('/')[0]  # Strip CIDR notation if present

            instances.append(Comment(
                id=r['id'],
                article_id=r['article_id'],
                parent_id=r['parent_id'],
                name=r['author_name'] or 'Anonymous',
                email=r['author_email'] or '',
                content=r['content'] or '',
                status=status_map.get(r['status'], 'pending'),
                ip_address=ip,
            ))

        return self._bulk_insert(Comment, instances)

    # ─── Article Revisions ──────────────────────────────────────────

    def _migrate_article_revisions(self):
        from apps.articles.models import Article, ArticleRevision

        rows = self._read_php_table("""
            SELECT id, article_id, title, content, created_by, created_at
            FROM article_revisions
        """)

        valid_article_ids = set(
            Article.objects.with_deleted().values_list('pk', flat=True)
        )
        existing = _existing_pks(ArticleRevision)

        from apps.accounts.models import User
        valid_user_ids = set(User.objects.values_list('pk', flat=True))

        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            if r['article_id'] not in valid_article_ids:
                continue

            created_by = r['created_by'] if r['created_by'] in valid_user_ids else None

            instances.append(ArticleRevision(
                id=r['id'],
                article_id=r['article_id'],
                title=r['title'] or '',
                content=r['content'] or '',
                created_by_id=created_by,
            ))

        return self._bulk_insert(ArticleRevision, instances)

    # ─── Topic Follows ──────────────────────────────────────────────

    def _migrate_topic_follows(self):
        from apps.articles.models import TopicFollow

        rows = self._read_php_table("""
            SELECT id, email, follow_type, follow_id, created_at
            FROM topic_follows
        """)

        existing = _existing_pks(TopicFollow)
        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            instances.append(TopicFollow(
                id=r['id'],
                email=r['email'],
                follow_type=r['follow_type'] or 'category',
                follow_id=r['follow_id'],
            ))

        return self._bulk_insert(TopicFollow, instances)

    # ─── Crawl Sources ──────────────────────────────────────────────

    def _migrate_crawl_sources(self):
        from apps.articles.models import Category
        from apps.crawler.models import CrawlSource

        rows = self._read_php_table("""
            SELECT id, name, feed_url, source_type, is_active, crawl_interval,
                   last_crawled_at, default_category_id, max_articles,
                   strip_selectors, attribution_text, nofollow,
                   download_images, avg_articles_per_day, consecutive_empty,
                   consecutive_failures, last_new_content_at,
                   content_selector, region, auto_rewrite, editorial_review,
                   created_at, updated_at
            FROM crawl_sources
        """)

        existing = _existing_pks(CrawlSource)
        valid_category_ids = set(Category.objects.values_list('pk', flat=True))

        # Map PHP source_type to Django feed_type
        feed_type_map = {
            'rss': 'rss',
            'atom': 'atom',
            'html': 'web',
            'web': 'web',
        }

        instances = []
        for r in rows:
            if r['id'] in existing:
                continue

            category_id = r['default_category_id']
            if category_id and category_id not in valid_category_ids:
                category_id = None

            instances.append(CrawlSource(
                id=r['id'],
                name=r['name'] or '',
                url=r['feed_url'] or '',
                category_id=category_id,
                feed_type=feed_type_map.get(r['source_type'], 'rss'),
                content_selector=r.get('content_selector') or '',
                strip_selectors=r.get('strip_selectors') or '',
                crawl_interval=r['crawl_interval'] or 60,
                auto_rewrite=r.get('auto_rewrite') or False,
                editorial_review=r.get('editorial_review') or False,
                region=r.get('region') or 'international',
                download_images=r.get('download_images', True) if r.get('download_images') is not None else True,
                nofollow=r['nofollow'] if r['nofollow'] is not None else True,
                attribution_text=r['attribution_text'] or '',
                max_articles=r['max_articles'] or 50,
                avg_articles_per_day=r.get('avg_articles_per_day') or 0.0,
                consecutive_empty=r.get('consecutive_empty') or 0,
                consecutive_failures=r.get('consecutive_failures') or 0,
                last_crawled_at=r['last_crawled_at'],
                last_new_content_at=r.get('last_new_content_at'),
                is_active=r['is_active'] if r['is_active'] is not None else True,
            ))

        return self._bulk_insert(CrawlSource, instances)

    # ─── Crawl Logs ─────────────────────────────────────────────────

    def _migrate_crawl_logs(self):
        from apps.crawler.models import CrawlLog, CrawlSource

        rows = self._read_php_table("""
            SELECT id, source_id, status, articles_found, articles_new,
                   articles_dupes, error_message, details, started_at, finished_at
            FROM crawl_logs
        """)

        valid_source_ids = set(CrawlSource.objects.values_list('pk', flat=True))
        existing = _existing_pks(CrawlLog)

        # Map PHP status to Django
        status_map = {
            'success': 'ok',
            'partial': 'ok',
            'running': 'ok',
            'failed': 'error',
            'ok': 'ok',
            'error': 'error',
        }

        instances = []
        for r in rows:
            # PHP crawl_logs uses BIGSERIAL id; Django uses UUID
            source_id = r['source_id']
            if source_id not in valid_source_ids:
                continue

            new_id = uuid.uuid4()
            instances.append(CrawlLog(
                id=new_id,
                source_id=source_id,
                status=status_map.get(r['status'], 'ok'),
                found_count=r['articles_found'] or 0,
                new_count=r['articles_new'] or 0,
                duplicate_count=r['articles_dupes'] or 0,
                error_count=1 if r['status'] == 'failed' else 0,
                error_message=r['error_message'] or '',
                details=r['details'] if isinstance(r['details'], dict) else {},
                duration_ms=0,  # PHP schema stores started_at/finished_at, not duration
            ))

        return self._bulk_insert(CrawlLog, instances)

    # ─── Media ──────────────────────────────────────────────────────

    def _migrate_media(self):
        from apps.accounts.models import User
        from apps.media.models import MediaItem

        rows = self._read_php_table("""
            SELECT id, file_path, original_name, mime_type, file_size,
                   width, height, sha256, folder, uploaded_by, created_at
            FROM media_library
        """)

        existing = _existing_pks(MediaItem)
        valid_user_ids = set(User.objects.values_list('pk', flat=True))

        instances = []
        for r in rows:
            if r['id'] in existing:
                continue

            uploaded_by = r['uploaded_by'] if r['uploaded_by'] in valid_user_ids else None

            # Generate a filename from path or original_name
            path = r['file_path'] or ''
            original_name = r['original_name'] or 'unknown'
            filename = path.split('/')[-1] if path else original_name

            instances.append(MediaItem(
                id=r['id'],
                filename=filename,
                original_filename=original_name,
                path=path,
                mime_type=r['mime_type'] or 'application/octet-stream',
                size=r['file_size'] or 0,
                width=r['width'],
                height=r['height'],
                hash=r['sha256'] or '',
                alt_text='',
                storage_driver='local',
                folder=r['folder'] or '',
                uploaded_by_id=uploaded_by,
            ))

        return self._bulk_insert(MediaItem, instances)

    # ─── Newsletter Subscribers ─────────────────────────────────────

    def _migrate_subscribers(self):
        from apps.newsletter.models import Subscriber

        rows = self._read_php_table("""
            SELECT id, email, status, source, unsub_token, created_at
            FROM newsletter_subscribers
        """)

        existing = _existing_pks(Subscriber)
        existing_emails = set(Subscriber.objects.values_list('email', flat=True))

        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            if r['email'] in existing_emails:
                continue

            unsub_token = r['unsub_token'] or uuid.uuid4().hex

            instances.append(Subscriber(
                id=r['id'],
                email=r['email'],
                unsub_token=unsub_token,
                source=r['source'] or '',
                status=r['status'] or 'active',
            ))
            existing_emails.add(r['email'])

        return self._bulk_insert(Subscriber, instances)

    # ─── Newsletter Issues ──────────────────────────────────────────

    def _migrate_newsletter_issues(self):
        from apps.newsletter.models import NewsletterIssue

        rows = self._read_php_table("""
            SELECT id, subject, content, status, scheduled_at, sent_at,
                   recipient_count, created_at
            FROM newsletter_issues
        """)

        existing = _existing_pks(NewsletterIssue)
        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            instances.append(NewsletterIssue(
                id=r['id'],
                subject=r['subject'] or '',
                content=r['content'] or '',
                status=r['status'] or 'draft',
                scheduled_at=r.get('scheduled_at'),
                sent_at=r.get('sent_at'),
                recipient_count=r.get('recipient_count') or 0,
            ))

        return self._bulk_insert(NewsletterIssue, instances)

    # ─── Email Queue ────────────────────────────────────────────────

    def _migrate_email_queue(self):
        from apps.newsletter.models import EmailQueue

        rows = self._read_php_table("""
            SELECT id, to_email, subject, body_html, status,
                   attempts, error_message, sent_at, created_at
            FROM email_queue
        """)

        existing = _existing_pks(EmailQueue)
        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            instances.append(EmailQueue(
                id=r['id'],
                to_email=r['to_email'] or '',
                subject=r['subject'] or '',
                body_html=r['body_html'] or '',
                status=r['status'] or 'pending',
                attempts=r['attempts'] or 0,
                error_message=r['error_message'] or '',
                sent_at=r.get('sent_at'),
            ))

        return self._bulk_insert(EmailQueue, instances)

    # ─── Site Visitors ──────────────────────────────────────────────

    def _migrate_visitors(self):
        from apps.analytics.models import SiteVisitor

        rows = self._read_php_table("""
            SELECT id, ip_address, country, city, latitude, longitude,
                   visit_date, device_type, browser, os, created_at
            FROM site_visitors
        """)

        existing = _existing_pks(SiteVisitor)
        instances = []
        for r in rows:
            # PHP uses BIGSERIAL; Django uses UUID
            ip = str(r['ip_address']).split('/')[0] if r['ip_address'] else '0.0.0.0'
            new_id = uuid.uuid4()

            instances.append(SiteVisitor(
                id=new_id,
                ip_address=ip,
                country=r.get('country') or '',
                city=r.get('city') or '',
                latitude=r.get('latitude'),
                longitude=r.get('longitude'),
                visit_date=r['visit_date'],
                device_type=r.get('device_type') or '',
                browser=r.get('browser') or '',
                os=r.get('os') or '',
            ))

        return self._bulk_insert(SiteVisitor, instances)

    # ─── Article Views ──────────────────────────────────────────────

    def _migrate_article_views(self):
        from apps.analytics.models import ArticleView
        from apps.articles.models import Article

        rows = self._read_php_table("""
            SELECT id, article_id, visitor_ip, session_id, viewed_at
            FROM article_views
        """)

        valid_article_ids = set(
            Article.objects.with_deleted().values_list('pk', flat=True)
        )
        existing = _existing_pks(ArticleView)

        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            if r['article_id'] not in valid_article_ids:
                continue

            ip = str(r['visitor_ip']).split('/')[0] if r['visitor_ip'] else '0.0.0.0'
            instances.append(ArticleView(
                id=r['id'],
                article_id=r['article_id'],
                visitor_ip=ip,
                session_id=r.get('session_id') or '',
            ))

        return self._bulk_insert(ArticleView, instances)

    # ─── Daily Stats ────────────────────────────────────────────────

    def _migrate_daily_stats(self):
        from apps.analytics.models import DailyStats

        rows = self._read_php_table("""
            SELECT stat_date, total_views, unique_visitors,
                   articles_published, comments_count, subscribers_count, created_at
            FROM daily_stats
        """)

        existing_dates = set(DailyStats.objects.values_list('date', flat=True))

        instances = []
        for r in rows:
            if r['stat_date'] in existing_dates:
                continue
            instances.append(DailyStats(
                id=uuid.uuid4(),
                date=r['stat_date'],
                total_views=r['total_views'] or 0,
                unique_visitors=r['unique_visitors'] or 0,
                new_articles=r.get('articles_published') or 0,
                new_comments=r.get('comments_count') or 0,
                new_subscribers=r.get('subscribers_count') or 0,
            ))
            existing_dates.add(r['stat_date'])

        return self._bulk_insert(DailyStats, instances)

    # ─── Ad Slots ───────────────────────────────────────────────────

    def _migrate_ad_slots(self):
        from apps.ads.models import AdSlot

        rows = self._read_php_table("""
            SELECT id, name, slot_name, html_content, is_active,
                   device_targeting, impressions, clicks, created_at, updated_at
            FROM ad_slots
        """)

        existing = _existing_pks(AdSlot)
        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            instances.append(AdSlot(
                id=r['id'],
                name=r['name'] or '',
                slot_name=r['slot_name'] or '',
                html_content=r['html_content'] or '',
                is_active=r['is_active'] if r['is_active'] is not None else True,
                device_targeting=r.get('device_targeting') or 'all',
                impressions=r['impressions'] or 0,
                clicks=r['clicks'] or 0,
            ))

        return self._bulk_insert(AdSlot, instances)

    # ─── Popups ─────────────────────────────────────────────────────

    def _migrate_popups(self):
        from apps.ads.models import Popup

        rows = self._read_php_table("""
            SELECT id, name, type, content, is_active, device_targeting,
                   trigger_type, trigger_value, impressions, clicks, variant,
                   created_at, updated_at
            FROM popups
        """)

        existing = _existing_pks(Popup)
        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            instances.append(Popup(
                id=r['id'],
                name=r['name'] or '',
                type=r['type'] or 'modal',
                content=r['content'] or '',
                is_active=r['is_active'] if r['is_active'] is not None else True,
                device_targeting=r.get('device_targeting') or 'all',
                trigger_type=r.get('trigger_type') or '',
                trigger_value=r.get('trigger_value') or '',
                impressions=r['impressions'] or 0,
                clicks=r['clicks'] or 0,
                variant=r.get('variant'),
            ))

        return self._bulk_insert(Popup, instances)

    # ─── SEO Audits ─────────────────────────────────────────────────

    def _migrate_seo_audits(self):
        from apps.seo.models import SeoAudit

        rows = self._read_php_table("""
            SELECT id, score, critical_count, warning_count, info_count,
                   pages_scanned, created_at
            FROM seo_audits
        """)

        existing = _existing_pks(SeoAudit)
        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            instances.append(SeoAudit(
                id=r['id'],
                score=r['score'] or 0,
                critical_count=r['critical_count'] or 0,
                warning_count=r['warning_count'] or 0,
                info_count=r['info_count'] or 0,
                pages_scanned=r['pages_scanned'] or 0,
            ))

        return self._bulk_insert(SeoAudit, instances)

    # ─── SEO Issues ─────────────────────────────────────────────────

    def _migrate_seo_issues(self):
        from apps.seo.models import SeoAudit, SeoIssue

        rows = self._read_php_table("""
            SELECT id, audit_id, url, issue_type, severity, message, created_at
            FROM seo_issues
        """)

        valid_audit_ids = set(SeoAudit.objects.values_list('pk', flat=True))
        existing = _existing_pks(SeoIssue)

        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            if r['audit_id'] not in valid_audit_ids:
                continue
            instances.append(SeoIssue(
                id=r['id'],
                audit_id=r['audit_id'],
                url=r['url'] or '',
                issue_type=r['issue_type'] or '',
                severity=r['severity'] or 'info',
                message=r['message'] or '',
            ))

        return self._bulk_insert(SeoIssue, instances)

    # ─── Social Keywords ────────────────────────────────────────────

    def _migrate_social_keywords(self):
        from apps.social.models import SocialKeyword

        rows = self._read_php_table("""
            SELECT id, keyword, is_active, created_at
            FROM social_keywords
        """)

        existing = _existing_pks(SocialKeyword)
        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            instances.append(SocialKeyword(
                id=r['id'],
                keyword=r['keyword'] or '',
                is_active=r['is_active'] if r['is_active'] is not None else True,
            ))

        return self._bulk_insert(SocialKeyword, instances)

    # ─── Social Mentions ────────────────────────────────────────────

    def _migrate_social_mentions(self):
        from apps.social.models import SocialKeyword, SocialMention

        rows = self._read_php_table("""
            SELECT id, keyword_id, source, title, text, url,
                   sentiment, mentioned_at, created_at
            FROM social_mentions
        """)

        valid_keyword_ids = set(SocialKeyword.objects.values_list('pk', flat=True))
        existing = _existing_pks(SocialMention)

        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            if r['keyword_id'] not in valid_keyword_ids:
                continue
            instances.append(SocialMention(
                id=r['id'],
                keyword_id=r['keyword_id'],
                source=r['source'] or '',
                title=r['title'] or '',
                text=r['text'] or '',
                url=r['url'] or '',
                sentiment=r['sentiment'] or 'neutral',
                mentioned_at=r['mentioned_at'] or timezone.now(),
            ))

        return self._bulk_insert(SocialMention, instances)

    # ─── Webhooks ───────────────────────────────────────────────────

    def _migrate_webhooks(self):
        from apps.webhooks.models import Webhook

        rows = self._read_php_table("""
            SELECT id, url, events, secret, is_active, created_at
            FROM webhooks
        """)

        existing = _existing_pks(Webhook)
        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            # PHP stores events as comma-separated TEXT; Django expects JSON list
            events_raw = r['events'] or ''
            if isinstance(events_raw, list):
                events = events_raw
            else:
                events = [e.strip() for e in events_raw.split(',') if e.strip()]

            instances.append(Webhook(
                id=r['id'],
                url=r['url'] or '',
                events=events,
                secret=r['secret'] or '',
                is_active=r['is_active'] if r['is_active'] is not None else True,
            ))

        return self._bulk_insert(Webhook, instances)

    # ─── Webhook Logs ───────────────────────────────────────────────

    def _migrate_webhook_logs(self):
        from apps.webhooks.models import Webhook, WebhookLog

        rows = self._read_php_table("""
            SELECT id, webhook_id, event, payload, response_code,
                   response_body, created_at
            FROM webhook_logs
        """)

        valid_webhook_ids = set(Webhook.objects.values_list('pk', flat=True))
        existing = _existing_pks(WebhookLog)

        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            if r['webhook_id'] not in valid_webhook_ids:
                continue
            instances.append(WebhookLog(
                id=r['id'],
                webhook_id=r['webhook_id'],
                event=r['event'] or '',
                payload=r['payload'] if isinstance(r['payload'], dict) else {},
                response_code=r['response_code'],
                response_body=r['response_body'] or '',
            ))

        return self._bulk_insert(WebhookLog, instances)

    # ─── System Log ─────────────────────────────────────────────────

    def _migrate_system_log(self):
        from apps.accounts.models import User
        from apps.system.models import SystemLog

        rows = self._read_php_table("""
            SELECT id, user_id, action, details, ip_address, created_at
            FROM system_log
        """)

        valid_user_ids = set(User.objects.values_list('pk', flat=True))
        # PHP uses SERIAL; Django uses UUID
        instances = []
        for r in rows:
            user_id = r['user_id'] if r['user_id'] in valid_user_ids else None
            ip = str(r['ip_address']).split('/')[0] if r['ip_address'] else '127.0.0.1'

            # PHP stores details as TEXT; Django expects JSON
            details = r['details']
            if isinstance(details, str):
                details = {'message': details} if details else None
            elif not isinstance(details, dict):
                details = None

            instances.append(SystemLog(
                id=uuid.uuid4(),
                user_id=user_id,
                action=r['action'] or '',
                details=details,
                ip_address=ip,
            ))

        return self._bulk_insert(SystemLog, instances)

    # ─── Cron Runs ──────────────────────────────────────────────────

    def _migrate_cron_runs(self):
        from apps.system.models import CronRun

        rows = self._read_php_table("""
            SELECT id, task_name, status, duration_ms, records_affected,
                   output, error_msg, started_at, finished_at
            FROM cron_runs
        """)

        # PHP uses SERIAL; Django uses UUID
        instances = []
        for r in rows:
            status_map = {'success': 'ok', 'error': 'error', 'running': 'ok'}
            instances.append(CronRun(
                id=uuid.uuid4(),
                name=r['task_name'] or '',
                status=status_map.get(r['status'], 'ok'),
                items_processed=r['records_affected'] or 0,
                notes=r['output'] or r['error_msg'] or '',
                started_at=r['started_at'],
                finished_at=r['finished_at'],
            ))

        return self._bulk_insert(CronRun, instances)

    # ─── DB Backups ─────────────────────────────────────────────────

    def _migrate_db_backups(self):
        from apps.system.models import DbBackup

        rows = self._read_php_table("""
            SELECT id, filename, file_size, created_at
            FROM db_backups
        """)

        # PHP uses SERIAL; Django uses UUID
        instances = []
        for r in rows:
            instances.append(DbBackup(
                id=uuid.uuid4(),
                filename=r['filename'] or '',
                size=r['file_size'] or 0,
            ))

        return self._bulk_insert(DbBackup, instances)

    # ─── Policy Pages ───────────────────────────────────────────────

    def _migrate_policies(self):
        from apps.system.models import PolicyPage

        rows = self._read_php_table("""
            SELECT id, title, slug, content, is_published, show_in_footer,
                   sort_order, created_at, updated_at
            FROM policy_pages
        """)

        existing = _existing_pks(PolicyPage)
        existing_slugs = set(PolicyPage.objects.values_list('slug', flat=True))

        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            slug = r['slug']
            if slug in existing_slugs:
                continue
            instances.append(PolicyPage(
                id=r['id'],
                title=r['title'] or '',
                slug=slug,
                content=r['content'] or '',
                is_published=r['is_published'] if r['is_published'] is not None else False,
                show_in_footer=r['show_in_footer'] if r['show_in_footer'] is not None else False,
                sort_order=r['sort_order'] or 0,
            ))
            existing_slugs.add(slug)

        return self._bulk_insert(PolicyPage, instances)

    # ─── Login Quotes ───────────────────────────────────────────────

    def _migrate_login_quotes(self):
        from apps.system.models import LoginQuote

        rows = self._read_php_table("""
            SELECT id, quote, author, is_active, created_at
            FROM login_quotes
        """)

        existing = _existing_pks(LoginQuote)
        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            instances.append(LoginQuote(
                id=r['id'],
                quote=r['quote'] or '',
                author=r['author'] or '',
                is_active=r['is_active'] if r['is_active'] is not None else True,
            ))

        return self._bulk_insert(LoginQuote, instances)

    # ─── Notifications ──────────────────────────────────────────────

    def _migrate_notifications(self):
        from apps.accounts.models import User
        from apps.system.models import Notification

        rows = self._read_php_table("""
            SELECT id, user_id, title, message, is_read, created_at
            FROM notifications
        """)

        valid_user_ids = set(User.objects.values_list('pk', flat=True))
        existing = _existing_pks(Notification)

        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            if r['user_id'] not in valid_user_ids:
                continue
            instances.append(Notification(
                id=r['id'],
                user_id=r['user_id'],
                title=r['title'] or '',
                message=r['message'] or '',
                is_read=r['is_read'] if r['is_read'] is not None else False,
            ))

        return self._bulk_insert(Notification, instances)

    # ─── Contact Messages ───────────────────────────────────────────

    def _migrate_contact_messages(self):
        from apps.system.models import ContactMessage

        rows = self._read_php_table("""
            SELECT id, name, email, subject, message, ip_address, created_at
            FROM contact_messages
        """)

        existing = _existing_pks(ContactMessage)
        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            ip = str(r['ip_address']).split('/')[0] if r['ip_address'] else '127.0.0.1'
            instances.append(ContactMessage(
                id=r['id'],
                name=r['name'] or '',
                email=r['email'] or '',
                subject=r['subject'] or '',
                message=r['message'] or '',
                ip_address=ip,
            ))

        return self._bulk_insert(ContactMessage, instances)

    # ─── Password Resets ────────────────────────────────────────────

    def _migrate_password_resets(self):
        from apps.accounts.models import User
        from apps.system.models import PasswordReset

        rows = self._read_php_table("""
            SELECT id, email, token, expires_at, created_at
            FROM password_resets
        """)

        # PHP links by email; Django links by user FK
        email_to_user = {}
        for u in User.objects.all().only('id', 'email'):
            email_to_user[u.email] = u.pk

        existing = _existing_pks(PasswordReset)
        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            user_id = email_to_user.get(r['email'])
            if not user_id:
                continue
            instances.append(PasswordReset(
                id=r['id'],
                user_id=user_id,
                token=r['token'] or uuid.uuid4().hex,
                expires_at=r['expires_at'] or timezone.now(),
                used_at=None,
            ))

        return self._bulk_insert(PasswordReset, instances)

    # ─── Image Health Logs ──────────────────────────────────────────

    def _migrate_image_health_logs(self):
        from apps.articles.models import Article
        from apps.system.models import ImageHealthLog

        rows = self._read_php_table("""
            SELECT id, article_id, url, status_code, is_healthy, checked_at
            FROM image_health_logs
        """)

        valid_article_ids = set(
            Article.objects.with_deleted().values_list('pk', flat=True)
        )
        existing = _existing_pks(ImageHealthLog)

        instances = []
        for r in rows:
            if r['id'] in existing:
                continue
            article_id = r['article_id'] if r['article_id'] in valid_article_ids else None

            instances.append(ImageHealthLog(
                id=r['id'],
                article_id=article_id,
                url=r['url'] or '',
                status_code=r['status_code'],
                is_healthy=r['is_healthy'] if r['is_healthy'] is not None else True,
            ))

        return self._bulk_insert(ImageHealthLog, instances)
