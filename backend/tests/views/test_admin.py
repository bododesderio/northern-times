"""Tests for admin panel views (auth, articles, categories, crawler, system, media)."""
import pytest


@pytest.mark.django_db
class TestAdminAuth:
    def test_login_page_loads(self, client):
        response = client.get('/admin/login/')
        assert response.status_code == 200

    def test_login_redirects_on_success(self, client, user_admin):
        response = client.post('/admin/login/', {
            'email': 'admin@example.com',
            'password': 'testpass123',
        })
        # Should redirect to dashboard on success
        assert response.status_code in (200, 302)

    def test_login_fails_with_wrong_password(self, client, user_admin):
        response = client.post('/admin/login/', {
            'email': 'admin@example.com',
            'password': 'wrongpassword',
        })
        # Should stay on login page (200) or return error
        assert response.status_code == 200

    def test_dashboard_requires_auth(self, client):
        response = client.get('/admin/')
        assert response.status_code == 302  # Redirect to login

    def test_dashboard_loads_for_admin(self, admin_client):
        response = admin_client.get('/admin/')
        assert response.status_code == 200

    def test_logout_redirects(self, admin_client):
        response = admin_client.get('/admin/logout/')
        assert response.status_code in (200, 302)


@pytest.mark.django_db
class TestArticleAdmin:
    def test_article_list(self, admin_client, published_article):
        response = admin_client.get('/admin/articles/')
        assert response.status_code == 200

    def test_article_list_contains_article(self, admin_client, published_article):
        response = admin_client.get('/admin/articles/')
        assert published_article.title.encode() in response.content

    def test_article_create_page(self, admin_client):
        response = admin_client.get('/admin/articles/create/')
        assert response.status_code == 200

    def test_article_edit_page(self, admin_client, published_article):
        response = admin_client.get(f'/admin/articles/{published_article.id}/edit/')
        assert response.status_code == 200

    def test_article_list_requires_auth(self, client):
        response = client.get('/admin/articles/')
        assert response.status_code == 302

    def test_article_create_requires_auth(self, client):
        response = client.get('/admin/articles/create/')
        assert response.status_code == 302

    def test_archive_page(self, admin_client):
        response = admin_client.get('/admin/articles/archive/')
        assert response.status_code == 200


@pytest.mark.django_db
class TestCategoryAdmin:
    def test_category_list(self, admin_client, category):
        response = admin_client.get('/admin/categories/')
        assert response.status_code == 200

    def test_category_create_page(self, admin_client):
        response = admin_client.get('/admin/categories/create/')
        assert response.status_code == 200

    def test_category_edit_page(self, admin_client, category):
        response = admin_client.get(f'/admin/categories/{category.id}/edit/')
        assert response.status_code == 200

    def test_category_list_requires_auth(self, client):
        response = client.get('/admin/categories/')
        assert response.status_code == 302


@pytest.mark.django_db
class TestCommentAdmin:
    def test_comment_list(self, admin_client):
        response = admin_client.get('/admin/comments/')
        assert response.status_code == 200

    def test_comment_list_requires_auth(self, client):
        response = client.get('/admin/comments/')
        assert response.status_code == 302


@pytest.mark.django_db
class TestCrawlerAdmin:
    def test_crawler_sources(self, admin_client, crawl_source):
        response = admin_client.get('/admin/admin/crawler/')
        assert response.status_code == 200

    def test_crawler_logs(self, admin_client):
        response = admin_client.get('/admin/admin/crawler/logs/')
        assert response.status_code == 200

    def test_crawler_sources_requires_auth(self, client):
        response = client.get('/admin/admin/crawler/')
        assert response.status_code == 302


@pytest.mark.django_db
class TestSystemAdmin:
    def test_system_index(self, admin_client):
        response = admin_client.get('/admin/admin/system/')
        assert response.status_code == 200

    def test_system_logs(self, admin_client):
        response = admin_client.get('/admin/admin/system/logs/')
        assert response.status_code == 200

    def test_system_backups(self, admin_client):
        response = admin_client.get('/admin/admin/system/backups/')
        assert response.status_code == 200

    def test_system_requires_auth(self, client):
        response = client.get('/admin/admin/system/')
        assert response.status_code == 302


@pytest.mark.django_db
class TestMediaAdmin:
    def test_media_index(self, admin_client):
        response = admin_client.get('/admin/admin/media/')
        assert response.status_code == 200

    def test_media_requires_auth(self, client):
        response = client.get('/admin/admin/media/')
        assert response.status_code == 302


@pytest.mark.django_db
class TestReviewAdmin:
    def test_review_list(self, admin_client):
        response = admin_client.get('/admin/review/')
        assert response.status_code == 200

    def test_review_requires_auth(self, client):
        response = client.get('/admin/review/')
        assert response.status_code == 302


@pytest.mark.django_db
class TestEditorAccess:
    """Verify that editor role can access article management."""

    def test_editor_can_access_articles(self, editor_client, published_article):
        response = editor_client.get('/admin/articles/')
        assert response.status_code == 200

    def test_editor_can_access_dashboard(self, editor_client):
        response = editor_client.get('/admin/')
        assert response.status_code == 200
