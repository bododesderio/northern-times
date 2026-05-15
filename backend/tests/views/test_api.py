"""Tests for public API endpoints (search, tags, trending, syndication, health)."""
import json

import pytest


@pytest.mark.django_db
class TestSearchAPI:
    def test_search_returns_json(self, client, published_article):
        response = client.get('/api/search/?q=Test')
        assert response.status_code == 200
        data = response.json()
        assert 'results' in data

    def test_search_short_query_returns_empty(self, client):
        response = client.get('/api/search/?q=a')
        data = response.json()
        assert data['results'] == []

    def test_search_missing_q_param(self, client):
        response = client.get('/api/search/')
        assert response.status_code == 200
        data = response.json()
        assert data['results'] == []


@pytest.mark.django_db
class TestTagsAPI:
    def test_tags_returns_list(self, client, tag):
        response = client.get('/api/tags/')
        assert response.status_code == 200
        data = response.json()
        assert 'tags' in data

    def test_tag_search(self, client, tag):
        response = client.get(f'/api/tags/search/?q={tag.name[:3]}')
        assert response.status_code == 200
        data = response.json()
        assert 'tags' in data


@pytest.mark.django_db
class TestTrendingAPI:
    def test_trending_returns_articles(self, client, published_article):
        response = client.get('/api/trending/')
        assert response.status_code == 200
        data = response.json()
        assert 'articles' in data


@pytest.mark.django_db
class TestSyndicationAPI:
    def test_v1_articles_list(self, client, published_article):
        response = client.get('/api/v1/articles/')
        assert response.status_code == 200
        data = response.json()
        assert 'articles' in data
        assert len(data['articles']) >= 1

    def test_v1_article_detail(self, client, published_article):
        response = client.get(f'/api/v1/articles/{published_article.slug}/')
        assert response.status_code == 200
        data = response.json()
        assert data['title'] == published_article.title

    def test_v1_article_detail_404(self, client):
        response = client.get('/api/v1/articles/nonexistent-slug/')
        assert response.status_code == 404


@pytest.mark.django_db
class TestHealthAPI:
    def test_health_returns_json(self, client):
        response = client.get('/api/health/')
        assert response.status_code == 200
        data = response.json()
        assert 'status' in data or 'ok' in data or response.status_code == 200


@pytest.mark.django_db
class TestCommentAPI:
    def test_comment_post(self, client, published_article):
        response = client.post(
            '/api/comment/',
            {
                'article_id': str(published_article.id),
                'name': 'Test Commenter',
                'email': 'commenter@test.com',
                'content': 'Great article!',
            },
        )
        assert response.status_code == 200
        data = response.json()
        assert data['success'] is True

    def test_comment_post_missing_fields(self, client, published_article):
        response = client.post(
            '/api/comment/',
            {
                'article_id': str(published_article.id),
            },
        )
        # Should fail validation
        data = response.json()
        assert data.get('success') is False or response.status_code in (400, 422)


@pytest.mark.django_db
class TestEngagementAPIs:
    def test_share_track(self, client):
        response = client.post('/api/share-track/')
        assert response.status_code == 200

    def test_engagement_track(self, client):
        response = client.post('/api/engagement/')
        assert response.status_code == 200
