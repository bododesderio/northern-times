import json
import uuid

from django.core.cache import cache
from django.db import models


CACHE_PREFIX = 'site_setting:'
CACHE_ALL_KEY = 'site_settings:all'
CACHE_TTL = 60 * 60  # 1 hour


class Setting(models.Model):
    """Key-value site settings store with typed values and caching."""

    TYPE_CHOICES = [
        ('string', 'String'),
        ('bool', 'Boolean'),
        ('int', 'Integer'),
        ('json', 'JSON'),
    ]

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    key = models.CharField(max_length=255, unique=True, db_index=True)
    value = models.TextField(blank=True, default='')
    type = models.CharField(max_length=10, choices=TYPE_CHOICES, default='string')
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'site_settings'
        ordering = ['key']
        verbose_name = 'Site Setting'
        verbose_name_plural = 'Site Settings'

    def __str__(self):
        return f'{self.key} = {self.value}'

    def _cast_value(self):
        """Cast the stored string value to its declared type."""
        if self.type == 'bool':
            return self.value.lower() in ('1', 'true', 'yes', 'on')
        if self.type == 'int':
            try:
                return int(self.value)
            except (ValueError, TypeError):
                return 0
        if self.type == 'json':
            try:
                return json.loads(self.value)
            except (json.JSONDecodeError, TypeError):
                return None
        return self.value

    @classmethod
    def get(cls, key, default=None):
        """Get a setting value by key, reading from cache first."""
        cache_key = f'{CACHE_PREFIX}{key}'
        cached = cache.get(cache_key)
        if cached is not None:
            return cached

        try:
            setting = cls.objects.get(key=key)
            value = setting._cast_value()
            cache.set(cache_key, value, CACHE_TTL)
            return value
        except cls.DoesNotExist:
            return default

    @classmethod
    def set(cls, key, value, type='string'):
        """Set a setting value, updating DB and invalidating cache."""
        if type == 'json' and not isinstance(value, str):
            value = json.dumps(value)
        elif type == 'bool':
            value = '1' if value else '0'
        else:
            value = str(value)

        setting, _created = cls.objects.update_or_create(
            key=key,
            defaults={'value': value, 'type': type},
        )
        # Invalidate caches
        cache.delete(f'{CACHE_PREFIX}{key}')
        cache.delete(CACHE_ALL_KEY)
        return setting

    @classmethod
    def get_all(cls):
        """Return a dict of all settings (cached)."""
        cached = cache.get(CACHE_ALL_KEY)
        if cached is not None:
            return cached

        settings = {}
        for setting in cls.objects.all():
            settings[setting.key] = setting._cast_value()

        cache.set(CACHE_ALL_KEY, settings, CACHE_TTL)
        return settings
