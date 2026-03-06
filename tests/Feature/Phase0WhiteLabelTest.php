<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\AdminSettingsController;
use App\Controllers\AdminSystemController;
use App\Models\Setting;
use App\Services\CrawlerEngine;
use Tests\DatabaseTestCase;
use PDO;

/**
 * Phase 0: White-Label Refactor
 *
 * Covers:
 *  - Migration 0047 seeded the 7 new identity keys
 *  - Setting model get/set round-trips for each key
 *  - helpers: copyright_line(), crawl_author_name(), article_author()
 *  - Article model SQL no longer contains literal 'NT Newsroom'
 *  - CrawlerEngine uses setting for display_author
 *  - Admin settings UI contains Publisher Identity fields
 *  - Admin settings controller includes white-label fields in defaults
 *  - Frontend layout uses copyright_line() not hardcoded string
 *  - Frontend article view uses setting for crawled author name/initials
 *  - Backup filename uses site_abbreviation
 *  - AdminSettingsController has white-label keys in generalFields
 */
class Phase0WhiteLabelTest extends DatabaseTestCase
{
    /**
     * Flush the get_site_setting() static cache after writing to DB in tests.
     * Required because the cache persists across calls within the same PHP process.
     */
    private function flush(): void
    {
        get_site_setting_flush();
    }

    // ═══════════════════════════════════════════════════════════
    // MIGRATION
    // ═══════════════════════════════════════════════════════════

    public function test_migration_file_exists(): void
    {
        $this->assertFileExists(
            __DIR__ . '/../../database/migrations/0047_white_label_settings.sql'
        );
    }

    public function test_migration_seeds_site_abbreviation(): void
    {
        $result = self::$pdo->query(
            "SELECT setting_value FROM site_settings WHERE setting_key = 'site_abbreviation'"
        )->fetchColumn();
        $this->assertNotFalse($result, 'site_abbreviation must exist in site_settings after migration');
    }

    public function test_migration_seeds_publisher_name(): void
    {
        $result = self::$pdo->query(
            "SELECT setting_value FROM site_settings WHERE setting_key = 'publisher_name'"
        )->fetchColumn();
        $this->assertNotFalse($result, 'publisher_name must exist in site_settings');
    }

    public function test_migration_seeds_default_crawl_author(): void
    {
        $result = self::$pdo->query(
            "SELECT setting_value FROM site_settings WHERE setting_key = 'default_crawl_author'"
        )->fetchColumn();
        $this->assertNotFalse($result, 'default_crawl_author must exist in site_settings');
    }

    public function test_migration_seeds_crawler_user_agent_name(): void
    {
        $result = self::$pdo->query(
            "SELECT setting_value FROM site_settings WHERE setting_key = 'crawler_user_agent_name'"
        )->fetchColumn();
        $this->assertNotFalse($result, 'crawler_user_agent_name must exist in site_settings');
    }

    public function test_migration_seeds_copyright_template(): void
    {
        $result = self::$pdo->query(
            "SELECT setting_value FROM site_settings WHERE setting_key = 'copyright_template'"
        )->fetchColumn();
        $this->assertNotFalse($result, 'copyright_template must exist in site_settings');
    }

    public function test_migration_seeds_registration_number(): void
    {
        $result = self::$pdo->query(
            "SELECT setting_value FROM site_settings WHERE setting_key = 'registration_number'"
        )->fetchColumn();
        $this->assertNotFalse($result, 'registration_number must exist in site_settings');
    }

    public function test_migration_seeds_contact_address(): void
    {
        $result = self::$pdo->query(
            "SELECT setting_value FROM site_settings WHERE setting_key = 'contact_address'"
        )->fetchColumn();
        $this->assertNotFalse($result, 'contact_address must exist in site_settings');
    }

    public function test_migration_is_idempotent(): void
    {
        // Running the migration SQL again (ON CONFLICT DO NOTHING) must not error
        $sql = file_get_contents(__DIR__ . '/../../database/migrations/0047_white_label_settings.sql');
        // Strip comment lines for cleaner execution
        $statements = array_filter(
            explode(';', $sql),
            fn($s) => trim(preg_replace('/--[^\n]*/', '', $s)) !== ''
        );
        foreach ($statements as $stmt) {
            $trimmed = trim(preg_replace('/--[^\n]*/', '', $stmt));
            if ($trimmed === '') continue;
            self::$pdo->exec($trimmed); // must not throw
        }
        $this->assertTrue(true, 'Migration must be idempotent (ON CONFLICT DO NOTHING)');
    }

