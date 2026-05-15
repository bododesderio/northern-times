import uuid
from datetime import timedelta

import pytest
from django.db import IntegrityError
from django.utils import timezone

from apps.articles.models import (
    Article,
    ArticleEntity,
    ArticleTag,
    Category,
    Comment,
    StoryCluster,
    Tag,
    TopicFollow,
)


class TestArticleManager:
    def test_published_returns_only_published(self, published_article, draft_article):
        qs = Article.objects.published()
        assert published_article in qs
        assert draft_article not in qs

    def test_published_excludes_future_dated(self, category, user_author, db):
        future = Article.objects.create(
            title='Future Article',
            slug='future-article',
            content='<p>Content</p>',
            status='published',
            published_at=timezone.now() + timedelta(hours=2),
            category=category,
            author=user_author,
        )
        qs = Article.objects.published()
        assert future not in qs

    def test_default_manager_excludes_soft_deleted(self, published_article):
        published_article.soft_delete()
        assert published_article not in Article.objects.all()

    def test_with_deleted_includes_soft_deleted(self, published_article):
        published_article.soft_delete()
        assert published_article in Article.objects.with_deleted()

    def test_published_excludes_soft_deleted(self, published_article):
        published_article.soft_delete()
        qs = Article.objects.published()
        assert published_article not in qs


class TestArticleModel:
    def test_soft_delete_sets_deleted_at(self, published_article):
        assert published_article.deleted_at is None
        published_article.soft_delete()
        published_article.refresh_from_db()
        assert published_article.deleted_at is not None

    def test_restore_clears_deleted_at(self, published_article):
        published_article.soft_delete()
        published_article.refresh_from_db()
        assert published_article.deleted_at is not None
        published_article.restore()
        # Need to use with_deleted since default manager filters out deleted
        article = Article.objects.with_deleted().get(pk=published_article.pk)
        assert article.deleted_at is None

    def test_reading_time_short_content(self, db, category):
        article = Article.objects.create(
            title='Short',
            slug='short-content',
            content='Hello world',
            status='draft',
            category=category,
        )
        assert article.reading_time == 1  # min 1 minute

    def test_reading_time_long_content(self, db, category):
        # 600 words ~ 3 minutes
        words = ' '.join(['word'] * 600)
        article = Article.objects.create(
            title='Long',
            slug='long-content',
            content=f'<p>{words}</p>',
            status='draft',
            category=category,
        )
        assert article.reading_time == 3

    def test_reading_time_empty_content(self, db, category):
        article = Article.objects.create(
            title='Empty',
            slug='empty-content',
            content='',
            status='draft',
            category=category,
        )
        assert article.reading_time == 1

    def test_slug_unique(self, published_article, db, category):
        with pytest.raises(IntegrityError):
            Article.objects.create(
                title='Duplicate Slug',
                slug=published_article.slug,
                content='Content',
                status='draft',
                category=category,
            )

    def test_str(self, published_article):
        assert str(published_article) == 'Test Article Title'

    def test_story_cluster_assignment(self, published_article, db):
        cluster = StoryCluster.objects.create(title='Test Cluster')
        published_article.story_cluster_id = cluster.pk
        published_article.save(update_fields=['story_cluster_id'])
        published_article.refresh_from_db()
        assert published_article.story_cluster_id == cluster.pk

    def test_crawled_article_fields(self, crawled_article):
        assert crawled_article.is_crawled is True
        assert crawled_article.source_url == 'https://example.com/article/123'
        assert crawled_article.source_name == 'Example News'
        assert crawled_article.source_hash == 'abc123def456'


class TestCategory:
    def test_str(self, category):
        assert str(category) == 'Politics'

    def test_auto_slug(self, db):
        cat = Category.objects.create(name='World News')
        assert cat.slug == 'world-news'

    def test_slug_unique(self, category, db):
        with pytest.raises(IntegrityError):
            Category.objects.create(name='Other', slug='politics')

    def test_articles_relation(self, published_article, category):
        assert published_article in category.articles.all()


