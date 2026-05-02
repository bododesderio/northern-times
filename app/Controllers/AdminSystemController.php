<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SystemLog;
use App\Models\CronRun;
use App\Models\ActiveSession;
use App\Models\DbBackup;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\DB;
use App\Services\Flash;
use PDO;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * System Administration Controller — Super Admin only.
 *
 * Provides reset tools, purge actions, media management,
 * database tools, and factory reset with tiered confirmation.
 */
final class AdminSystemController extends Controller
{
    // ── Dashboard ────────────────────────────────────────────────

    public function index(): Response
    {
        $pdo = DB::pdo();

        // System health stats
        $health = $this->getSystemHealth($pdo);

        // Table counts for display
        $counts = $this->getTableCounts($pdo);

        // Recent system logs
        $logs = SystemLog::recent(1, 30);

        // Cron tasks
        $cronTasks = [];
        $cronFailures = 0;
        if ($this->tableExists($pdo, 'cron_runs')) {
            $cronTasks    = CronRun::lastRunPerTask();
            $cronFailures = CronRun::failureCount(24);
        }

        // Active sessions
        $sessions = [];
        $activeUserCount = 0;
        if ($this->tableExists($pdo, 'active_sessions')) {
            $sessions        = ActiveSession::allSessions();
            $activeUserCount = ActiveSession::activeCount(30);
        }

        // Database backups
        $backups = [];
        $backupTotalSize = 0;
        if ($this->tableExists($pdo, 'db_backups')) {
            $backups = DbBackup::all();
            $backupTotalSize = DbBackup::totalSize();
        }

        // Environment info
        $environment = $this->getEnvironmentInfo();

        return $this->render('admin/system/index', [
            'pageTitle'       => 'System Administration',
            'activeNav'       => 'system',
            'health'          => $health,
            'counts'          => $counts,
            'logs'            => $logs,
            'cronTasks'       => $cronTasks,
            'cronFailures'    => $cronFailures,
            'sessions'        => $sessions,
            'activeUserCount' => $activeUserCount,
            'backups'         => $backups,
            'backupTotalSize' => $backupTotalSize,
            'environment'     => $environment,
            'csrf'            => Csrf::token(),
            'flash_success'   => Flash::get('success'),
            'flash_error'     => Flash::get('error'),
        ]);
    }

    // ── 🟢 Safe Resets (Analytics) ──────────────────────────────

    public function resetArticleViews(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        $pdo = DB::pdo();
        $count = (int)$pdo->query("SELECT COUNT(*) FROM article_views")->fetchColumn();
        $pdo->exec("TRUNCATE article_views");
        $pdo->exec("UPDATE articles SET views = 0");

        SystemLog::log('reset_article_views', "Cleared all article view counters", $count, 'safe');
        Flash::set('success', "Reset article views ({$count} records cleared).");
        return $this->redirect('/admin/system');
    }

    public function resetSiteVisitors(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        $pdo = DB::pdo();
        $count = (int)$pdo->query("SELECT COUNT(*) FROM site_visitors")->fetchColumn();
        $pdo->exec("TRUNCATE site_visitors");

        SystemLog::log('reset_site_visitors', "Cleared site visitor tracking", $count, 'safe');
        Flash::set('success', "Reset site visitors ({$count} records cleared).");
        return $this->redirect('/admin/system');
    }

    public function resetAdStats(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        $pdo = DB::pdo();
        $count = $pdo->exec("UPDATE ad_slots SET impressions = 0, clicks = 0");

        SystemLog::log('reset_ad_stats', "Reset ad impressions and clicks", $count, 'safe');
        Flash::set('success', "Reset ad statistics ({$count} slots updated).");
        return $this->redirect('/admin/system');
    }

    public function resetPopupAnalytics(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        $pdo   = DB::pdo();
        $count = (int)$pdo->query("SELECT COUNT(*) FROM popup_events")->fetchColumn();
        $pdo->exec("TRUNCATE popup_events RESTART IDENTITY CASCADE");

        SystemLog::log('reset_popup_analytics', "Cleared popup event log", $count, 'safe');
        Flash::set('success', "Reset popup analytics ({$count} events cleared).");
        return $this->redirect('/admin/system');
    }