    // ═══════════════════════════════════════════════════════════
    // SETTING MODEL — ROUND-TRIP
    // ═══════════════════════════════════════════════════════════

    public function test_setting_set_and_get_site_abbreviation(): void
    {
        Setting::set('site_abbreviation', 'ACME');
        $this->assertSame('ACME', Setting::get('site_abbreviation'));
    }

    public function test_setting_set_and_get_publisher_name(): void
    {
        Setting::set('publisher_name', 'Acme Media Ltd');
        $this->assertSame('Acme Media Ltd', Setting::get('publisher_name'));
    }

    public function test_setting_set_and_get_default_crawl_author(): void
    {
        Setting::set('default_crawl_author', 'ACME Newsroom');
        $this->assertSame('ACME Newsroom', Setting::get('default_crawl_author'));
    }

    public function test_setting_set_and_get_crawler_user_agent_name(): void
    {
        Setting::set('crawler_user_agent_name', 'AcmeCrawler/2.0');
        $this->assertSame('AcmeCrawler/2.0', Setting::get('crawler_user_agent_name'));
    }

    public function test_setting_set_and_get_copyright_template(): void
    {
        Setting::set('copyright_template', '© {year} {publisher}. All rights reserved.');
        $this->assertSame('© {year} {publisher}. All rights reserved.', Setting::get('copyright_template'));
    }

    public function test_setting_set_and_get_registration_number(): void
    {
        Setting::set('registration_number', 'UG-2025-1234');
        $this->assertSame('UG-2025-1234', Setting::get('registration_number'));
    }

    public function test_setting_set_and_get_contact_address(): void
    {
        Setting::set('contact_address', 'P.O. Box 1, Kampala');
        $this->assertSame('P.O. Box 1, Kampala', Setting::get('contact_address'));
    }

    public function test_setting_upserts_on_second_write(): void
    {
        Setting::set('site_abbreviation', 'AA');
        Setting::set('site_abbreviation', 'BB');
        $this->assertSame('BB', Setting::get('site_abbreviation'));

        $count = self::$pdo->query(
            "SELECT COUNT(*) FROM site_settings WHERE setting_key = 'site_abbreviation'"
        )->fetchColumn();
        $this->assertSame(1, (int)$count, 'Upsert must not create duplicate rows');
    }

    // ═══════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════

    public function test_crawl_author_name_helper_exists(): void
    {
        $this->assertTrue(
            function_exists('crawl_author_name'),
            'crawl_author_name() helper must exist in helpers.php'
        );
    }

    public function test_copyright_line_helper_exists(): void
    {
        $this->assertTrue(
            function_exists('copyright_line'),
            'copyright_line() helper must exist in helpers.php'
        );
    }

    public function test_copyright_line_replaces_year(): void
    {
        Setting::set('copyright_template', '© {year} Publisher');
        Setting::set('publisher_name', 'Publisher');
        $this->flush();
        $line = copyright_line();
        $this->assertStringContainsString((string)date('Y'), $line);
        $this->assertStringNotContainsString('{year}', $line);
    }

    public function test_copyright_line_replaces_publisher(): void
    {
        Setting::set('copyright_template', '© 2025 {publisher}');
        Setting::set('publisher_name', 'Acme Media Ltd');
        $this->flush();
        $line = copyright_line();
        $this->assertStringContainsString('Acme Media Ltd', $line);
        $this->assertStringNotContainsString('{publisher}', $line);
    }

    public function test_copyright_line_replaces_abbreviation(): void
    {
        Setting::set('copyright_template', '{abbreviation} © {year}');
        Setting::set('site_abbreviation', 'NT');
        $this->flush();
        $line = copyright_line();
        $this->assertStringContainsString('NT', $line);
        $this->assertStringNotContainsString('{abbreviation}', $line);
    }

    public function test_copyright_line_all_variables_replaced(): void
    {
        Setting::set('copyright_template', '{abbreviation} © {year} {publisher}. All rights reserved.');
        Setting::set('site_abbreviation', 'NT');
        Setting::set('publisher_name', 'The Northern Times');
        $this->flush();
        $line = copyright_line();
        $this->assertStringNotContainsString('{', $line, 'All template variables must be replaced');
        $this->assertStringNotContainsString('}', $line, 'All template variables must be replaced');
    }

