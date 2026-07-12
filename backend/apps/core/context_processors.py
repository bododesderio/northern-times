import logging

from django.conf import settings as django_settings

from .models import Setting

logger = logging.getLogger(__name__)


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


def location(request):
    """Reader-location context for local-news personalization.

    `location_mode` (off | auto | manual) gates the client-side geo label;
    `reader_location` is the server-resolved {lat, lon, source, city} or None.
    """
    loc = None
    try:
        # Session-only here (allow_ip=False) so a GeoIP call never runs on every
        # page — the Local News view opts into the IP fallback when it ranks.
        from apps.articles.services.geo import reader_location
        loc = reader_location(request, allow_ip=False)
    except Exception:
        logger.exception('location context: reader_location failed')
        loc = None
    return {
        'location_mode': Setting.get('location_mode', 'auto'),
        'reader_location': loc,
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
    from django.core.cache import cache
    from django.db.models import Count, Q
    from django.utils import timezone

    from apps.articles.models import Category
    from apps.system.models import PolicyPage

    # This runs on every request; the "which categories have articles" answer
    # changes slowly, so cache it briefly to avoid two aggregate scans per page.
    cached_nav = cache.get('nav_categories_v1')
    if cached_nav is not None:
        nav_categories, sidebar_categories = cached_nav
    else:
        try:
            # Only surface categories that actually have published articles, so
            # empty topics (e.g. a not-yet-populated Environment) don't render as
            # dead nav links. They reappear once an article lands in them.
            published = Q(
                articles__status='published',
                articles__deleted_at__isnull=True,
                articles__published_at__lte=timezone.now(),
            )
            with_counts = Category.objects.annotate(
                n_published=Count('articles', filter=published)
            ).filter(n_published__gt=0)
            nav_categories = list(
                with_counts.filter(show_in_nav=True).order_by('sort_order', 'name'))
            sidebar_categories = list(
                with_counts.filter(show_in_sidebar=True).order_by('sort_order', 'name'))
            cache.set('nav_categories_v1', (nav_categories, sidebar_categories), 120)
        except Exception:
            logger.exception('navigation context: category query failed')
            nav_categories = []
            sidebar_categories = []

    try:
        # Exclude 'about' — the footer already has a dedicated About Us link, so
        # an About Us policy page would render twice in the Company column.
        footer_policies = list(
            PolicyPage.objects.filter(is_published=True, show_in_footer=True)
            .exclude(slug='about').order_by('sort_order'))
    except Exception:
        logger.exception('navigation context: footer_policies query failed')
        footer_policies = []

    # Most-read list for the editorial sidebar (cached; cheap query).
    most_read = cache.get('most_read_v1')
    if most_read is None:
        try:
            from apps.articles.models import Article
            most_read = list(
                Article.objects.published().order_by('-views', '-published_at')
                .only('slug', 'title')[:5])
        except Exception:
            logger.exception('navigation context: most_read query failed')
            most_read = []
        cache.set('most_read_v1', most_read, 300)

    return {
        'nav_categories': nav_categories,
        'sidebar_categories': sidebar_categories,
        'footer_policies': footer_policies,
        'most_read': most_read,
    }
