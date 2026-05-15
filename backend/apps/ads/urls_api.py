from django.urls import path

from . import views

app_name = 'ads_api'

urlpatterns = [
    path('api/popups/', views.active_popups, name='active_popups'),
    path('api/popup-track/', views.popup_track, name='popup_track'),
    path('api/ad-track/', views.ad_track, name='ad_track'),
]
