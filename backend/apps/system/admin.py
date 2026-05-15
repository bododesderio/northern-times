from django.contrib import admin

from .models import (
    ContactMessage, CronRun, DbBackup, ImageHealthLog,
    LoginQuote, Notification, PasswordReset, PolicyPage, SystemLog,
)


@admin.register(SystemLog)
class SystemLogAdmin(admin.ModelAdmin):
    list_display = ('action', 'user', 'ip_address', 'created_at')
    list_filter = ('created_at',)
    search_fields = ('action',)
    readonly_fields = ('created_at',)


@admin.register(CronRun)
class CronRunAdmin(admin.ModelAdmin):
    list_display = ('name', 'status', 'items_processed', 'started_at', 'finished_at')
    list_filter = ('status', 'name')
    readonly_fields = ('started_at', 'finished_at')


@admin.register(DbBackup)
class DbBackupAdmin(admin.ModelAdmin):
    list_display = ('filename', 'size', 'created_at')
    readonly_fields = ('created_at',)


@admin.register(PolicyPage)
class PolicyPageAdmin(admin.ModelAdmin):
    list_display = ('title', 'slug', 'is_published', 'show_in_footer', 'sort_order', 'updated_at')
    list_filter = ('is_published',)
    list_editable = ('is_published', 'show_in_footer', 'sort_order')
    search_fields = ('title', 'content')
    prepopulated_fields = {'slug': ('title',)}


@admin.register(LoginQuote)
class LoginQuoteAdmin(admin.ModelAdmin):
    list_display = ('quote_short', 'author', 'is_active')
    list_filter = ('is_active',)

    def quote_short(self, obj):
        return obj.quote[:60] + '...' if len(obj.quote) > 60 else obj.quote
    quote_short.short_description = 'Quote'


@admin.register(Notification)
class NotificationAdmin(admin.ModelAdmin):
    list_display = ('user', 'title', 'is_read', 'created_at')
    list_filter = ('is_read',)
    search_fields = ('title',)
    readonly_fields = ('created_at',)


@admin.register(ImageHealthLog)
class ImageHealthLogAdmin(admin.ModelAdmin):
    list_display = ('article', 'url', 'is_healthy', 'status_code', 'checked_at')
    list_filter = ('is_healthy',)
    readonly_fields = ('checked_at',)


@admin.register(ContactMessage)
class ContactMessageAdmin(admin.ModelAdmin):
    list_display = ('name', 'email', 'subject', 'created_at')
    search_fields = ('name', 'email', 'subject')
    readonly_fields = ('created_at',)


@admin.register(PasswordReset)
class PasswordResetAdmin(admin.ModelAdmin):
    list_display = ('user', 'used_at', 'expires_at', 'created_at')
    readonly_fields = ('created_at',)
