from django.urls import path

from . import views

urlpatterns = [
    path('webhooks/', views.webhook_index, name='admin_webhooks'),
    path('webhooks/create/', views.webhook_create, name='admin_webhook_create'),
    path('webhooks/edit/<uuid:pk>/', views.webhook_edit, name='admin_webhook_edit'),
    path('webhooks/delete/<uuid:pk>/', views.webhook_delete, name='admin_webhook_delete'),
    path('webhooks/<uuid:pk>/test/', views.webhook_test, name='admin_webhook_test'),
    path('webhooks/logs/', views.webhook_logs, name='admin_webhook_logs'),
]
