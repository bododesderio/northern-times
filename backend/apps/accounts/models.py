import uuid

from django.conf import settings
from django.contrib.auth.models import AbstractUser
from django.db import models

from .managers import UserManager


class Role(models.Model):
    """Role model for RBAC with permission checking."""

    AUTHOR = 'author'
    EDITOR = 'editor'
    SUPER_ADMIN = 'super_admin'

    SYSTEM_ROLES = {
        AUTHOR: 1,
        EDITOR: 2,
        SUPER_ADMIN: 3,
    }

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    name = models.CharField(max_length=50, unique=True)
    level = models.IntegerField(
        default=1,
        help_text='Role hierarchy level. Higher = more privileges.',
    )
    permissions = models.JSONField(
        default=list,
        blank=True,
        help_text='List of permission keys. Use "*" for full access.',
    )

    class Meta:
        ordering = ['level']

    def __str__(self):
        return self.name

    def has_permission(self, perm_key):
        """Check if this role has a specific permission.

        Supports wildcard "*" for full access.
        """
        if not isinstance(self.permissions, list):
            return False
        if '*' in self.permissions:
            return True
        return perm_key in self.permissions


class User(AbstractUser):
    """Custom user model with UUID primary key and role-based access."""

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    email = models.EmailField('email address', unique=True)
    display_name = models.CharField(max_length=150, blank=True)
    bio = models.TextField(blank=True)
    avatar_url = models.URLField(max_length=500, blank=True)
    social_links = models.JSONField(default=dict, blank=True)
    role = models.ForeignKey(
        Role,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='users',
    )
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    USERNAME_FIELD = 'email'
    REQUIRED_FIELDS = ['username']

    objects = UserManager()

    class Meta:
        ordering = ['-created_at']

    def __str__(self):
        return self.display_name or self.email

    def get_role_level(self):
        """Return the user's role level, or 0 if no role assigned."""
        if self.role:
            return self.role.level
        return 0

    def has_role_permission(self, perm_key):
        """Check if the user's role grants a specific permission."""
        if self.role:
            return self.role.has_permission(perm_key)
        return False


class ActiveSession(models.Model):
    """Tracks active user sessions for security and audit purposes."""

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    session_id = models.CharField(max_length=255, unique=True)
    user = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.CASCADE,
        related_name='active_sessions',
    )
    ip_address = models.GenericIPAddressField(null=True, blank=True)
    user_agent = models.TextField(blank=True)
    last_activity_at = models.DateTimeField(auto_now=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['-last_activity_at']

    def __str__(self):
        return f'{self.user} - {self.ip_address} ({self.session_id[:8]}...)'