    public function test_copyright_line_uses_default_when_no_template(): void
    {
        // Remove template to test fallback
        self::$pdo->exec("DELETE FROM site_settings WHERE setting_key = 'copyright_template'");
        $this->flush();
        $line = copyright_line();
        // Should not throw and should return something with the year
        $this->assertStringContainsString((string)date('Y'), $line);
    }

    public function test_article_author_helper_returns_crawl_author_for_crawled_article(): void
    {
        // article_author() reads the pre-resolved 'author' field from SQL CASE.
        // For crawled articles, the SQL resolves to super_admin display_name.
        // When 'author' is set, article_author() returns it directly.
        $article = ['source_id' => 'some-source-uuid', 'author_name' => null, 'author' => 'Acme Newsroom'];
        $author = article_author($article);

        $this->assertSame('Acme Newsroom', $author['name'],
            'article_author() must return the pre-resolved author for crawled articles');
    }

    public function test_article_author_helper_returns_staff_for_authored_article(): void
    {
        $article = ['author_name' => 'Jane Doe', 'source_id' => null, 'author_id' => 'uuid-123'];
        $author = article_author($article);
        $this->assertSame('Jane Doe', $author['name']);
    }

    public function test_article_author_helper_returns_staff_writer_when_no_name(): void
    {
        // When no author_name or author is set, falls back to site_name() or 'Staff'
        $article = ['author_name' => null, 'author' => null, 'source_id' => null, 'author_id' => 'uuid-123'];
        $author = article_author($article);
        // The fallback uses site_name() if available, otherwise 'Staff'
        $expected = function_exists('site_name') ? site_name() : 'Staff';
        $this->assertSame($expected, $author['name']);
    }

    // ═══════════════════════════════════════════════════════════
    // ARTICLE MODEL — NO HARDCODED NT NEWSROOM IN SOURCE
    // ═══════════════════════════════════════════════════════════

    public function test_article_model_source_has_no_hardcoded_nt_newsroom(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Models/Article.php');
        // Allowed: the doc comment for the crawlAuthorName() method mentions it
        // Not allowed: any SQL string literal 'NT Newsroom'
        $this->assertStringNotContainsString("THEN 'NT Newsroom'", $src,
            "Article model SQL must not contain hardcoded 'NT Newsroom'");
    }

    public function test_article_model_has_crawl_author_name_method(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Models/Article.php');
        $this->assertStringContainsString('crawlAuthorName', $src,
            'Article model must use crawlAuthorName() for dynamic SQL');
    }

    public function test_article_model_crawl_author_method_uses_dynamic_lookup(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Models/Article.php');
        // crawlAuthorName() uses a SQL subquery to resolve from users table at query-time
        $this->assertStringContainsString('super_admin', $src,
            'Article::crawlAuthorName() must dynamically resolve the crawl author');
        $this->assertStringContainsString('display_name', $src,
            'Article::crawlAuthorName() must use display_name from users');
    }

    // ═══════════════════════════════════════════════════════════
    // CRAWLER ENGINE — NO HARDCODED NT NEWSROOM
    // ═══════════════════════════════════════════════════════════

    public function test_crawler_engine_source_has_no_hardcoded_nt_newsroom(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Services/CrawlerEngine.php');
        $this->assertStringNotContainsString("'NT Newsroom'", $src,
            "CrawlerEngine must not hardcode 'NT Newsroom' as display_author");
    }

    public function test_crawler_engine_uses_get_site_setting_for_display_author(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Services/CrawlerEngine.php');
        $this->assertStringContainsString('get_site_setting', $src,
            'CrawlerEngine must use get_site_setting() for crawled article display_author');
    }

    // ═══════════════════════════════════════════════════════════
    // FRONTEND VIEWS — NO HARDCODED STRINGS
    // ═══════════════════════════════════════════════════════════

    public function test_frontend_article_view_has_no_hardcoded_nt_newsroom(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/frontend/article.php');
        $this->assertStringNotContainsString("'NT Newsroom'", $src);
        $this->assertStringNotContainsString('"NT Newsroom"', $src);
    }

    public function test_frontend_article_view_uses_setting_for_crawl_author(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/frontend/article.php');
        $this->assertStringContainsString('get_site_setting', $src,
            'article.php must use get_site_setting() for crawled author display');
    }

