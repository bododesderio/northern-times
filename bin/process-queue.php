#!/usr/bin/env php
<?php
/**
 * process-queue.php — Process pending emails in the email_queue table.
 *
 * Usage:
 *   php bin/process-queue.php              # Process up to 50 emails
 *   php bin/process-queue.php --batch=100  # Process up to 100 emails
 *   php bin/process-queue.php --stats      # Show queue stats only
 *
 * Crontab (every 2 minutes):
 *   0/2 * * * * cd /var/www/html && php bin/process-queue.php >> storage/logs/queue.log 2>&1
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

require_once __DIR__ . '/../app/Support/helpers.php';

use App\Services\Mailer;

$timestamp = date('Y-m-d H:i:s');

// Parse args
$args = getopt('', ['batch:', 'stats', 'help']);

if (isset($args['help'])) {
    echo "Usage: php bin/process-queue.php [--batch=N] [--stats] [--help]\n";
    echo "  --batch=N  Process up to N emails (default: 50)\n";
    echo "  --stats    Show queue stats and exit\n";
    exit(0);
}

if (isset($args['stats'])) {
    $stats = Mailer::queueStats();
    echo "[{$timestamp}] Queue stats: pending={$stats['pending']} sent={$stats['sent']} failed={$stats['failed']}\n";
    exit(0);
}

$batchSize = isset($args['batch']) ? max(1, (int)$args['batch']) : 50;

// Prevent overlapping runs — two concurrent queue processors can double-send emails
$lockFile   = sys_get_temp_dir() . '/nt_email_queue.lock';
$lockHandle = @fopen($lockFile, 'c');
if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "[{$timestamp}] Queue processor already running (locked). Skipping.\n";
    if ($lockHandle) fclose($lockHandle);
    exit(0);
}
ftruncate($lockHandle, 0);
fwrite($lockHandle, (string)getmypid());
fflush($lockHandle);
register_shutdown_function(function () use ($lockHandle, $lockFile) {
    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);
    @unlink($lockFile);
});

echo "[{$timestamp}] Processing queue (batch={$batchSize})...\n";

try {
    $result = Mailer::processQueue($batchSize);
    echo "[{$timestamp}] Done: sent={$result['sent']} failed={$result['failed']}\n";

    if ($result['sent'] === 0 && $result['failed'] === 0) {
        echo "[{$timestamp}] Queue empty.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "[{$timestamp}] ERROR: {$e->getMessage()}\n");
    exit(1);
}

// Also run notification cleanup (older than 30 days)
try {
    $cleaned = \App\Models\Notification::cleanup(30);
    if ($cleaned > 0) {
        echo "[{$timestamp}] Cleaned {$cleaned} old notifications.\n";
    }
} catch (Throwable) {
    // Non-critical
}