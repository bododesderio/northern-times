import uuid

from django.db import models


class SiteVisitor(models.Model):
    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    ip_address = models.GenericIPAddressField()
    country = models.CharField(max_length=100, blank=True, default='')
    city = models.CharField(max_length=100, blank=True, default='')
    latitude = models.FloatField(null=True, blank=True)
    longitude = models.FloatField(null=True, blank=True)
    visit_date = models.DateField()
    device_type = models.CharField(max_length=50, blank=True, default='')
    browser = models.CharField(max_length=100, blank=True, default='')
    os = models.CharField(max_length=100, blank=True, default='')
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['-created_at']

    def __str__(self):
        return f'{self.ip_address} — {self.visit_date}'


class ArticleView(models.Model):
    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    article = models.ForeignKey(
        'articles.Article',
        on_delete=models.CASCADE,
        related_name='views',
    )
    visitor_ip = models.GenericIPAddressField()
    session_id = models.CharField(max_length=64, null=True, blank=True)
    viewed_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['-viewed_at']

    def __str__(self):
        return f'{self.article} viewed by {self.visitor_ip}'


class DailyStats(models.Model):
    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    date = models.DateField(unique=True)
    total_views = models.IntegerField(default=0)
    unique_visitors = models.IntegerField(default=0)
    new_articles = models.IntegerField(default=0)
    new_comments = models.IntegerField(default=0)
    new_subscribers = models.IntegerField(default=0)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['-date']
        verbose_name_plural = 'daily stats'

    def __str__(self):
        return f'Stats for {self.date}'
