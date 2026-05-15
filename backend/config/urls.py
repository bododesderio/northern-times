from django.contrib import admin
from django.urls import path, include
from django.conf import settings
from django.conf.urls.static import static
from drf_spectacular.views import SpectacularAPIView, SpectacularSwaggerView

urlpatterns = [
    # Django built-in admin (developer access)
    path('django-admin/', admin.site.urls),

    # Custom admin panel (editorial UI)
    path('admin/', include('apps.accounts.urls_auth')),
    path('admin/', include('apps.articles.urls_admin')),
    path('admin/', include('apps.crawler.urls')),
    path('admin/', include('apps.rewriter.urls')),
    path('admin/', include('apps.media.urls')),
    path('admin/', include('apps.newsletter.urls_admin')),
    path('admin/', include('apps.analytics.urls')),
    path('admin/', include('apps.ads.urls')),
    path('admin/', include('apps.seo.urls')),
    path('admin/', include('apps.social.urls')),
    path('admin/', include('apps.webhooks.urls')),
    path('admin/', include('apps.system.urls')),
    path('admin/', include('apps.core.urls_admin')),

    # Public API endpoints
    path('api/', include('apps.articles.urls_api')),
    path('api/', include('apps.newsletter.urls_api')),
    path('api/', include('apps.analytics.urls_api')),
    path('api/', include('apps.ads.urls_api')),
    path('api/', include('apps.core.urls_api')),

    # API documentation
    path('api/schema/', SpectacularAPIView.as_view(), name='schema'),
    path('api/docs/', SpectacularSwaggerView.as_view(url_name='schema'), name='swagger-ui'),

    # Public frontend pages (catch-all, must be last)
    path('', include('apps.articles.urls_frontend')),
]

if settings.DEBUG:
    urlpatterns += static(settings.MEDIA_URL, document_root=settings.MEDIA_ROOT)

# Custom error handlers
handler403 = 'apps.core.error_handlers.handler403'
handler404 = 'apps.core.error_handlers.handler404'
handler500 = 'apps.core.error_handlers.handler500'
