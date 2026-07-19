import uuid

from django.contrib import messages
from django.contrib.auth import authenticate, login, logout
from django.contrib.auth.decorators import login_required
from django.contrib.auth.tokens import default_token_generator
from django.core.mail import send_mail
from django.shortcuts import get_object_or_404, redirect, render
from django.utils.http import url_has_allowed_host_and_scheme
from django.views.decorators.http import require_POST
from django.utils.encoding import force_bytes, force_str
from django.utils.http import urlsafe_base64_decode, urlsafe_base64_encode

from collections import OrderedDict

from .decorators import role_required
from .models import Role, User


# ---------------------------------------------------------------------------
# Permission categories for role form
# ---------------------------------------------------------------------------

PERMISSION_CATEGORIES = OrderedDict([
    ('Articles', [
        ('create_articles', 'Create articles'),
        ('edit_own_articles', 'Edit own articles'),
        ('edit_all_articles', 'Edit all articles'),
        ('delete_articles', 'Delete articles'),
        ('publish_articles', 'Publish articles'),
        ('archive_articles', 'Archive articles'),
    ]),
    ('Categories', [
        ('manage_categories', 'Manage categories'),
    ]),
    ('Comments', [
        ('moderate_comments', 'Moderate comments'),
        ('delete_comments', 'Delete comments'),
    ]),
    ('Media', [
        ('upload_media', 'Upload media'),
        ('manage_media', 'Manage media library'),
    ]),
    ('Newsletter', [
        ('compose_newsletter', 'Compose newsletters'),
        ('send_newsletter', 'Send newsletters'),
        ('manage_subscribers', 'Manage subscribers'),
    ]),
    ('Analytics', [
        ('view_analytics', 'View analytics'),
    ]),
    ('Ads & Popups', [
        ('manage_ads', 'Manage ad placements'),
        ('manage_popups', 'Manage popups'),
    ]),
    ('Crawler', [
        ('manage_sources', 'Manage crawl sources'),
        ('run_crawler', 'Run crawler'),
        ('view_crawl_logs', 'View crawl logs'),
    ]),
    ('AI Rewriter', [
        ('manage_rewriter', 'Manage rewriter'),
    ]),
    ('SEO', [
        ('run_seo_audit', 'Run SEO audit'),
    ]),
    ('Social', [
        ('manage_social', 'Manage social monitor'),
    ]),
    ('Webhooks', [
        ('manage_webhooks', 'Manage webhooks'),
    ]),
    ('Users & Roles', [
        ('view_users', 'View users'),
        ('manage_users', 'Manage users'),
        ('manage_roles', 'Manage roles'),
    ]),
    ('System', [
        ('manage_settings', 'Manage settings'),
        ('manage_backups', 'Manage backups'),
        ('view_logs', 'View system logs'),
        ('danger_zone', 'Danger zone operations'),
    ]),
])


# ---------------------------------------------------------------------------
# Authentication views
# ---------------------------------------------------------------------------

def login_view(request):
    """GET: render login form. POST: authenticate and redirect."""
    if request.method == 'POST':
        email = request.POST.get('email', '').strip()
        password = request.POST.get('password', '')
        user = authenticate(request, username=email, password=password)
        if user is not None:
            login(request, user)
            next_url = request.GET.get('next', '/admin/')
            if not url_has_allowed_host_and_scheme(next_url, allowed_hosts={request.get_host()}):
                next_url = '/admin/'
            return redirect(next_url)
        messages.error(request, 'Invalid email or password.')
    return render(request, 'admin/editorial_login.html')


@require_POST
def logout_view(request):
    """Log out the current user and redirect to login."""
    logout(request)
    messages.success(request, 'You have been logged out.')
    return redirect('admin_login')


