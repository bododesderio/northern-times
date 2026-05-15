from django.contrib import admin

from .models import SocialKeyword, SocialMention


@admin.register(SocialKeyword)
class SocialKeywordAdmin(admin.ModelAdmin):
    list_display = ['keyword', 'is_active', 'created_at']
    list_filter = ['is_active']
    search_fields = ['keyword']


@admin.register(SocialMention)
class SocialMentionAdmin(admin.ModelAdmin):
    list_display = ['keyword', 'source', 'sentiment', 'mentioned_at', 'created_at']
    list_filter = ['source', 'sentiment']
    search_fields = ['text', 'title']
