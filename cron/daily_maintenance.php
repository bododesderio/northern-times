<?php
/**
 * Daily Maintenance Cron — runs once per day.
 *
 * Tasks:
 *   1. Aggregate dashboard stats for today
 *   2. Create automated database backup (keep last 7)
 *   3. Purge old webhook logs (> 30 days)
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\StatsAggregator;
use App\Models\DbBackup;
use App\Services\DB;

// Lock file to prevent overlapping runs
$lockFile = __DIR__ . '/../storage/cache/daily_maintenance.lock';
$fp = fopen($lockFile, 'w');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    echo "[daily] Already running, skipping.\n";
    exit(0);
}

echo "[daily] " . date('Y-m-d H:i:s') . " Starting daily maintenance...\n";

// 1. Stats aggregation
try {
    StatsAggregator::aggregateToday();
    echo "[daily] Stats aggregated.\n";
} catch (\Throwable $e) {
    echo "[daily] Stats error: " . $e->getMessage() . "\n";
}

// 2. Automated backup
try {
    $pdo = DB::pdo();
    $tableExists = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'db_backups'")->fetchColumn();
    if ($tableExists) {
        $backup = DbBackup::createBackup(false);
        if ($backup && $backup['status'] !== 'failed') {
            echo "[daily] Backup created: " . ($backup['filename'] ?? 'unknown') . "\n";

            // Keep only last 7 backups
            $all = DbBackup::all();
            if (count($all) > 7) {
                $toDelete = array_slice($all, 7);
                foreach ($toDelete as $old) {
                    DbBackup::deleteBackup($old['id']);
                }
                echo "[daily] Pruned " . count($toDelete) . " old backups.\n";
            }
        } else {
            echo "[daily] Backup failed: " . ($backup['error_msg'] ?? 'unknown') . "\n";
        }
    }
} catch (\Throwable $e) {
    echo "[daily] Backup error: " . $e->getMessage() . "\n";
}

// 3. Purge old webhook logs
try {
    $pdo = DB::pdo();
    $tableExists = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'webhook_logs'")->fetchColumn();
    if ($tableExists) {
        $stmt = $pdo->prepare("DELETE FROM webhook_logs WHERE created_at < NOW() - INTERVAL '30 days'");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        if ($deleted > 0) {
            echo "[daily] Purged {$deleted} old webhook logs.\n";
        }
    }
} catch (\Throwable $e) {
    echo "[daily] Webhook cleanup error: " . $e->getMessage() . "\n";
}

flock($fp, LOCK_UN);
fclose($fp);

echo "[daily] Done.\n";
