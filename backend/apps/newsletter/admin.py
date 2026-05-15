from django.contrib import admin

from .models import EmailQueue, NewsletterIssue, PushSubscription, Subscriber


@admin.register(Subscriber)
class SubscriberAdmin(admin.ModelAdmin):
    list_display = ['email', 'status', 'source', 'created_at']
    list_filter = ['status', 'source']
    search_fields = ['email']
    readonly_fields = ['created_at']


@admin.register(NewsletterIssue)
class NewsletterIssueAdmin(admin.ModelAdmin):
    list_display = ['subject', 'status', 'recipient_count', 'scheduled_at', 'sent_at', 'created_at']
    list_filter = ['status']
    search_fields = ['subject']
    readonly_fields = ['created_at']


@admin.register(PushSubscription)
class PushSubscriptionAdmin(admin.ModelAdmin):
    list_display = ['endpoint', 'created_at']
    search_fields = ['endpoint']
    readonly_fields = ['created_at']


@admin.register(EmailQueue)
class EmailQueueAdmin(admin.ModelAdmin):
    list_display = ['to_email', 'subject', 'status', 'attempts', 'created_at', 'sent_at']
    list_filter = ['status']
    search_fields = ['to_email', 'subject']
    readonly_fields = ['created_at']
