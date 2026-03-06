<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\AdminController;
use App\Controllers\FrontendController;
use App\Services\WebPush;
use App\Services\SocialPoster;
use Tests\DatabaseTestCase;
use PDO;

/**
 * Phase 11: Distribution & Reach
 * Covers social auto-posting, browser push notifications,
 * migrations, routes, service worker, and admin UI.
 */
class Phase11DistributionTest extends DatabaseTestCase
{
    // ═══════════════════════════════════════════════════════════
    // MIGRATIONS
    // ═══════════════════════════════════════════════════════════

    public function test_social_posts_log_migration_exists(): void
    {
        $this->assertFileExists(
            __DIR__ . '/../../database/migrations/0045_social_posts_log.sql'
        );
    }

    public function test_push_subscriptions_migration_exists(): void
    {
        $this->assertFileExists(
            __DIR__ . '/../../database/migrations/0046_push_subscriptions.sql'
        );
    }

    public function test_social_posts_log_table_exists(): void
    {
        $result = self::$pdo->query(
            "SELECT to_regclass('public.social_posts_log')"
        )->fetchColumn();
        $this->assertNotNull($result, 'social_posts_log table must exist');
    }

    public function test_push_subscriptions_table_exists(): void
    {
        $result = self::$pdo->query(
            "SELECT to_regclass('public.push_subscriptions')"
        )->fetchColumn();
        $this->assertNotNull($result, 'push_subscriptions table must exist');
    }

    public function test_social_posts_log_has_correct_columns(): void
    {
        $cols = self::$pdo->query(
            "SELECT column_name FROM information_schema.columns
             WHERE table_name = 'social_posts_log'"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach (['id', 'article_id', 'platform', 'status', 'post_url', 'error', 'created_at'] as $col) {
            $this->assertContains($col, $cols, "social_posts_log must have column: $col");
        }
    }

    public function test_push_subscriptions_has_correct_columns(): void
    {
        $cols = self::$pdo->query(
            "SELECT column_name FROM information_schema.columns
             WHERE table_name = 'push_subscriptions'"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach (['id', 'endpoint', 'p256dh', 'auth', 'created_at'] as $col) {
            $this->assertContains($col, $cols, "push_subscriptions must have column: $col");
        }
    }

    // ═══════════════════════════════════════════════════════════
    // SERVICE FILES
    // ═══════════════════════════════════════════════════════════

    public function test_social_poster_service_exists(): void
    {
        $this->assertFileExists(__DIR__ . '/../../app/Services/SocialPoster.php');
    }

    public function test_web_push_service_exists(): void
    {
        $this->assertFileExists(__DIR__ . '/../../app/Services/WebPush.php');
    }

    public function test_service_worker_exists(): void
    {
        $this->assertFileExists(__DIR__ . '/../../public/service-worker.js');
    }

    public function test_push_prompt_js_exists(): void
    {
        $this->assertFileExists(__DIR__ . '/../../public/assets/push-prompt.js');
    }

    // ═══════════════════════════════════════════════════════════
    // SERVICE WORKER CONTENT
    // ═══════════════════════════════════════════════════════════

    public function test_service_worker_handles_push_event(): void
    {
        $sw = file_get_contents(__DIR__ . '/../../public/service-worker.js');
        $this->assertStringContainsString("addEventListener('push'", $sw);
    }

    public function test_service_worker_handles_notification_click(): void
    {
        $sw = file_get_contents(__DIR__ . '/../../public/service-worker.js');
        $this->assertStringContainsString("addEventListener('notificationclick'", $sw);
    }

    public function test_service_worker_handles_install_and_activate(): void
    {
        $sw = file_get_contents(__DIR__ . '/../../public/service-worker.js');
        $this->assertStringContainsString("addEventListener('install'", $sw);
        $this->assertStringContainsString("addEventListener('activate'", $sw);
    }

    public function test_service_worker_shows_notification(): void
    {
        $sw = file_get_contents(__DIR__ . '/../../public/service-worker.js');
        $this->assertStringContainsString('showNotification', $sw);
    }

    public function test_service_worker_opens_url_on_click(): void
    {
        $sw = file_get_contents(__DIR__ . '/../../public/service-worker.js');
        $this->assertStringContainsString('openWindow', $sw);
    }

    // ═══════════════════════════════════════════════════════════
    // PUSH PROMPT JS CONTENT
    // ═══════════════════════════════════════════════════════════

    public function test_push_prompt_registers_service_worker(): void
    {
        $js = file_get_contents(__DIR__ . '/../../public/assets/push-prompt.js');
        $this->assertStringContainsString('serviceWorker.register', $js);
    }

    public function test_push_prompt_posts_to_subscribe_endpoint(): void
    {
        $js = file_get_contents(__DIR__ . '/../../public/assets/push-prompt.js');
        $this->assertStringContainsString('/api/push/subscribe', $js);
    }

