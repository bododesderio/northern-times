<?php
/**
 * Crawler Cron Job — hardened for 100+ sources.
 *
 * Runs every 5 minutes via crontab. Only crawls sources whose interval
 * has elapsed (dueForCrawl), so it won't re-crawl sources already done.
 *
 * Safety features:
 *  - Lock file prevents overlapping runs
 *  - Unlimited execution time for CLI
 *  - Memory limit raised to 512M
 *  - Per-source error isolation (one failure doesn't kill the batch)
 *  - Graceful output to log file
 *
 * Crontab:
 *   cd /var/www/html && php cron/crawl.php >> storage/logs/crawler.log 2>&1
 */

declare(strict_types=1);

// CLI-only safety
set_time_limit(0);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '0');

// Lock file — prevent overlapping cron runs
$lockFile = sys_get_temp_dir() . '/nt_crawler.lock';
if (file_exists($lockFile)) {
    $lockAge = time() - filemtime($lockFile);
    if ($lockAge < 1800) { // 30 min max lock
        echo "[" . date('Y-m-d H:i:s') . "] Crawler already running (lock age: {$lockAge}s). Skipping.\n";
        exit(0);
    }
    // Stale lock — remove it
    unlink($lockFile);
}
file_put_contents($lockFile, getmypid());

// Cleanup lock on exit
register_shutdown_function(function () use ($lockFile) {
    @unlink($lockFile);
});

// Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}
require_once __DIR__ . '/../app/Support/helpers.php';

use App\Services\CrawlerEngine;
use App\Services\BreakingNewsEngine;

$start = microtime(true);
$ts    = date('Y-m-d H:i:s');

echo "[{$ts}] Crawler cron starting...\n";

// Track cron run
$cronRun = null;
try {
    $cronRun = \App\Models\CronRun::start('crawler');
} catch (\Throwable $e) {
    // Table may not exist yet
}

try {
    $results = CrawlerEngine::crawlAll();

    if (isset($results['skipped'])) {
        echo "[{$ts}] Skipped: {$results['reason']}\n";
    } elseif (empty($results)) {
        echo "[{$ts}] No sources due for crawl.\n";
    } else {
        $ok = $err = 0;
        foreach ($results as $r) {
            if ($r['status'] === 'ok') {
                $ok++;
                if ($r['new'] > 0) {
                    echo "[{$ts}] ✅ {$r['source']}: {$r['found']} found, {$r['new']} new\n";
                }
            } else {
                $err++;
                echo "[{$ts}] ❌ {$r['source']}: {$r['error']}\n";
            }
        }
        $totalNew = array_sum(array_column($results, 'new'));
        echo "[{$ts}] Summary: {$ok} ok, {$err} errors, {$totalNew} new articles\n";
    }

    // Finish cron tracking
    if ($cronRun) {
        try {
            $ok  = $ok ?? 0;
            $err = $err ?? 0;
            $totalNew = is_array($results) && !isset($results['skipped']) ? array_sum(array_column($results, 'new')) : 0;
            \App\Models\CronRun::finish($cronRun['id'], $totalNew, "{$ok} ok, {$err} errors, {$totalNew} new");
        } catch (\Throwable $e) {}
    }
} catch (\Throwable $e) {
    echo "[{$ts}] ❌ Fatal: {$e->getMessage()}\n";
    if ($cronRun) {
        try { \App\Models\CronRun::fail($cronRun['id'], $e->getMessage()); } catch (\Throwable $e2) {}
    }
    exit(1);
}

$elapsed = round(microtime(true) - $start, 2);
echo "[{$ts}] Crawl done in {$elapsed}s\n";

// ── Breaking News Engine: recalculate scores ─────────────────
$brkRun = null;
try {
    try { $brkRun = \App\Models\CronRun::start('breaking_news_engine'); } catch (\Throwable $e) {}

    $brkStats = BreakingNewsEngine::recalculateAll();
    echo "[{$ts}] Breaking engine: scored {$brkStats['scored']}, breaking {$brkStats['breaking']}, expired {$brkStats['expired']}, snapshots {$brkStats['snapshots']}\n";

    if ($brkRun) {
        try { \App\Models\CronRun::finish($brkRun['id'], $brkStats['scored'], "breaking={$brkStats['breaking']}, expired={$brkStats['expired']}"); } catch (\Throwable $e) {}
    }
} catch (\Throwable $e) {
    echo "[{$ts}] ❌ Breaking engine error: {$e->getMessage()}\n";
    if ($brkRun) {
        try { \App\Models\CronRun::fail($brkRun['id'], $e->getMessage()); } catch (\Throwable $e2) {}
    }
}

$totalElapsed = round(microtime(true) - $start, 2);
echo "[{$ts}] All done in {$totalElapsed}s\n\n";