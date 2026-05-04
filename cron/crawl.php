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

// Lock file — prevent overlapping cron runs (atomic flock)
$lockFile = sys_get_temp_dir() . '/nt_crawler.lock';
$lockHandle = @fopen($lockFile, 'c');
if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Crawler already running (locked). Skipping.\n";
    if ($lockHandle) fclose($lockHandle);
    exit(0);
}
ftruncate($lockHandle, 0);
fwrite($lockHandle, (string)getmypid());
fflush($lockHandle);

// Release lock on exit
register_shutdown_function(function () use ($lockHandle, $lockFile) {
    if (is_resource($lockHandle)) {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    }
    @unlink($lockFile);
});

// Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}
require_once __DIR__ . '/../app/Support/helpers.php';
\App\Services\ErrorTracker::register();

use App\Services\CrawlerEngine;
use App\Services\BreakingNewsEngine;
use App\Services\Cache;

// ── Distributed Redis lock (prevents overlapping across containers) ──
$redisLockKey   = 'crawl:lock';
$redisLockTTL   = 600; // 10 minutes max — auto-expires if process dies
$redisLockToken = bin2hex(random_bytes(8));
$redis          = Cache::redis();
if ($redis) {
    // SETNX with TTL — only one crawl process can hold the lock
    $acquired = $redis->set($redisLockKey, $redisLockToken, ['NX', 'EX' => $redisLockTTL]);
    if (!$acquired) {
        echo "[" . date('Y-m-d H:i:s') . "] Crawler already running (Redis lock). Skipping.\n";
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
        @unlink($lockFile);
        exit(0);
    }
    // Release Redis lock on exit (only if we still own it)
    register_shutdown_function(function () use ($redis, $redisLockKey, $redisLockToken) {
        try {
            if ($redis->get($redisLockKey) === $redisLockToken) {
                $redis->del($redisLockKey);
            }
        } catch (\Throwable $e) {
            error_log('Crawl Redis lock release failed: ' . $e->getMessage());
        }
    });
}

$start = microtime(true);
$ts    = date('Y-m-d H:i:s');

echo "[{$ts}] Crawler cron starting...\n";

// Track cron run
$cronRun = null;
try {
    $cronRun = \App\Models\CronRun::start('crawler');
} catch (\Throwable $e) {
    // Table may not exist yet on first run
    error_log('CronRun tracking unavailable: ' . $e->getMessage());
}

$useParallel = in_array('--parallel', $argv ?? [], true);

try {
    $results = $useParallel ? CrawlerEngine::crawlAllParallel() : CrawlerEngine::crawlAll();

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
                    $source = preg_replace('/[\r\n\x00]/', '', (string)($r['source'] ?? ''));
                    echo "[{$ts}] OK {$source}: {$r['found']} found, {$r['new']} new\n";
                }
            } else {
                $err++;
                $source = preg_replace('/[\r\n\x00]/', '', (string)($r['source'] ?? ''));
                $error  = preg_replace('/[\r\n\x00]/', '', substr((string)($r['error'] ?? ''), 0, 500));
                echo "[{$ts}] FAIL {$source}: {$error}\n";
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
    $safeMsg = preg_replace('/[\r\n\x00]/', ' ', $e->getMessage());
    echo "[{$ts}] Fatal: {$safeMsg}\n";
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