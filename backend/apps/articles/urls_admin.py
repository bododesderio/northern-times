from django.urls import path

from . import views_admin

urlpatterns = [
    path('', views_admin.admin_dashboard, name='admin_dashboard'),
    path('articles/', views_admin.article_list, name='admin_articles'),
    path('articles/create/', views_admin.article_create, name='admin_article_create'),
    path('articles/<uuid:pk>/edit/', views_admin.article_edit, name='admin_article_edit'),
    path('articles/<uuid:pk>/delete/', views_admin.article_delete, name='admin_article_delete'),
    path('articles/<uuid:pk>/restore/', views_admin.article_restore, name='admin_article_restore'),
    path('articles/archive/', views_admin.archive, name='admin_articles_archive'),
    path('articles/archive/bulk/', views_admin.bulk_archive, name='admin_articles_archive_bulk'),
    path('categories/', views_admin.category_list, name='admin_categories'),
    path('categories/create/', views_admin.category_create, name='admin_category_create'),
    path('categories/<uuid:pk>/edit/', views_admin.category_edit, name='admin_category_edit'),
    path('categories/<uuid:pk>/delete/', views_admin.category_delete, name='admin_category_delete'),
    path('comments/', views_admin.comment_list, name='admin_comments'),
    path('comments/<uuid:pk>/moderate/', views_admin.comment_moderate, name='admin_comment_moderate'),
    path('comments/<uuid:pk>/hide/', views_admin.comment_hide, name='admin_comment_hide'),
    path('comments/<uuid:pk>/show/', views_admin.comment_show, name='admin_comment_show'),
    path('comments/<uuid:pk>/delete/', views_admin.comment_delete, name='admin_comment_delete'),
    path('comments/<uuid:pk>/destroy/', views_admin.comment_destroy, name='admin_comment_destroy'),
    path('comments/bulk/', views_admin.comments_bulk, name='admin_comments_bulk'),
    path('review/', views_admin.review_list, name='admin_review'),
    path('review/<uuid:pk>/', views_admin.review_show, name='admin_review_show'),
    path('review/<uuid:pk>/approve/', views_admin.review_approve, name='admin_review_approve'),
    path('review/<uuid:pk>/reject/', views_admin.review_reject, name='admin_review_reject'),
    path('articles/<uuid:pk>/permanent-delete/', views_admin.article_permanent_delete, name='admin_article_permanent_delete'),
    path('articles/<uuid:pk>/toggle-breaking/', views_admin.article_toggle_breaking, name='admin_article_toggle_breaking'),
    path('articles/<uuid:article_pk>/revisions/<uuid:revision_pk>/restore/', views_admin.article_revision_restore, name='admin_article_revision_restore'),
    path('api/dashboard-pulse/', views_admin.dashboard_pulse, name='admin_dashboard_pulse'),
    path('api/reader-map/', views_admin.reader_map_api, name='admin_reader_map'),
]
