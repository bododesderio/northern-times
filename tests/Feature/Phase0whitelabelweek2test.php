<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\FrontendController;
use App\Models\Setting;
use Tests\DatabaseTestCase;
use PDO;

/**
 * Phase 0 — White-Label Refactor: Week 2
 *
 * Covers:
 *  - Migration 0048 seeds mail_from_name and robots_txt_custom
 *  - AdminSettingsController defaults are now brand-neutral
 *  - Admin layout sidebar subtitle uses site_abbreviation setting
 *  - Service worker has no hardcoded "The Northern Times" or "nt-" strings
 *  - push-prompt.js sends site title to service worker
 *  - Frontend layout exposes <meta name="site-title"> for push SW
 *  - robots.txt is now generated dynamically via FrontendController::robotsTxt()
 *  - robots.txt route is registered
 *  - Session name derives from APP_NAME env, not hardcoded 'northern_times_session'
 *  - Mailer already reads from_name from site_settings (regression guard)
 */
class Phase0WhiteLabelWeek2Test extends DatabaseTestCase
{
    private function flush(): void
    {
        get_site_setting_flush();
    }

    // ═══════════════════════════════════════════════════════════
    // MIGRATION 0048
    // ═══════════════════════════════════════════════════════════

    public function test_migration_0048_file_exists(): void
    {
        $this->assertFileExists(
            __DIR__ . '/../../database/migrations/0048_white_label_week2.sql'
        );
    }

    public function test_migration_0048_seeds_mail_from_name(): void
    {
        $result = self::$pdo->query(
            "SELECT setting_value FROM site_settings WHERE setting_key = 'mail_from_name'"
        )->fetchColumn();
        $this->assertNotFalse($result, 'mail_from_name must exist in site_settings after migration');
    }

    public function test_migration_0048_seeds_robots_txt_custom(): void
    {
        $result = self::$pdo->query(
            "SELECT setting_value FROM site_settings WHERE setting_key = 'robots_txt_custom'"
        )->fetchColumn();
        $this->assertNotFalse($result, 'robots_txt_custom must exist in site_settings after migration');
    }

    public function test_migration_0048_is_idempotent(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../database/migrations/0048_white_label_week2.sql');
        $statements = array_filter(
            explode(';', $sql),
            fn($s) => trim(preg_replace('/--[^\n]*/', '', $s)) !== ''
        );
        foreach ($statements as $stmt) {
            $trimmed = trim(preg_replace('/--[^\n]*/', '', $stmt));
            if ($trimmed === '') continue;
            self::$pdo->exec($trimmed); // must not throw
        }
        $this->assertTrue(true, 'Migration 0048 must be idempotent');
    }

    // ═══════════════════════════════════════════════════════════
    // ADMIN SETTINGS CONTROLLER — BRAND-NEUTRAL DEFAULTS
    // ═══════════════════════════════════════════════════════════

    public function test_admin_settings_controller_site_title_default_is_empty(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Controllers/AdminSettingsController.php');
        $this->assertStringNotContainsString(
            "'site_title'              => 'The Northern Times'",
            $src,
            'site_title default must not be hardcoded to The Northern Times'
        );
    }

    public function test_admin_settings_controller_publisher_name_default_is_empty(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Controllers/AdminSettingsController.php');
        $this->assertStringNotContainsString(
            "'publisher_name'          => 'The Northern Times'",
            $src,
            'publisher_name default must not be hardcoded to The Northern Times'
        );
    }

    public function test_admin_settings_controller_crawl_author_default_is_empty(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Controllers/AdminSettingsController.php');
        $this->assertStringNotContainsString(
            "'default_crawl_author'    => 'NT Newsroom'",
            $src,
            'default_crawl_author default must not be hardcoded to NT Newsroom'
        );
    }