def forgot_password(request):
    """Password reset request — sends email with reset link."""
    if request.method == 'POST':
        email = request.POST.get('email', '').strip()
        try:
            user = User.objects.get(email=email)
        except User.DoesNotExist:
            # Don't reveal whether a user exists
            messages.success(request, 'If that email exists, a reset link has been sent.')
            return redirect('admin_forgot_password')

        uid = urlsafe_base64_encode(force_bytes(str(user.pk)))
        token = default_token_generator.make_token(user)
        reset_url = request.build_absolute_uri(f'/accounts/reset-password/{uid}/{token}/')

        send_mail(
            subject='Password Reset — Northern Times',
            message=f'Click the link to reset your password:\n\n{reset_url}',
            from_email=None,  # uses DEFAULT_FROM_EMAIL
            recipient_list=[user.email],
            fail_silently=True,
        )
        messages.success(request, 'If that email exists, a reset link has been sent.')
        return redirect('admin_forgot_password')

    return render(request, 'admin/forgot_password.html')


def reset_password(request, token):
    """Password reset with token (token format: uid/token)."""
    # The URL captures a combined token; split uid and actual token
    parts = token.rsplit('/', 1) if '/' in token else (None, None)
    if len(parts) != 2:
        messages.error(request, 'Invalid reset link.')
        return redirect('admin_forgot_password')

    uid_b64, actual_token = parts
    try:
        uid = force_str(urlsafe_base64_decode(uid_b64))
        user = User.objects.get(pk=uuid.UUID(uid))
    except (User.DoesNotExist, ValueError, TypeError):
        messages.error(request, 'Invalid reset link.')
        return redirect('admin_forgot_password')

    if not default_token_generator.check_token(user, actual_token):
        messages.error(request, 'This reset link has expired.')
        return redirect('admin_forgot_password')

    if request.method == 'POST':
        password = request.POST.get('password', '')
        password_confirm = request.POST.get('password_confirm', '')
        if password and password == password_confirm:
            user.set_password(password)
            user.save()
            messages.success(request, 'Password updated. Please log in.')
            return redirect('admin_login')
        messages.error(request, 'Passwords do not match.')

    return render(request, 'admin/reset_password.html', {'token': token})


# ---------------------------------------------------------------------------
# Admin — User management
# ---------------------------------------------------------------------------

@login_required
@role_required(2)  # editor+
def user_list(request):
    """List all users."""
    users = User.objects.select_related('role').all()
    return render(request, 'admin/users/index.html', {
        'users': users,
        'active_nav': 'users',
        'page_title': 'Users',
    })


@login_required
@role_required(3)  # super_admin
def user_create(request):
    """Create a new user."""
    roles = Role.objects.all()
    if request.method == 'POST':
        email = request.POST.get('email', '').strip()
        username = request.POST.get('username', '').strip()
        password = request.POST.get('password', '')
        display_name = request.POST.get('display_name', '').strip()
        # Form field is ``role_id``; keep ``role`` as a legacy fallback.
        role_id = request.POST.get('role_id') or request.POST.get('role')

        if User.objects.filter(email=email).exists():
            messages.error(request, 'A user with this email already exists.')
        else:
            user = User.objects.create_user(
                email=email,
                username=username or email.split('@')[0],
                password=password,
            )
            user.display_name = display_name
            if role_id:
                user.role_id = role_id
            user.save()
            messages.success(request, f'User {email} created.')
            return redirect('admin_users')

    return render(request, 'admin/users/form.html', {
        'roles': roles,
        'action': 'Create',
        'active_nav': 'users',
        'page_title': 'Create User',
    })


@login_required
@role_required(3)  # super_admin
def user_edit(request, pk):
    """Edit an existing user."""
    user = get_object_or_404(User, pk=pk)
    roles = Role.objects.all()

    if request.method == 'POST':
        user.email = request.POST.get('email', user.email).strip()
        user.username = request.POST.get('username', user.username).strip()
        # Only override when the form actually submitted the field (the slim form
        # omits display_name/bio — don't wipe them on every save).
        if 'display_name' in request.POST:
            user.display_name = request.POST.get('display_name', '').strip()
        if 'bio' in request.POST:
            user.bio = request.POST.get('bio', '')
        role_id = request.POST.get('role_id') or request.POST.get('role')
        user.role_id = role_id if role_id else None

        password = request.POST.get('password', '')
        if password:
            user.set_password(password)

        user.save()
        messages.success(request, f'User {user.email} updated.')
        return redirect('admin_users')

    return render(request, 'admin/users/form.html', {
        'edit_user': user,
        'roles': roles,
        'action': 'Edit',
        'active_nav': 'users',
        'page_title': 'Edit User',
    })