    public function resetNewsletterStats(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        $pdo  = DB::pdo();
        $cols = $pdo->query(
            "SELECT column_name FROM information_schema.columns
             WHERE table_name = 'newsletter_issues' AND column_name IN ('opens','clicks')"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $count = 0;
        if (!empty($cols)) {
            $sets  = implode(', ', array_map(fn($col) => "$col = 0", $cols));
            $count = (int)$pdo->exec("UPDATE newsletter_issues SET $sets");
        }

        SystemLog::log('reset_newsletter_stats', "Reset newsletter open/click stats", $count, 'safe');
        Flash::set('success', "Reset newsletter stats ({$count} issues updated).");
        return $this->redirect('/admin/system');
    }

    public function resetCrawlerStats(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        $pdo   = DB::pdo();
        $count = 0;

        if ($this->tableExists($pdo, 'crawl_logs')) {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM crawl_logs")->fetchColumn();
            $pdo->exec("TRUNCATE crawl_logs RESTART IDENTITY CASCADE");
        }
        if ($this->tableExists($pdo, 'crawl_sources')) {
            $crawlCols = $pdo->query(
                "SELECT column_name FROM information_schema.columns
                 WHERE table_name = 'crawl_sources' AND column_name IN ('last_crawled_at','articles_found','error_count')"
            )->fetchAll(\PDO::FETCH_COLUMN);
            $parts = [];
            if (in_array('last_crawled_at', $crawlCols))  $parts[] = 'last_crawled_at = NULL';
            if (in_array('articles_found',  $crawlCols))  $parts[] = 'articles_found = 0';
            if (in_array('error_count',     $crawlCols))  $parts[] = 'error_count = 0';
            if (!empty($parts)) {
                $pdo->exec('UPDATE crawl_sources SET ' . implode(', ', $parts));
            }
        }

        SystemLog::log('reset_crawler_stats', "Cleared crawl logs and reset source stats", $count, 'safe');
        Flash::set('success', "Reset crawler stats ({$count} log entries cleared).");
        return $this->redirect('/admin/system');
    }

    public function resetAllAnalytics(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        $pdo   = DB::pdo();
        $total = 0;

        try {
            // Article views
            if ($this->tableExists($pdo, 'article_views')) {
                $c = (int)$pdo->query("SELECT COUNT(*) FROM article_views")->fetchColumn();
                $pdo->exec("TRUNCATE article_views RESTART IDENTITY CASCADE");
                $pdo->exec("UPDATE articles SET views = 0");
                $total += $c;
            }

            // Site visitors
            if ($this->tableExists($pdo, 'site_visitors')) {
                $c = (int)$pdo->query("SELECT COUNT(*) FROM site_visitors")->fetchColumn();
                $pdo->exec("TRUNCATE site_visitors RESTART IDENTITY CASCADE");
                $total += $c;
            }

            // Ad stats
            $total += (int)$pdo->exec("UPDATE ad_slots SET impressions = 0, clicks = 0");

            // Popup analytics — clear the events log (counter columns don't exist on popups table)
            $c = (int)$pdo->query("SELECT COUNT(*) FROM popup_events")->fetchColumn();
            $pdo->exec("TRUNCATE popup_events RESTART IDENTITY CASCADE");
            $total += $c;

            // Newsletter stats — only reset columns that actually exist in the table
            $colCheck = $pdo->query(
                "SELECT column_name FROM information_schema.columns
                 WHERE table_name = 'newsletter_issues' AND column_name IN ('opens','clicks')"
            )->fetchAll(\PDO::FETCH_COLUMN);
            if (!empty($colCheck)) {
                $sets = implode(', ', array_map(fn($col) => "$col = 0", $colCheck));
                $total += (int)$pdo->exec("UPDATE newsletter_issues SET $sets");
            }

            // Crawler stats
            if ($this->tableExists($pdo, 'crawl_logs')) {
                $c = (int)$pdo->query("SELECT COUNT(*) FROM crawl_logs")->fetchColumn();
                $pdo->exec("TRUNCATE crawl_logs RESTART IDENTITY CASCADE");
                $total += $c;
            }
            if ($this->tableExists($pdo, 'crawl_sources')) {
                $crawlCols = $pdo->query(
                    "SELECT column_name FROM information_schema.columns
                     WHERE table_name = 'crawl_sources' AND column_name IN ('last_crawled_at','articles_found','error_count')"
                )->fetchAll(\PDO::FETCH_COLUMN);
                $parts = [];
                if (in_array('last_crawled_at', $crawlCols))  $parts[] = 'last_crawled_at = NULL';
                if (in_array('articles_found',  $crawlCols))  $parts[] = 'articles_found = 0';
                if (in_array('error_count',     $crawlCols))  $parts[] = 'error_count = 0';
                if (!empty($parts)) {
                    $pdo->exec('UPDATE crawl_sources SET ' . implode(', ', $parts));
                }
            }

            SystemLog::log('reset_all_analytics', "Nuclear reset of ALL analytics counters", $total, 'safe');
            Flash::set('success', "Reset ALL analytics ({$total} total records affected).");

        } catch (\PDOException $e) {
            Flash::set('error', 'Reset failed: ' . $e->getMessage());
        }

        return $this->redirect('/admin/system');
    }

    // ── 🟡 Content Purges ───────────────────────────────────────

    public function purgeComments(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        return $this->purgeTable('comments', 'purge_comments', 'comments', 'content');
    }

    public function purgeSubscribers(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        return $this->purgeTable('newsletter_subscribers', 'purge_subscribers', 'subscribers', 'content');
    }

    public function purgeNotifications(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        return $this->purgeTable('notifications', 'purge_notifications', 'notifications', 'content');
    }

    public function purgeEmailQueue(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        return $this->purgeTable('email_queue', 'purge_email_queue', 'email queue', 'content');
    }

    public function purgeCrawlHistory(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        return $this->purgeTable('crawl_logs', 'purge_crawl_history', 'crawl history', 'content');
    }

    public function purgeSeoHistory(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        $pdo = DB::pdo();
        $count = 0;

        // seo_audits + seo_issues
        if ($this->tableExists($pdo, 'seo_issues')) {
            $count += (int)$pdo->query("SELECT COUNT(*) FROM seo_issues")->fetchColumn();
            $pdo->exec("TRUNCATE seo_issues CASCADE");
        }
        if ($this->tableExists($pdo, 'seo_audits')) {
            $count += (int)$pdo->query("SELECT COUNT(*) FROM seo_audits")->fetchColumn();
            $pdo->exec("TRUNCATE seo_audits CASCADE");
        }

        SystemLog::log('purge_seo_history', "Purged SEO audit history", $count, 'content');
        Flash::set('success', "Purged SEO history ({$count} records).");
        return $this->redirect('/admin/system');
    }

    public function purgeSocialMentions(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        return $this->purgeTable('social_mentions', 'purge_social_mentions', 'social mentions', 'content');
    }

    public function purgeLoginAttempts(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        return $this->purgeTable('login_attempts', 'purge_login_attempts', 'login attempts', 'content');
    }

    // ── 🟠 Media Management ─────────────────────────────────────

    public function purgeAllMedia(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        $pdo   = DB::pdo();
        $count = (int)$pdo->query("SELECT COUNT(*) FROM media_library")->fetchColumn();

        // SECURITY FIX (Audit S-06): Use the known storage root rather than
        // DOCUMENT_ROOT which can be manipulated or wrong under Docker.
        // All paths are validated to stay inside the storage directory.
        $storageRoot = realpath(__DIR__ . '/../../storage');
        $files   = $pdo->query("SELECT file_path, thumbnail_url AS thumbnail_path FROM media_library")->fetchAll(\PDO::FETCH_ASSOC);
        $deleted = 0;

        foreach ($files as $f) {
            foreach (['file_path', 'thumbnail_path'] as $col) {
                $relPath = trim($f[$col] ?? '');
                if ($relPath === '') continue;

                // file_path is stored as a web-relative URL like /uploads/Articles/img.webp
                // Map to physical storage path: /uploads/... → storage/uploads/...
                $physicalPath = $storageRoot . str_replace('/uploads', '/uploads', $relPath);
                $physicalPath = str_replace('//', '/', $physicalPath);
                $realPath     = realpath($physicalPath);

                // Path traversal guard: must stay inside storage root
                if ($realPath === false || !str_starts_with($realPath, $storageRoot)) {
                    continue;
                }

                if (is_file($realPath) && @unlink($realPath)) {
                    $deleted++;
                }
            }
        }

        $pdo->exec("UPDATE articles SET featured_image = NULL");
        $pdo->exec("TRUNCATE media_library CASCADE");

        SystemLog::log('purge_all_media', "Purged all media ({$deleted} files deleted from disk)", $count, 'media');
        Flash::set('success', "Purged all media ({$count} records, {$deleted} files).");
        return $this->redirect('/admin/system');
    }

    public function cleanOrphanMedia(): Response
    {
        $pdo = DB::pdo();

        // Find media not referenced in articles (featured image or inline)
        $orphans = $pdo->query("
            SELECT m.id, m.file_path, m.thumbnail_url AS thumbnail_path
            FROM media_library m
            LEFT JOIN articles a ON a.featured_image = m.file_path
            WHERE a.id IS NULL
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $storageRoot = realpath(__DIR__ . '/../../storage');
        $count = count($orphans);

        foreach ($orphans as $o) {
            foreach (['file_path', 'thumbnail_path'] as $col) {
                $relPath = trim($o[$col] ?? '');
                if ($relPath === '') continue;

                $physicalPath = $storageRoot . str_replace('/uploads', '/uploads', $relPath);
                $physicalPath = str_replace('//', '/', $physicalPath);
                $realPath     = realpath($physicalPath);

                if ($realPath !== false
                    && str_starts_with($realPath, $storageRoot)
                    && is_file($realPath))
                {
                    @unlink($realPath);
                }
            }
            $pdo->prepare("DELETE FROM media_library WHERE id = :id")->execute([':id' => $o['id']]);
        }

        SystemLog::log('clean_orphan_media', "Removed {$count} orphan media files", $count, 'media');
        Flash::set('success', "Cleaned {$count} orphan media items.");
        return $this->redirect('/admin/system');
    }

    // ── 🔴 Danger Zone ──────────────────────────────────────────

    public function deleteAllArticles(): Response
    {
        $request = Request::createFromGlobals();
        $confirm = trim((string)$request->request->get('confirmation', ''));

        if ($confirm !== 'DELETE ALL ARTICLES') {
            Flash::set('error', 'Confirmation phrase did not match. No action taken.');
            return $this->redirect('/admin/system');
        }

        $pdo = DB::pdo();
        $count = (int)$pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn();

        // Clean related tables first
        $this->safeExec($pdo, "DELETE FROM article_views");
        $this->safeExec($pdo, "DELETE FROM article_tags");
        $this->safeExec($pdo, "DELETE FROM article_revisions");
        $this->safeExec($pdo, "DELETE FROM comments");
        $pdo->exec("DELETE FROM articles");

        SystemLog::log('delete_all_articles', "Deleted ALL articles", $count, 'danger');
        Flash::set('success', "Deleted all {$count} articles.");
        return $this->redirect('/admin/system');
    }

    public function deleteCrawledArticles(): Response
    {
        $request = Request::createFromGlobals();
        $confirm = trim((string)$request->request->get('confirmation', ''));

        if ($confirm !== 'DELETE CRAWLED ARTICLES') {
            Flash::set('error', 'Confirmation phrase did not match. No action taken.');
            return $this->redirect('/admin/system');
        }

        $pdo = DB::pdo();
        $count = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE source_url IS NOT NULL AND source_url != ''")->fetchColumn();

        $this->safeExec($pdo, "DELETE FROM article_views WHERE article_id IN (SELECT id FROM articles WHERE source_url IS NOT NULL AND source_url != '')");
        $this->safeExec($pdo, "DELETE FROM article_tags WHERE article_id IN (SELECT id FROM articles WHERE source_url IS NOT NULL AND source_url != '')");
        $this->safeExec($pdo, "DELETE FROM article_revisions WHERE article_id IN (SELECT id FROM articles WHERE source_url IS NOT NULL AND source_url != '')");
        $this->safeExec($pdo, "DELETE FROM comments WHERE article_id IN (SELECT id FROM articles WHERE source_url IS NOT NULL AND source_url != '')");
        $pdo->exec("DELETE FROM articles WHERE source_url IS NOT NULL AND source_url != ''");

        SystemLog::log('delete_crawled_articles', "Deleted crawled articles only", $count, 'danger');
        Flash::set('success', "Deleted {$count} crawled articles.");
        return $this->redirect('/admin/system');
    }

    public function resetAllPopups(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        $request = Request::createFromGlobals();
        $confirm = trim((string)$request->request->get('confirmation', ''));

        if ($confirm !== 'DELETE ALL POPUPS') {
            Flash::set('error', 'Confirmation phrase did not match. No action taken.');
            return $this->redirect('/admin/system');
        }

        $pdo = DB::pdo();
        $count = (int)$pdo->query("SELECT COUNT(*) FROM popups")->fetchColumn();
        $pdo->exec("DELETE FROM popups");

        SystemLog::log('reset_all_popups', "Deleted all popups", $count, 'danger');
        Flash::set('success', "Deleted all {$count} popups.");
        return $this->redirect('/admin/system');
    }

    public function resetCrawlerSystem(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        $request = Request::createFromGlobals();
        $confirm = trim((string)$request->request->get('confirmation', ''));

        if ($confirm !== 'RESET CRAWLER') {
            Flash::set('error', 'Confirmation phrase did not match. No action taken.');
            return $this->redirect('/admin/system');
        }

        $pdo = DB::pdo();
        $srcCount  = (int)$pdo->query("SELECT COUNT(*) FROM crawl_sources")->fetchColumn();
        $logCount  = (int)$pdo->query("SELECT COUNT(*) FROM crawl_logs")->fetchColumn();

        $pdo->exec("TRUNCATE crawl_logs");
        $pdo->exec("DELETE FROM crawl_sources");

        SystemLog::log('reset_crawler_system', "Reset entire crawler ({$srcCount} sources, {$logCount} logs)", $srcCount + $logCount, 'danger');
        Flash::set('success', "Reset crawler system ({$srcCount} sources, {$logCount} logs deleted).");
        return $this->redirect('/admin/system');
    }

    // ── 🔴🔴 Factory Reset ──────────────────────────────────────

    public function factoryReset(): Response
    {
        $request  = Request::createFromGlobals();
        $confirm  = trim((string)$request->request->get('confirmation', ''));

        // FIX (Audit B-05 / White-Label): Confirmation phrase is now dynamic,
        // derived from the site name so it works after rebranding.
        // Falls back to APP_NAME env, then generic phrase.
        $siteName     = \get_site_setting('site_title');
        if ($siteName === '') $siteName = $_ENV['APP_NAME'] ?? 'MY PLATFORM';
        $expectedPhrase = 'FACTORY RESET ' . strtoupper($siteName);

        if ($confirm !== $expectedPhrase) {
            Flash::set('error', "Factory reset phrase did not match. Expected: \"{$expectedPhrase}\". No action taken.");
            return $this->redirect('/admin/system');
        }

        $pdo = DB::pdo();
        $details = [];

        // Order matters — clear dependents first
        $tablesToTruncate = [
            'article_views', 'article_tags', 'article_revisions',
            'comments', 'articles',
            'media_library', 'categories',
            'newsletter_subscribers', 'newsletter_issues',
            'popups', 'popup_events', 'popup_dismissals',
            'crawl_logs', 'crawl_sources',
            'notifications',
            'social_mentions', 'social_keywords',
            'login_attempts',
        ];

        // Optional tables (may or may not exist based on phase)
        $optionalTables = [
            'seo_issues', 'seo_audits', 'image_health_log',
            'email_queue', 'story_threads',
            'tags', 'push_subscriptions',
            'social_posts_log',
        ];

        foreach ($tablesToTruncate as $t) {
            $quoted = '"' . str_replace('"', '', $t) . '"';
            if ($this->tableExists($pdo, $t)) {
                $c = (int)$pdo->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();
                $pdo->exec("TRUNCATE {$quoted} CASCADE");
                $details[] = "{$t}: {$c}";
            }
        }

        foreach ($optionalTables as $t) {
            $quoted = '"' . str_replace('"', '', $t) . '"';
            if ($this->tableExists($pdo, $t)) {
                $c = (int)$pdo->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();
                $pdo->exec("TRUNCATE {$quoted} CASCADE");
                $details[] = "{$t}: {$c}";
            }
        }

        // Reset ad stats (preserve ad slot configurations)
        if ($this->tableExists($pdo, 'ad_slots')) {
            $pdo->exec("UPDATE ad_slots SET impressions = 0, clicks = 0");
            $details[] = "ad_slots: stats reset";
        }

        // Reset site visitors and article views
        if ($this->tableExists($pdo, 'site_visitors')) {
            $pdo->exec("TRUNCATE site_visitors");
            $details[] = "site_visitors: cleared";
        }

        // PRESERVED: users, roles, site_settings, system_log (append-only)
        // system_log is protected at DB level (no_delete_system_log RULE)

        $summary = implode('; ', $details);
        SystemLog::log('factory_reset', "FACTORY RESET — {$summary}", count($details), 'factory');
        Flash::set('success', 'Factory reset complete. Users, settings, theme, SMTP, and system logs preserved.');
        return $this->redirect('/admin/system');
    }

    // ── 🟠 Media: Regenerate Thumbnails ────────────────────────────

    /**
     * Regenerate all image thumbnails and responsive variants.
     *
     * FIX (Audit B-05): This method was listed in the README System Admin spec
     * and the view had a button for it, but the method and route were both missing.
     *
     * Iterates all media_library records, attempts to regenerate thumbnails
     * using the Image service. Reports success/failure counts.
     * Safe to re-run — uses the same Image service pipeline as uploads.
     */
    public function regenerateThumbnails(): Response
    {
        // Guard against direct GET access (route is POST-only; GET causes 405 → 500)
        $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
        if ($request->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }

        $pdo = DB::pdo();

        if (!$this->tableExists($pdo, 'media_library')) {
            Flash::set('success', 'No media items found — nothing to regenerate.');
            return $this->redirect('/admin/system');
        }

        $items = $pdo->query(
            "SELECT id, file_path FROM media_library WHERE file_path IS NOT NULL ORDER BY created_at DESC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $storageRoot = realpath(__DIR__ . '/../../storage');
        if (!$storageRoot) {
            Flash::set('error', 'Storage directory not found.');
            return $this->redirect('/admin/system');
        }

        $success  = 0;
        $skipped  = 0;
        $failed   = 0;
        $batchMax = 200; // prevent timeout on large libraries

        foreach (array_slice($items, 0, $batchMax) as $item) {
            $filePath = $item['file_path'];
            if (empty($filePath)) { $skipped++; continue; }

            // Build physical path — file_path is stored as /uploads/... relative URL
            $physicalPath = $storageRoot . str_replace('/uploads', '', $filePath);
            $physicalPath = str_replace('//', '/', $physicalPath);

            // Path traversal guard
            $realPhysical = realpath($physicalPath);
            if ($realPhysical === false || !str_starts_with($realPhysical, $storageRoot)) {
                $skipped++;
                continue;
            }

            if (!is_file($realPhysical)) { $skipped++; continue; }

            try {
                \App\Services\Image::regenerateThumbnail($realPhysical);
                $success++;
            } catch (\Throwable $e) {
                error_log('[AdminSystemController] regenerateThumbnails: ' . $e->getMessage() . ' for ' . $filePath);
                $failed++;
            }
        }

        $total    = count($items);
        $message  = "Regenerated {$success} thumbnails";
        if ($skipped > 0) $message .= ", {$skipped} skipped (missing/external)";
        if ($failed  > 0) $message .= ", {$failed} failed";
        if ($total > $batchMax) $message .= ". Processed first {$batchMax} of {$total} — run again for the rest.";

        SystemLog::log('regenerate_thumbnails', $message, $success, 'media');
        Flash::set($failed > 0 ? 'warning' : 'success', $message . '.');
        return $this->redirect('/admin/system');
    }



    public function exportDatabase(): Response
    {
        // Guard against direct GET access (POST-only route)
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }
        $pdo = DB::pdo();
        $dbName = getenv('DB_NAME') ?: 'northern_times';
        $dbHost = getenv('DB_HOST') ?: 'db';
        $dbUser = getenv('DB_USER') ?: 'northern_user';
        $dbPass = getenv('DB_PASS') ?: '';

        $slug     = preg_replace('/[^a-z0-9]+/', '_', strtolower(
            function_exists('get_site_setting') ? get_site_setting('site_abbreviation', 'site') : 'site'
        ));
        $filename = $slug . "_backup_" . date('Y-m-d_His') . ".sql";
        $tmpPath  = sys_get_temp_dir() . '/' . $filename;

        putenv("PGPASSWORD={$dbPass}");
        $cmd = "pg_dump -h " . escapeshellarg($dbHost)
             . " -U " . escapeshellarg($dbUser)
             . " " . escapeshellarg($dbName)
             . " > " . escapeshellarg($tmpPath) . " 2>&1";

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($tmpPath)) {
            SystemLog::log('export_database_failed', "pg_dump failed: " . implode("\n", $output), 0, 'safe');
            Flash::set('error', 'Database export failed. Check server logs.');
            return $this->redirect('/admin/system');
        }

        SystemLog::log('export_database', "Exported database to {$filename}", 0, 'safe');

        $content = file_get_contents($tmpPath);
        @unlink($tmpPath);

        return new Response($content, 200, [
            'Content-Type'        => 'application/sql',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length'      => (string)strlen($content),
        ]);
    }

    public function tableSizes(): Response
    {
        $pdo = DB::pdo();

        $sizes = $pdo->query("
            SELECT
                relname AS table_name,
                pg_size_pretty(pg_total_relation_size(relid)) AS total_size,
                pg_total_relation_size(relid) AS raw_size,
                n_live_tup AS row_count
            FROM pg_stat_user_tables
            ORDER BY pg_total_relation_size(relid) DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        return $this->json(['tables' => $sizes]);
    }

    public function vacuumDatabase(): Response
    {
        $pdo = DB::pdo();

        // VACUUM cannot run inside a transaction block — disable autocommit wrapping
        $pdo->setAttribute(\PDO::ATTR_AUTOCOMMIT, 1);
        $pdo->exec("VACUUM ANALYZE");
        $pdo->setAttribute(\PDO::ATTR_AUTOCOMMIT, 0);

        SystemLog::log('vacuum_database', "Ran VACUUM ANALYZE on database", 0, 'safe');
        Flash::set('success', 'Database vacuumed and analyzed successfully.');
        return $this->redirect('/admin/system');
    }

    public function clearCache(): Response
    {
        // Clear any file-based caches
        $cacheDir = __DIR__ . '/../../storage/cache';
        $count    = 0;
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            foreach ($files as $f) {
                if (is_file($f)) { @unlink($f); $count++; }
            }
        }

        SystemLog::log('clear_cache', "Cleared {$count} cache files", $count, 'safe');
        Flash::set('success', "Cache cleared ({$count} files removed).");
        return $this->redirect('/admin/system');
    }

    // ── System Log (JSON endpoint) ──────────────────────────────

    public function logsApi(): Response
    {
        $request = Request::createFromGlobals();
        $page = max(1, (int)$request->query->get('page', 1));
        $data = SystemLog::recent($page, 50);

        return $this->json($data);
    }

    // ── Scheduled Tasks Monitor ─────────────────────────────────

    public function cronHistory(): Response
    {
        $request = Request::createFromGlobals();
        $task = $request->query->get('task');
        $runs = CronRun::recent(100, $task);

        return $this->json(['runs' => $runs]);
    }

    public function cronCleanOld(): Response
    {
        $deleted = CronRun::cleanOld(30);
        SystemLog::log('cron_clean_old', "Cleaned cron logs older than 30 days", $deleted, 'safe');
        Flash::set('success', "Cleaned {$deleted} old cron log entries.");
        return $this->redirect('/admin/system');
    }

    // ── User Session Manager ────────────────────────────────────

    public function forceLogoutSession(): Response
    {
        $request = Request::createFromGlobals();
        $sessionId = $request->request->get('session_id', '');

        if (empty($sessionId)) {
            Flash::set('error', 'No session ID provided.');
            return $this->redirect('/admin/system');
        }

        // Don't let user kill their own session
        if ($sessionId === session_id()) {
            Flash::set('error', 'You cannot force-logout your own session.');
            return $this->redirect('/admin/system');
        }

        ActiveSession::forceLogout($sessionId);
        SystemLog::log('force_logout_session', "Force-terminated session: {$sessionId}", 1, 'content');
        Flash::set('success', 'Session terminated.');
        return $this->redirect('/admin/system');
    }

    public function forceLogoutUser(): Response
    {
        $request = Request::createFromGlobals();
        $userId = $request->request->get('user_id', '');

        if (empty($userId)) {
            Flash::set('error', 'No user ID provided.');
            return $this->redirect('/admin/system');
        }

        $count = ActiveSession::forceLogoutUser($userId);
        SystemLog::log('force_logout_user', "Force-terminated all sessions for user: {$userId}", $count, 'content');
        Flash::set('success', "Terminated {$count} session(s) for user.");
        return $this->redirect('/admin/system');
    }

    public function cleanStaleSessions(): Response
    {
        $cleaned = ActiveSession::cleanStale(24);
        SystemLog::log('clean_stale_sessions', "Cleaned stale sessions older than 24h", $cleaned, 'safe');
        Flash::set('success', "Cleaned {$cleaned} stale sessions.");
        return $this->redirect('/admin/system');
    }

    // ── Database Backups ────────────────────────────────────────

    public function createBackup(): Response
    {
        // Guard against direct GET access
        if (\Symfony\Component\HttpFoundation\Request::createFromGlobals()->getMethod() === 'GET') {
            return $this->redirect('/admin/system');
        }

        // Guard: db_backups table must exist
        $pdo = DB::pdo();
        if (!$this->tableExists($pdo, 'db_backups')) {
            Flash::set('error', 'Backup system not initialised — run migration 0041_system_admin_expansion_v2.sql first.');
            return $this->redirect('/admin/system');
        }

        try {
            $backup = DbBackup::createBackup(false);
        } catch (\Throwable $e) {
            Flash::set('error', 'Backup failed: ' . $e->getMessage());
            return $this->redirect('/admin/system');
        }

        if (!$backup || $backup['status'] === 'failed') {
            $msg = $backup['error_msg'] ?? 'Unknown error';
            SystemLog::log('backup_failed', "Manual backup failed: {$msg}", 0, 'danger');
            Flash::set('error', "Backup failed: {$msg}");
            return $this->redirect('/admin/system');
        }

        $size = DbBackup::formatSize((int)$backup['file_size']);
        SystemLog::log('backup_created', "Manual backup: {$backup['filename']} ({$size})", 1, 'safe');
        Flash::set('success', "Backup created: {$backup['filename']} ({$size})");
        return $this->redirect('/admin/system');
    }

    public function downloadBackup(string $id): Response
    {
        $download = DbBackup::getDownload($id);

        if (!$download) {
            Flash::set('error', 'Backup file not found.');
            return $this->redirect('/admin/system');
        }

        [$path, $filename] = $download;
        $content = file_get_contents($path);

        return new Response($content, 200, [
            'Content-Type'        => 'application/sql',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length'      => (string)strlen($content),
        ]);
    }

    public function deleteBackup(string $id): Response
    {
        $record = DbBackup::find($id);
        if (!$record) {
            Flash::set('error', 'Backup not found.');
            return $this->redirect('/admin/system');
        }

        DbBackup::deleteBackup($id);
        SystemLog::log('backup_deleted', "Deleted backup: {$record['filename']}", 1, 'content');
        Flash::set('success', "Deleted backup: {$record['filename']}");
        return $this->redirect('/admin/system');
    }

    // ── Webhooks ──────────────────────────────────────────────────

    public function webhooksApi(): Response
    {
        $pdo = DB::pdo();
        if (!$this->tableExists($pdo, 'webhooks')) {
            return $this->json(['webhooks' => [], 'message' => 'Webhooks table not initialized']);
        }

        $webhooks = $pdo->query("SELECT id, name, url, events, is_active, last_triggered_at, last_status_code, failure_count, created_at FROM webhooks ORDER BY created_at DESC")->fetchAll();
        return $this->json(['webhooks' => $webhooks]);
    }

    public function webhookCreate(): Response
    {
        $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
        $name   = trim((string)$request->request->get('name', ''));
        $url    = trim((string)$request->request->get('url', ''));
        $events = trim((string)$request->request->get('events', 'article.published'));
        $secret = trim((string)$request->request->get('secret', ''));

        if ($name === '' || $url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            Flash::set('error', 'Webhook name and valid URL are required.');
            return $this->redirect('/admin/system');
        }

        try {
            $pdo = DB::pdo();
            $pdo->prepare("INSERT INTO webhooks (name, url, events, secret) VALUES (:name, :url, :events, :secret)")
                ->execute([':name' => mb_substr($name, 0, 100), ':url' => mb_substr($url, 0, 500), ':events' => $events, ':secret' => $secret ?: null]);
            Flash::set('success', "Webhook '{$name}' created.");
        } catch (\Throwable $e) {
            Flash::set('error', 'Failed to create webhook: ' . $e->getMessage());
        }

        return $this->redirect('/admin/system');
    }

    public function webhookDelete(string $id): Response
    {
        try {
            $pdo = DB::pdo();
            $pdo->prepare("DELETE FROM webhooks WHERE id = :id")->execute([':id' => $id]);
            Flash::set('success', 'Webhook deleted.');
        } catch (\Throwable $e) {
            Flash::set('error', 'Failed to delete webhook: ' . $e->getMessage());
        }
        return $this->redirect('/admin/system');
    }

    // ── Environment Inspector ───────────────────────────────────

    public function environmentApi(): Response
    {
        return $this->json(['environment' => $this->getEnvironmentInfo()]);
    }

    // ── Private Helpers ─────────────────────────────────────────

    private function getSystemHealth(PDO $pdo): array
    {
        // Database size
        $dbSize = $pdo->query("SELECT pg_size_pretty(pg_database_size(current_database()))")->fetchColumn();

        // PHP version
        $phpVersion = phpversion();

        // PostgreSQL version
        $pgVersion = $pdo->query("SELECT version()")->fetchColumn();
        $pgVersion = preg_match('/PostgreSQL ([\d.]+)/', $pgVersion, $m) ? $m[1] : 'unknown';

        // Uptime (process)
        $uptime = @file_get_contents('/proc/uptime');
        $uptimeFormatted = 'N/A';
        if ($uptime) {
            $secs = (int)explode(' ', $uptime)[0];
            $days = floor($secs / 86400);
            $hours = floor(($secs % 86400) / 3600);
            $uptimeFormatted = "{$days}d {$hours}h";
        }

        // Disk space
        $diskFree  = @disk_free_space('/') ?: 0;
        $diskTotal = @disk_total_space('/') ?: 1;
        $diskUsedPct = round((1 - $diskFree / $diskTotal) * 100, 1);

        return [
            'db_size'       => $dbSize,
            'php_version'   => $phpVersion,
            'pg_version'    => $pgVersion,
            'uptime'        => $uptimeFormatted,
            'disk_used_pct' => $diskUsedPct,
            'disk_free'     => $this->formatBytes($diskFree),
        ];
    }

    private function getTableCounts(PDO $pdo): array
    {
        $tables = [
            'articles', 'comments', 'newsletter_subscribers', 'media_library',
            'popups', 'crawl_sources', 'crawl_logs', 'notifications',
            'newsletter_issues', 'categories', 'ad_slots',
        ];

        // Optional tables
        $optional = [
            'article_views', 'site_visitors', 'popup_events', 'social_mentions',
            'seo_audits', 'login_attempts', 'article_tags',
            'article_revisions', 'story_threads',
        ];

        $counts = [];
        foreach (array_merge($tables, $optional) as $t) {
            if ($this->tableExists($pdo, $t)) {
                $counts[$t] = (int)$pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
            }
        }

        return $counts;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = :t)");
        $stmt->execute([':t' => $table]);
        return (bool)$stmt->fetchColumn();
    }

