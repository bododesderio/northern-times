"""Tests for remaining models: Setting, Subscriber, Newsletter, EmailQueue,
MediaItem, SiteVisitor, DailyStats, AdSlot, Webhook, SystemLog, PolicyPage,
Notification."""
import uuid
from datetime import date

import pytest
from django.db import IntegrityError
from django.utils import timezone


class TestSetting:
    def test_get_returns_default_when_missing(self, db):
        from apps.core.models import Setting
        assert Setting.get('nonexistent', 'fallback') == 'fallback'

    def test_set_and_get_string(self, db):
        from apps.core.models import Setting
        Setting.set('site_name', 'Northern Times')
        assert Setting.get('site_name') == 'Northern Times'

    def test_set_and_get_bool(self, db):
        from apps.core.models import Setting
        Setting.set('maintenance_mode', True, type='bool')
        assert Setting.get('maintenance_mode') is True

    def test_set_and_get_int(self, db):
        from apps.core.models import Setting
        Setting.set('max_items', 42, type='int')
        assert Setting.get('max_items') == 42

    def test_set_and_get_json(self, db):
        from apps.core.models import Setting
        data = {'colors': ['red', 'blue']}
        Setting.set('theme', data, type='json')
        assert Setting.get('theme') == data

    def test_set_overwrites_existing(self, db):
        from apps.core.models import Setting
        Setting.set('key', 'old')
        Setting.set('key', 'new')
        assert Setting.get('key') == 'new'

    def test_cache_invalidation(self, db):
        from django.core.cache import cache
        from apps.core.models import Setting, CACHE_PREFIX
        Setting.set('cached_key', 'value1')
        # After set, cache for that key should be cleared
        assert cache.get(f'{CACHE_PREFIX}cached_key') is None
        # get should repopulate cache
        val = Setting.get('cached_key')
        assert val == 'value1'
        assert cache.get(f'{CACHE_PREFIX}cached_key') == 'value1'

    def test_get_all(self, db):
        from apps.core.models import Setting
        Setting.set('a', '1')
        Setting.set('b', '2')
        all_settings = Setting.get_all()
        assert all_settings['a'] == '1'
        assert all_settings['b'] == '2'

    def test_key_unique(self, db):
        from apps.core.models import Setting
        Setting.objects.create(key='unique_key', value='v1')
        with pytest.raises(IntegrityError):
            Setting.objects.create(key='unique_key', value='v2')

    def test_str(self, db):
        from apps.core.models import Setting
        s = Setting.objects.create(key='test_key', value='test_val')
        assert str(s) == 'test_key = test_val'


class TestSubscriber:
    def test_creation(self, subscriber):
        assert subscriber.email == 'subscriber@example.com'
        assert subscriber.status == 'active'

    def test_email_unique(self, subscriber, db):
        from apps.newsletter.models import Subscriber
        with pytest.raises(IntegrityError):
            Subscriber.objects.create(
                email='subscriber@example.com',
                unsub_token='different-token',
            )

    def test_unsub_token_unique(self, subscriber, db):
        from apps.newsletter.models import Subscriber
        with pytest.raises(IntegrityError):
            Subscriber.objects.create(
                email='other@example.com',
                unsub_token='unsub-token-123',
            )

    def test_str(self, subscriber):
        assert str(subscriber) == 'subscriber@example.com'


class TestNewsletterIssue:
    def test_default_status(self, db):
        from apps.newsletter.models import NewsletterIssue
        issue = NewsletterIssue.objects.create(
            subject='Weekly Digest', content='<p>Content</p>'
        )
        assert issue.status == 'draft'

    def test_status_transitions(self, db):
        from apps.newsletter.models import NewsletterIssue
        issue = NewsletterIssue.objects.create(
            subject='Test Issue', content='<p>Content</p>'
        )
        assert issue.status == 'draft'
        issue.status = 'scheduled'
        issue.scheduled_at = timezone.now()
        issue.save()
        issue.refresh_from_db()
        assert issue.status == 'scheduled'
        issue.status = 'sent'
        issue.sent_at = timezone.now()
        issue.recipient_count = 150
        issue.save()
        issue.refresh_from_db()
        assert issue.status == 'sent'
        assert issue.recipient_count == 150

    def test_str(self, db):
        from apps.newsletter.models import NewsletterIssue
        issue = NewsletterIssue.objects.create(
            subject='Big News', content='<p>Body</p>'
        )
        assert str(issue) == 'Big News'


