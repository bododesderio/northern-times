"""
Comprehensive View Tests — Verify all admin and public views return expected status codes.

Tests that views are accessible (or properly restrict access), render correct
templates, and don't crash on GET/POST requests.
"""
import pytest
from django.test import Client
from django.urls import reverse


# ── Public Pages (no login required) ────────────────────────────

@pytest.mark.django_db
class TestPublicPages:
    def test_home(self, client):
        resp = client.get(reverse('articles_frontend:home'))
        assert resp.status_code == 200

    def test_search(self, client):
        resp = client.get(reverse('articles_frontend:search'))
        assert resp.status_code == 200

    def test_about(self, client):
        resp = client.get(reverse('articles_frontend:about'))
        assert resp.status_code == 200

    def test_contact(self, client):
        resp = client.get(reverse('articles_frontend:contact'))
        assert resp.status_code == 200

    def test_category_page(self, client, category):
        resp = client.get(reverse('articles_frontend:category', kwargs={'slug': category.slug}))
        assert resp.status_code == 200

    def test_article_page(self, client, published_article):
        resp = client.get(reverse('articles_frontend:article', kwargs={'slug': published_article.slug}))
        assert resp.status_code == 200

    def test_tag_page(self, client, tag):
        resp = client.get(reverse('articles_frontend:tag', kwargs={'slug': tag.slug}))
        assert resp.status_code == 200

    def test_404_page(self, client):
        resp = client.get('/nonexistent-page-xyz/')
        assert resp.status_code == 404


# ── Public API Endpoints ────────────────────────────────────────

@pytest.mark.django_db
class TestPublicAPI:
    def test_health_check(self, client):
        resp = client.get(reverse('core_api:health'))
        assert resp.status_code in (200, 503)
        assert resp.json().get('status') in ('ok', 'degraded')

    def test_geo_lookup(self, client):
        resp = client.get(reverse('core_api:geo'))
        assert resp.status_code == 200
        data = resp.json()
        assert 'ip' in data

    def test_popup_track_requires_post(self, client):
        resp = client.get(reverse('ads_api:popup_track'))
        assert resp.status_code == 405  # Method Not Allowed


# ── Auth Pages ──────────────────────────────────────────────────

@pytest.mark.django_db
class TestAuthPages:
    def test_login_page(self, client):
        resp = client.get(reverse('admin_login'))
        assert resp.status_code == 200

    def test_login_success(self, client, user_admin):
        resp = client.post(reverse('admin_login'), {
            'email': 'admin@example.com',
            'password': 'testpass123',
        })
        # Should redirect to dashboard on success
        assert resp.status_code in (200, 302)


# ── Admin Pages (require login) ────────────────────────────────

@pytest.mark.django_db
class TestAdminPagesRequireLogin:
    """Verify unauthenticated users get redirected."""

    def test_dashboard_redirects(self, client):
        resp = client.get(reverse('admin_dashboard'))
        assert resp.status_code == 302
        assert 'login' in resp.url

    def test_articles_redirects(self, client):
        resp = client.get(reverse('admin_articles'))
        assert resp.status_code == 302

    def test_system_redirects(self, client):
        resp = client.get(reverse('admin_system'))
        assert resp.status_code == 302


@pytest.mark.django_db
class TestAdminDashboard:
    def test_dashboard_loads(self, admin_client):
        resp = admin_client.get(reverse('admin_dashboard'))
        assert resp.status_code == 200

    def test_dashboard_pulse_api(self, admin_client):
        resp = admin_client.get(reverse('admin_dashboard_pulse'))
        assert resp.status_code == 200


@pytest.mark.django_db
class TestAdminArticles:
    def test_article_list(self, admin_client):
        resp = admin_client.get(reverse('admin_articles'))
        assert resp.status_code == 200

    def test_article_create_form(self, admin_client):
        resp = admin_client.get(reverse('admin_article_create'))
        assert resp.status_code == 200

    def test_article_edit(self, admin_client, published_article):
        resp = admin_client.get(
            reverse('admin_article_edit', kwargs={'pk': published_article.pk})
        )
        assert resp.status_code == 200


