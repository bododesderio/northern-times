import uuid

from django.conf import settings
from django.db import models


class MediaItem(models.Model):
    STORAGE_CHOICES = [
        ('local', 'Local'),
        ('s3', 'S3'),
    ]

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    filename = models.CharField(max_length=255)
    original_filename = models.CharField(max_length=255)
    path = models.CharField(max_length=500)
    mime_type = models.CharField(max_length=100)
    size = models.BigIntegerField()
    width = models.IntegerField(null=True, blank=True)
    height = models.IntegerField(null=True, blank=True)
    hash = models.CharField(max_length=64)
    alt_text = models.CharField(max_length=500, blank=True, default='')
    storage_driver = models.CharField(max_length=10, choices=STORAGE_CHOICES, default='local')
    folder = models.CharField(max_length=255, blank=True, default='')
    uploaded_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='uploaded_media',
    )
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['-created_at']
        verbose_name_plural = 'media items'

    def __str__(self):
        return self.original_filename
