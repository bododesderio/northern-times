"""Tests for related-article selection (apps.articles.services.related).

Semantic (embedding) ranking needs real vectors, so these cover the
non-embedding paths: story-cluster priority, category fallback, self-exclusion,
de-duplication and the limit. The embedding branch is a no-op when embedding is
None and is exercised end-to-end against live data separately.
"""
import uuid

import pytest
from django.utils import timezone

from apps.articles.models import Article
from apps.articles.services.related import related_articles


def _art(category, title, *, slug, cluster=None):
    return Article.objects.create(
        title=title, slug=slug, status='published',
        published_at=timezone.now(), category=category,
        story_cluster_id=cluster,  # embedding left NULL
    )


@pytest.mark.django_db
class TestRelatedArticles:
    def test_excludes_self_and_respects_limit(self, category):
        base = _art(category, 'Base story', slug='r-base')
        for i in range(8):
            _art(category, f'Other story {i}', slug=f'r-o{i}')
        rel = related_articles(base, limit=6)
        assert base.id not in [a.id for a in rel]
        assert len(rel) == 6

    def test_story_cluster_prioritised(self, category, category_sports):
        cluster = uuid.uuid4()
        base = _art(category, 'Cluster base', slug='r-cb', cluster=cluster)
        member = _art(category_sports, 'Cluster member', slug='r-cm', cluster=cluster)
        # Same-category noise that should rank BELOW the cluster member.
        for i in range(3):
            _art(category, f'Noise {i}', slug=f'r-n{i}')
        rel = related_articles(base, limit=6)
        assert member.id in [a.id for a in rel]
        assert rel[0].id == member.id  # cluster member first

    def test_category_fallback(self, category, category_sports):
        base = _art(category, 'Base', slug='r-fb')
        same = _art(category, 'Same category', slug='r-sc')
        other = _art(category_sports, 'Different category', slug='r-dc')
        rel = related_articles(base, limit=6)
        ids = [a.id for a in rel]
        assert same.id in ids
        # With no embeddings, selection is category-scoped, so the off-category
        # story is not pulled in.
        assert other.id not in ids

    def test_empty_when_no_peers(self, category):
        base = _art(category, 'Lonely', slug='r-lonely')
        assert related_articles(base, limit=6) == []
