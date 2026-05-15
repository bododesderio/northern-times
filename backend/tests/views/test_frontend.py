"""Tests for public frontend views (articles, categories, search, RSS, sitemap)."""
import pytest


@pytest.mark.django_db
class TestHomePage:
    def test_home_returns_200(self, client, published_article):
        response = client.get('/')
        assert response.status_code == 200

    def test_home_contains_article(self, client, published_article):
        response = client.get('/')
        assert published_article.title.encode() in response.content


@pytest.mark.django_db
class TestArticlePage:
    def test_article_returns_200(self, client, published_article):
        response = client.get(f'/article/{published_article.slug}/')
        assert response.status_code == 200

    def test_article_contains_title(self, client, published_article):
        response = client.get(f'/article/{published_article.slug}/')
        assert published_article.title.encode() in response.content

    def test_article_404_for_nonexistent(self, client):
        response = client.get('/article/nonexistent-slug/')
        assert response.status_code == 404

    def test_draft_article_not_accessible(self, client, draft_article):
        response = client.get(f'/article/{draft_article.slug}/')
        assert response.status_code == 404


@pytest.mark.django_db
class TestCategoryPage:
    def test_category_returns_200(self, client, published_article, category):
        response = client.get(f'/category/{category.slug}/')
        assert response.status_code == 200

    def test_category_contains_article(self, client, published_article, category):
        response = client.get(f'/category/{category.slug}/')
        assert published_article.title.encode() in response.content

    def test_category_404_for_nonexistent(self, client):
        response = client.get('/category/nonexistent/')
        assert response.status_code == 404


@pytest.mark.django_db
class TestSearchPage:
    def test_search_returns_200(self, client):
        response = client.get('/search/?q=test')
        assert response.status_code == 200

    def test_search_finds_article(self, client, published_article):
        response = client.get(f'/search/?q={published_article.title[:10]}')
        assert response.status_code == 200

    def test_search_empty_query(self, client):
        response = client.get('/search/')
        assert response.status_code == 200


@pytest.mark.django_db
class TestTagPage:
    def test_tag_returns_200(self, client, tag, published_article):
        published_article.tags.add(tag)
        response = client.get(f'/tag/{tag.slug}/')
        assert response.status_code == 200

    def test_tag_404_for_nonexistent(self, client):
        response = client.get('/tag/nonexistent/')
        assert response.status_code == 404


@pytest.mark.django_db
class TestStaticPages:
    def test_about_returns_200(self, client):
        response = client.get('/about/')
        assert response.status_code == 200

    def test_contact_returns_200(self, client):
        response = client.get('/contact/')
        assert response.status_code == 200


@pytest.mark.django_db
class TestRSSFeed:
    def test_rss_returns_xml(self, client, published_article):
        response = client.get('/feed.xml')
        assert response.status_code == 200
        assert 'xml' in response['Content-Type']

    def test_rss_contains_article(self, client, published_article):
        response = client.get('/feed.xml')
        assert published_article.title.encode() in response.content


@pytest.mark.django_db
class TestSitemap:
    def test_sitemap_returns_xml(self, client, published_article):
        response = client.get('/sitemap.xml')
        assert response.status_code == 200
        assert 'xml' in response['Content-Type']

    def test_sitemap_contains_article_slug(self, client, published_article):
        response = client.get('/sitemap.xml')
        assert published_article.slug.encode() in response.content


@pytest.mark.django_db
class TestAuthorPage:
    def test_author_returns_200(self, client, published_article, user_author):
        response = client.get(f'/author/{user_author.username}/')
        assert response.status_code == 200

    def test_author_404_for_nonexistent(self, client):
        response = client.get('/author/nonexistent-user/')
        assert response.status_code == 404
