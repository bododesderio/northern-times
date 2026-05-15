from django.contrib import admin
from django.utils.html import format_html

from .models import CrawlLog, CrawlSource


@admin.register(CrawlSource)
class CrawlSourceAdmin(admin.ModelAdmin):
    list_display = [
        'name', 'region', 'is_active', 'use_browser', 'category',
        'crawl_interval', 'last_crawled_at', 'failures_display', 'articles_per_day',
    ]
    list_filter = ['feed_type', 'region', 'is_active', 'use_browser', 'category']
    list_editable = ['is_active', 'use_browser', 'crawl_interval']
    search_fields = ['name', 'url']
    readonly_fields = ['created_at', 'updated_at', 'last_crawled_at', 'last_new_content_at', 'next_crawl_at']
    fieldsets = (
        (None, {'fields': ('name', 'url', 'feed_type', 'region', 'category', 'is_active')}),
        ('Extraction', {'fields': ('content_selector', 'strip_selectors', 'attribution_text')}),
        ('Behavior', {'fields': ('crawl_interval', 'max_articles', 'auto_rewrite', 'editorial_review', 'download_images', 'nofollow', 'use_browser')}),
        ('Stats', {'fields': ('avg_articles_per_day', 'consecutive_empty', 'consecutive_failures', 'last_crawled_at', 'last_new_content_at', 'next_crawl_at')}),
        ('Meta', {'fields': ('created_at', 'updated_at')}),
    )

    def failures_display(self, obj):
        f = obj.consecutive_failures
        if f == 0:
            return format_html('<span style="color:#28a745">0</span>')
        if f < 3:
            return format_html('<span style="color:#ffc107;font-weight:600">{}</span>', f)
        return format_html('<span style="color:#dc3545;font-weight:600">{}</span>', f)
    failures_display.short_description = 'Failures'
    failures_display.admin_order_field = 'consecutive_failures'

    def articles_per_day(self, obj):
        return f'{obj.avg_articles_per_day:.1f}'
    articles_per_day.short_description = 'Avg/Day'
    articles_per_day.admin_order_field = 'avg_articles_per_day'


@admin.register(CrawlLog)
class CrawlLogAdmin(admin.ModelAdmin):
    list_display = ['source', 'status_display', 'found_count', 'new_count', 'duplicate_count', 'error_count', 'duration_display', 'created_at']
    list_filter = ['status', 'source', 'created_at']
    readonly_fields = ['created_at']
    date_hierarchy = 'created_at'

    def status_display(self, obj):
        if obj.status == 'ok':
            return format_html('<span style="color:#28a745;font-weight:600">OK</span>')
        return format_html('<span style="color:#dc3545;font-weight:600">ERROR</span>')
    status_display.short_description = 'Status'

    def duration_display(self, obj):
        if obj.duration_ms < 1000:
            return f'{obj.duration_ms}ms'
        return f'{obj.duration_ms / 1000:.1f}s'
    duration_display.short_description = 'Duration'
    duration_display.admin_order_field = 'duration_ms'