    private const ALLOWED_PURGE_TABLES = [
        'comments', 'newsletter_subscribers', 'notifications', 'email_queue',
        'crawl_logs', 'crawl_logs', 'social_mentions', 'login_attempts',
        'article_views', 'site_visitors', 'popup_events', 'seo_issues',
        'seo_audits', 'image_health_log', 'story_threads', 'tags',
        'push_subscriptions', 'social_posts_log', 'article_revisions',
        'article_tags', 'articles', 'ad_slots', 'ad_events',
    ];

    private function purgeTable(string $table, string $action, string $label, string $level): Response
    {
        if (!in_array($table, self::ALLOWED_PURGE_TABLES, true)) {
            Flash::set('error', "Table '{$table}' is not allowed for purge.");
            return $this->redirect('/admin/system');
        }

        $pdo = DB::pdo();

        if (!$this->tableExists($pdo, $table)) {
            Flash::set('error', "Table '{$table}' does not exist.");
            return $this->redirect('/admin/system');
        }

        // CWE-89: Quote identifier for defense-in-depth (table is already whitelist-validated)
        $quotedTable = '"' . str_replace('"', '""', $table) . '"';
        $count = (int)$pdo->query("SELECT COUNT(*) FROM " . $quotedTable)->fetchColumn();
        $pdo->exec("TRUNCATE " . $quotedTable . " CASCADE");

        SystemLog::log($action, "Purged all {$label}", $count, $level);
        Flash::set('success', "Purged {$label} ({$count} records).");
        return $this->redirect('/admin/system');
    }