class TestEmailQueue:
    def test_defaults(self, db):
        from apps.newsletter.models import EmailQueue
        eq = EmailQueue.objects.create(
            to_email='user@example.com',
            subject='Welcome',
            body_html='<p>Hello</p>',
        )
        assert eq.status == 'pending'
        assert eq.attempts == 0
        assert eq.error_message == ''

    def test_attempts_counter(self, db):
        from apps.newsletter.models import EmailQueue
        eq = EmailQueue.objects.create(
            to_email='user@example.com',
            subject='Test',
            body_html='<p>Body</p>',
        )
        eq.attempts += 1
        eq.save(update_fields=['attempts'])
        eq.refresh_from_db()
        assert eq.attempts == 1
        eq.attempts += 1
        eq.status = 'failed'
        eq.error_message = 'SMTP timeout'
        eq.save()
        eq.refresh_from_db()
        assert eq.attempts == 2
        assert eq.status == 'failed'

    def test_str(self, db):
        from apps.newsletter.models import EmailQueue
        eq = EmailQueue.objects.create(
            to_email='user@test.com', subject='Hello', body_html='<p>Hi</p>'
        )
        assert 'user@test.com' in str(eq)


class TestMediaItem:
    def test_creation(self, db, user_author):
        from apps.media.models import MediaItem
        item = MediaItem.objects.create(
            filename='abc123.jpg',
            original_filename='photo.jpg',
            path='/media/uploads/abc123.jpg',
            mime_type='image/jpeg',
            size=204800,
            width=1920,
            height=1080,
            hash='deadbeef1234567890abcdef',
            uploaded_by=user_author,
        )
        assert item.hash == 'deadbeef1234567890abcdef'
        assert item.size == 204800
        assert str(item) == 'photo.jpg'

    def test_defaults(self, db):
        from apps.media.models import MediaItem
        item = MediaItem.objects.create(
            filename='file.png',
            original_filename='file.png',
            path='/media/file.png',
            mime_type='image/png',
            size=1024,
            hash='abc',
        )
        assert item.storage_driver == 'local'
        assert item.alt_text == ''
        assert item.folder == ''


class TestSiteVisitor:
    def test_creation(self, db):
        from apps.analytics.models import SiteVisitor
        visitor = SiteVisitor.objects.create(
            ip_address='192.168.1.1',
            country='Uganda',
            city='Kampala',
            visit_date=date.today(),
            device_type='mobile',
            browser='Chrome',
            os='Android',
        )
        assert str(visitor) == f'192.168.1.1 — {date.today()}'

    def test_per_day_tracking(self, db):
        from apps.analytics.models import SiteVisitor
        today = date.today()
        SiteVisitor.objects.create(
            ip_address='10.0.0.1', visit_date=today
        )
        SiteVisitor.objects.create(
            ip_address='10.0.0.1', visit_date=today
        )
        # Same IP can have multiple visits on same day (not unique)
        assert SiteVisitor.objects.filter(
            ip_address='10.0.0.1', visit_date=today
        ).count() == 2


class TestDailyStats:
    def test_creation(self, db):
        from apps.analytics.models import DailyStats
        stats = DailyStats.objects.create(
            date=date.today(),
            total_views=500,
            unique_visitors=200,
            new_articles=15,
            new_comments=30,
            new_subscribers=5,
        )
        assert str(stats) == f'Stats for {date.today()}'

    def test_unique_date(self, db):
        from apps.analytics.models import DailyStats
        DailyStats.objects.create(date=date(2026, 1, 1))
        with pytest.raises(IntegrityError):
            DailyStats.objects.create(date=date(2026, 1, 1))

    def test_defaults(self, db):
        from apps.analytics.models import DailyStats
        stats = DailyStats.objects.create(date=date(2026, 6, 15))
        assert stats.total_views == 0
        assert stats.unique_visitors == 0
        assert stats.new_articles == 0