@pytest.mark.django_db
class TestAdminCrawler:
    def test_crawler_index(self, admin_client):
        resp = admin_client.get(reverse('admin_crawler'))
        assert resp.status_code == 200

    def test_crawler_logs(self, admin_client):
        resp = admin_client.get(reverse('admin_crawler_logs'))
        assert resp.status_code == 200

    def test_source_articles(self, admin_client, crawl_source):
        resp = admin_client.get(
            reverse('admin_source_articles', kwargs={'pk': crawl_source.pk})
        )
        assert resp.status_code == 200


@pytest.mark.django_db
class TestAdminAnalytics:
    def test_analytics_index(self, admin_client):
        resp = admin_client.get(reverse('admin_analytics'))
        assert resp.status_code == 200


@pytest.mark.django_db
class TestAdminNewsletter:
    def test_newsletter_index(self, admin_client):
        resp = admin_client.get(reverse('admin_newsletter'))
        assert resp.status_code == 200


@pytest.mark.django_db
class TestAdminRewriter:
    def test_rewriter_index(self, admin_client):
        resp = admin_client.get(reverse('admin_rewriter'))
        assert resp.status_code == 200


@pytest.mark.django_db
class TestAdminAds:
    def test_ads_index(self, admin_client):
        resp = admin_client.get(reverse('admin_ads'))
        assert resp.status_code == 200

    def test_popups_index(self, admin_client):
        resp = admin_client.get(reverse('admin_popups'))
        assert resp.status_code == 200


@pytest.mark.django_db
class TestAdminSEO:
    def test_seo_index(self, admin_client):
        resp = admin_client.get(reverse('admin_seo'))
        assert resp.status_code == 200


@pytest.mark.django_db
class TestAdminSocial:
    def test_social_index(self, admin_client):
        resp = admin_client.get(reverse('admin_social'))
        assert resp.status_code == 200


@pytest.mark.django_db
class TestAdminSystem:
    def test_system_index(self, admin_client):
        resp = admin_client.get(reverse('admin_system'))
        assert resp.status_code == 200

    def test_policies_index(self, admin_client):
        resp = admin_client.get(reverse('admin_policies'))
        assert resp.status_code == 200

    def test_cron_history(self, admin_client):
        resp = admin_client.get(reverse('admin_cron_history'))
        assert resp.status_code == 200

    def test_environment_api(self, admin_client):
        resp = admin_client.get(reverse('admin_environment_api'))
        assert resp.status_code == 200
        assert 'python_version' in resp.json()


@pytest.mark.django_db
class TestAdminWebhooks:
    def test_webhooks_index(self, admin_client):
        resp = admin_client.get(reverse('admin_webhooks'))
        assert resp.status_code == 200

    def test_webhook_logs(self, admin_client):
        resp = admin_client.get(reverse('admin_webhook_logs'))
        assert resp.status_code == 200


@pytest.mark.django_db
class TestAdminUsers:
    def test_user_list(self, admin_client):
        resp = admin_client.get(reverse('admin_users'))
        assert resp.status_code == 200

    def test_role_list(self, admin_client):
        resp = admin_client.get(reverse('admin_roles'))
        assert resp.status_code == 200


# ── POST Actions (CSRF protected) ──────────────────────────────

@pytest.mark.django_db
class TestAdminPostActions:
    def test_clear_cache(self, admin_client):
        resp = admin_client.post(reverse('admin_system_clear_cache'))
        assert resp.status_code == 302  # redirect after POST

    def test_reset_article_views(self, admin_client):
        resp = admin_client.post(reverse('admin_reset_article_views'))
        assert resp.status_code == 302

    def test_ad_toggle(self, admin_client):
        from apps.ads.models import AdSlot
        slot = AdSlot.objects.create(name='Test', slot_name='test', is_active=True)
        resp = admin_client.post(
            reverse('admin_ad_toggle', kwargs={'pk': slot.pk})
        )
        assert resp.status_code == 302
        slot.refresh_from_db()
        assert slot.is_active is False

    def test_category_delete(self, admin_client, category):
        resp = admin_client.post(
            reverse('admin_category_delete', kwargs={'pk': category.pk})
        )
        assert resp.status_code == 302

    def test_article_soft_delete(self, admin_client, published_article):
        resp = admin_client.post(
            reverse('admin_article_delete', kwargs={'pk': published_article.pk})
        )
        assert resp.status_code == 302
