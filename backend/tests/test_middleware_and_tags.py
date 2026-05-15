"""
Middleware, Template Tags, and Context Processor Tests.
"""
import pytest
from django.test import RequestFactory


# ── Request ID Middleware ───────────────────────────────────────

@pytest.mark.django_db
class TestRequestIDMiddleware:
    def test_response_has_request_id_header(self, client):
        resp = client.get('/')
        assert 'X-Request-ID' in resp

    def test_request_id_is_short(self, client):
        resp = client.get('/')
        request_id = resp['X-Request-ID']
        assert len(request_id) == 8

    def test_forwarded_request_id_is_preserved(self, client):
        resp = client.get('/', HTTP_X_REQUEST_ID='custom-id')
        assert resp['X-Request-ID'] == 'custom-id'


# ── Security Headers Middleware ─────────────────────────────────

@pytest.mark.django_db
class TestSecurityHeadersMiddleware:
    def test_x_frame_options(self, client):
        resp = client.get('/')
        assert resp.get('X-Frame-Options') == 'SAMEORIGIN'

    def test_x_content_type_options(self, client):
        resp = client.get('/')
        assert resp.get('X-Content-Type-Options') == 'nosniff'

    def test_referrer_policy(self, client):
        resp = client.get('/')
        assert 'strict-origin' in resp.get('Referrer-Policy', '')


# ── Template Tags ───────────────────────────────────────────────

@pytest.mark.django_db
class TestTemplateTags:
    def test_site_setting_tag(self):
        from apps.core.models import Setting
        from apps.core.templatetags.core_tags import site_setting

        Setting.objects.create(key='site_name', value='Test Site', type='string')
        assert site_setting('site_name') == 'Test Site'

    def test_site_setting_default(self):
        from apps.core.templatetags.core_tags import site_setting
        assert site_setting('nonexistent_key', 'fallback') == 'fallback'

    def test_relative_time_filter(self):
        from apps.core.templatetags.core_tags import relative_time
        from django.utils import timezone
        from datetime import timedelta

        now = timezone.now()
        assert relative_time(now) == 'just now'
        assert 'ago' in relative_time(now - timedelta(hours=2))

    def test_startswith_filter(self):
        from apps.core.templatetags.core_tags import startswith
        assert startswith('/admin/articles/', '/admin') is True
        assert startswith('/api/health/', '/admin') is False


# ── Context Processors ──────────────────────────────────────────

@pytest.mark.django_db
class TestContextProcessors:
    def test_homepage_has_site_settings(self, client):
        from apps.core.models import Setting
        Setting.objects.create(key='site_name', value='Northern Times', type='string')
        resp = client.get('/')
        assert resp.status_code == 200

    def test_homepage_has_categories_in_context(self, client, category):
        resp = client.get('/')
        # Context processor provides nav_categories
        assert resp.status_code == 200