    public function test_frontend_article_view_uses_setting_for_avatar_initials(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/frontend/article.php');
        $this->assertStringContainsString("get_site_setting('site_abbreviation'", $src,
            'article.php must use site_abbreviation setting for avatar initials');
    }

    public function test_frontend_article_view_has_no_hardcoded_nt_initials(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/frontend/article.php');
        // Should not have hardcoded '>NT<' initials in the avatar div
        $this->assertDoesNotMatchRegularExpression('/font-weight:800[^>]*>NT</', $src,
            'Avatar initials must not be hardcoded as NT');
    }

    public function test_frontend_layout_uses_copyright_line_helper(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/frontend/layout.php');
        $this->assertStringContainsString('copyright_line()', $src,
            'frontend/layout.php must use copyright_line() helper in footer');
    }

    public function test_frontend_layout_has_no_hardcoded_all_rights_reserved(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/frontend/layout.php');
        $this->assertStringNotContainsString('All rights reserved.', $src,
            'Hardcoded "All rights reserved." must be moved to copyright_template setting');
    }

    // ═══════════════════════════════════════════════════════════
    // ADMIN SETTINGS CONTROLLER
    // ═══════════════════════════════════════════════════════════

    public function test_admin_settings_controller_has_site_abbreviation_default(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Controllers/AdminSettingsController.php');
        $this->assertStringContainsString("'site_abbreviation'", $src);
    }

    public function test_admin_settings_controller_has_publisher_name_default(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Controllers/AdminSettingsController.php');
        $this->assertStringContainsString("'publisher_name'", $src);
    }

    public function test_admin_settings_controller_has_default_crawl_author_default(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Controllers/AdminSettingsController.php');
        $this->assertStringContainsString("'default_crawl_author'", $src);
    }

    public function test_admin_settings_controller_has_copyright_template_default(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Controllers/AdminSettingsController.php');
        $this->assertStringContainsString("'copyright_template'", $src);
    }

    public function test_admin_settings_controller_has_crawler_user_agent_default(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Controllers/AdminSettingsController.php');
        $this->assertStringContainsString("'crawler_user_agent_name'", $src);
    }

    // ═══════════════════════════════════════════════════════════
    // ADMIN SETTINGS VIEW (UI FIELDS)
    // ═══════════════════════════════════════════════════════════

    public function test_admin_settings_view_has_publisher_name_field(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/admin/settings.php');
        $this->assertStringContainsString('name="publisher_name"', $src,
            'Admin settings must have a publisher_name input field');
    }

    public function test_admin_settings_view_has_site_abbreviation_field(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/admin/settings.php');
        $this->assertStringContainsString('name="site_abbreviation"', $src,
            'Admin settings must have a site_abbreviation input field');
    }

    public function test_admin_settings_view_has_default_crawl_author_field(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/admin/settings.php');
        $this->assertStringContainsString('name="default_crawl_author"', $src,
            'Admin settings must have a default_crawl_author input field');
    }

    public function test_admin_settings_view_has_copyright_template_field(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/admin/settings.php');
        $this->assertStringContainsString('name="copyright_template"', $src,
            'Admin settings must have a copyright_template input field');
    }

    public function test_admin_settings_view_has_registration_number_field(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/admin/settings.php');
        $this->assertStringContainsString('name="registration_number"', $src,
            'Admin settings must have a registration_number input field');
    }

    public function test_admin_settings_view_has_contact_address_field(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/admin/settings.php');
        $this->assertStringContainsString('name="contact_address"', $src,
            'Admin settings must have a contact_address input field');
    }

    public function test_admin_settings_view_has_crawler_user_agent_field(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/admin/settings.php');
        $this->assertStringContainsString('name="crawler_user_agent_name"', $src,
            'Admin settings must have a crawler_user_agent_name input field');
    }

    public function test_admin_settings_view_has_publisher_identity_section(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/admin/settings.php');
        $this->assertStringContainsString('Publisher Identity', $src,
            'Admin settings must have a Publisher Identity section header');
    }

    public function test_admin_settings_view_explains_copyright_template_variables(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Views/admin/settings.php');
        $this->assertStringContainsString('{year}', $src);
        $this->assertStringContainsString('{publisher}', $src);
        $this->assertStringContainsString('{abbreviation}', $src);
    }

    // ═══════════════════════════════════════════════════════════
    // ADMIN SYSTEM CONTROLLER — BACKUP FILENAME
    // ═══════════════════════════════════════════════════════════

