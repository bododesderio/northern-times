<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\DB;
use PDO;

/**
 * DbBackup — manages database backup records and file lifecycle.
 */
final class DbBackup extends BaseModel
{
    protected static string $table   = 'db_backups';
    protected static string $orderBy = 'created_at DESC';

    /** Default retention: keep last 10 backups */
    public const MAX_BACKUPS = 10;

    /**
     * Create a new backup record and perform the dump.
     */
    public static function createBackup(bool $isAuto = false): ?array
    {
        $dbName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'northern_times');
        $dbHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'db');
        $dbUser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'northern');
        $dbPass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? '');

        $filename = "nt_backup_" . date('Y-m-d_His') . ($isAuto ? '_auto' : '_manual') . ".sql";
        $backupDir = __DIR__ . '/../../storage/backups';

        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
        }

        $filePath = $backupDir . '/' . $filename;
        $userId = $_SESSION['user']['id'] ?? null;

        // Create record first
        $record = self::create([
            'filename'   => $filename,
            'file_path'  => $filePath,
            'created_by' => $userId,
            'is_auto'    => $isAuto ? 'true' : 'false',
            'status'     => 'in_progress',
        ]);

        if (!$record) {
            return null;
        }

        // Run pg_dump
        putenv("PGPASSWORD={$dbPass}");
        $cmd = sprintf(
            'pg_dump -h %s -U %s %s > %s 2>&1',
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            escapeshellarg($dbName),
            escapeshellarg($filePath)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($filePath)) {
            self::update($record['id'], [
                'status'    => 'failed',
                'error_msg' => implode("\n", $output),
            ]);
            return self::find($record['id']);
        }

        $fileSize = filesize($filePath) ?: 0;

        self::update($record['id'], [
            'status'    => 'completed',
            'file_size' => $fileSize,
        ]);

        // Enforce retention policy
        self::enforceRetention();

        return self::find($record['id']);
    }

    /**
     * Delete a backup record and its file.
     */
    public static function deleteBackup(string $id): bool
    {
        $record = self::find($id);
        if (!$record) {
            return false;
        }

        // Delete physical file
        if (!empty($record['file_path']) && is_file($record['file_path'])) {
            @unlink($record['file_path']);
        }

        return self::delete($id);
    }

    /**
     * Download a backup file. Returns [path, filename] or null.
     */
    public static function getDownload(string $id): ?array
    {
        $record = self::find($id);
        if (!$record || $record['status'] !== 'completed') {
            return null;
        }

        if (!is_file($record['file_path'])) {
            return null;
        }

        return [$record['file_path'], $record['filename']];
    }

    /**
     * Enforce retention — keep only MAX_BACKUPS most recent.
     */
    public static function enforceRetention(int $maxKeep = self::MAX_BACKUPS): void
    {
        $old = self::query(
            "SELECT id, file_path FROM db_backups ORDER BY created_at DESC OFFSET :off",
            [':off' => $maxKeep]
        );

        foreach ($old as $record) {
            if (!empty($record['file_path']) && is_file($record['file_path'])) {
                @unlink($record['file_path']);
            }
            self::delete($record['id']);
        }
    }

    /**
     * Get total size of all backups.
     */
    public static function totalSize(): int
    {
        return (int)self::queryColumn(
            "SELECT COALESCE(SUM(file_size), 0) FROM db_backups WHERE status = 'completed'"
        );
    }

    /**
     * Format bytes to human-readable string.
     */
    public static function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float)$bytes;
        while ($size >= 1024 && $i < 3) { $size /= 1024; $i++; }
        return round($size, 1) . ' ' . $units[$i];
    }
}
