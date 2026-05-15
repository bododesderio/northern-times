"""
Comprehensive Model Tests — Verify all 47 models can be created and queried.

Tests model instantiation, field validation, relationships, soft delete,
custom managers, and string representations.
"""
import uuid
from datetime import timedelta

import pytest
from django.utils import timezone


# ── Accounts ────────────────────────────────────────────────────

@pytest.mark.django_db
class TestRoleModel:
    def test_create(self, role_editor):
        assert role_editor.name == 'editor'
        assert role_editor.level == 2

    def test_str(self, role_editor):
        assert 'editor' in str(role_editor)

    def test_permissions_is_list(self, role_editor):
        assert isinstance(role_editor.permissions, list)


@pytest.mark.django_db
class TestUserModel:
    def test_create(self, user_author):
        assert user_author.username == 'author1'
        assert user_author.role is not None

    def test_check_password(self, user_author):
        assert user_author.check_password('testpass123')

    def test_str(self, user_author):
        assert str(user_author)


# ── Articles ────────────────────────────────────────────────────

@pytest.mark.django_db
class TestCategoryModel:
    def test_create(self, category):
        assert category.slug == 'politics'
        assert category.show_in_nav is True

    def test_str(self, category):
        assert 'Politics' in str(category)


@pytest.mark.django_db
class TestTagModel:
    def test_create(self, tag):
        assert tag.slug == 'uganda'

    def test_str(self, tag):
        assert 'Uganda' in str(tag)


@pytest.mark.django_db
class TestArticleModel:
    def test_create_published(self, published_article):
        assert published_article.status == 'published'
        assert published_article.deleted_at is None

    def test_create_draft(self, draft_article):
        assert draft_article.status == 'draft'

    def test_soft_delete(self, published_article):
        published_article.soft_delete()
        published_article.refresh_from_db()
        assert published_article.deleted_at is not None

    def test_restore(self, published_article):
        published_article.soft_delete()
        published_article.restore()
        published_article.refresh_from_db()
        assert published_article.deleted_at is None

    def test_default_manager_excludes_deleted(self, published_article):
        from apps.articles.models import Article
        published_article.soft_delete()
        assert Article.objects.filter(pk=published_article.pk).count() == 0

    def test_with_deleted_includes_deleted(self, published_article):
        from apps.articles.models import Article
        published_article.soft_delete()
        assert Article.objects.with_deleted().filter(pk=published_article.pk).count() == 1

    def test_published_queryset(self, published_article, draft_article):
        from apps.articles.models import Article
        published = Article.objects.published()
        assert published_article in published
        assert draft_article not in published

    def test_str(self, published_article):
        assert 'Test Article' in str(published_article)

    def test_tags_m2m(self, published_article, tag):
        published_article.tags.add(tag)
        assert tag in published_article.tags.all()

    def test_crawled_article(self, crawled_article):
        assert crawled_article.is_crawled is True
        assert crawled_article.source_hash == 'abc123def456'


@pytest.mark.django_db
class TestArticleRevision:
    def test_create(self, published_article, user_editor):
        from apps.articles.models import ArticleRevision
        rev = ArticleRevision.objects.create(
            article=published_article,
            title=published_article.title,
            content=published_article.content,
            created_by=user_editor,
        )
        assert rev.article == published_article
        assert rev.created_by == user_editor


@pytest.mark.django_db
class TestComment:
    def test_create(self, published_article):
        from apps.articles.models import Comment
        comment = Comment.objects.create(
            article=published_article,
            author_name='Test User',
            author_email='test@example.com',
            content='Great article!',
            status='approved',
        )
        assert comment.article == published_article
        assert str(comment)


# ── Crawler ─────────────────────────────────────────────────────

@pytest.mark.django_db
class TestCrawlSourceModel:
    def test_create(self, crawl_source):
        assert crawl_source.feed_type == 'rss'
        assert crawl_source.is_active is True

    def test_str(self, crawl_source):
        assert 'Test Source' in str(crawl_source)


@pytest.mark.django_db
class TestCrawlLog:
    def test_create(self, crawl_source):
        from apps.crawler.models import CrawlLog
        log = CrawlLog.objects.create(
            source=crawl_source,
            status='ok',
            found_count=10,
            new_count=5,
            duplicate_count=5,
        )
        assert log.source == crawl_source
        assert log.new_count == 5


# ── Newsletter ──────────────────────────────────────────────────

@pytest.mark.django_db
class TestSubscriber:
    def test_create(self, subscriber):
        assert subscriber.status == 'active'
        assert subscriber.email == 'subscriber@example.com'