    public function test_admin_settings_controller_user_agent_default_is_empty(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Controllers/AdminSettingsController.php');
        $this->assertStringNotContainsString(
            "'crawler_user_agent_name' => 'NTCrawler/1.0'",
            $src,
            'crawler_user_agent_name default must not be hardcoded to NTCrawler/1.0'
        );
    }

    public function test_admin_settings_controller_still_has_all_white_label_keys(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Controllers/AdminSettingsController.php');
        foreach ([
            'site_abbreviation', 'publisher_name', 'registration_number',
            'contact_address', 'copyright_template', 'default_crawl_author',
            'crawler_user_agent_name',
        ] as $key) {
            $this->assertStringContainsString("'$key'", $src,
                "AdminSettingsController must still declare '$key' field");
        }
    }

    // ═══════════════════════════════════════════════════════════
    // ADMIN LAYOUT — DYNAMIC SIDEBAR SUBTITLE
    // ═══════════════════════════════════════════════════════════

    public function test_admin_layout_sidebar_subtitle_is_not_hardcoded(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/admin/layout.php');
        $this->assertStringNotContainsString(
            '>Newsroom Admin<',
            $src,
            'Admin sidebar subtitle must not be hardcoded as "Newsroom Admin"'
        );
    }

    public function test_admin_layout_sidebar_subtitle_uses_site_abbreviation(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/admin/layout.php');
        $this->assertStringContainsString(
            "get_site_setting('site_abbreviation'",
            $src,
            'Admin sidebar subtitle must use site_abbreviation setting'
        );
    }

    // ═══════════════════════════════════════════════════════════
    // SERVICE WORKER — NO HARDCODED BRAND STRINGS
    // ═══════════════════════════════════════════════════════════

    public function test_service_worker_has_no_hardcoded_northern_times(): void
    {
        $src = file_get_contents(__DIR__ . '/../../public/service-worker.js');
        $this->assertStringNotContainsString(
            'The Northern Times',
            $src,
            'service-worker.js must not contain hardcoded "The Northern Times"'
        );
    }

    public function test_service_worker_cache_name_is_not_nt_specific(): void
    {
        $src = file_get_contents(__DIR__ . '/../../public/service-worker.js');
        $this->assertStringNotContainsString(
            "'nt-cache-v1'",
            $src,
            'Cache name must not be hardcoded as nt-cache-v1'
        );
    }

    public function test_service_worker_push_tag_is_not_nt_specific(): void
    {
        $src = file_get_contents(__DIR__ . '/../../public/service-worker.js');
        $this->assertStringNotContainsString(
            "'nt-news'",
            $src,
            'Push notification tag must not be hardcoded as nt-news'
        );
    }

    public function test_service_worker_has_message_listener_for_site_title(): void
    {
        $src = file_get_contents(__DIR__ . '/../../public/service-worker.js');
        $this->assertStringContainsString(
            'SET_SITE_TITLE',
            $src,
            'service-worker.js must have a message listener to receive site title'
        );
    }

    public function test_service_worker_uses_dynamic_site_title_for_push(): void
    {
        $src = file_get_contents(__DIR__ . '/../../public/service-worker.js');
        $this->assertStringContainsString(
            '_siteTitle',
            $src,
            'service-worker.js must use self._siteTitle for push notification title fallback'
        );
    }

    // ═══════════════════════════════════════════════════════════
    // PUSH PROMPT JS — SENDS SITE TITLE TO SW
    // ═══════════════════════════════════════════════════════════

    public function test_push_prompt_sends_site_title_to_service_worker(): void
    {
        $src = file_get_contents(__DIR__ . '/../../public/assets/push-prompt.js');
        $this->assertStringContainsString(
            'SET_SITE_TITLE',
            $src,
            'push-prompt.js must send SET_SITE_TITLE message to service worker'
        );
    }

    public function test_push_prompt_reads_site_title_meta_tag(): void
    {
        $src = file_get_contents(__DIR__ . '/../../public/assets/push-prompt.js');
        $this->assertStringContainsString(
            'site-title',
            $src,
            'push-prompt.js must read <meta name="site-title"> to get the brand name'
        );
    }

