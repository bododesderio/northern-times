from django.urls import path

from . import views

urlpatterns = [
    path('newsletter/', views.newsletter_index, name='admin_newsletter'),
    path('newsletter/compose/', views.newsletter_compose, name='admin_newsletter_compose'),
    path('newsletter/preview/<uuid:pk>/', views.newsletter_preview, name='admin_newsletter_preview'),
    path('newsletter/test/', views.newsletter_send_test, name='admin_newsletter_test'),
    path('newsletter/test/<uuid:pk>/', views.newsletter_test, name='admin_newsletter_test_issue'),
    path('newsletter/<uuid:pk>/schedule/', views.newsletter_schedule, name='admin_newsletter_schedule'),
    path('newsletter/<uuid:pk>/draft-delete/', views.newsletter_draft_delete, name='admin_newsletter_draft_delete'),
    path('newsletter/<uuid:pk>/delete/', views.newsletter_delete, name='admin_newsletter_delete'),
    path('newsletter/<uuid:pk>/restore/', views.newsletter_restore, name='admin_newsletter_restore'),
    path('newsletter/queue/', views.newsletter_queue, name='admin_newsletter_queue'),
    path('newsletter/queue/<uuid:pk>/delete/', views.newsletter_queue_delete, name='admin_newsletter_queue_delete'),
    path('newsletter/queue/<uuid:pk>/retry/', views.newsletter_queue_retry, name='admin_newsletter_queue_retry'),
    path('subscribers/', views.subscriber_index, name='admin_subscribers'),
    path('subscribers/export/', views.subscriber_export, name='admin_subscribers_export'),
    path('subscribers/delete/<uuid:pk>/', views.subscriber_delete, name='admin_subscriber_delete'),
]
