from django.urls import path

from . import views

app_name = 'analytics_api'

urlpatterns = [
    path('api/ad-click/<uuid:pk>/', views.ad_click, name='ad_click'),
    path('api/ad-impression/', views.ad_impression, name='ad_impression'),
    path('api/visitor-location/', views.visitor_location, name='visitor_location'),
]
