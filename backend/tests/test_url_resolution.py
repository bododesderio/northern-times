"""
URL Resolution Tests — Verify every named URL pattern resolves without error.

Tests that all URL names referenced in templates actually exist and resolve.
This catches the class of bugs where templates use {% url 'name' %} but
no URL pattern with that name is defined.
"""
import uuid

import pytest
from django.urls import NoReverseMatch, reverse


# ── Helper ──────────────────────────────────────────────────────

SAMPLE_UUID = str(uuid.uuid4())


def _resolve(name, **kwargs):
    """Attempt to reverse a URL name, return (url, None) or (None, error)."""
    try:
        url = reverse(name, kwargs=kwargs)
        return url, None
    except NoReverseMatch as e:
        return None, str(e)


# ── Public Frontend URLs ────────────────────────────────────────

class TestFrontendURLs:
    def test_home(self):
        assert reverse('articles_frontend:home') == '/'

    def test_category(self):
        assert _resolve('articles_frontend:category', slug='politics')[0]

    def test_article(self):
        assert _resolve('articles_frontend:article', slug='test-article')[0]

    def test_author(self):
        assert _resolve('articles_frontend:author', username='admin')[0]

    def test_tag(self):
        assert _resolve('articles_frontend:tag', slug='uganda')[0]

    def test_search(self):
        assert reverse('articles_frontend:search')

    def test_about(self):
        assert reverse('articles_frontend:about')

    def test_contact(self):
        assert reverse('articles_frontend:contact')

    def test_policy(self):
        assert _resolve('articles_frontend:policy', slug='privacy')[0]


# ── Public API URLs ─────────────────────────────────────────────

class TestAPIURLs:
    def test_health(self):
        assert reverse('core_api:health')

    def test_geo(self):
        assert reverse('core_api:geo')

    def test_active_popups(self):
        assert reverse('ads_api:active_popups')

    def test_popup_track(self):
        assert reverse('ads_api:popup_track')

    def test_ad_track(self):
        assert reverse('ads_api:ad_track')

    def test_schema(self):
        assert reverse('schema')

    def test_swagger(self):
        assert reverse('swagger-ui')


# ── Auth URLs ───────────────────────────────────────────────────

class TestAuthURLs:
    def test_login(self):
        assert reverse('admin_login')

    def test_logout(self):
        assert reverse('admin_logout')

    def test_forgot_password(self):
        assert reverse('admin_forgot_password')


# ── Article Admin URLs ──────────────────────────────────────────

class TestArticleAdminURLs:
    def test_dashboard(self):
        assert reverse('admin_dashboard')

    def test_article_list(self):
        assert reverse('admin_articles')

    def test_article_create(self):
        assert reverse('admin_article_create')

    def test_article_edit(self):
        assert _resolve('admin_article_edit', pk=SAMPLE_UUID)[0]

    def test_article_delete(self):
        assert _resolve('admin_article_delete', pk=SAMPLE_UUID)[0]

    def test_article_restore(self):
        assert _resolve('admin_article_restore', pk=SAMPLE_UUID)[0]

    def test_category_list(self):
        assert reverse('admin_categories')

    def test_category_create(self):
        assert reverse('admin_category_create')

    def test_category_edit(self):
        assert _resolve('admin_category_edit', pk=SAMPLE_UUID)[0]

    def test_category_delete(self):
        assert _resolve('admin_category_delete', pk=SAMPLE_UUID)[0]

    def test_comment_list(self):
        assert reverse('admin_comments')

    def test_review_list(self):
        assert reverse('admin_review')


# ── Crawler Admin URLs ──────────────────────────────────────────

class TestCrawlerAdminURLs:
    def test_source_list(self):
        assert reverse('admin_crawler')

    def test_source_create(self):
        assert reverse('admin_crawler_create')

    def test_crawl_all(self):
        assert reverse('admin_crawl_all')

    def test_crawler_logs(self):
        assert reverse('admin_crawler_logs')

    def test_source_articles(self):
        assert _resolve('admin_source_articles', pk=SAMPLE_UUID)[0]

    def test_archive_source_articles(self):
        assert _resolve('admin_archive_source_articles', pk=SAMPLE_UUID)[0]

    def test_restore_source_articles(self):
        assert _resolve('admin_restore_source_articles', pk=SAMPLE_UUID)[0]

    def test_delete_source_articles(self):
        assert _resolve('admin_delete_source_articles', pk=SAMPLE_UUID)[0]


