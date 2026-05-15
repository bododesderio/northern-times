import uuid

from django.db import models
from django.utils import timezone


class CrawlSource(models.Model):
    FEED_TYPE_CHOICES = [
        ('rss', 'RSS'),
        ('atom', 'Atom'),
        ('web', 'Web'),
    ]
    REGION_CHOICES = [
        ('ugandan', 'Ugandan'),
        ('east_african', 'East African'),
        ('international', 'International'),
    ]

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    name = models.CharField(max_length=255)
    url = models.URLField(max_length=500)
    category = models.ForeignKey(
        'articles.Category',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='crawl_sources',
    )
    feed_type = models.CharField(max_length=10, choices=FEED_TYPE_CHOICES, default='rss')
    content_selector = models.CharField(max_length=500, blank=True, default='')
    strip_selectors = models.TextField(blank=True, default='')
    crawl_interval = models.IntegerField(default=60, help_text='Crawl interval in minutes')
    auto_rewrite = models.BooleanField(default=False)
    editorial_review = models.BooleanField(default=False)
    region = models.CharField(max_length=20, choices=REGION_CHOICES, default='international')
    download_images = models.BooleanField(default=True)
    use_browser = models.BooleanField(default=False, help_text='Auto-detected: use Selenium for sources that block HTTP')
    nofollow = models.BooleanField(default=True)
    attribution_text = models.CharField(max_length=500, blank=True, default='')
    max_articles = models.IntegerField(default=50)
    avg_articles_per_day = models.FloatField(default=0.0)
    consecutive_empty = models.IntegerField(default=0)
    consecutive_failures = models.IntegerField(default=0)
    last_crawled_at = models.DateTimeField(null=True, blank=True)
    last_new_content_at = models.DateTimeField(null=True, blank=True)
    next_crawl_at = models.DateTimeField(null=True, blank=True)
    is_active = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ['name']

    def __str__(self):
        return self.name

    def due_for_crawl(self):
        """Return True if this source is due for crawling."""
        if not self.is_active:
            return False
        if self.next_crawl_at is None:
            return True
        return timezone.now() >= self.next_crawl_at


class CrawlLog(models.Model):
    STATUS_CHOICES = [
        ('ok', 'OK'),
        ('error', 'Error'),
    ]

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    source = models.ForeignKey(
        CrawlSource,
        on_delete=models.CASCADE,
        related_name='logs',
    )
    status = models.CharField(max_length=10, choices=STATUS_CHOICES, default='ok')
    found_count = models.IntegerField(default=0)
    new_count = models.IntegerField(default=0)
    duplicate_count = models.IntegerField(default=0)
    error_count = models.IntegerField(default=0)
    error_message = models.TextField(blank=True, default='')
    details = models.JSONField(default=dict, blank=True)
    duration_ms = models.IntegerField(default=0)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['-created_at']

    def __str__(self):
        return f'{self.source.name} — {self.status} @ {self.created_at:%Y-%m-%d %H:%M}'