@login_required
@role_required(3)  # super_admin
def user_delete(request, pk):
    """Delete a user."""
    user = get_object_or_404(User, pk=pk)
    if request.method == 'POST':
        email = user.email
        user.delete()
        messages.success(request, f'User {email} deleted.')
        return redirect('admin_users')
    return render(request, 'admin/users/form.html', {
        'delete_user': user,
        'active_nav': 'users',
        'page_title': 'Delete User',
    })


# ---------------------------------------------------------------------------
# Admin — Role management
# ---------------------------------------------------------------------------

@login_required
@role_required(3)  # super_admin
def role_list(request):
    """List all roles."""
    roles = Role.objects.all()
    return render(request, 'admin/roles/index.html', {
        'roles': roles,
        'active_nav': 'roles',
        'page_title': 'Roles',
    })


@login_required
@role_required(3)  # super_admin
def role_create(request):
    """Create a new role."""
    if request.method == 'POST':
        name = request.POST.get('name', '').strip()
        try:
            level = int(request.POST.get('level', 1))
        except (ValueError, TypeError):
            level = 1
        permissions = request.POST.getlist('permissions')

        if Role.objects.filter(name=name).exists():
            messages.error(request, 'A role with this name already exists.')
        else:
            Role.objects.create(name=name, level=level, permissions=permissions)
            messages.success(request, f'Role "{name}" created.')
            return redirect('admin_roles')

    return render(request, 'admin/roles/form.html', {
        'action': 'Create',
        'active_nav': 'roles',
        'page_title': 'Create Role',
        'permission_categories': PERMISSION_CATEGORIES,
        'role_permissions': [],
    })


@login_required
@role_required(3)  # super_admin
def role_edit(request, pk):
    """Edit an existing role."""
    role = get_object_or_404(Role, pk=pk)

    if request.method == 'POST':
        role.name = request.POST.get('name', role.name).strip()
        try:
            role.level = int(request.POST.get('level', role.level))
        except (ValueError, TypeError):
            role.level = role.level
        role.permissions = request.POST.getlist('permissions')
        role.save()
        messages.success(request, f'Role "{role.name}" updated.')
        return redirect('admin_roles')

    return render(request, 'admin/roles/form.html', {
        'role': role,
        'is_edit': True,
        'action': 'Edit',
        'active_nav': 'roles',
        'page_title': 'Edit Role',
        'permission_categories': PERMISSION_CATEGORIES,
        'role_permissions': role.permissions if isinstance(role.permissions, list) else [],
    })


@login_required
@role_required(3)
@require_POST
def role_delete(request, pk):
    """Delete a role (prevents deleting roles with assigned users)."""
    role = get_object_or_404(Role, pk=pk)
    if role.users.exists():
        messages.error(request, f'Cannot delete role "{role.name}" — it has assigned users.')
        return redirect('admin_roles')
    name = role.name
    role.delete()
    messages.success(request, f'Role "{name}" deleted.')
    return redirect('admin_roles')


# ---------------------------------------------------------------------------
# Profile
# ---------------------------------------------------------------------------

@login_required
def profile(request):
    """View/edit the current user's profile."""
    if request.method == 'POST':
        request.user.display_name = request.POST.get('display_name', '').strip()
        request.user.bio = request.POST.get('bio', '')
        request.user.avatar_url = request.POST.get('avatar_url', '').strip()

        password = request.POST.get('password', '')
        password_confirm = request.POST.get('password_confirm', '')
        if password:
            if password == password_confirm:
                request.user.set_password(password)
                messages.success(request, 'Password updated. Please log in again.')
            else:
                messages.error(request, 'Passwords do not match.')
                return render(request, 'admin/profile.html', {
                    'active_nav': 'profile',
                    'page_title': 'My Profile',
                })

        request.user.save()
        messages.success(request, 'Profile updated.')
        return redirect('admin_profile')

    return render(request, 'admin/profile.html', {
        'active_nav': 'profile',
        'page_title': 'My Profile',
    })
