from django.contrib import admin

from .models import Webhook, WebhookLog


@admin.register(Webhook)
class WebhookAdmin(admin.ModelAdmin):
    list_display = ['url', 'is_active', 'created_at']
    list_filter = ['is_active']
    search_fields = ['url']
    readonly_fields = ['created_at']


@admin.register(WebhookLog)
class WebhookLogAdmin(admin.ModelAdmin):
    list_display = ['webhook', 'event', 'response_code', 'created_at']
    list_filter = ['event', 'response_code']
    readonly_fields = ['created_at']
