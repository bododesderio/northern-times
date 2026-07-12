from django.urls import path

from . import views

app_name = 'core_api'
urlpatterns = [
    path('health/', views.health_check, name='health'),
    path('geo/', views.geo_lookup, name='geo'),
    path('reader-location/', views.set_reader_location, name='set_reader_location'),
]