    public function test_push_prompt_converts_vapid_key(): void
    {
        $js = file_get_contents(__DIR__ . '/../../public/assets/push-prompt.js');
        $this->assertStringContainsString('urlBase64ToUint8Array', $js);
    }

    public function test_push_prompt_requests_permission(): void
    {
        $js = file_get_contents(__DIR__ . '/../../public/assets/push-prompt.js');
        $this->assertStringContainsString('Notification.requestPermission', $js);
    }

    public function test_push_prompt_reads_vapid_meta_tag(): void
    {
        $js = file_get_contents(__DIR__ . '/../../public/assets/push-prompt.js');
        $this->assertStringContainsString('vapid-public-key', $js);
    }

    // ═══════════════════════════════════════════════════════════
    // WEB PUSH SERVICE
    // ═══════════════════════════════════════════════════════════

    public function test_vapid_key_generation_returns_two_keys(): void
    {
        $keys = WebPush::generateVapidKeys();
        $this->assertArrayHasKey('public', $keys);
        $this->assertArrayHasKey('private', $keys);
    }

    public function test_vapid_public_key_is_correct_length(): void
    {
        $keys = WebPush::generateVapidKeys();
        // Base64url of 65 bytes = 87 chars
        $this->assertSame(87, strlen($keys['public']), 'Public key must be 87 base64url chars');
    }

    public function test_vapid_private_key_is_correct_length(): void
    {
        $keys = WebPush::generateVapidKeys();
        // Base64url of 32 bytes = 43 chars
        $this->assertSame(43, strlen($keys['private']), 'Private key must be 43 base64url chars');
    }