class TestAdSlot:
    def test_creation(self, db):
        from apps.ads.models import AdSlot
        slot = AdSlot.objects.create(
            name='Top Banner',
            slot_name='top_banner',
            html_content='<div>Ad</div>',
            is_active=True,
        )
        assert str(slot) == 'Top Banner'
        assert slot.is_active is True

    def test_is_active_filtering(self, db):
        from apps.ads.models import AdSlot
        AdSlot.objects.create(name='Active', slot_name='active', is_active=True)
        AdSlot.objects.create(name='Inactive', slot_name='inactive', is_active=False)
        active = AdSlot.objects.filter(is_active=True)
        assert active.count() == 1
        assert active.first().slot_name == 'active'

    def test_slot_name_unique(self, db):
        from apps.ads.models import AdSlot
        AdSlot.objects.create(name='Slot', slot_name='sidebar')
        with pytest.raises(IntegrityError):
            AdSlot.objects.create(name='Slot 2', slot_name='sidebar')

    def test_defaults(self, db):
        from apps.ads.models import AdSlot
        slot = AdSlot.objects.create(name='Default', slot_name='default_slot')
        assert slot.impressions == 0
        assert slot.clicks == 0
        assert slot.device_targeting == 'all'


class TestWebhook:
    def test_creation(self, db):
        from apps.webhooks.models import Webhook
        wh = Webhook.objects.create(
            url='https://hooks.example.com/notify',
            events=['article.published', 'article.updated'],
            secret='mysecret123',
        )
        assert isinstance(wh.events, list)
        assert 'article.published' in wh.events
        assert wh.is_active is True

    def test_events_jsonfield(self, db):
        from apps.webhooks.models import Webhook
        wh = Webhook.objects.create(
            url='https://hooks.example.com/test',
            events=['article.*'],
            secret='secret',
        )
        wh.refresh_from_db()
        assert wh.events == ['article.*']

    def test_str(self, db):
        from apps.webhooks.models import Webhook
        wh = Webhook.objects.create(
            url='https://example.com/hook',
            events=['article.published'],
            secret='s',
        )
        assert 'https://example.com/hook' in str(wh)


class TestWebhookLog:
    def test_creation(self, db):
        from apps.webhooks.models import Webhook, WebhookLog
        wh = Webhook.objects.create(
            url='https://example.com/hook',
            events=['article.published'],
            secret='secret',
        )
        log = WebhookLog.objects.create(
            webhook=wh,
            event='article.published',
            payload={'article_id': str(uuid.uuid4())},
            response_code=200,
            response_body='OK',
        )
        assert log.response_code == 200
        assert 'article.published' in str(log)


class TestSystemLog:
    def test_creation(self, db, user_admin):
        from apps.system.models import SystemLog
        log = SystemLog.objects.create(
            user=user_admin,
            action='article.create',
            details={'article_title': 'Test'},
            ip_address='192.168.1.1',
        )
        assert 'article.create' in str(log)
        assert log.user == user_admin

    def test_creation_without_user(self, db):
        from apps.system.models import SystemLog
        log = SystemLog.objects.create(
            action='cron.crawl',
            ip_address='127.0.0.1',
        )
        assert log.user is None
        assert log.action == 'cron.crawl'


class TestPolicyPage:
    def test_creation(self, db):
        from apps.system.models import PolicyPage
        page = PolicyPage.objects.create(
            title='Privacy Policy',
            slug='privacy-policy',
            content='<p>Your data is safe.</p>',
            is_published=True,
            show_in_footer=True,
        )
        assert str(page) == 'Privacy Policy'

    def test_slug_unique(self, db):
        from apps.system.models import PolicyPage
        PolicyPage.objects.create(
            title='Terms', slug='terms', content='<p>Terms</p>'
        )
        with pytest.raises(IntegrityError):
            PolicyPage.objects.create(
                title='Terms 2', slug='terms', content='<p>Other terms</p>'
            )


class TestNotification:
    def test_is_read_default(self, db, user_admin):
        from apps.system.models import Notification
        notif = Notification.objects.create(
            user=user_admin,
            title='New comment',
            message='Someone commented on your article.',
        )
        assert notif.is_read is False

    def test_mark_as_read(self, db, user_admin):
        from apps.system.models import Notification
        notif = Notification.objects.create(
            user=user_admin,
            title='Alert',
            message='System alert.',
        )
        notif.is_read = True
        notif.save(update_fields=['is_read'])
        notif.refresh_from_db()
        assert notif.is_read is True

    def test_str(self, db, user_admin):
        from apps.system.models import Notification
        notif = Notification.objects.create(
            user=user_admin, title='Test Title', message='msg'
        )
        assert str(notif) == 'Test Title'
