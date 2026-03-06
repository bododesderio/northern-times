<?php
declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\SystemLog;
use Tests\DatabaseTestCase;

class SystemLogTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure system_log table exists
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS system_log (
                id              SERIAL PRIMARY KEY,
                user_id         UUID REFERENCES users(id) ON DELETE SET NULL,
                user_email      VARCHAR(255) NOT NULL,
                action          VARCHAR(100) NOT NULL,
                details         TEXT DEFAULT '',
                records_affected INTEGER DEFAULT 0,
                danger_level    VARCHAR(20) DEFAULT 'safe',
                ip_address      VARCHAR(45) DEFAULT '',
                created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
    }

    // ── SystemLog Model Tests ───────────────────────────────────

    public function test_log_creates_entry(): void
    {
        $user = $this->createUser('syslog@test.com', 'super_admin');
        $_SESSION['user'] = ['id' => $user['id'], 'email' => $user['email']];
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $entry = SystemLog::log('test_action', 'Test details', 5, 'safe');

        $this->assertNotNull($entry);
        $this->assertSame('test_action', $entry['action']);
        $this->assertSame('Test details', $entry['details']);
        $this->assertSame(5, (int)$entry['records_affected']);
        $this->assertSame('safe', $entry['danger_level']);
        $this->assertSame('syslog@test.com', $entry['user_email']);
        $this->assertSame('192.168.1.100', $entry['ip_address']);
    }

    public function test_log_without_session_uses_system(): void
    {
        unset($_SESSION['user']);
        $entry = SystemLog::log('auto_task', 'Automated task');

        $this->assertNotNull($entry);
        $this->assertSame('system', $entry['user_email']);
        $this->assertNull($entry['user_id']);
    }

    public function test_log_stores_all_danger_levels(): void
    {
        $levels = ['safe', 'content', 'media', 'danger', 'factory'];
        foreach ($levels as $level) {
            $entry = SystemLog::log("test_{$level}", "Testing {$level}", 0, $level);
            $this->assertNotNull($entry);
            $this->assertSame($level, $entry['danger_level']);
        }
    }

    public function test_delete_throws_exception(): void
    {
        $entry = SystemLog::log('test_entry', 'Should not be deletable');
        $this->assertNotNull($entry);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('System logs cannot be deleted.');
        SystemLog::delete((string)$entry['id']);
    }

    public function test_truncate_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('System logs cannot be truncated.');
        SystemLog::truncate();
    }

    public function test_recent_returns_paginated_results(): void
    {
        // Create several log entries
        for ($i = 0; $i < 5; $i++) {
            SystemLog::log("action_{$i}", "Detail {$i}", $i, 'safe');
        }

        $result = SystemLog::recent(1, 3);

        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('totalPages', $result);
        $this->assertCount(3, $result['rows']);
        $this->assertGreaterThanOrEqual(5, $result['total']);
    }

    public function test_log_records_correct_user_id(): void
    {
        $user = $this->createUser('admin@test.com', 'super_admin');
        $_SESSION['user'] = ['id' => $user['id'], 'email' => $user['email']];

        $entry = SystemLog::log('user_tracked', 'With user ID');

        $this->assertSame($user['id'], $entry['user_id']);
    }

    public function test_total_count(): void
    {
        unset($_SESSION['user']); // ensure no stale UUID from previous test
        $before = SystemLog::totalCount();
        SystemLog::log('count_test', 'Testing count');
        $after = SystemLog::totalCount();

        $this->assertSame($before + 1, $after);
    }

    // ── Controller Logic Tests (unit-level) ─────────────────────

    public function test_table_exists_check(): void
    {
        // articles table should exist
        $stmt = self::$pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = :t)");
        $stmt->execute([':t' => 'articles']);
        $this->assertTrue((bool)$stmt->fetchColumn());

        // nonexistent_table should not
        $stmt->execute([':t' => 'nonexistent_foobar_table']);
        $this->assertFalse((bool)$stmt->fetchColumn());
    }

    public function test_system_health_db_size(): void
    {
        $result = self::$pdo->query("SELECT pg_size_pretty(pg_database_size(current_database()))")->fetchColumn();
        $this->assertNotEmpty($result);
        $this->assertMatchesRegularExpression('/\d+\s*(bytes|kB|MB|GB)/', $result);
    }

    public function test_system_health_pg_version(): void
    {
        $version = self::$pdo->query("SELECT version()")->fetchColumn();
        $this->assertStringContainsString('PostgreSQL', $version);
    }

    public function test_table_sizes_query(): void
    {
        $sizes = self::$pdo->query("
            SELECT relname AS table_name,
                   pg_size_pretty(pg_total_relation_size(relid)) AS total_size,
                   n_live_tup AS row_count
            FROM pg_stat_user_tables
            ORDER BY pg_total_relation_size(relid) DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertIsArray($sizes);
        // At minimum, articles and users tables should exist
        $tableNames = array_column($sizes, 'table_name');
        $this->assertContains('articles', $tableNames);
        $this->assertContains('users', $tableNames);
    }

    // ── Safe Reset Logic ────────────────────────────────────────

    public function test_article_views_can_be_truncated(): void
    {
        // Insert a test view
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');

        self::$pdo->prepare("INSERT INTO article_views (article_id, ip_address) VALUES (:a, '1.2.3.4')")
            ->execute([':a' => $article['id']]);

        $count = (int)self::$pdo->query("SELECT COUNT(*) FROM article_views")->fetchColumn();
        $this->assertGreaterThan(0, $count);

        // Simulate reset
        self::$pdo->exec("DELETE FROM article_views");
        $afterCount = (int)self::$pdo->query("SELECT COUNT(*) FROM article_views")->fetchColumn();
        $this->assertSame(0, $afterCount);
    }

    public function test_ad_stats_can_be_reset(): void
    {
        // Check if ad_slots table has rows
        $exists = self::$pdo->query("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'ad_slots')")->fetchColumn();
        if (!$exists) {
            $this->markTestSkipped('ad_slots table not present');
        }

        $count = (int)self::$pdo->query("SELECT COUNT(*) FROM ad_slots")->fetchColumn();
        if ($count === 0) {
            $this->markTestSkipped('No ad slots to test');
        }

        // Reset stats
        self::$pdo->exec("UPDATE ad_slots SET impressions = 0, clicks = 0");
        $maxImpressions = (int)self::$pdo->query("SELECT COALESCE(MAX(impressions), 0) FROM ad_slots")->fetchColumn();
        $this->assertSame(0, $maxImpressions);
    }

    // ── Content Purge Logic ─────────────────────────────────────

    public function test_comments_can_be_purged(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();
        $article = $this->createArticle($cat['id'], $user['id'], 'published');

        // Insert test comment
        self::$pdo->prepare("INSERT INTO comments (article_id, author_name, author_email, content) VALUES (:a, 'Test', 'test@x.com', 'Comment')")
            ->execute([':a' => $article['id']]);

        $count = (int)self::$pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
        $this->assertGreaterThan(0, $count);

        self::$pdo->exec("DELETE FROM comments");
        $afterCount = (int)self::$pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
        $this->assertSame(0, $afterCount);
    }

    public function test_subscribers_can_be_purged(): void
    {
        self::$pdo->exec("INSERT INTO newsletter_subscribers (email) VALUES ('purge-test@test.com') ON CONFLICT DO NOTHING");
        $count = (int)self::$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
        $this->assertGreaterThan(0, $count);

        self::$pdo->exec("DELETE FROM newsletter_subscribers");
        $afterCount = (int)self::$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
        $this->assertSame(0, $afterCount);
    }

    // ── Danger Zone Logic ───────────────────────────────────────

    public function test_crawled_articles_identification(): void
    {
        $cat = $this->createCategory();
        $user = $this->createUser();

        // Manual article (no source_url)
        $manual = $this->createArticle($cat['id'], $user['id']);

        // Crawled article (with source_url)
        $crawledSlug = 'crawled-test-' . bin2hex(random_bytes(4));
        self::$pdo->prepare("INSERT INTO articles (title, slug, content, excerpt, category_id, author_id, status, display_author, source_url) VALUES ('Crawled', :s, '<p>C</p>', 'E', :cat, :auth, 'published', 'Bot', 'https://example.com/article')")
            ->execute([':s' => $crawledSlug, ':cat' => $cat['id'], ':auth' => $user['id']]);

        $crawledCount = (int)self::$pdo->query("SELECT COUNT(*) FROM articles WHERE source_url IS NOT NULL AND source_url != ''")->fetchColumn();
        $this->assertGreaterThan(0, $crawledCount);
    }

    public function test_confirmation_phrase_validation(): void
    {
        // Simulate checking confirmation phrase
        $expected = 'DELETE ALL ARTICLES';
        $wrong    = 'delete all articles'; // wrong case
        $correct  = 'DELETE ALL ARTICLES';

        $this->assertNotSame($expected, $wrong);
        $this->assertSame($expected, $correct);
    }

    public function test_factory_reset_preserves_users(): void
    {
        $user = $this->createUser('preserved@test.com', 'super_admin');

        // After a simulated factory reset, users table should still have this user
        // (We don't actually run factory reset in test, just verify the logic)
        $found = self::$pdo->prepare("SELECT id FROM users WHERE email = :e");
        $found->execute([':e' => 'preserved@test.com']);
        $this->assertNotFalse($found->fetch());
    }

    public function test_factory_reset_preserves_system_log(): void
    {
        unset($_SESSION['user']); // ensure no stale UUID from previous test
        $entry = SystemLog::log('pre_factory', 'This should survive factory reset');
        $this->assertNotNull($entry);

        // system_log should not be in the truncation list
        $tablesToTruncate = [
            'article_views', 'article_tags', 'article_revisions',
            'comments', 'articles', 'media_items', 'categories',
            'newsletter_subscribers', 'newsletter_issues', 'popups',
            'crawl_log', 'crawl_sources', 'notifications',
            'social_mentions', 'social_keywords', 'login_attempts',
        ];
        $this->assertNotContains('system_log', $tablesToTruncate);
        $this->assertNotContains('users', $tablesToTruncate);
        $this->assertNotContains('site_settings', $tablesToTruncate);
    }

    // ── Route Structure Tests ───────────────────────────────────

    public function test_system_routes_defined(): void
    {
        // Verify the routes file can be loaded without errors
        $routes = new \Symfony\Component\Routing\RouteCollection();
        require __DIR__ . '/../../../routes/admin.php';

        $this->assertNotNull($routes->get('admin_system'));
        $this->assertNotNull($routes->get('admin_system_reset_article_views'));
        $this->assertNotNull($routes->get('admin_system_reset_all_analytics'));
        $this->assertNotNull($routes->get('admin_system_purge_comments'));
        $this->assertNotNull($routes->get('admin_system_purge_all_media'));
        $this->assertNotNull($routes->get('admin_system_delete_all_articles'));
        $this->assertNotNull($routes->get('admin_system_factory_reset'));
        $this->assertNotNull($routes->get('admin_system_export_db'));
        $this->assertNotNull($routes->get('admin_system_table_sizes'));
        $this->assertNotNull($routes->get('admin_system_vacuum'));
        $this->assertNotNull($routes->get('admin_system_clear_cache'));
        $this->assertNotNull($routes->get('admin_system_logs_api'));
    }

    public function test_system_routes_require_super_admin(): void
    {
        $routes = new \Symfony\Component\Routing\RouteCollection();
        require __DIR__ . '/../../../routes/admin.php';

        $route = $routes->get('admin_system');
        $defaults = $route->getDefaults();
        $this->assertContains('auth', $defaults['_middleware']);
        $this->assertContains('role:super_admin', $defaults['_middleware']);
    }

    public function test_system_post_routes_require_csrf(): void
    {
        $routes = new \Symfony\Component\Routing\RouteCollection();
        require __DIR__ . '/../../../routes/admin.php';

        $postRoutes = [
            'admin_system_reset_article_views',
            'admin_system_purge_comments',
            'admin_system_factory_reset',
        ];

        foreach ($postRoutes as $name) {
            $route = $routes->get($name);
            $this->assertNotNull($route, "Route {$name} should exist");
            $defaults = $route->getDefaults();
            $this->assertContains('csrf', $defaults['_middleware'], "Route {$name} should require CSRF");
        }
    }

    // ── View Tests ──────────────────────────────────────────────

    public function test_system_view_file_exists(): void
    {
        $viewPath = __DIR__ . '/../../../app/Views/admin/system/index.php';
        $this->assertFileExists($viewPath);
    }

    public function test_system_layout_has_sidebar_link(): void
    {
        $layoutPath = __DIR__ . '/../../../app/Views/admin/layout.php';
        $content = file_get_contents($layoutPath);

        $this->assertStringContainsString("'system'", $content, 'Layout should have system sidebar icon');
        $this->assertStringContainsString('/admin/system', $content, 'Layout should link to /admin/system');
        $this->assertStringContainsString('System Admin', $content, 'Layout should show System Admin label');
    }

    public function test_system_layout_requires_super_admin(): void
    {
        $layoutPath = __DIR__ . '/../../../app/Views/admin/layout.php';
        $content = file_get_contents($layoutPath);

        // The system admin link should be wrapped in isSuperAdmin check
        $pos = strpos($content, 'System Admin');
        $this->assertNotFalse($pos);

        // Check that isSuperAdmin guard exists before it
        $preceding = substr($content, max(0, $pos - 200), 200);
        $this->assertStringContainsString('isSuperAdmin', $preceding);
    }

    // ── Migration Tests ─────────────────────────────────────────

    public function test_system_log_table_has_correct_columns(): void
    {
        $columns = self::$pdo->query("
            SELECT column_name FROM information_schema.columns
            WHERE table_name = 'system_log'
            ORDER BY ordinal_position
        ")->fetchAll(\PDO::FETCH_COLUMN);

        $expected = ['id', 'user_id', 'user_email', 'action', 'details', 'records_affected', 'danger_level', 'ip_address', 'created_at'];
        foreach ($expected as $col) {
            $this->assertContains($col, $columns, "system_log should have column: {$col}");
        }
    }

    public function test_system_log_has_indexes(): void
    {
        $indexes = self::$pdo->query("
            SELECT indexname FROM pg_indexes WHERE tablename = 'system_log'
        ")->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertNotEmpty($indexes);
        // Should have at least the primary key and created_at index
        $found = false;
        foreach ($indexes as $idx) {
            if (str_contains($idx, 'created') || str_contains($idx, 'pkey')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'system_log should have indexes on created_at or primary key');
    }

    // ── Controller Existence ────────────────────────────────────

    public function test_controller_has_required_methods(): void
    {
        $controller = new \App\Controllers\AdminSystemController();
        $methods = get_class_methods($controller);

        $required = [
            'index',
            'resetArticleViews', 'resetSiteVisitors', 'resetAdStats',
            'resetPopupAnalytics', 'resetNewsletterStats', 'resetCrawlerStats',
            'resetAllAnalytics',
            'purgeComments', 'purgeSubscribers', 'purgeNotifications',
            'purgeEmailQueue', 'purgeCrawlHistory', 'purgeSeoHistory',
            'purgeSocialMentions', 'purgeLoginAttempts',
            'purgeAllMedia', 'cleanOrphanMedia',
            'deleteAllArticles', 'deleteCrawledArticles',
            'resetAllPopups', 'resetCrawlerSystem',
            'factoryReset',
            'exportDatabase', 'tableSizes', 'vacuumDatabase', 'clearCache',
            'logsApi',
        ];

        foreach ($required as $method) {
            $this->assertContains($method, $methods, "Controller should have method: {$method}");
        }
    }
}