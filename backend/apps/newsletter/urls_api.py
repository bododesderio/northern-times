from django.urls import path

from . import views

app_name = 'newsletter_api'

urlpatterns = [
    path('api/newsletter/subscribe/', views.subscribe, name='subscribe'),
]