class TestTag:
    def test_str(self, tag):
        assert str(tag) == 'Uganda'

    def test_type(self, tag):
        assert tag.type == 'location'

    def test_auto_slug(self, db):
        t = Tag.objects.create(name='East Africa')
        assert t.slug == 'east-africa'

    def test_slug_unique(self, tag, db):
        with pytest.raises(IntegrityError):
            Tag.objects.create(name='Uganda 2', slug='uganda')


class TestComment:
    def test_creation(self, published_article, db):
        comment = Comment.objects.create(
            article=published_article,
            name='Reader',
            email='reader@example.com',
            content='Great article!',
        )
        assert comment.status == 'pending'
        assert str(comment) == f'Comment by Reader on {published_article}'

    def test_reply(self, published_article, db):
        parent = Comment.objects.create(
            article=published_article,
            name='Parent',
            email='parent@example.com',
            content='Parent comment',
        )
        reply = Comment.objects.create(
            article=published_article,
            name='Child',
            email='child@example.com',
            content='Reply comment',
            parent=parent,
        )
        assert reply.parent == parent
        assert parent.replies.count() == 1


class TestArticleTag:
    def test_creation(self, published_article, tag, db):
        at = ArticleTag.objects.create(article=published_article, tag=tag)
        assert published_article.tags.count() == 1
        assert tag in published_article.tags.all()

    def test_unique_together(self, published_article, tag, db):
        ArticleTag.objects.create(article=published_article, tag=tag)
        with pytest.raises(IntegrityError):
            ArticleTag.objects.create(article=published_article, tag=tag)


class TestStoryCluster:
    def test_creation(self, db):
        cluster = StoryCluster.objects.create(title='Uganda Elections 2026')
        assert str(cluster) == 'Uganda Elections 2026'
        assert isinstance(cluster.pk, uuid.UUID)

    def test_canonical_article(self, published_article, db):
        cluster = StoryCluster.objects.create(
            title='Test Cluster', canonical_article=published_article
        )
        assert cluster.canonical_article == published_article


class TestArticleEntity:
    def test_creation(self, published_article, db):
        entity = ArticleEntity.objects.create(
            article=published_article,
            entity_text='Yoweri Museveni',
            entity_type='person',
            salience=0.85,
        )
        assert str(entity) == 'Yoweri Museveni (person)'
        assert entity.salience == 0.85

    def test_ordering_by_salience(self, published_article, db):
        ArticleEntity.objects.create(
            article=published_article,
            entity_text='Low',
            entity_type='org',
            salience=0.2,
        )
        ArticleEntity.objects.create(
            article=published_article,
            entity_text='High',
            entity_type='person',
            salience=0.9,
        )
        entities = list(published_article.entities.all())
        assert entities[0].entity_text == 'High'
        assert entities[1].entity_text == 'Low'


class TestTopicFollow:
    def test_creation(self, category, db):
        follow = TopicFollow.objects.create(
            email='follower@example.com',
            follow_type='category',
            follow_id=category.pk,
        )
        assert str(follow) == f'follower@example.com follows category:{category.pk}'

    def test_unique_together(self, category, db):
        TopicFollow.objects.create(
            email='follower@example.com',
            follow_type='category',
            follow_id=category.pk,
        )
        with pytest.raises(IntegrityError):
            TopicFollow.objects.create(
                email='follower@example.com',
                follow_type='category',
                follow_id=category.pk,
            )

    def test_different_types_allowed(self, category, tag, db):
        TopicFollow.objects.create(
            email='follower@example.com',
            follow_type='category',
            follow_id=category.pk,
        )
        follow2 = TopicFollow.objects.create(
            email='follower@example.com',
            follow_type='tag',
            follow_id=tag.pk,
        )
        assert follow2.pk is not None
