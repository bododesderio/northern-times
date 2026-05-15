from django.urls import path

from . import views

urlpatterns = [
    path('social/', views.keywords, name='admin_social'),
    path('social/keyword-toggle/<uuid:pk>/', views.keyword_toggle, name='admin_social_keyword_toggle'),
    path('social/mentions/', views.mentions, name='admin_social_mentions'),
    path('social/settings/', views.social_settings, name='admin_social_settings'),
    path('social/post-log/', views.post_log, name='admin_social_post_log'),
]
