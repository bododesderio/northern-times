<?php
/**
 * Database Backup — run via cron daily at 2:00 AM:
 *   0 2 * * * php /var/www/html/backup.php >> /var/www/html/storage/logs/backup.log 2>&1
 *
 * Can also be triggered manually:
 *   php backup.php
 *
 * Features:
 *   - Compressed pg_dump (.sql.gz)
 *   - 7-day retention (auto-deletes older backups)
 *   - Stored in storage/backups/ (not publicly accessible)
 */
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Silence display, log errors
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/storage/logs/php-errors.log');

$ts = date('Y-m-d H:i:s');

// ── Config ──────────────────────────────────────────────────────
$dbHost = $_ENV['DB_HOST'] ?? 'db';
$dbPort = $_ENV['DB_PORT'] ?? '5432';
$dbName = $_ENV['DB_NAME'] ?? 'northern_times';
$dbUser = $_ENV['DB_USER'] ?? 'northern';
$dbPass = $_ENV['DB_PASS'] ?? 'northern_secret';

$backupDir   = __DIR__ . '/storage/backups';
$retainDays  = 7;
$filename    = 'nt_backup_' . date('Y-m-d_His') . '.sql.gz';
$filepath    = $backupDir . '/' . $filename;

// ── Ensure backup directory exists ──────────────────────────────
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}

// ── Run pg_dump ─────────────────────────────────────────────────
// Set password via environment variable (pg_dump reads PGPASSWORD)
putenv("PGPASSWORD={$dbPass}");

$cmd = sprintf(
    'pg_dump -h %s -p %s -U %s -d %s --no-owner --no-acl 2>&1 | gzip > %s',
    escapeshellarg($dbHost),
    escapeshellarg($dbPort),
    escapeshellarg($dbUser),
    escapeshellarg($dbName),
    escapeshellarg($filepath)
);

exec($cmd, $output, $exitCode);

// Clear password from environment
putenv("PGPASSWORD");

if ($exitCode !== 0) {
    $error = preg_replace('/[\r\n\x00]/', ' ', implode(" ", $output));
    $safeError = substr($error, 0, 500);
    echo "[{$ts}] ERROR: pg_dump failed (exit {$exitCode}): {$safeError}\n";
    error_log("backup.php: pg_dump failed (exit {$exitCode}): {$safeError}");

    // Clean up empty/broken file
    if (file_exists($filepath)) {
        @unlink($filepath);
    }
    exit(1);
}

$size = filesize($filepath);
$sizeHuman = $size > 1048576
    ? round($size / 1048576, 1) . ' MB'
    : round($size / 1024, 1) . ' KB';

echo "[{$ts}] Backup created: {$filename} ({$sizeHuman})\n";

// ── Cleanup: delete backups older than retention period ─────────
$cutoff = time() - ($retainDays * 86400);
$deleted = 0;

foreach (glob($backupDir . '/nt_backup_*.sql.gz') as $file) {
    if (filemtime($file) < $cutoff) {
        @unlink($file);
        $deleted++;
    }
}

if ($deleted > 0) {
    echo "[{$ts}] Cleaned up {$deleted} old backup(s) (>{$retainDays} days).\n";
}