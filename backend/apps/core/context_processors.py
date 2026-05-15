from django.conf import settings as django_settings

from .models import Setting


def site_settings(request):
    """Inject common site settings into every template context."""
    site_logo = Setting.get('site_logo', '')
    site_name = Setting.get('site_name', 'Northern Times')
    return {
        'site_name': site_name,
        'site_title': site_name,  # alias used by admin templates
        'site_tagline': Setting.get('site_tagline', ''),
        'site_logo': site_logo,
        'site_logo_url': site_logo or '',
        'site_description': Setting.get('site_description', ''),
        'site_abbreviation': Setting.get('site_abbreviation', 'NT'),
        'theme_mode': Setting.get('theme_mode', 'light'),
        'theme_accent': Setting.get('theme_accent', '#cc0000'),
        'favicon_url': Setting.get('favicon_url', ''),
        'favicon_mime': Setting.get('favicon_mime', 'image/svg+xml'),
        'og_default_image': Setting.get('og_default_image', ''),
        'twitter_handle': Setting.get('twitter_handle', ''),
    }


def branding(request):
    """Inject application branding from Django settings."""
    return {
        'app_name': getattr(django_settings, 'APP_NAME', 'Northern Times'),
        'app_version': getattr(django_settings, 'APP_VERSION', '1.0.0'),
        'app_url': getattr(django_settings, 'APP_URL', ''),
    }


def ads(request):
    """Inject active ad slots into every template context."""
    from apps.ads.models import AdSlot
    try:
        return {'ads': AdSlot.objects.filter(is_active=True)}
    except Exception:
        return {'ads': None}


def navigation(request):
    """Inject navigation categories for header, sidebar, and mobile drawer."""
    from apps.articles.models import Category
    from apps.system.models import PolicyPage

    try:
        nav_categories = list(Category.objects.filter(show_in_nav=True).order_by('sort_order', 'name'))
        sidebar_categories = list(Category.objects.filter(show_in_sidebar=True).order_by('sort_order', 'name'))
    except Exception:
        nav_categories = []
        sidebar_categories = []

    try:
        footer_policies = list(PolicyPage.objects.filter(is_published=True, show_in_footer=True).order_by('sort_order'))
    except Exception:
        footer_policies = []

    return {
        'nav_categories': nav_categories,
        'sidebar_categories': sidebar_categories,
        'footer_policies': footer_policies,
    }