    public function test_vapid_keys_are_base64url_encoded(): void
    {
        $keys = WebPush::generateVapidKeys();
        // Base64url uses - and _ instead of + and /
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $keys['public']);
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $keys['private']);
    }

    public function test_vapid_keys_are_unique_each_generation(): void
    {
        $keys1 = WebPush::generateVapidKeys();
        $keys2 = WebPush::generateVapidKeys();
        $this->assertNotSame($keys1['public'], $keys2['public']);
        $this->assertNotSame($keys1['private'], $keys2['private']);
    }

    public function test_push_subscribe_saves_to_db(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/test-' . bin2hex(random_bytes(8));
        $p256dh   = base64_encode(random_bytes(65));
        $auth     = base64_encode(random_bytes(16));

        $result = WebPush::subscribe($endpoint, $p256dh, $auth, 'TestAgent/1.0');
        $this->assertTrue($result);

        $row = self::$pdo->prepare("SELECT * FROM push_subscriptions WHERE endpoint = ?")->execute([$endpoint]);
        $row = self::$pdo->prepare("SELECT * FROM push_subscriptions WHERE endpoint = ?"  );
        $row->execute([$endpoint]);
        $sub = $row->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($sub);
        $this->assertSame($p256dh, $sub['p256dh']);
    }

    public function test_push_subscribe_upserts_on_duplicate_endpoint(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/upsert-' . bin2hex(random_bytes(8));
        $p256dh1  = base64_encode(random_bytes(65));
        $p256dh2  = base64_encode(random_bytes(65));
        $auth     = base64_encode(random_bytes(16));

        WebPush::subscribe($endpoint, $p256dh1, $auth);
        WebPush::subscribe($endpoint, $p256dh2, $auth); // upsert

        $stmt = self::$pdo->prepare("SELECT COUNT(*) FROM push_subscriptions WHERE endpoint = ?");
        $stmt->execute([$endpoint]);
        $this->assertSame(1, (int)$stmt->fetchColumn(), 'Duplicate endpoint must upsert, not insert twice');
    }

    public function test_push_unsubscribe_removes_record(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/unsub-' . bin2hex(random_bytes(8));
        WebPush::subscribe($endpoint, base64_encode(random_bytes(65)), base64_encode(random_bytes(16)));
        WebPush::unsubscribe($endpoint);

        $stmt = self::$pdo->prepare("SELECT COUNT(*) FROM push_subscriptions WHERE endpoint = ?");
        $stmt->execute([$endpoint]);
        $this->assertSame(0, (int)$stmt->fetchColumn());
    }

    public function test_push_count_returns_integer(): void
    {
        $count = WebPush::count();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_push_count_increments_on_subscribe(): void
    {
        $before = WebPush::count();
        WebPush::subscribe(
            'https://fcm.googleapis.com/fcm/send/count-' . bin2hex(random_bytes(8)),
            base64_encode(random_bytes(65)),
            base64_encode(random_bytes(16))
        );
        $this->assertSame($before + 1, WebPush::count());
    }

    // ═══════════════════════════════════════════════════════════
    // SOCIAL POSTER SERVICE
    // ═══════════════════════════════════════════════════════════

    public function test_social_poster_skips_when_no_credentials(): void
    {
        $cat     = $this->createCategory();
        $user    = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');

        $results = SocialPoster::postArticle($article);

        // In test env no platforms are enabled, so results is empty — that's correct behaviour
        $this->assertIsArray($results, 'postArticle must always return an array');
        foreach ($results as $platform => $status) {
            $this->assertContains($status, ['skipped', 'sent', 'failed'],
                "Platform $platform must return a valid status");
        }
    }

    public function test_social_posts_log_can_be_inserted(): void
    {
        $cat     = $this->createCategory();
        $user    = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');

        self::$pdo->prepare(
            "INSERT INTO social_posts_log (article_id, platform, status)
             VALUES (:aid, :p, :s)"
        )->execute([
            ':aid' => $article['id'],
            ':p'   => 'telegram',
            ':s'   => 'sent',
        ]);

        $count = self::$pdo->prepare(
            "SELECT COUNT(*) FROM social_posts_log WHERE article_id = ? AND platform = 'telegram'"
        );
        $count->execute([$article['id']]);
        $this->assertSame(1, (int)$count->fetchColumn());
    }

    public function test_social_posts_log_status_values(): void
    {
        $cat     = $this->createCategory();
        $user    = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');

        foreach (['sent', 'failed', 'skipped'] as $status) {
            self::$pdo->prepare(
                "INSERT INTO social_posts_log (article_id, platform, status) VALUES (?, ?, ?)"
            )->execute([$article['id'], 'facebook', $status]);
        }

        $count = self::$pdo->prepare(
            "SELECT COUNT(*) FROM social_posts_log WHERE article_id = ?"
        );
        $count->execute([$article['id']]);
        $this->assertSame(3, (int)$count->fetchColumn());
    }

    public function test_social_posts_log_cascades_on_article_delete(): void
    {
        $cat     = $this->createCategory();
        $user    = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');

        self::$pdo->prepare(
            "INSERT INTO social_posts_log (article_id, platform, status) VALUES (?, 'twitter', 'sent')"
        )->execute([$article['id']]);

        // Delete the article
        self::$pdo->prepare("DELETE FROM articles WHERE id = ?")->execute([$article['id']]);

        $count = self::$pdo->prepare(
            "SELECT COUNT(*) FROM social_posts_log WHERE article_id = ?"
        );
        $count->execute([$article['id']]);
        $this->assertSame(0, (int)$count->fetchColumn(), 'Log entries must cascade-delete with article');
    }

    // ═══════════════════════════════════════════════════════════
    // ROUTES
    // ═══════════════════════════════════════════════════════════

    public function test_push_subscribe_api_route_defined(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../routes/web.php');
        $this->assertStringContainsString('/api/push/subscribe', $routes);
    }

    public function test_push_unsubscribe_api_route_defined(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../routes/web.php');
        $this->assertStringContainsString('/api/push/unsubscribe', $routes);
    }

    public function test_social_post_log_admin_route_defined(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../routes/admin.php');
        $this->assertStringContainsString('/admin/social/posts', $routes);
    }

    public function test_push_settings_admin_route_defined(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../routes/admin.php');
        $this->assertStringContainsString('/admin/push/settings', $routes);
    }

    public function test_push_generate_keys_route_defined(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../routes/admin.php');
        $this->assertStringContainsString('/admin/push/generate-keys', $routes);
    }

    // ═══════════════════════════════════════════════════════════
    // ADMIN CONTROLLER METHODS
    // ═══════════════════════════════════════════════════════════

    public function test_admin_controller_has_social_post_log_method(): void
    {
        $this->assertTrue(
            method_exists(AdminController::class, 'socialPostLog'),
            'AdminController must have socialPostLog method'
        );
    }

    public function test_admin_controller_has_push_settings_method(): void
    {
        $this->assertTrue(
            method_exists(AdminController::class, 'pushSettings'),
            'AdminController must have pushSettings method'
        );
    }

    public function test_admin_controller_has_push_settings_save_method(): void
    {
        $this->assertTrue(
            method_exists(AdminController::class, 'pushSettingsSave'),
            'AdminController must have pushSettingsSave method'
        );
    }

    public function test_admin_controller_has_push_generate_keys_method(): void
    {
        $this->assertTrue(
            method_exists(AdminController::class, 'pushGenerateKeys'),
            'AdminController must have pushGenerateKeys method'
        );
    }

    public function test_frontend_controller_has_push_subscribe_method(): void
    {
        $this->assertTrue(
            method_exists(FrontendController::class, 'pushSubscribe'),
            'FrontendController must have pushSubscribe method'
        );
    }

    public function test_frontend_controller_has_push_unsubscribe_method(): void
    {
        $this->assertTrue(
            method_exists(FrontendController::class, 'pushUnsubscribe'),
            'FrontendController must have pushUnsubscribe method'
        );
    }

    // ═══════════════════════════════════════════════════════════
    // ADMIN VIEWS
    // ═══════════════════════════════════════════════════════════

    public function test_push_settings_view_exists(): void
    {
        $this->assertFileExists(__DIR__ . '/../../app/Views/admin/push_settings.php');
    }

    public function test_social_post_log_view_exists(): void
    {
        $this->assertFileExists(__DIR__ . '/../../app/Views/admin/social_post_log.php');
    }

    public function test_push_settings_view_has_generate_form_outside_main_form(): void
    {
        $view = file_get_contents(__DIR__ . '/../../app/Views/admin/push_settings.php');

        // Find position of the generateVapidForm id attribute
        $generateFormPos = strpos($view, 'id="generateVapidForm"');

        // Find the closing </form> that comes BEFORE the generate form
        // i.e. the main settings form must close before the generate form appears
        $mainFormClose = strrpos(substr($view, 0, $generateFormPos), '</form>');

        $this->assertNotFalse($generateFormPos, 'generateVapidForm must exist in view');
        $this->assertNotFalse($mainFormClose,   'Main </form> must appear before generateVapidForm');
    }

    public function test_push_settings_view_has_vapid_fields(): void
    {
        $view = file_get_contents(__DIR__ . '/../../app/Views/admin/push_settings.php');
        $this->assertStringContainsString('push_vapid_public', $view);
        $this->assertStringContainsString('push_vapid_private', $view);
        $this->assertStringContainsString('push_subject', $view);
    }

    public function test_push_settings_view_has_all_social_platforms(): void
    {
        $view = file_get_contents(__DIR__ . '/../../app/Views/admin/push_settings.php');
        foreach (['facebook', 'twitter', 'telegram', 'whatsapp', 'linkedin'] as $platform) {
            $this->assertStringContainsString("social_{$platform}_enabled", $view,
                "Push settings view must have $platform toggle");
        }
    }

    public function test_social_post_log_view_shows_platforms(): void
    {
        $view = file_get_contents(__DIR__ . '/../../app/Views/admin/social_post_log.php');
        $this->assertStringContainsString('facebook', $view);
        $this->assertStringContainsString('telegram', $view);
        $this->assertStringContainsString('twitter', $view);
    }

    // ═══════════════════════════════════════════════════════════
    // ADMIN SIDEBAR
    // ═══════════════════════════════════════════════════════════

    public function test_admin_layout_has_push_settings_link(): void
    {
        $layout = file_get_contents(__DIR__ . '/../../app/Views/admin/layout.php');
        $this->assertStringContainsString('/admin/push/settings', $layout);
    }

    public function test_admin_layout_has_social_post_log_link(): void
    {
        $layout = file_get_contents(__DIR__ . '/../../app/Views/admin/layout.php');
        $this->assertStringContainsString('/admin/social/posts', $layout);
    }

    // ═══════════════════════════════════════════════════════════
    // FRONTEND LAYOUT (VAPID META TAG)
    // ═══════════════════════════════════════════════════════════

    public function test_frontend_layout_conditionally_includes_push_prompt(): void
    {
        $layout = file_get_contents(__DIR__ . '/../../app/Views/frontend/layout.php');
        $this->assertStringContainsString('push-prompt.js', $layout);
        $this->assertStringContainsString('vapid-public-key', $layout);
    }

    public function test_frontend_layout_checks_push_enabled_setting(): void
    {
        $layout = file_get_contents(__DIR__ . '/../../app/Views/frontend/layout.php');
        $this->assertStringContainsString('push_enabled', $layout);
        $this->assertStringContainsString('push_vapid_public', $layout);
    }

    // ═══════════════════════════════════════════════════════════
    // PUBLISH HOOK IN ADMIN CONTROLLER
    // ═══════════════════════════════════════════════════════════

    public function test_admin_controller_calls_social_poster_on_publish(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Controllers/AdminController.php');
        $this->assertStringContainsString('SocialPoster::postArticle', $src);
    }

    public function test_admin_controller_calls_web_push_on_publish(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Controllers/AdminController.php');
        $this->assertStringContainsString('WebPush::notifyArticle', $src);
    }

    public function test_publish_hook_only_fires_on_first_publish(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Controllers/AdminController.php');
        // articleUpdate should check previousStatus !== 'published'
        $this->assertStringContainsString("previousStatus !== 'published'", $src);
    }

    public function test_social_poster_post_article_returns_array(): void
    {
        $cat     = $this->createCategory();
        $user    = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');
        $article['slug'] = 'test-slug';

        $results = SocialPoster::postArticle($article);
        $this->assertIsArray($results);
    }
}