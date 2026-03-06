<?php
declare(strict_types=1);

use Symfony\Component\Routing\Route;
use App\Controllers\AdminController;
use App\Controllers\AdminMediaController;
use App\Controllers\AdminSettingsController;
use App\Controllers\AdminUserController;
use App\Controllers\AdminProfileController;
use App\Controllers\AdminPolicyController;
use App\Controllers\AdminSubscriberController;
use App\Controllers\AdminCategoryController;
use App\Controllers\AdminCommentController;
use App\Controllers\AdminAdController;
use App\Controllers\AdminRoleController;
use App\Controllers\AdminCrawlerController;
use App\Controllers\AdminSeoController;
use App\Controllers\AdminSocialController;
use App\Controllers\AdminSystemController;

$admin       = new AdminController();
$media       = new AdminMediaController();
$settings    = new AdminSettingsController();
$users       = new AdminUserController();
$profile     = new AdminProfileController();
$policies    = new AdminPolicyController();
$subscribers = new AdminSubscriberController();
$categories  = new AdminCategoryController();
$comments    = new AdminCommentController();
$adsCtrl     = new AdminAdController();
$rolesCtrl   = new AdminRoleController();
$crawler     = new AdminCrawlerController();
$seo         = new AdminSeoController();
$social      = new AdminSocialController();
$system      = new AdminSystemController();

// --- Auth ---
$routes->add('admin_login',      new Route('/admin/login',  ['_controller' => [$admin, 'login']],     [], [], '', [], ['GET']));
$routes->add('admin_login_post', new Route('/admin/login',  ['_controller' => [$admin, 'loginPost'], '_middleware' => ['csrf']], [], [], '', [], ['POST']));
$routes->add('admin_logout',     new Route('/admin/logout', ['_controller' => [$admin, 'logout']],    [], [], '', [], ['GET']));

// ── Middleware stacks ──────────────────────────────────────────
$auth       = ['auth'];
$authCsrf   = ['auth', 'csrf'];
$editor     = ['auth', 'role:editor'];
$editorCsrf = ['auth', 'role:editor', 'csrf'];
$super      = ['auth', 'role:super_admin'];
$superCsrf  = ['auth', 'role:super_admin', 'csrf'];

// --- Dashboard ---
$routes->add('admin_dashboard', new Route('/admin', ['_controller' => [$admin, 'dashboard'], '_middleware' => $auth], [], [], '', [], ['GET']));
$routes->add('admin_reader_map_api', new Route('/admin/api/reader-map', ['_controller' => [$admin, 'readerMapApi'], '_middleware' => $auth], [], [], '', [], ['GET']));
$routes->add('admin_api_badges',     new Route('/admin/api/badges',      ['_controller' => [$admin, 'apiBadges'],    '_middleware' => $auth], [], [], '', [], ['GET']));
$routes->add('admin_dashboard_pulse', new Route('/admin/api/dashboard-pulse', ['_controller' => [$admin, 'dashboardPulse'], '_middleware' => $auth], [], [], '', [], ['GET']));
$routes->add('admin_engagement_radar', new Route('/admin/api/engagement-radar', ['_controller' => [$admin, 'engagementRadar'], '_middleware' => $auth], [], [], '', [], ['GET']));
$routes->add('admin_traffic_chart', new Route('/admin/api/traffic-chart', ['_controller' => [$admin, 'trafficChart'], '_middleware' => $auth], [], [], '', [], ['GET']));
$routes->add('admin_analytics', new Route('/admin/analytics', ['_controller' => [$admin, 'analytics'], '_middleware' => $auth], [], [], '', [], ['GET']));

