import math
import uuid

from django.conf import settings
from django.contrib.postgres.indexes import GinIndex, OpClass
from django.contrib.postgres.search import SearchVector, SearchVectorField
from django.db import models
from django.db.models import F, GeneratedField
from django.utils import timezone
from django.utils.text import slugify
from pgvector.django import HnswIndex, VectorField


class Category(models.Model):
    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    name = models.CharField(max_length=100)
    slug = models.SlugField(max_length=120, unique=True)
    color = models.CharField(max_length=7, blank=True, default='')
    icon = models.CharField(max_length=50, blank=True, default='')
    description = models.TextField(blank=True, default='')
    sort_order = models.IntegerField(default=0)
    show_in_nav = models.BooleanField(default=True)
    show_in_sidebar = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        verbose_name_plural = 'categories'
        ordering = ['sort_order', 'name']

    def __str__(self):
        return self.name

    def save(self, *args, **kwargs):
        if not self.slug:
            self.slug = slugify(self.name)
        super().save(*args, **kwargs)


class Tag(models.Model):
    TYPE_CHOICES = [
        ('person', 'Person'),
        ('org', 'Organization'),
        ('location', 'Location'),
        ('topic', 'Topic'),
    ]

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    name = models.CharField(max_length=100)
    slug = models.SlugField(max_length=120, unique=True)
    type = models.CharField(max_length=20, choices=TYPE_CHOICES, default='topic')
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['name']

    def __str__(self):
        return self.name

    def save(self, *args, **kwargs):
        if not self.slug:
            self.slug = slugify(self.name)
        super().save(*args, **kwargs)


class ArticleQuerySet(models.QuerySet):
    def published(self):
        """Return only published, non-deleted articles with published_at in the past."""
        return self.filter(
            status='published',
            deleted_at__isnull=True,
            published_at__lte=timezone.now(),
        )

    def active(self):
        """Exclude soft-deleted articles."""
        return self.filter(deleted_at__isnull=True)


class ArticleManager(models.Manager):
    def get_queryset(self):
        return ArticleQuerySet(self.model, using=self._db).filter(deleted_at__isnull=True)

    def published(self):
        return self.get_queryset().published()

    def with_deleted(self):
        """Return a queryset that includes soft-deleted articles."""
        return ArticleQuerySet(self.model, using=self._db)


class Article(models.Model):
    STATUS_CHOICES = [
        ('draft', 'Draft'),
        ('pending_review', 'Pending Review'),
        ('published', 'Published'),
        ('archived', 'Archived'),
        ('scheduled', 'Scheduled'),
    ]

    REWRITE_STATUS_CHOICES = [
        ('queued', 'Queued'),
        ('processing', 'Processing'),
        ('completed', 'Completed'),
        ('failed', 'Failed'),
        ('approved', 'Approved'),
        ('rejected', 'Rejected'),
    ]

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    title = models.CharField(max_length=300)
    slug = models.SlugField(max_length=320, unique=True)
    excerpt = models.TextField(blank=True, default='')
    content = models.TextField(blank=True, default='')
    featured_image = models.URLField(max_length=500, blank=True, default='')

    status = models.CharField(max_length=20, choices=STATUS_CHOICES, default='draft')
    published_at = models.DateTimeField(null=True, blank=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)
    deleted_at = models.DateTimeField(null=True, blank=True)

    is_breaking_manual = models.BooleanField(default=False)
    is_crawled = models.BooleanField(default=False)

    # Source info (for crawled articles)
    source_url = models.URLField(max_length=500, blank=True, default='')
    source_name = models.CharField(max_length=200, blank=True, default='')
    source_hash = models.CharField(max_length=64, blank=True, default='')
    display_author = models.CharField(max_length=200, blank=True, default='')

    # Relations
    category = models.ForeignKey(
        Category, on_delete=models.SET_NULL, null=True, blank=True, related_name='articles'
    )
    author = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.SET_NULL,
        null=True, blank=True, related_name='articles'
    )
    tags = models.ManyToManyField(Tag, through='ArticleTag', blank=True, related_name='articles')

    # Story clustering
    story_cluster_id = models.UUIDField(null=True, blank=True, db_index=True)

    # Geo — story location (from the most salient GPE entity via the gazetteer).
    # Powers reader-proximity ranking on the Local News feed.
    latitude = models.FloatField(null=True, blank=True)
    longitude = models.FloatField(null=True, blank=True)
    geo_place = models.CharField(max_length=120, blank=True, default='')

    # AI enrichment — 384-dimensional sentence-transformer embeddings
    embedding = VectorField(dimensions=384, null=True, blank=True)
    ai_summary = models.TextField(blank=True, default='')
    sentiment = models.CharField(max_length=20, blank=True, default='')
    sentiment_score = models.FloatField(null=True, blank=True)
    quality_score = models.FloatField(null=True, blank=True)

    # Content metrics
    word_count = models.IntegerField(default=0)
    pull_quotes = models.JSONField(default=list, blank=True)
    keywords = models.JSONField(default=list, blank=True)

    # Weighted full-text search vector, maintained by Postgres as a STORED
    # generated column (title > excerpt/summary > body). Read-only from Django;
    # backs the keyword ranker in apps.articles.services.search.HybridSearch and
    # is served by the ``article_fts_gin`` GIN index below.
    search_vector = GeneratedField(
        expression=(
            SearchVector('title', weight='A', config='english')
            + SearchVector('excerpt', weight='B', config='english')
            + SearchVector('ai_summary', weight='B', config='english')
            + SearchVector('content', weight='C', config='english')
        ),
        output_field=SearchVectorField(),
        db_persist=True,
    )

    # Rewriting
    rewrite_status = models.CharField(
        max_length=20, choices=REWRITE_STATUS_CHOICES, null=True, blank=True
    )
    rewritten_title = models.CharField(max_length=300, blank=True, default='')
    rewritten_content = models.TextField(blank=True, default='')
    rewritten_excerpt = models.TextField(blank=True, default='')
    rewrite_tokens_used = models.IntegerField(default=0)
    rewrite_cost = models.DecimalField(max_digits=8, decimal_places=5, default=0)

    objects = ArticleManager()

    class Meta:
        ordering = ['-published_at', '-created_at']
        indexes = [
            models.Index(fields=['status', 'published_at']),
            models.Index(fields=['slug']),
            models.Index(fields=['source_hash']),
            models.Index(fields=['deleted_at']),
            # Weighted FTS — matches HybridSearch._keyword_rank exactly.
            GinIndex(fields=['search_vector'], name='article_fts_gin'),
            # Trigram on title — consulted by the ``%`` operator (title__trigram_similar).
            GinIndex(
                OpClass(F('title'), name='gin_trgm_ops'),
                name='article_title_trgm',
            ),
            # Approximate-nearest-neighbour over the 384-dim embedding (cosine).
            # HNSW needs no training data, so it is healthy even when built on an
            # empty table (unlike ivfflat).
            HnswIndex(
                name='article_emb_hnsw',
                fields=['embedding'],
                m=16,
                ef_construction=64,
                opclasses=['vector_cosine_ops'],
            ),
        ]

    def __str__(self):
        return self.title

    @property
    def reading_time(self):
        """Estimated reading time in minutes (based on 200 wpm)."""
        wc = self.word_count or (len(self.content.split()) if self.content else 0)
        return max(1, math.ceil(wc / 200))

    def soft_delete(self):
        self.deleted_at = timezone.now()
        self.save(update_fields=['deleted_at'])

    def restore(self):
        self.deleted_at = None
        self.save(update_fields=['deleted_at'])


