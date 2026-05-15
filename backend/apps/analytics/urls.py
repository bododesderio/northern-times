from django.urls import path

from . import views

urlpatterns = [
    path('analytics/', views.analytics_index, name='admin_analytics'),
    path('analytics/export/', views.analytics_export, name='admin_analytics_export'),
    path('performance/', views.performance_index, name='admin_analytics_performance'),
    path('engagement/', views.engagement_index, name='admin_analytics_engagement'),
    path('api/badges/', views.badges_api, name='admin_badges_api'),
    path('api/engagement-radar/', views.engagement_radar_api, name='admin_engagement_radar_api'),
    path('api/traffic-chart/', views.traffic_chart_api, name='admin_traffic_chart_api'),
    path('api/daily-stats/', views.daily_stats_api, name='admin_daily_stats_api'),
]
