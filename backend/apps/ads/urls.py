from django.urls import path

from . import views

urlpatterns = [
    path('ads/', views.ads_index, name='admin_ads'),
    path('ads/create/', views.ads_create, name='admin_ad_create'),
    path('ads/edit/<uuid:pk>/', views.ads_edit, name='admin_ad_edit'),
    path('ads/delete/<uuid:pk>/', views.ads_delete, name='admin_ad_delete'),
    path('ads/<uuid:pk>/toggle/', views.ads_toggle, name='admin_ad_toggle'),
    path('popups/', views.popups_index, name='admin_popups'),
    path('popups/create/', views.popups_create, name='admin_popup_create'),
    path('popups/edit/<uuid:pk>/', views.popups_edit, name='admin_popup_edit'),
    path('popups/delete/<uuid:pk>/', views.popups_delete, name='admin_popup_delete'),
    path('popups/analytics/', views.popups_analytics, name='admin_popup_analytics'),
    path('popups/analytics/export/', views.popups_analytics_export, name='admin_popups_analytics_export'),
    path('popups/ab/', views.ab_tests, name='admin_ab_tests'),
    path('popups/ab/create/', views.ab_test_create, name='admin_ab_test_create'),
    path('popups/<uuid:pk>/duplicate/', views.popup_duplicate, name='admin_popup_duplicate'),
    path('popups/<uuid:pk>/toggle/', views.popup_toggle, name='admin_popup_toggle'),
]
