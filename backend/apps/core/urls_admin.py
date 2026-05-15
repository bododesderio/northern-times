from django.urls import path

from . import views

urlpatterns = [
    path('settings/', views.admin_settings, name='admin_settings'),
    path('settings/update/', views.admin_settings_update, name='admin_settings_update'),
]
