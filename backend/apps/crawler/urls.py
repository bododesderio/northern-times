from django.urls import path

from . import views

urlpatterns = [
    path('crawler/', views.source_list, name='admin_crawler'),
    path('crawler/create/', views.source_create, name='admin_crawler_create'),
    path('crawler/edit/<uuid:pk>/', views.source_edit, name='admin_crawler_edit'),
    path('crawler/delete/<uuid:pk>/', views.source_delete, name='admin_crawler_delete'),
    path('crawler/crawl/<uuid:pk>/', views.crawl_now, name='admin_crawler_toggle'),
    path('crawler/logs/', views.logs, name='admin_crawler_logs'),
    path('crawler/crawl-all/', views.crawl_all, name='admin_crawl_all'),
    path('crawler/settings/', views.crawler_settings, name='admin_crawler_settings'),
    path('crawler/<uuid:pk>/test-feed/', views.test_feed, name='admin_test_feed'),
    path('crawler/<uuid:pk>/robots-check/', views.robots_check, name='admin_robots_check'),
    path('crawler/<uuid:pk>/archive-articles/', views.archive_source_articles, name='admin_archive_source_articles'),
    path('crawler/<uuid:pk>/restore-articles/', views.restore_source_articles, name='admin_restore_source_articles'),
    path('crawler/<uuid:pk>/delete-articles/', views.delete_source_articles, name='admin_delete_source_articles'),
    path('crawler/<uuid:pk>/articles/', views.source_articles, name='admin_source_articles'),
    path('crawler/stats-api/', views.crawler_stats_api, name='admin_crawler_stats_api'),
]
