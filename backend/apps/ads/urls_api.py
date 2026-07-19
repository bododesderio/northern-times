from django.urls import path

from . import views

app_name = 'ads_api'

# NOTE: this module is included under the 'api/' prefix in config/urls.py, so the
# paths here must NOT repeat 'api/' — otherwise they resolve to '/api/api/...' and
# the frontend's '/api/popups/' fetch 404s (popups/banners never load).
urlpatterns = [
    path('popups/', views.active_popups, name='active_popups'),
    path('popup-track/', views.popup_track, name='popup_track'),
    path('ad-track/', views.ad_track, name='ad_track'),
]