// --- Articles ---
$routes->add('admin_articles',       new Route('/admin/articles',             ['_controller' => [$admin, 'articles'],      '_middleware' => $auth],     [], [], '', [], ['GET']));
$routes->add('admin_article_create', new Route('/admin/articles/create',      ['_controller' => [$admin, 'articleCreate'], '_middleware' => $auth],     [], [], '', [], ['GET']));
$routes->add('admin_articles_archive',        new Route('/admin/articles/archive',            ['_controller' => [$admin, 'archive'],                '_middleware' => $auth],     [], [], '', [], ['GET']));
$routes->add('admin_articles_bulk_archive',    new Route('/admin/articles/archive/bulk',       ['_controller' => [$admin, 'bulkArchiveAction'],      '_middleware' => $authCsrf], [], [], '', [], ['POST']));
$routes->add('admin_article_store',  new Route('/admin/articles',             ['_controller' => [$admin, 'articleStore'],  '_middleware' => $authCsrf], [], [], '', [], ['POST']));
$routes->add('admin_article_edit',   new Route('/admin/articles/{id}/edit',   ['_controller' => [$admin, 'articleEdit'],   '_middleware' => $auth],     [], [], '', [], ['GET']));
$routes->add('admin_article_update', new Route('/admin/articles/{id}',        ['_controller' => [$admin, 'articleUpdate'], '_middleware' => $authCsrf], [], [], '', [], ['POST']));
$routes->add('admin_article_delete', new Route('/admin/articles/{id}/delete', ['_controller' => [$admin, 'articleDelete'], '_middleware' => $authCsrf], [], [], '', [], ['POST']));
$routes->add('admin_article_restore',          new Route('/admin/articles/{id}/restore',           ['_controller' => [$admin, 'restoreArticle'],         '_middleware' => $authCsrf], ['id' => '[0-9a-f-]+'], [], '', [], ['POST']));
$routes->add('admin_article_revision_restore', new Route('/admin/articles/{id}/revisions/{rid}/restore', ['_controller' => [$admin, 'revisionRestore'],        '_middleware' => $authCsrf], ['id' => '[0-9a-f-]+', 'rid' => '[0-9]+'], [], '', [], ['POST']));
$routes->add('admin_article_permanent_delete', new Route('/admin/articles/{id}/permanent-delete', ['_controller' => [$admin, 'permanentDeleteArticle'],'_middleware' => $authCsrf], ['id' => '[0-9a-f-]+'], [], '', [], ['POST']));
$routes->add('admin_article_toggle_breaking',  new Route('/admin/articles/{id}/toggle-breaking',  ['_controller' => [$admin, 'toggleBreaking'],        '_middleware' => $authCsrf], ['id' => '[0-9a-f-]+'], [], '', [], ['POST']));

// --- Comments ---
$routes->add('admin_comments',        new Route('/admin/comments',                ['_controller' => [$comments, 'index'],      '_middleware' => $auth],     [], [], '', [], ['GET']));
$routes->add('admin_comments_bulk',   new Route('/admin/comments/bulk',           ['_controller' => [$comments, 'bulk'],       '_middleware' => $authCsrf], [], [], '', [], ['POST']));
$routes->add('admin_comments_hide',   new Route('/admin/comments/{id}/hide',      ['_controller' => [$comments, 'hide'],       '_middleware' => $authCsrf], [], [], '', [], ['POST']));
$routes->add('admin_comments_show',   new Route('/admin/comments/{id}/show',      ['_controller' => [$comments, 'show'],       '_middleware' => $authCsrf], [], [], '', [], ['POST']));
$routes->add('admin_comments_delete', new Route('/admin/comments/{id}/delete',    ['_controller' => [$comments, 'softDelete'], '_middleware' => $authCsrf], [], [], '', [], ['POST']));
$routes->add('admin_comments_destroy',new Route('/admin/comments/{id}/destroy',   ['_controller' => [$comments, 'destroy'],    '_middleware' => $authCsrf], [], [], '', [], ['POST']));

// --- Media ---
$routes->add('admin_media',        new Route('/admin/media',             ['_controller' => [$media, 'index'],  '_middleware' => $auth],     [], [], '', [], ['GET']));
$routes->add('admin_media_picker', new Route('/admin/media/picker',      ['_controller' => [$media, 'picker'], '_middleware' => $auth],     [], [], '', [], ['GET']));
$routes->add('admin_media_upload', new Route('/admin/media/upload',      ['_controller' => [$media, 'upload'], '_middleware' => $authCsrf], [], [], '', [], ['POST']));
$routes->add('admin_media_upload_inline', new Route('/admin/media/upload-inline', ['_controller' => [$media, 'uploadInline'], '_middleware' => $authCsrf], [], [], '', [], ['POST']));
$routes->add('admin_media_delete', new Route('/admin/media/{id}/delete', ['_controller' => [$media, 'delete'], '_middleware' => $authCsrf], [], [], '', [], ['POST']));