class ArticleTag(models.Model):
    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    article = models.ForeignKey(Article, on_delete=models.CASCADE, related_name='article_tags')
    tag = models.ForeignKey(Tag, on_delete=models.CASCADE, related_name='article_tags')

    class Meta:
        unique_together = ('article', 'tag')

    def __str__(self):
        return f'{self.article} — {self.tag}'


class Comment(models.Model):
    STATUS_CHOICES = [
        ('pending', 'Pending'),
        ('approved', 'Approved'),
        ('rejected', 'Rejected'),
    ]

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    article = models.ForeignKey(Article, on_delete=models.CASCADE, related_name='comments')
    name = models.CharField(max_length=100)
    email = models.EmailField()
    content = models.TextField()
    ip_address = models.GenericIPAddressField(null=True, blank=True)
    status = models.CharField(max_length=10, choices=STATUS_CHOICES, default='pending')
    parent = models.ForeignKey(
        'self', on_delete=models.CASCADE, null=True, blank=True, related_name='replies'
    )
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['-created_at']

    def __str__(self):
        return f'Comment by {self.name} on {self.article}'


class ArticleRevision(models.Model):
    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    article = models.ForeignKey(Article, on_delete=models.CASCADE, related_name='revisions')
    title = models.CharField(max_length=300)
    content = models.TextField()
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.SET_NULL, null=True, blank=True,
        related_name='article_revisions',
    )
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['-created_at']

    def __str__(self):
        return f'Revision of "{self.article}" at {self.created_at}'


class StoryCluster(models.Model):
    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    title = models.CharField(max_length=300)
    canonical_article = models.ForeignKey(
        Article, on_delete=models.SET_NULL, null=True, blank=True, related_name='canonical_for'
    )
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['-created_at']

    def __str__(self):
        return self.title


class ArticleEntity(models.Model):
    ENTITY_TYPE_CHOICES = [
        ('person', 'Person'),
        ('org', 'Organization'),
        ('gpe', 'Geo-Political Entity'),
        ('event', 'Event'),
    ]

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    article = models.ForeignKey(Article, on_delete=models.CASCADE, related_name='entities')
    entity_text = models.CharField(max_length=200)
    entity_type = models.CharField(max_length=20, choices=ENTITY_TYPE_CHOICES)
    salience = models.FloatField(default=0.0)

    class Meta:
        ordering = ['-salience']

    def __str__(self):
        return f'{self.entity_text} ({self.entity_type})'


class TopicFollow(models.Model):
    FOLLOW_TYPE_CHOICES = [
        ('category', 'Category'),
        ('tag', 'Tag'),
    ]

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    email = models.EmailField()
    follow_type = models.CharField(max_length=20, choices=FOLLOW_TYPE_CHOICES)
    follow_id = models.UUIDField()
    unfollow_token = models.CharField(max_length=64, blank=True, default='', db_index=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        unique_together = ('email', 'follow_type', 'follow_id')
        ordering = ['-created_at']

    def save(self, *args, **kwargs):
        if not self.unfollow_token:
            import secrets
            self.unfollow_token = secrets.token_urlsafe(32)
        super().save(*args, **kwargs)

    def __str__(self):
        return f'{self.email} follows {self.follow_type}:{self.follow_id}'