    private function safeExec(PDO $pdo, string $sql): void
    {
        try { $pdo->exec($sql); } catch (\PDOException $e) { /* table may not exist */ }
    }

    private function formatBytes(float $bytes): string
    {
        $units = ['B','KB','MB','GB','TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 4) { $bytes /= 1024; $i++; }
        return round($bytes, 1) . ' ' . $units[$i];
    }

    private function getEnvironmentInfo(): array
    {
        $env = [];

        // PHP
        $env['php_version']     = phpversion();
        $env['php_sapi']        = php_sapi_name();
        $env['memory_limit']    = ini_get('memory_limit');
        $env['max_execution']   = ini_get('max_execution_time') . 's';
        $env['upload_max']      = ini_get('upload_max_filesize');
        $env['post_max']        = ini_get('post_max_size');
        $env['opcache_enabled'] = function_exists('opcache_get_status') && @opcache_get_status() !== false;
        $env['timezone']        = date_default_timezone_get();

        // PHP Extensions
        $important = ['pdo', 'pdo_pgsql', 'mbstring', 'curl', 'json', 'openssl', 'xml', 'gd', 'redis', 'fileinfo', 'zip', 'intl'];
        $env['extensions'] = [];
        foreach ($important as $ext) {
            $env['extensions'][$ext] = extension_loaded($ext);
        }

        // Redis
        $env['redis_status'] = 'disconnected';
        $env['redis_info']   = null;
        if (extension_loaded('redis')) {
            try {
                $redis = new \Redis();
                $host = getenv('REDIS_HOST') ?: ($_ENV['REDIS_HOST'] ?? 'redis');
                $port = (int)(getenv('REDIS_PORT') ?: ($_ENV['REDIS_PORT'] ?? 6379));
                if (@$redis->connect($host, $port, 2)) {
                    $pass = getenv('REDIS_PASSWORD') ?: ($_ENV['REDIS_PASSWORD'] ?? '');
                    if ($pass !== '') {
                        $redis->auth($pass);
                    }
                    $env['redis_status'] = 'connected';
                    $info = $redis->info();
                    $env['redis_info'] = [
                        'version'     => $info['redis_version'] ?? '?',
                        'memory_used' => $info['used_memory_human'] ?? '?',
                        'connected_clients' => $info['connected_clients'] ?? '?',
                        'uptime_days' => round(($info['uptime_in_seconds'] ?? 0) / 86400, 1),
                    ];
                    $redis->close();
                }
            } catch (\Throwable $e) {
                $env['redis_status'] = 'error: ' . $e->getMessage();
            }
        }

        // OS
        $env['os'] = php_uname('s') . ' ' . php_uname('r');
        $env['hostname'] = gethostname() ?: 'unknown';

        // .env summary (sensitive values masked)
        $env['env_vars'] = [];
        $showKeys = ['APP_ENV', 'APP_DEBUG', 'APP_URL', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'REDIS_HOST', 'REDIS_PORT', 'SESSION_NAME'];
        $maskKeys = ['DB_PASS', 'SMTP_PASSWORD', 'API_KEY'];

        foreach ($showKeys as $k) {
            $v = getenv($k) ?: ($_ENV[$k] ?? null);
            if ($v !== null) $env['env_vars'][$k] = $v;
        }
        foreach ($maskKeys as $k) {
            $v = getenv($k) ?: ($_ENV[$k] ?? null);
            if ($v !== null) $env['env_vars'][$k] = str_repeat('•', min(strlen($v), 20));
        }

        return $env;
    }
}