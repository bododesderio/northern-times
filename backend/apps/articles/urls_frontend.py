from django.urls import path

from . import views
from apps.newsletter.views import subscribe as newsletter_subscribe_view

app_name = 'articles_frontend'
urlpatterns = [
    path('', views.home, name='home'),
    path('category/<slug:slug>/', views.category, name='category'),
    path('article/<slug:slug>/', views.article, name='article'),
    path('author/<str:username>/', views.author, name='author'),
    path('tag/<slug:slug>/', views.tag, name='tag'),
    path('search/', views.search, name='search'),
    path('about/', views.about, name='about'),
    path('contact/', views.contact, name='contact'),
    path('policies/<slug:slug>/', views.policy, name='policy'),
    path('newsletter/subscribe/', newsletter_subscribe_view, name='newsletter_subscribe'),
    path('feed.xml', views.rss_feed, name='rss_feed'),
    path('rss.xml', views.rss_feed, name='rss_feed_alt'),
    path('sitemap.xml', views.sitemap, name='sitemap'),
    path('robots.txt', views.robots_txt, name='robots_txt'),
]
