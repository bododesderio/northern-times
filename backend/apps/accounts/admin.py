from django.contrib import admin
from django.contrib.auth.admin import UserAdmin as BaseUserAdmin

from .models import ActiveSession, Role, User


@admin.register(Role)
class RoleAdmin(admin.ModelAdmin):
    list_display = ('name', 'level', 'permissions')
    search_fields = ('name',)
    ordering = ('level',)


@admin.register(User)
class UserAdmin(BaseUserAdmin):
    list_display = ('email', 'display_name', 'role', 'is_active', 'created_at')
    list_filter = ('is_active', 'is_staff', 'role')
    search_fields = ('email', 'display_name', 'username')
    ordering = ('-created_at',)

    fieldsets = (
        (None, {'fields': ('email', 'username', 'password')}),
        ('Profile', {'fields': ('display_name', 'bio', 'avatar_url', 'social_links')}),
        ('Role', {'fields': ('role',)}),
        ('Permissions', {'fields': ('is_active', 'is_staff', 'is_superuser', 'groups', 'user_permissions')}),
        ('Dates', {'fields': ('last_login',)}),
    )

    add_fieldsets = (
        (None, {
            'classes': ('wide',),
            'fields': ('email', 'username', 'password1', 'password2', 'role'),
        }),
    )


@admin.register(ActiveSession)
class ActiveSessionAdmin(admin.ModelAdmin):
    list_display = ('user', 'ip_address', 'last_activity_at', 'created_at')
    list_filter = ('last_activity_at',)
    search_fields = ('user__email', 'ip_address')
    readonly_fields = ('session_id', 'user', 'ip_address', 'user_agent', 'last_activity_at', 'created_at')