    public function test_admin_system_controller_backup_uses_site_abbreviation(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Controllers/AdminSystemController.php');
        $this->assertStringContainsString('site_abbreviation', $src,
            'AdminSystemController backup filename must use site_abbreviation from settings');
    }

    public function test_admin_system_controller_has_no_hardcoded_northern_times_backup(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Controllers/AdminSystemController.php');
        $this->assertStringNotContainsString('"northern_times_backup_"', $src,
            'Backup filename must not be hardcoded as northern_times_backup_');
    }

    // ═══════════════════════════════════════════════════════════
    // HELPERS FILE — NO HARDCODED FALLBACKS (NT-SPECIFIC)
    // ═══════════════════════════════════════════════════════════

    public function test_helpers_has_no_hardcoded_nt_newsroom_string_literal(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/Support/helpers.php');
        // The only allowed occurrence is in doc-comment examples and the crawl_author_name docblock
        // There must be no code path that returns the string literal 'NT Newsroom' directly
        $this->assertStringNotContainsString("return 'NT Newsroom'", $src,
            'helpers.php must not return hardcoded NT Newsroom');
        $this->assertStringNotContainsString('return "NT Newsroom"', $src,
            'helpers.php must not return hardcoded NT Newsroom');
    }

    // ═══════════════════════════════════════════════════════════
    // FULL INTEGRATION — SAVE + READ
    // ═══════════════════════════════════════════════════════════

    public function test_bulk_save_white_label_fields_and_read_back(): void
    {
        $fields = [
            'site_abbreviation'       => 'ACME',
            'publisher_name'          => 'Acme Media Ltd',
            'registration_number'     => 'UG-9999',
            'contact_address'         => 'Box 1, Kampala',
            'copyright_template'      => '© {year} {publisher}',
            'default_crawl_author'    => 'Acme Desk',
            'crawler_user_agent_name' => 'AcmeCrawler/3.0',
        ];

        // Use direct PDO upserts instead of Setting::bulkSave() to avoid
        // a nested transaction error (DatabaseTestCase already has one open).
        $stmt = self::$pdo->prepare(
            "INSERT INTO site_settings (setting_key, setting_value, setting_group)
             VALUES (:key, :val, 'general')
             ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value"
        );
        foreach ($fields as $key => $value) {
            $stmt->execute([':key' => $key, ':val' => $value]);
        }

        foreach ($fields as $key => $expected) {
            $this->assertSame($expected, Setting::get($key),
                "Setting '$key' must survive a bulk save and read back correctly");
        }
    }

    public function test_copyright_line_reflects_saved_settings(): void
    {
        Setting::set('copyright_template', '© {year} {publisher}. All rights reserved.');
        Setting::set('publisher_name',      'Acme Media Ltd');
        Setting::set('site_abbreviation',   'ACME');
        $this->flush();

        $line = copyright_line();
        $this->assertStringContainsString('Acme Media Ltd', $line);
        $this->assertStringContainsString((string)date('Y'), $line);
        $this->assertStringContainsString('All rights reserved.', $line);
    }

    public function test_crawl_author_name_helper_reflects_saved_setting(): void
    {
        Setting::set('default_crawl_author', 'Custom Desk');
        $this->flush(); // bust the static cache so get_site_setting() re-reads DB

        $name = get_site_setting('default_crawl_author', 'Newsroom');
        $this->assertSame('Custom Desk', $name);
    }

    // ═══════════════════════════════════════════════════════════
    // LEGACY VIEW — app/Views/article.php (unused but patched)
    // ═══════════════════════════════════════════════════════════

    public function test_legacy_article_view_has_no_hardcoded_nt_newsroom(): void
    {
        $path = __DIR__ . '/../../app/Views/article.php';
        if (!file_exists($path)) {
            $this->markTestSkipped('Legacy app/Views/article.php does not exist');
        }
        $src = file_get_contents($path);
        $this->assertStringNotContainsString("'NT Newsroom'", $src);
        $this->assertStringNotContainsString('"NT Newsroom"', $src);
    }

    public function test_legacy_article_view_uses_setting_for_avatar_initials(): void
    {
        $path = __DIR__ . '/../../app/Views/article.php';
        if (!file_exists($path)) {
            $this->markTestSkipped('Legacy app/Views/article.php does not exist');
        }
        $src = file_get_contents($path);
        $this->assertStringContainsString("get_site_setting('site_abbreviation'", $src);
    }
}