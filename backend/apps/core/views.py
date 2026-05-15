import shutil

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.cache import cache
from django.db import connection
from django.http import JsonResponse
from django.shortcuts import redirect, render

from apps.accounts.decorators import role_required
from .models import Setting


@login_required
@role_required(2)
def admin_settings(request):
    """Render the site-settings admin page with all settings grouped."""
    settings_qs = Setting.objects.order_by('key')
    return render(request, 'admin/settings.html', {
        'settings': settings_qs,
        'active_nav': 'settings',
        'page_title': 'Settings',
    })


@login_required
@role_required(2)
def admin_settings_update(request):
    """Handle site-settings update form submission."""
    if request.method == 'POST':
        for key in request.POST:
            if key.startswith('setting_'):
                setting_key = key[len('setting_'):]
                Setting.set(setting_key, request.POST[key])
        messages.success(request, 'Settings saved.')
        return redirect('admin_settings')
    return JsonResponse({'error': 'POST required'}, status=405)


def health_check(request):
    """Return JSON health status for DB, Redis, and disk."""
    status = {'status': 'ok'}

    # Database check
    try:
        with connection.cursor() as cursor:
            cursor.execute('SELECT 1')
        status['db'] = 'up'
    except Exception as exc:
        status['db'] = f'down: {exc}'
        status['status'] = 'degraded'

    # Redis / cache check
    try:
        cache.set('_health_check', '1', 5)
        if cache.get('_health_check') == '1':
            status['redis'] = 'up'
        else:
            status['redis'] = 'down: read-back failed'
            status['status'] = 'degraded'
    except Exception as exc:
        status['redis'] = f'down: {exc}'
        status['status'] = 'degraded'

    # Disk check
    try:
        usage = shutil.disk_usage('/')
        free_pct = round(usage.free / usage.total * 100, 1)
        status['disk'] = {'free_pct': free_pct, 'free_gb': round(usage.free / (1024**3), 2)}
        if free_pct < 5:
            status['status'] = 'degraded'
    except Exception as exc:
        status['disk'] = f'error: {exc}'

    http_status = 200 if status['status'] == 'ok' else 503
    return JsonResponse(status, status=http_status)


def geo_lookup(request):
    """Return the client IP and country via MaxMind GeoIP (with ip-api.com fallback)."""
    ip = (
        request.META.get('HTTP_X_FORWARDED_FOR', '').split(',')[0].strip()
        or request.META.get('REMOTE_ADDR', '')
    )
    geo = {}
    try:
        from apps.analytics.services.geoip import lookup
        geo = lookup(ip)
    except Exception:
        pass
    return JsonResponse({
        'ip': ip,
        'country': geo.get('country', None),
        'city': geo.get('city', None),
        'latitude': geo.get('latitude'),
        'longitude': geo.get('longitude'),
    })
