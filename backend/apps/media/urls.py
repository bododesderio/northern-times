from django.urls import path

from . import views

urlpatterns = [
    path('media/', views.media_index, name='admin_media'),
    path('media/picker/', views.media_picker, name='admin_media_picker'),
    path('media/upload/', views.media_upload, name='admin_media_upload'),
    path('media/delete/<uuid:pk>/', views.media_delete, name='admin_media_delete'),
    path('media/bulk-delete/', views.media_bulk_delete, name='admin_media_bulk_delete'),
]