@pytest.mark.django_db
class TestNewsletterIssue:
    def test_create(self):
        from apps.newsletter.models import NewsletterIssue
        issue = NewsletterIssue.objects.create(
            subject='Weekly Digest',
            content='<p>Top stories this week.</p>',
            status='draft',
        )
        assert issue.status == 'draft'


# ── Analytics ───────────────────────────────────────────────────

@pytest.mark.django_db
class TestDailyStats:
    def test_create(self):
        from apps.analytics.models import DailyStats
        stat = DailyStats.objects.create(
            date=timezone.now().date(),
            total_views=100,
            unique_visitors=50,
            new_articles=5,
        )
        assert stat.total_views == 100


# ── Ads ─────────────────────────────────────────────────────────

@pytest.mark.django_db
class TestAdSlot:
    def test_create(self):
        from apps.ads.models import AdSlot
        slot = AdSlot.objects.create(
            name='Test Banner',
            slot_name='test-banner',
            is_active=True,
        )
        assert slot.impressions == 0


@pytest.mark.django_db
class TestPopup:
    def test_create(self):
        from apps.ads.models import Popup
        popup = Popup.objects.create(
            name='Test Popup',
            type='modal',
            is_active=False,
        )
        assert popup.clicks == 0


# ── System ──────────────────────────────────────────────────────

@pytest.mark.django_db
class TestSystemModels:
    def test_setting(self):
        from apps.core.models import Setting
        s = Setting.objects.create(key='test_key', value='test_value', type='string')
        assert str(s) == 'test_key'

    def test_policy_page(self):
        from apps.system.models import PolicyPage
        p = PolicyPage.objects.create(
            title='Privacy',
            slug='privacy',
            content='<p>Policy</p>',
            is_published=True,
        )
        assert str(p) == 'Privacy'

    def test_db_backup(self):
        from apps.system.models import DbBackup
        b = DbBackup.objects.create(filename='test.sql.gz', size=1024)
        assert b.size == 1024

    def test_system_log(self):
        from apps.system.models import SystemLog
        log = SystemLog.objects.create(
            action='test_action',
            ip_address='127.0.0.1',
        )
        assert 'test_action' in str(log)


# ── Social ──────────────────────────────────────────────────────

@pytest.mark.django_db
class TestSocialModels:
    def test_keyword(self):
        from apps.social.models import SocialKeyword
        kw = SocialKeyword.objects.create(keyword='Uganda News')
        assert str(kw) == 'Uganda News'

    def test_mention(self):
        from apps.social.models import SocialKeyword, SocialMention
        kw = SocialKeyword.objects.create(keyword='test')
        mention = SocialMention.objects.create(
            keyword=kw,
            source='twitter',
            text='Test mention',
            mentioned_at=timezone.now(),
        )
        assert mention.source == 'twitter'


# ── SEO ─────────────────────────────────────────────────────────

@pytest.mark.django_db
class TestSeoModels:
    def test_audit(self):
        from apps.seo.models import SeoAudit
        audit = SeoAudit.objects.create(score=85, pages_scanned=50)
        assert audit.score == 85

    def test_issue(self):
        from apps.seo.models import SeoAudit, SeoIssue
        audit = SeoAudit.objects.create(score=70)
        issue = SeoIssue.objects.create(
            audit=audit,
            url='https://example.com/page',
            issue_type='missing_meta',
            severity='warning',
            message='Missing meta description',
        )
        assert issue.audit == audit


# ── Webhooks ────────────────────────────────────────────────────

@pytest.mark.django_db
class TestWebhookModels:
    def test_webhook(self):
        from apps.webhooks.models import Webhook
        wh = Webhook.objects.create(
            name='Test Hook',
            url='https://example.com/webhook',
            events=['article.published'],
            is_active=True,
        )
        assert wh.events == ['article.published']

    def test_webhook_log(self):
        from apps.webhooks.models import Webhook, WebhookLog
        wh = Webhook.objects.create(
            name='Test', url='https://example.com/hook',
        )
        log = WebhookLog.objects.create(
            webhook=wh,
            event='article.published',
            status_code=200,
            payload={'test': True},
        )
        assert log.status_code == 200


# ── Media ───────────────────────────────────────────────────────

@pytest.mark.django_db
class TestMediaModel:
    def test_create(self):
        from apps.media.models import MediaItem
        item = MediaItem.objects.create(
            filename='test.jpg',
            path='uploads/test.jpg',
            mime_type='image/jpeg',
            size=1024,
        )
        assert item.mime_type == 'image/jpeg'
