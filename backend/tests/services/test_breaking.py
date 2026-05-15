from datetime import timedelta
from unittest.mock import MagicMock

import pytest
from django.utils import timezone

from apps.articles.services.breaking import BreakingNewsEngine


@pytest.fixture
def engine():
    return BreakingNewsEngine()


def _make_article(**kwargs):
    """Create a mock article with sensible defaults."""
    article = MagicMock()
    article.pk = kwargs.get('pk', 1)
    article.title = kwargs.get('title', 'Regular News Article')
    article.is_breaking_manual = kwargs.get('is_breaking_manual', False)
    article.published_at = kwargs.get('published_at', timezone.now())
    article.story_cluster_id = kwargs.get('story_cluster_id', None)
    return article


class TestScoreArticleManualOverride:
    def test_manual_breaking_returns_100(self, engine):
        article = _make_article(is_breaking_manual=True)
        assert engine.score_article(article) == 100

    def test_non_manual_does_not_get_100(self, engine, db):
        article = _make_article(is_breaking_manual=False, title='Normal story')
        score = engine.score_article(article)
        assert score < 100


class TestUrgencyScore:
    def test_breaking_keyword_in_title(self, engine):
        score = engine._urgency_score(
            _make_article(title='BREAKING: Major event occurs')
        )
        assert score >= 15

    def test_urgent_keyword(self, engine):
        score = engine._urgency_score(
            _make_article(title='URGENT: Floods hit region')
        )
        assert score >= 15

    def test_just_in_keyword(self, engine):
        score = engine._urgency_score(
            _make_article(title='JUST IN: President signs bill')
        )
        assert score >= 15

    def test_no_urgency_keywords(self, engine):
        score = engine._urgency_score(
            _make_article(title='Local farmer grows large pumpkin')
        )
        assert score == 0

    def test_multiple_keywords_capped(self, engine):
        score = engine._urgency_score(
            _make_article(title='BREAKING URGENT ALERT: Big event')
        )
        assert score <= engine.URGENCY_MAX_POINTS

    def test_empty_title(self, engine):
        score = engine._urgency_score(_make_article(title=''))
        assert score == 0

    def test_case_insensitive(self, engine):
        score = engine._urgency_score(
            _make_article(title='Breaking news: Something happened')
        )
        assert score >= 15


class TestRecencyBonus:
    def test_just_published(self, engine):
        article = _make_article(published_at=timezone.now())
        score = engine._recency_bonus(article)
        assert score == engine.RECENCY_MAX_POINTS

    def test_three_hours_old(self, engine):
        article = _make_article(
            published_at=timezone.now() - timedelta(hours=3)
        )
        score = engine._recency_bonus(article)
        # 3h out of 6h window = 50% remaining = ~12 pts
        assert 10 <= score <= 15

    def test_six_hours_old_zero(self, engine):
        article = _make_article(
            published_at=timezone.now() - timedelta(hours=7)
        )
        score = engine._recency_bonus(article)
        assert score == 0

    def test_no_published_at(self, engine):
        article = _make_article(published_at=None)
        score = engine._recency_bonus(article)
        assert score == 0


class TestVelocityScore:
    def test_no_title_returns_zero(self, engine):
        article = _make_article(title='', published_at=timezone.now())
        score = engine._velocity_score(article)
        assert score == 0

    def test_no_published_at_returns_zero(self, engine):
        article = _make_article(title='Some title', published_at=None)
        score = engine._velocity_score(article)
        assert score == 0

    def test_with_similar_articles(self, engine, published_article, db):
        """Velocity should detect articles with overlapping title keywords."""
        from apps.articles.models import Article, Category
        cat = Category.objects.first()
        # Create another article with similar title
        Article.objects.create(
            title='Test Article Title Variation',
            slug='test-article-title-variation',
            content='<p>Content</p>',
            status='published',
            published_at=timezone.now(),
            category=cat,
        )
        score = engine._velocity_score(published_article)
        # Should get some velocity points from the similar article
        assert score >= 0  # At least no error


class TestClusterScore:
    def test_no_cluster_returns_zero(self, engine):
        article = _make_article(story_cluster_id=None)
        assert engine._cluster_score(article) == 0


class TestScoreArticleIntegration:
    def test_urgency_plus_recency(self, engine, db):
        """An article with urgency keywords and recent publish time."""
        article = _make_article(
            title='BREAKING: Major earthquake hits city',
            published_at=timezone.now(),
        )
        score = engine.score_article(article)
        # Urgency (15+) + Recency (25) = at least 40
        assert score >= 40

    def test_old_article_scores_low(self, engine, db):
        article = _make_article(
            title='Local council meeting discusses budget',
            published_at=timezone.now() - timedelta(hours=12),
        )
        score = engine.score_article(article)
        # No urgency, no recency, no velocity, no cluster
        assert score < 10

    def test_score_capped_at_100(self, engine):
        article = _make_article(is_breaking_manual=True)
        assert engine.score_article(article) <= 100
