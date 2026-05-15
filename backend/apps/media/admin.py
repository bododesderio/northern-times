from django.contrib import admin

from .models import MediaItem


@admin.register(MediaItem)
class MediaItemAdmin(admin.ModelAdmin):
    list_display = ['original_filename', 'mime_type', 'size', 'storage_driver', 'created_at']
    list_filter = ['storage_driver', 'mime_type']
    search_fields = ['original_filename', 'alt_text']
    readonly_fields = ['created_at']
