from django.contrib import admin

from .models import SeoAudit, SeoIssue


@admin.register(SeoAudit)
class SeoAuditAdmin(admin.ModelAdmin):
    list_display = ['id', 'score', 'critical_count', 'warning_count', 'info_count', 'pages_scanned', 'created_at']
    readonly_fields = ['created_at']


@admin.register(SeoIssue)
class SeoIssueAdmin(admin.ModelAdmin):
    list_display = ['audit', 'issue_type', 'severity', 'url', 'created_at']
    list_filter = ['severity', 'issue_type']
    readonly_fields = ['created_at']
