from django.contrib import admin

from .models import ArticleView, DailyStats, SiteVisitor


@admin.register(SiteVisitor)
class SiteVisitorAdmin(admin.ModelAdmin):
    list_display = ['ip_address', 'country', 'city', 'device_type', 'visit_date']
    list_filter = ['country', 'device_type', 'visit_date']
    search_fields = ['ip_address', 'country', 'city']
    readonly_fields = ['created_at']


@admin.register(ArticleView)
class ArticleViewAdmin(admin.ModelAdmin):
    list_display = ['article', 'visitor_ip', 'viewed_at']
    readonly_fields = ['viewed_at']


@admin.register(DailyStats)
class DailyStatsAdmin(admin.ModelAdmin):
    list_display = ['date', 'total_views', 'unique_visitors', 'new_articles', 'new_comments', 'new_subscribers']
    readonly_fields = ['created_at']
