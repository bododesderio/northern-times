import uuid

from django.db import models


class SocialKeyword(models.Model):
    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    keyword = models.CharField(max_length=255)
    is_active = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['keyword']

    def __str__(self):
        return self.keyword


class SocialMention(models.Model):
    SENTIMENT_CHOICES = [
        ('positive', 'Positive'),
        ('negative', 'Negative'),
        ('neutral', 'Neutral'),
    ]

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    keyword = models.ForeignKey(
        SocialKeyword,
        on_delete=models.CASCADE,
        related_name='mentions',
    )
    source = models.CharField(max_length=100)
    title = models.CharField(max_length=500, blank=True, default='')
    text = models.TextField()
    url = models.URLField(max_length=500, blank=True, default='')
    sentiment = models.CharField(max_length=10, choices=SENTIMENT_CHOICES, default='neutral')
    mentioned_at = models.DateTimeField()
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['-mentioned_at']

    def __str__(self):
        return f'{self.keyword} — {self.source} @ {self.mentioned_at:%Y-%m-%d}'
