from django.urls import path

from . import views

urlpatterns = [
    path('rewriter/', views.rewriter_index, name='admin_rewriter'),
    path('rewriter/queue/', views.rewriter_queue, name='admin_rewriter_queue'),
    path('rewriter/approve/<uuid:pk>/', views.rewriter_approve, name='admin_rewriter_approve'),
    path('rewriter/reject/<uuid:pk>/', views.rewriter_reject, name='admin_rewriter_reject'),
    path('rewriter/queue-bulk/', views.rewriter_queue_bulk, name='admin_rewriter_queue_bulk'),
    path('rewriter/settings/', views.rewriter_settings, name='admin_rewriter_settings'),
    path('rewriter/<uuid:pk>/review/', views.rewriter_review, name='admin_rewriter_review'),
    path('rewriter/<uuid:pk>/retry/', views.rewriter_retry, name='admin_rewriter_retry'),
    path('rewriter/<uuid:pk>/revert/', views.rewriter_revert, name='admin_rewriter_revert'),
    path('rewriter/status-api/', views.rewriter_status_api, name='admin_rewriter_status_api'),
]