// --- Settings ---
$routes->add('admin_settings',      new Route('/admin/settings', ['_controller' => [$settings, 'index'], '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_settings_save', new Route('/admin/settings', ['_controller' => [$settings, 'save'],  '_middleware' => $editorCsrf], [], [], '', [], ['POST']));

// --- Social Post Log (Phase 11) ---
$routes->add('admin_social_post_log', new Route('/admin/social/posts', ['_controller' => [$admin, 'socialPostLog'], '_middleware' => $editor], [], [], '', [], ['GET']));

// --- Push Notification Settings (Phase 11) ---
$routes->add('admin_push_settings',      new Route('/admin/push/settings', ['_controller' => [$admin, 'pushSettings'],     '_middleware' => $super],     [], [], '', [], ['GET']));
$routes->add('admin_push_settings_save', new Route('/admin/push/settings', ['_controller' => [$admin, 'pushSettingsSave'], '_middleware' => $superCsrf], [], [], '', [], ['POST']));
$routes->add('admin_push_generate_keys', new Route('/admin/push/generate-keys', ['_controller' => [$admin, 'pushGenerateKeys'], '_middleware' => $superCsrf], [], [], '', [], ['POST']));

// --- Users (super_admin only) ---
$routes->add('admin_users',         new Route('/admin/users',                  ['_controller' => [$users, 'index'],  '_middleware' => $super],     [], [], '', [], ['GET']));
$routes->add('admin_users_create',  new Route('/admin/users/create',           ['_controller' => [$users, 'create'], '_middleware' => $super],     [], [], '', [], ['GET']));
$routes->add('admin_users_store',   new Route('/admin/users',                  ['_controller' => [$users, 'store'],  '_middleware' => $superCsrf], [], [], '', [], ['POST']));
$routes->add('admin_users_edit',    new Route('/admin/users/{id}/edit',        ['_controller' => [$users, 'edit'],   '_middleware' => $super],     [], [], '', [], ['GET']));
$routes->add('admin_users_update',  new Route('/admin/users/{id}',             ['_controller' => [$users, 'update'], '_middleware' => $superCsrf], [], [], '', [], ['POST']));
$routes->add('admin_users_delete',  new Route('/admin/users/{id}/delete',      ['_controller' => [$users, 'delete'], '_middleware' => $superCsrf], [], [], '', [], ['POST']));
$routes->add('admin_users_toggle',  new Route('/admin/users/{id}/toggle',      ['_controller' => [$users, 'toggle'], '_middleware' => $superCsrf], [], [], '', [], ['POST']));

// --- Profile (all authenticated users) ---
$routes->add('admin_profile',       new Route('/admin/profile', ['_controller' => [$profile, 'index'],  '_middleware' => $auth],     [], [], '', [], ['GET']));
$routes->add('admin_profile_save',  new Route('/admin/profile', ['_controller' => [$profile, 'update'], '_middleware' => $authCsrf], [], [], '', [], ['POST']));

// --- Policy Pages ---
$routes->add('admin_policies',        new Route('/admin/policies',                  ['_controller' => [$policies, 'index'],  '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_policies_create', new Route('/admin/policies/create',           ['_controller' => [$policies, 'create'], '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_policies_store',  new Route('/admin/policies',                  ['_controller' => [$policies, 'store'],  '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_policies_edit',   new Route('/admin/policies/{id}/edit',        ['_controller' => [$policies, 'edit'],   '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_policies_update', new Route('/admin/policies/{id}',             ['_controller' => [$policies, 'update'], '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_policies_delete', new Route('/admin/policies/{id}/delete',      ['_controller' => [$policies, 'delete'], '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_policies_toggle', new Route('/admin/policies/{id}/toggle',      ['_controller' => [$policies, 'toggle'], '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_policies_bulk',   new Route('/admin/policies/bulk',             ['_controller' => [$policies, 'bulk'],   '_middleware' => $editorCsrf], [], [], '', [], ['POST']));

// --- Subscribers ---
$routes->add('admin_subscribers',        new Route('/admin/subscribers',                   ['_controller' => [$subscribers, 'index'],         '_middleware' => $auth],     [], [], '', [], ['GET']));
$routes->add('admin_subscribers_export', new Route('/admin/subscribers/export',            ['_controller' => [$subscribers, 'export'],        '_middleware' => $auth],     [], [], '', [], ['GET']));
$routes->add('admin_subscribers_delete', new Route('/admin/subscribers/{id}/delete',       ['_controller' => [$subscribers, 'delete'],        '_middleware' => $authCsrf], [], [], '', [], ['POST']));
$routes->add('admin_subscribers_toggle', new Route('/admin/subscribers/{id}/toggle',       ['_controller' => [$subscribers, 'toggleConfirm'], '_middleware' => $authCsrf], [], [], '', [], ['POST']));

// --- Categories ---
$routes->add('admin_categories',        new Route('/admin/categories',             ['_controller' => [$categories, 'index'],  '_middleware' => $auth],     [], [], '', [], ['GET']));
$routes->add('admin_categories_create', new Route('/admin/categories/create',      ['_controller' => [$categories, 'create'], '_middleware' => $auth],     [], [], '', [], ['GET']));
$routes->add('admin_categories_store',  new Route('/admin/categories',             ['_controller' => [$categories, 'store'],  '_middleware' => $authCsrf], [], [], '', [], ['POST']));
$routes->add('admin_categories_edit',   new Route('/admin/categories/{id}/edit',   ['_controller' => [$categories, 'edit'],   '_middleware' => $auth],     [], [], '', [], ['GET']));
$routes->add('admin_categories_update', new Route('/admin/categories/{id}',        ['_controller' => [$categories, 'update'], '_middleware' => $authCsrf], [], [], '', [], ['POST']));
$routes->add('admin_categories_delete', new Route('/admin/categories/{id}/delete', ['_controller' => [$categories, 'delete'], '_middleware' => $authCsrf], [], [], '', [], ['POST']));

// --- Ads ---
$routes->add('admin_ads',        new Route('/admin/ads',                  ['_controller' => [$adsCtrl, 'index'],  '_middleware' => $auth],     [], [], '', [], ['GET']));
$routes->add('admin_ads_update', new Route('/admin/ads/{id}',             ['_controller' => [$adsCtrl, 'update'], '_middleware' => $authCsrf], [], [], '', [], ['POST']));
$routes->add('admin_ads_toggle', new Route('/admin/ads/{id}/toggle',      ['_controller' => [$adsCtrl, 'toggle'], '_middleware' => $authCsrf], [], [], '', [], ['POST']));

// --- Roles ---
$routes->add('admin_roles',        new Route('/admin/roles',                  ['_controller' => [$rolesCtrl, 'index'],  '_middleware' => $super],     [], [], '', [], ['GET']));
$routes->add('admin_roles_create', new Route('/admin/roles/create',           ['_controller' => [$rolesCtrl, 'create'], '_middleware' => $super],     [], [], '', [], ['GET']));
$routes->add('admin_roles_store',  new Route('/admin/roles',                  ['_controller' => [$rolesCtrl, 'store'],  '_middleware' => $superCsrf], [], [], '', [], ['POST']));
$routes->add('admin_roles_edit',   new Route('/admin/roles/{id}/edit',        ['_controller' => [$rolesCtrl, 'edit'],   '_middleware' => $super],     [], [], '', [], ['GET']));
$routes->add('admin_roles_update', new Route('/admin/roles/{id}',             ['_controller' => [$rolesCtrl, 'update'], '_middleware' => $superCsrf], [], [], '', [], ['POST']));
$routes->add('admin_roles_delete',       new Route('/admin/roles/{id}/delete',       ['_controller' => [$rolesCtrl, 'delete'],      '_middleware' => $superCsrf], [], [], '', [], ['POST']));
$routes->add('admin_roles_update_color', new Route('/admin/roles/{id}/color',        ['_controller' => [$rolesCtrl, 'updateColor'], '_middleware' => $superCsrf], [], [], '', [], ['POST']));

// ── Login Page Quotes ─────────────────────────────────────────────────────────
$lqCtrl = new \App\Controllers\AdminLoginQuoteController();
$routes->add('admin_login_quotes',        new Route('/admin/login-quotes',             ['_controller' => [$lqCtrl, 'index'],  '_middleware' => $super],     [], [], '', [], ['GET']));
$routes->add('admin_login_quotes_store',  new Route('/admin/login-quotes',             ['_controller' => [$lqCtrl, 'store'],  '_middleware' => $superCsrf], [], [], '', [], ['POST']));
$routes->add('admin_login_quotes_update', new Route('/admin/login-quotes/{id}',        ['_controller' => [$lqCtrl, 'update'], '_middleware' => $superCsrf], [], [], '', [], ['POST']));
$routes->add('admin_login_quotes_delete', new Route('/admin/login-quotes/{id}/delete', ['_controller' => [$lqCtrl, 'delete'], '_middleware' => $superCsrf], [], [], '', [], ['POST']));
$routes->add('admin_login_quotes_toggle', new Route('/admin/login-quotes/{id}/toggle', ['_controller' => [$lqCtrl, 'toggle'], '_middleware' => $superCsrf], [], [], '', [], ['POST']));

// --- Review Queue (editors + super_admin) ---
$review = new \App\Controllers\AdminReviewController();
$routes->add('admin_review',         new Route('/admin/review',                    ['_controller' => [$review, 'index'],   '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_review_show',    new Route('/admin/review/{id}',               ['_controller' => [$review, 'show'],    '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_review_approve', new Route('/admin/review/{id}/approve',       ['_controller' => [$review, 'approve'], '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_review_reject',  new Route('/admin/review/{id}/reject',        ['_controller' => [$review, 'reject'],  '_middleware' => $editorCsrf], [], [], '', [], ['POST']));

// --- Notifications ---
$routes->add('admin_notifications',      new Route('/admin/notifications',              ['_controller' => [$review, 'notifications'], '_middleware' => $auth],     [], [], '', [], ['GET']));
$routes->add('admin_notif_read_all',     new Route('/admin/notifications/read-all',     ['_controller' => [$review, 'markAllRead'],   '_middleware' => $authCsrf], [], [], '', [], ['POST']));
$routes->add('admin_notif_read',         new Route('/admin/notifications/{id}/read',    ['_controller' => [$review, 'markRead'],      '_middleware' => $auth],     [], [], '', [], ['GET']));

// --- Newsletter ---
$newsletter = new \App\Controllers\AdminNewsletterController();
$routes->add('admin_newsletter',         new Route('/admin/newsletter',             ['_controller' => [$newsletter, 'index'],   '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_newsletter_compose', new Route('/admin/newsletter/compose',     ['_controller' => [$newsletter, 'compose'], '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_newsletter_preview', new Route('/admin/newsletter/preview',     ['_controller' => [$newsletter, 'preview'], '_middleware' => $editorCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_newsletter_send',    new Route('/admin/newsletter/send',        ['_controller' => [$newsletter, 'send'],    '_middleware' => $editorCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_newsletter_test',    new Route('/admin/newsletter/test',        ['_controller' => [$newsletter, 'sendTest'],       '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_newsletter_queue',        new Route('/admin/newsletter/process-queue',          ['_controller' => [$newsletter, 'processQueue'], '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_newsletter_queue_delete', new Route('/admin/newsletter/queue/{id}/delete',   ['_controller' => [$newsletter, 'queueDelete'],   '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_newsletter_queue_retry',  new Route('/admin/newsletter/queue/{id}/retry',    ['_controller' => [$newsletter, 'queueRetry'],    '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_newsletter_delete',  new Route('/admin/newsletter/{id}/delete',          ['_controller' => [$newsletter, 'delete'],          '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_newsletter_restore', new Route('/admin/newsletter/{id}/restore',         ['_controller' => [$newsletter, 'restore'],         '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_newsletter_perm_del',new Route('/admin/newsletter/{id}/permanent-delete',['_controller' => [$newsletter, 'permanentDelete'], '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_newsletter_draft',      new Route('/admin/newsletter/draft',                ['_controller' => [$newsletter, 'saveDraft'],        '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_newsletter_draft_del',  new Route('/admin/newsletter/{id}/draft-delete',    ['_controller' => [$newsletter, 'deleteDraft'],      '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_newsletter_schedule',   new Route('/admin/newsletter/schedule',             ['_controller' => [$newsletter, 'schedule'],         '_middleware' => $editorCsrf], [], [], '', [], ['POST']));

// --- Popups & Banners (Phase 6) ---
$popups = new \App\Controllers\AdminPopupController();
$routes->add('admin_popups',           new Route('/admin/popups',                    ['_controller' => [$popups, 'index'],           '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_popup_create',     new Route('/admin/popups/create',             ['_controller' => [$popups, 'create'],          '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_popup_store',      new Route('/admin/popups/store',              ['_controller' => [$popups, 'store'],           '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_popup_analytics',       new Route('/admin/popups/analytics',          ['_controller' => [$popups, 'analytics'],       '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_popup_analytics_export',new Route('/admin/popups/analytics/export',   ['_controller' => [$popups, 'analyticsExport'],'_middleware' => $editor],     [], [], '', [], ['GET']));
// --- Popup A/B Testing (before {id} routes) ---
$routes->add('admin_popup_ab_tests',   new Route('/admin/popups/ab',               ['_controller' => [$popups, 'abTests'],   '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_popup_ab_create',  new Route('/admin/popups/ab/create',        ['_controller' => [$popups, 'abCreate'],  '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_popup_ab_store',   new Route('/admin/popups/ab/store',         ['_controller' => [$popups, 'abStore'],   '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_popup_ab_detail',  new Route('/admin/popups/ab/{id}',          ['_controller' => [$popups, 'abDetail'],  '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_popup_ab_action',  new Route('/admin/popups/ab/{id}/action',   ['_controller' => [$popups, 'abAction'],  '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_popup_edit',       new Route('/admin/popups/{id}/edit',          ['_controller' => [$popups, 'edit'],            '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_popup_update',     new Route('/admin/popups/{id}/update',        ['_controller' => [$popups, 'update'],          '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_popup_delete',     new Route('/admin/popups/{id}/delete',        ['_controller' => [$popups, 'destroy'],         '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_popup_toggle',     new Route('/admin/popups/{id}/toggle',        ['_controller' => [$popups, 'toggle'],          '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_popup_duplicate',  new Route('/admin/popups/{id}/duplicate',     ['_controller' => [$popups, 'duplicateAction'], '_middleware' => $editorCsrf], [], [], '', [], ['POST']));

// --- News Crawler (Phase 7) ---
$routes->add('admin_crawler',                new Route('/admin/crawler',                          ['_controller' => [$crawler, 'index'],           '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_crawler_create',         new Route('/admin/crawler/create',                   ['_controller' => [$crawler, 'create'],          '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_crawler_store',          new Route('/admin/crawler/store',                    ['_controller' => [$crawler, 'store'],           '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_crawler_logs',           new Route('/admin/crawler/logs',                     ['_controller' => [$crawler, 'logs'],            '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_crawler_settings',       new Route('/admin/crawler/settings',                 ['_controller' => [$crawler, 'settings'],        '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_crawler_settings_save',  new Route('/admin/crawler/settings/save',            ['_controller' => [$crawler, 'saveSettings'],    '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_crawler_crawl_all',      new Route('/admin/crawler/crawl-all',                ['_controller' => [$crawler, 'crawlAll'],        '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_crawler_visual',         new Route('/admin/crawler/visual',                   ['_controller' => [$crawler, 'visualRunner'],    '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_crawler_crawl_api',      new Route('/admin/crawler/crawl-source/{id}',        ['_controller' => [$crawler, 'crawlSourceApi'],  '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_crawler_stats_api',      new Route('/admin/crawler/stats-api',                ['_controller' => [$crawler, 'crawlStatsApi'],   '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_crawler_test_feed',      new Route('/admin/crawler/test-feed',                ['_controller' => [$crawler, 'testFeed'],        '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_crawler_edit',           new Route('/admin/crawler/{id}/edit',                ['_controller' => [$crawler, 'edit'],            '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_crawler_update',         new Route('/admin/crawler/{id}/update',              ['_controller' => [$crawler, 'update'],          '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_crawler_delete',         new Route('/admin/crawler/{id}/delete',              ['_controller' => [$crawler, 'delete'],          '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_crawler_toggle',         new Route('/admin/crawler/{id}/toggle',              ['_controller' => [$crawler, 'toggle'],          '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_crawler_crawl_now',      new Route('/admin/crawler/{id}/crawl-now',           ['_controller' => [$crawler, 'crawlNow'],        '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_crawler_archive',        new Route('/admin/crawler/{id}/archive-articles',    ['_controller' => [$crawler, 'archiveArticles'], '_middleware' => $editorCsrf], [], [], '', [], ['POST']));

// --- SEO Audit (Phase 7B) ---
$routes->add('admin_seo',                   new Route('/admin/crawler/seo',                      ['_controller' => [$seo, 'index'],           '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_seo_run',               new Route('/admin/crawler/seo/run',                  ['_controller' => [$seo, 'runAudit'],        '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_seo_images',            new Route('/admin/crawler/seo/images',               ['_controller' => [$seo, 'imageHealth'],     '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_seo_images_run',        new Route('/admin/crawler/seo/images/run',           ['_controller' => [$seo, 'runImageCheck'],   '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_seo_settings_save',     new Route('/admin/crawler/seo/settings',             ['_controller' => [$seo, 'saveSettings'],    '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_seo_detail',            new Route('/admin/crawler/seo/{id}',                 ['_controller' => [$seo, 'auditDetail'],     '_middleware' => $editor],     ['id' => '[0-9]+'], [], '', [], ['GET']));

// --- Social Monitor (Phase 7C) ---
$routes->add('admin_social',                new Route('/admin/crawler/social',                   ['_controller' => [$social, 'index'],          '_middleware' => $editor],     [], [], '', [], ['GET']));
$routes->add('admin_social_scan',           new Route('/admin/crawler/social/scan',              ['_controller' => [$social, 'scan'],           '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_social_keyword_add',    new Route('/admin/crawler/social/keyword',           ['_controller' => [$social, 'addKeyword'],     '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_social_keyword_delete', new Route('/admin/crawler/social/keyword/{id}/delete', ['_controller' => [$social, 'deleteKeyword'], '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_social_keyword_toggle', new Route('/admin/crawler/social/keyword/{id}/toggle', ['_controller' => [$social, 'toggleKeyword'], '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_social_read_all',       new Route('/admin/crawler/social/read-all',          ['_controller' => [$social, 'markAllRead'],    '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_social_settings_save',  new Route('/admin/crawler/social/settings',          ['_controller' => [$social, 'saveSettings'],   '_middleware' => $editorCsrf], [], [], '', [], ['POST']));
$routes->add('admin_social_competitors',    new Route('/admin/crawler/social/competitors',       ['_controller' => [$social, 'competitors'],    '_middleware' => $editor],     [], [], '', [], ['GET']));

// --- System Administration (Phase 8 — super_admin only) ---
$routes->add('admin_system',                   new Route('/admin/system',                           ['_controller' => [$system, 'index'],               '_middleware' => $super],     [], [], '', [], ['GET']));

// 🟢 Safe Resets
$routes->add('admin_system_reset_article_views',  new Route('/admin/system/reset-article-views',    ['_controller' => [$system, 'resetArticleViews'],   '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_reset_site_visitors',  new Route('/admin/system/reset-site-visitors',    ['_controller' => [$system, 'resetSiteVisitors'],   '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_reset_ad_stats',       new Route('/admin/system/reset-ad-stats',         ['_controller' => [$system, 'resetAdStats'],        '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_reset_popup_analytics',new Route('/admin/system/reset-popup-analytics',  ['_controller' => [$system, 'resetPopupAnalytics'], '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_reset_newsletter',     new Route('/admin/system/reset-newsletter-stats', ['_controller' => [$system, 'resetNewsletterStats'],'_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_reset_crawler_stats',  new Route('/admin/system/reset-crawler-stats',    ['_controller' => [$system, 'resetCrawlerStats'],   '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_reset_all_analytics',  new Route('/admin/system/reset-all-analytics',    ['_controller' => [$system, 'resetAllAnalytics'],   '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));

// 🟡 Content Purges
$routes->add('admin_system_purge_comments',       new Route('/admin/system/purge-comments',         ['_controller' => [$system, 'purgeComments'],       '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_purge_subscribers',    new Route('/admin/system/purge-subscribers',      ['_controller' => [$system, 'purgeSubscribers'],    '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_purge_notifications',  new Route('/admin/system/purge-notifications',    ['_controller' => [$system, 'purgeNotifications'],  '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_purge_email_queue',    new Route('/admin/system/purge-email-queue',      ['_controller' => [$system, 'purgeEmailQueue'],     '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_purge_crawl_history',  new Route('/admin/system/purge-crawl-history',    ['_controller' => [$system, 'purgeCrawlHistory'],   '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_purge_seo_history',    new Route('/admin/system/purge-seo-history',      ['_controller' => [$system, 'purgeSeoHistory'],     '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_purge_social_mentions',new Route('/admin/system/purge-social-mentions',  ['_controller' => [$system, 'purgeSocialMentions'], '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_purge_login_attempts', new Route('/admin/system/purge-login-attempts',   ['_controller' => [$system, 'purgeLoginAttempts'],  '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));

// 🟠 Media Management
$routes->add('admin_system_purge_all_media',        new Route('/admin/system/purge-all-media',          ['_controller' => [$system, 'purgeAllMedia'],         '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_clean_orphan_media',     new Route('/admin/system/clean-orphan-media',       ['_controller' => [$system, 'cleanOrphanMedia'],      '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
// FIX (Audit B-05): This route was missing — button in system view POSTed to 404.
$routes->add('admin_system_regenerate_thumbnails',  new Route('/admin/system/regenerate-thumbnails',    ['_controller' => [$system, 'regenerateThumbnails'],  '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));

// 🔴 Danger Zone
$routes->add('admin_system_delete_all_articles',  new Route('/admin/system/delete-all-articles',    ['_controller' => [$system, 'deleteAllArticles'],   '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_delete_crawled',       new Route('/admin/system/delete-crawled-articles',['_controller' => [$system, 'deleteCrawledArticles'],'_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_reset_all_popups',     new Route('/admin/system/reset-all-popups',       ['_controller' => [$system, 'resetAllPopups'],      '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_reset_crawler',        new Route('/admin/system/reset-crawler-system',   ['_controller' => [$system, 'resetCrawlerSystem'],  '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_factory_reset',        new Route('/admin/system/factory-reset',          ['_controller' => [$system, 'factoryReset'],        '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));

// 💾 Database Tools
$routes->add('admin_system_export_db',            new Route('/admin/system/export-database',        ['_controller' => [$system, 'exportDatabase'],      '_middleware' => $super],     [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_table_sizes',          new Route('/admin/system/table-sizes',            ['_controller' => [$system, 'tableSizes'],          '_middleware' => $super],     [], [], '', [], ['GET']));
$routes->add('admin_system_vacuum',               new Route('/admin/system/vacuum',                 ['_controller' => [$system, 'vacuumDatabase'],      '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_clear_cache',          new Route('/admin/system/clear-cache',            ['_controller' => [$system, 'clearCache'],          '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_logs_api',             new Route('/admin/system/logs-api',               ['_controller' => [$system, 'logsApi'],             '_middleware' => $super],     [], [], '', [], ['GET']));

// Cron Monitor
$routes->add('admin_system_cron_history',         new Route('/admin/system/cron-history',            ['_controller' => [$system, 'cronHistory'],         '_middleware' => $super],     [], [], '', [], ['GET']));
$routes->add('admin_system_cron_clean',           new Route('/admin/system/cron-clean',              ['_controller' => [$system, 'cronCleanOld'],        '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));

// Session Manager
$routes->add('admin_system_force_logout_session', new Route('/admin/system/force-logout-session',    ['_controller' => [$system, 'forceLogoutSession'],  '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_force_logout_user',    new Route('/admin/system/force-logout-user',       ['_controller' => [$system, 'forceLogoutUser'],     '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_clean_sessions',       new Route('/admin/system/clean-sessions',          ['_controller' => [$system, 'cleanStaleSessions'],  '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));

// Database Backups
$routes->add('admin_system_create_backup',        new Route('/admin/system/create-backup',           ['_controller' => [$system, 'createBackup'],        '_middleware' => $superCsrf], [], [], '', [], ['GET', 'POST']));
$routes->add('admin_system_download_backup',      new Route('/admin/system/backup/{id}/download',    ['_controller' => [$system, 'downloadBackup'],      '_middleware' => $super],     ['id' => '[0-9]+'], [], '', [], ['GET']));
$routes->add('admin_system_delete_backup',        new Route('/admin/system/backup/{id}/delete',      ['_controller' => [$system, 'deleteBackup'],        '_middleware' => $superCsrf], ['id' => '[0-9]+'], [], '', [], ['POST']));

// Environment Inspector
$routes->add('admin_system_environment_api',      new Route('/admin/system/environment-api',         ['_controller' => [$system, 'environmentApi'],      '_middleware' => $super],     [], [], '', [], ['GET']));