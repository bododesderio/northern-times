from django.contrib import admin

from .models import AdSlot, Popup


@admin.register(AdSlot)
class AdSlotAdmin(admin.ModelAdmin):
    list_display = ['name', 'slot_name', 'is_active', 'device_targeting', 'impressions', 'clicks']
    list_filter = ['is_active', 'device_targeting']
    search_fields = ['name', 'slot_name']
    readonly_fields = ['created_at', 'updated_at']


@admin.register(Popup)
class PopupAdmin(admin.ModelAdmin):
    list_display = ['name', 'type', 'banner_style', 'is_active', 'frequency', 'impressions', 'clicks', 'variant']
    list_filter = ['is_active', 'type', 'banner_style', 'frequency', 'device_targeting', 'target_audience']
    search_fields = ['name', 'title']
    readonly_fields = ['created_at', 'updated_at', 'impressions', 'clicks']
    fieldsets = [
        (None, {'fields': ('name', 'type', 'is_active', 'variant')}),
        ('Content', {'fields': ('title', 'body', 'content', 'image_url')}),
        ('Display', {'fields': ('banner_style', 'position', 'bg_color', 'text_color', 'overlay_opacity')}),
        ('Buttons', {'fields': ('button_text', 'button_url', 'btn_bg_color', 'btn_text_color', 'secondary_btn_text', 'has_email_field')}),
        ('Trigger & Timing', {'fields': ('trigger_type', 'trigger_value', 'show_delay', 'close_delay')}),
        ('Frequency', {'fields': ('frequency', 'frequency_days', 'version')}),
        ('Targeting', {'fields': ('device_targeting', 'target_audience', 'target_pages')}),
        ('Stats', {'fields': ('impressions', 'clicks')}),
        ('Timestamps', {'fields': ('created_at', 'updated_at')}),
    ]
