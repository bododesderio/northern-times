from django.urls import path

from . import views

urlpatterns = [
    # Dashboard
    path('system/', views.system_index, name='admin_system'),
    path('system/logs/', views.system_logs, name='admin_system_logs'),

    # Backups
    path('system/backups/', views.backups, name='admin_system_backups'),
    path('system/backup/', views.backup_create, name='admin_system_backup'),
    path('system/backup-restore/<uuid:pk>/', views.backup_restore, name='admin_system_backup_restore'),

    # Cache & Logs
    path('system/clear-cache/', views.flush_cache, name='admin_system_clear_cache'),
    path('system/purge-logs/', views.purge_logs, name='admin_system_purge_logs'),

    # Mail queue
    path('system/mail-queue/', views.mail_queue, name='admin_system_mail_queue'),
    path('system/mail-retry/<uuid:pk>/', views.mail_retry, name='admin_system_mail_retry'),

    # Policies
    path('policies/', views.policy_index, name='admin_policies'),
    path('policies/create/', views.policy_create, name='admin_policy_create'),
    path('policies/edit/<uuid:pk>/', views.policy_edit, name='admin_policy_edit'),
    path('policies/delete/<uuid:pk>/', views.policy_delete, name='admin_policy_delete'),

    # Login quotes
    path('login-quotes/', views.login_quotes_index, name='admin_login_quotes'),

    # Data Resets
    path('system/reset/article-views/', views.reset_article_views, name='admin_reset_article_views'),
    path('system/reset/site-visitors/', views.reset_site_visitors, name='admin_reset_site_visitors'),
    path('system/reset/ad-stats/', views.reset_ad_stats, name='admin_reset_ad_stats'),
    path('system/reset/newsletter-stats/', views.reset_newsletter_stats, name='admin_reset_newsletter_stats'),
    path('system/reset/crawler-stats/', views.reset_crawler_stats, name='admin_reset_crawler_stats'),
    path('system/reset/all-analytics/', views.reset_all_analytics, name='admin_reset_all_analytics'),

    # Content Purges
    path('system/purge/comments/', views.purge_comments, name='admin_purge_comments'),
    path('system/purge/subscribers/', views.purge_subscribers, name='admin_purge_subscribers'),
    path('system/purge/notifications/', views.purge_notifications, name='admin_purge_notifications'),
    path('system/purge/email-queue/', views.purge_email_queue, name='admin_purge_email_queue'),
    path('system/purge/crawl-history/', views.purge_crawl_history, name='admin_purge_crawl_history'),
    path('system/purge/seo-history/', views.purge_seo_history, name='admin_purge_seo_history'),
    path('system/purge/social-mentions/', views.purge_social_mentions, name='admin_purge_social_mentions'),

    # Database & Infrastructure
    path('system/vacuum/', views.vacuum_db, name='admin_vacuum_db'),
    path('system/table-sizes/', views.table_sizes, name='admin_table_sizes'),
    path('system/environment/', views.environment_api, name='admin_environment_api'),
    path('system/cron-history/', views.cron_history, name='admin_cron_history'),
    path('system/clean-sessions/', views.clean_sessions, name='admin_clean_sessions'),
    path('system/force-logout/', views.force_logout_user, name='admin_force_logout'),

    # AI Tools
    path('system/ai-reclassify/', views.ai_reclassify, name='admin_ai_reclassify'),
    path('system/cleanup-duplicates/', views.cleanup_duplicates, name='admin_cleanup_duplicates'),

    # Danger Zone
    path('system/danger/delete-crawled-articles/', views.delete_crawled_articles, name='admin_delete_crawled_articles'),
    path('system/danger/delete-all-articles/', views.delete_all_articles, name='admin_delete_all_articles'),
    path('system/danger/reset-popups/', views.reset_all_popups, name='admin_reset_all_popups'),
    path('system/danger/reset-crawler/', views.reset_crawler_system, name='admin_reset_crawler_system'),
    path('system/danger/factory-reset/', views.factory_reset, name='admin_factory_reset'),
]