    public function test_push_prompt_has_no_hardcoded_northern_times(): void
    {
        $src = file_get_contents(__DIR__ . '/../../public/assets/push-prompt.js');
        $this->assertStringNotContainsString(
            'The Northern Times',
            $src
        );
    }

    // ═══════════════════════════════════════════════════════════
    // FRONTEND LAYOUT — SITE-TITLE META TAG FOR SW
    // ═══════════════════════════════════════════════════════════

    public function test_frontend_layout_has_site_title_meta_tag(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/frontend/layout.php');
        $this->assertStringContainsString(
            'name="site-title"',
            $src,
            'frontend/layout.php must include <meta name="site-title"> for service worker'
        );
    }

    public function test_frontend_layout_site_title_meta_uses_setting(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/frontend/layout.php');
        // The meta tag content must use the dynamic site title variable or get_site_setting
        $this->assertTrue(
            str_contains($src, 'name="site-title"') &&
            (str_contains($src, '$siteTitle') || str_contains($src, "get_site_setting('site_title'")),
            'site-title meta must be populated from $siteTitle or get_site_setting()'
        );
    }

    // ═══════════════════════════════════════════════════════════
    // ROBOTS.TXT — DYNAMIC ROUTE
    // ═══════════════════════════════════════════════════════════

    public function test_robots_txt_route_is_registered(): void
    {
        $src = file_get_contents(__DIR__ . '/../../routes/web.php');
        $this->assertStringContainsString(
            '/robots.txt',
            $src,
            'routes/web.php must register a /robots.txt route'
        );
    }

    public function test_frontend_controller_has_robots_txt_method(): void
    {
        $this->assertTrue(
            method_exists(FrontendController::class, 'robotsTxt'),
            'FrontendController must have a robotsTxt() method'
        );
    }

    public function test_robots_txt_method_returns_200(): void
    {
        $ctrl = new FrontendController();
        $resp = $ctrl->robotsTxt();
        $this->assertSame(200, $resp->getStatusCode());
    }

    public function test_robots_txt_response_is_plain_text(): void
    {
        $ctrl = new FrontendController();
        $resp = $ctrl->robotsTxt();
        $this->assertStringContainsString('text/plain', $resp->headers->get('Content-Type'));
    }

    public function test_robots_txt_disallows_admin(): void
    {
        $ctrl = new FrontendController();
        $body = $ctrl->robotsTxt()->getContent();
        $this->assertStringContainsString('Disallow: /admin', $body);
    }

    public function test_robots_txt_has_sitemap_directive(): void
    {
        $ctrl = new FrontendController();
        $body = $ctrl->robotsTxt()->getContent();
        $this->assertStringContainsString('Sitemap:', $body);
        $this->assertStringContainsString('/sitemap.xml', $body);
    }

    public function test_robots_txt_has_no_hardcoded_northerntimes_domain(): void
    {
        $ctrl = new FrontendController();
        $body = $ctrl->robotsTxt()->getContent();
        $this->assertStringNotContainsString(
            'northerntimes.co.ug',
            $body,
            'Dynamic robots.txt must not contain hardcoded northerntimes.co.ug domain'
        );
    }

    public function test_static_robots_txt_sitemap_is_no_longer_hardcoded_domain(): void
    {
        // The static public/robots.txt file may still exist but the sitemap URL
        // should not be hardcoded to northerntimes.co.ug since traffic now goes
        // through the PHP route which uses app_url()
        $path = __DIR__ . '/../../public/robots.txt';
        if (!file_exists($path)) {
            $this->markTestSkipped('Static robots.txt has been removed — dynamic route takes over');
        }
        // If it still exists, it's ok as a fallback, but it shouldn't have the NT domain
        // OR it should have a placeholder comment noting it's superseded by the PHP route
        $content = file_get_contents($path);
        $this->assertStringNotContainsString(
            'northerntimes.co.ug',
            $content,
            'Static robots.txt must not hardcode northerntimes.co.ug'
        );
    }