# ── System Admin URLs ──────────────────────────────────────────

class TestSystemAdminURLs:
    def test_system_index(self):
        assert reverse('admin_system')

    def test_clear_cache(self):
        assert reverse('admin_system_clear_cache')

    def test_backup(self):
        assert reverse('admin_system_backup')

    def test_purge_logs(self):
        assert reverse('admin_system_purge_logs')

    def test_reset_article_views(self):
        assert reverse('admin_reset_article_views')

    def test_reset_site_visitors(self):
        assert reverse('admin_reset_site_visitors')

    def test_reset_ad_stats(self):
        assert reverse('admin_reset_ad_stats')

    def test_reset_newsletter_stats(self):
        assert reverse('admin_reset_newsletter_stats')

    def test_reset_crawler_stats(self):
        assert reverse('admin_reset_crawler_stats')

    def test_reset_all_analytics(self):
        assert reverse('admin_reset_all_analytics')

    def test_purge_comments(self):
        assert reverse('admin_purge_comments')

    def test_purge_subscribers(self):
        assert reverse('admin_purge_subscribers')

    def test_purge_notifications(self):
        assert reverse('admin_purge_notifications')

    def test_purge_email_queue(self):
        assert reverse('admin_purge_email_queue')

    def test_purge_crawl_history(self):
        assert reverse('admin_purge_crawl_history')

    def test_purge_seo_history(self):
        assert reverse('admin_purge_seo_history')

    def test_purge_social_mentions(self):
        assert reverse('admin_purge_social_mentions')

    def test_vacuum_db(self):
        assert reverse('admin_vacuum_db')

    def test_table_sizes(self):
        assert reverse('admin_table_sizes')

    def test_environment_api(self):
        assert reverse('admin_environment_api')

    def test_cron_history(self):
        assert reverse('admin_cron_history')

    def test_clean_sessions(self):
        assert reverse('admin_clean_sessions')

    def test_delete_crawled_articles(self):
        assert reverse('admin_delete_crawled_articles')

    def test_delete_all_articles(self):
        assert reverse('admin_delete_all_articles')

    def test_reset_all_popups(self):
        assert reverse('admin_reset_all_popups')

    def test_reset_crawler_system(self):
        assert reverse('admin_reset_crawler_system')

    def test_factory_reset(self):
        assert reverse('admin_factory_reset')

    def test_policies(self):
        assert reverse('admin_policies')

    def test_policy_delete(self):
        assert _resolve('admin_policy_delete', pk=SAMPLE_UUID)[0]


# ── Other Admin URLs ────────────────────────────────────────────

class TestOtherAdminURLs:
    def test_ads_index(self):
        assert reverse('admin_ads')

    def test_ad_toggle(self):
        assert _resolve('admin_ad_toggle', pk=SAMPLE_UUID)[0]

    def test_popups_index(self):
        assert reverse('admin_popups')

    def test_rewriter_index(self):
        assert reverse('admin_rewriter')

    def test_newsletter_index(self):
        assert reverse('admin_newsletter')

    def test_newsletter_preview(self):
        assert _resolve('admin_newsletter_preview', pk=SAMPLE_UUID)[0]

    def test_analytics_index(self):
        assert reverse('admin_analytics')

    def test_seo_index(self):
        assert reverse('admin_seo')

    def test_social_index(self):
        assert reverse('admin_social')

    def test_webhooks_index(self):
        assert reverse('admin_webhooks')

    def test_webhook_test(self):
        assert _resolve('admin_webhook_test', pk=SAMPLE_UUID)[0]

    def test_role_list(self):
        assert reverse('admin_roles')

    def test_role_delete(self):
        assert _resolve('admin_role_delete', pk=SAMPLE_UUID)[0]

    def test_user_list(self):
        assert reverse('admin_users')

    def test_media_index(self):
        assert reverse('admin_media')