    // ═══════════════════════════════════════════════════════════
    // SESSION NAME — DERIVED FROM ENV, NOT HARDCODED
    // ═══════════════════════════════════════════════════════════

    public function test_public_index_php_has_no_hardcoded_northern_times_session(): void
    {
        $src = file_get_contents(__DIR__ . '/../../public/index.php');
        $this->assertStringNotContainsString(
            "'northern_times_session'",
            $src,
            'public/index.php must not hardcode session name as northern_times_session'
        );
    }

    public function test_middleware_index_php_has_no_hardcoded_northern_times_session(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Middleware/index.php');
        $this->assertStringNotContainsString(
            "'northern_times_session'",
            $src,
            'app/Middleware/index.php must not hardcode session name as northern_times_session'
        );
    }

    public function test_public_index_php_derives_session_from_app_name(): void
    {
        $src = file_get_contents(__DIR__ . '/../../public/index.php');
        $this->assertStringContainsString(
            'APP_NAME',
            $src,
            'public/index.php session name must derive from APP_NAME env variable'
        );
    }

    public function test_middleware_index_php_derives_session_from_app_name(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Middleware/index.php');
        $this->assertStringContainsString(
            'APP_NAME',
            $src,
            'app/Middleware/index.php session name must derive from APP_NAME env variable'
        );
    }

    // ═══════════════════════════════════════════════════════════
    // MAILER — REGRESSION: already dynamic (guard test)
    // ═══════════════════════════════════════════════════════════

    public function test_mailer_from_name_reads_from_site_settings(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Services/Mailer.php');
        $this->assertStringContainsString(
            "get_site_setting('site_title'",
            $src,
            'Mailer must use get_site_setting(site_title) as from_name fallback'
        );
    }

    public function test_mailer_from_name_does_not_hardcode_northern_times(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Services/Mailer.php');
        // The .env example comment may mention it, but not in code
        $lines = array_filter(explode("\n", $src), fn($l) => !str_starts_with(trim($l), '*') && !str_starts_with(trim($l), '//'));
        $code = implode("\n", $lines);
        $this->assertStringNotContainsString(
            "'The Northern Times'",
            $code,
            'Mailer must not hardcode "The Northern Times" in executable code'
        );
    }

    // ═══════════════════════════════════════════════════════════
    // FULL AUDIT — NO NEW HARDCODES INTRODUCED
    // ═══════════════════════════════════════════════════════════

    public function test_no_hardcoded_northern_times_in_admin_layout(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/admin/layout.php');
        // CSS class names like .nt-logo are fine (not brand identity strings)
        // Actual text content must be dynamic
        $this->assertStringNotContainsString(
            '>The Northern Times<',
            $src
        );
        $this->assertStringNotContainsString(
            '>Northern Times Admin<',
            $src
        );
    }

    public function test_no_hardcoded_northern_times_in_error_views(): void
    {
        $base = file_get_contents(__DIR__ . '/../../app/Views/errors/_base.php');
        // Must use get_site_setting, not a literal string in rendered output
        $this->assertStringNotContainsString(
            "'The Northern Times'",
            $base,
            'Error views must not hardcode The Northern Times as a fallback — use empty or generic'
        );
    }

    public function test_service_worker_still_handles_push_event(): void
    {
        // Regression: ensure Week 2 changes didn't break functional SW code
        $src = file_get_contents(__DIR__ . '/../../public/service-worker.js');
        $this->assertStringContainsString("addEventListener('push'", $src);
        $this->assertStringContainsString('showNotification', $src);
    }

    public function test_service_worker_still_handles_install_and_activate(): void
    {
        $src = file_get_contents(__DIR__ . '/../../public/service-worker.js');
        $this->assertStringContainsString("addEventListener('install'", $src);
        $this->assertStringContainsString("addEventListener('activate'", $src);
    }
}